<?php
/**
 * 
 * Copyright (C) 2010, 2011  Robin Harvey (harvey.robin@gmail.com)
 *
 * This  library is  free  software; you  can  redistribute it  and/or
 * modify it under the terms of  the GNU Lesser General Public License
 * as published by the Free Software Foundation; either version 2.1 of
 * the License, or (at your option) any later version.

 * This library is distributed in the hope that it will be useful, but
 * WITHOUT  ANY  WARRANTY;  without   even  the  implied  warranty  of
 * MERCHANTABILITY or FITNESS  FOR A PARTICULAR PURPOSE.   See the GNU
 * Lesser General Public License for more details.

 * You should  have received a copy  of the GNU Lesser  General Public
 * License along with this library; if not, write to the Free Software
 * Foundation,  Inc.,  51 Franklin  Street,  Fifth  Floor, Boston,  MA
 * 02110-1301 USA
 */


/**
 * This demo  shows off  the use of  exit strategies,  including using
 * combinations of strategies.
 */

use amqphp as amqp;
use amqphp\protocol;
use amqphp\wire;

// HACK : Manually pre-load the connection class so that Amqphp consts
// are available - these cannot be loaded by the class loader.
require __DIR__ . '/../src/amqphp/Connection.php';
require __DIR__ . '/class-loader.php';


define("DEFAULT_CONF", realpath(__DIR__ . '/configs/basic-connection.xml'));


if (! is_file(DEFAULT_CONF)) {
    warn("Fatal: cannnot find connection config %s\n", DEFAULT_CONF);
    die;
}


/** Demo implementation class, is both  a consumer and a channel event
 * handler. */
class MultiConsumer implements amqp\Consumer, amqp\ChannelEventHandler
{
    /** CLI Exit strategy identifier map */
    public static $StratMap = array(
        'cond' => amqp\STRAT_COND,
        'trel' => amqp\STRAT_TIMEOUT_REL,
        'tabs' => amqp\STRAT_TIMEOUT_ABS,
        'maxl' => amqp\STRAT_MAXLOOPS,
        'callb' => amqp\STRAT_CALLBACK
        );

    /* Specify default 'action messages' (these are defined in CLI args) */
    public $exitMessage = 'Cancel.';
    public $rejectMessage = 'Reject.';
    public $dropMessage = 'Drop.';

    private $connection;
    private $channel;
    private $evl;

    private $consumeTags = array();

    private $signalsInstalled = false;

    function __construct ($config) {
        // Set up connection using a Factory
        $fact = new amqp\Factory($config);
        $tmp = $fact->getConnections();
        $this->connection = reset($tmp);
        $tmp = $this->connection->getChannels();
        $this->channel = reset($tmp);
    }


    /** Add an exit strategy as defined on the command line */
    function addExitStrategy ($strat) {
        $bits = explode(' ', $strat);
        if (! array_key_exists($bits[0], self::$StratMap)) {
            throw new \Exception("Invalid strategy identifier: {$bits[0]}", 34678);
        }
        $bits[0] = self::$StratMap[$bits[0]];
        call_user_func_array(array($this->connection, 'pushExitStrategy'), $bits);
    }


    /**
     * Register a consume  session with the local channel  and add the
     * given consume params to the  local stack - these will be picked
     * up when the local connection is added to an event loop.
     */
    function addConsumeSession ($queue, $noLocal=false, $noAck=false, $exclusive=false) {
        $consumeParams = array('queue' => $queue,
                               'no-local' => $noLocal,
                               'no-ack' => $noAck,
                               'exclusive' => $exclusive,
                               'no-wait' => false);
        $this->channel->addConsumer($this, $consumeParams);
    }


    /**
     * Starts a consume session.
     */
    function runDemo () {
        info("Start consuming...");
        $this->testEnableSignalHandler();
        $this->evl = new amqp\EventLoop;
        $this->evl->addConnection($this->connection);
        $this->evl->select();
        $this->channel->removeAllConsumers();
        $this->connection->shutdown();
        info("Consumers removed, event loop exits.");
    }


    /**
     * Checks to see  if signal handler funcs are  available (i.e. not
     * Windows or Apache); if they are, installs handlers.
     */
    private function testEnableSignalHandler () {
        if (! $this->signalsInstalled && extension_loaded('pcntl')) {
            pcntl_signal(SIGTERM, array($this, 'sigHandler'));
            pcntl_signal(SIGHUP,  array($this, 'sigHandler'));
            pcntl_signal(SIGINT, array($this, 'sigHandler'));
            $this->signalsInstalled = true;
            info("Signal handler funcs installed OK");
        }
    }

    /** Callback for signal handlers.  */
    function sigHandler ($signo) {
        info("RECEIVED SIGNAL %d, force event loop exit", $signo);
        $this->evl->forceLoopExit();
    }

    /** @override \amqphp\Consumer */
    function handleCancelOk (wire\Method $m, amqp\Channel $chan) {
        // Remove this consume tag from local list
        $cTag = $m->getField('consumer-tag');
        $cNum = array_search($cTag, $this->consumeTags);
        if ($cNum === false) {
            warn("Received a cancel for an unknown consumer tag %s", $cTag);
        } else {
            info("Consumer %s [%s] cancelled OK", $cNum, $cTag);
        }
    }

    /** @override \amqphp\Consumer */
    function handleConsumeOk (wire\Method $m, amqp\Channel $chan) {
        $this->consumeTags[] = $m->getField('consumer-tag');
        info("Consume session started, ctag %s", $m->getField('consumer-tag'));
    }

    /** @override \amqphp\Consumer */
    function handleDelivery (wire\Method $m, amqp\Channel $chan) {
        // Look up which consumer is being delivered to.
        $cTag = $m->getField('consumer-tag');
        $cNum = array_search($cTag, $this->consumeTags);
        if ($cNum === false) {
            // This should never happen!
            warn("Received message for unknown consume tag %s, reject", $cTag);
            return amqp\CONSUMER_REJECT;
        }

        $content = $m->getContent();

        if (! $content) {
            info("Empty Message received on consumer %d [%s]", $cNum, $cTag);
            return amqp\CONSUMER_ACK;
        }

        if ($content === $this->exitMessage) {
            info("Received exit message, cancel consumer %d", $cNum);
            return array(amqp\CONSUMER_ACK, amqp\CONSUMER_CANCEL);
        } else if ($content === $this->rejectMessage) {
            info("Received reject message, reject consumer %d", $cNum);
            return amqp\CONSUMER_REJECT;
        } else if ($content === $this->dropMessage) {
            info("Received drop message, drop consumer %d", $cNum);
            return amqp\CONSUMER_DROP;
        } else {
            printf("[MSG] consumer-tag=%s [%d]\ndelivery-tag=%s redelivered=%s\nexchange=%s\nrouting-key=%s\n%s\n",
                   $cTag, $cNum, $m->getField('delivery-tag'), $m->getField('redelivered') ? 't' : 'f', $m->getField('exchange'),
                   $m->getField('routing-key'), $content);
            return amqp\CONSUMER_ACK;
        }
    }

    /** @override \amqphp\Consumer */
    function handleRecoveryOk (wire\Method $m, amqp\Channel $chan) { }

    /**
     * Called  by the  API  to look  for  consume session  parameters.
     * We're providing these via. the addConnection method rather than
     * here, although we could over-ride those params here
     * @override \amqphp\Consumer
     */
    function getConsumeMethod (amqp\Channel $chan) { }

    /** @override \amqphp\ChannelEventHandler */
    public function publishConfirm (wire\Method $m) { }

    /** @override \amqphp\ChannelEventHandler */
    public function publishReturn (wire\Method $m) {
        info("Your message was rejected: %s [%d]\n", $m->getField('reply-text'), $m->getField('reply-code'));
        $this->requests--;
    }

    // Server has cancelled us for some reason.
    function handleCancel (wire\Method $meth, amqp\Channel $chan) {
        $cTag = $meth->getField('consumer-tag');
        $cNum = array_search($cTag, $this->consumeTags);
        if ($cNum === false) {
            // This should never happen!
            warn("Received cancellation for unknown consumer tag %s, reject", $cTag);
        } else {
            info("Received a consumer cancel from broker for consumer %d [%s]", $cNum, $cTag);
        }
    }

    /** @override \amqphp\ChannelEventHandler */
    public function publishNack (wire\Method $m) { }
}


/**
 * Use or replace this to test the callback exit strategy
 */
function randomExitController () {
    $r = ((rand(0,25) % 25) != 0);
    info("Random exit controller invoked, returns %d", $r);
    return $r;
}


$USAGE = sprintf('Usage: php consumer.php [switches]

Starts a consume  session and prints received messages  to the command
line.  The consume parameters, exit  strategies and other items can be
configured with the following switches:

  --config [file-path] Load connection configs from this file, default
    %s

  --strat ["name args"]  - Adds a strategy to  the connection strategy
    chain, you can specify multiple strategies

      name: {%s}
      args:  Optional whitespace separated list of strategy parameters

    You can  specify multiple strategies  using more than  one --strat
    option.

  --consumer ["queue consume-args"]  Adds  a  consumer

      queue:          Name of the queue to listen on
      consume-args:   3 consumer setup flags, must be a sequence  of 3
      t/f  values  corresponding   to  the  following  consumer  setup
      properties, with the following defaults:
                      no-local: f
                      no-ack: f
                      exclusive: f
      (Note: RabbitMQ does not support the no-local flag:
             http://www.rabbitmq.com/interoperability.html)

  --exit-message  [string]  - when  the  following  message string  is
    received,  exit  the   receiving  consumer  with  CONSUMER_CANCEL.
    Default Value: "Cancel."

  --reject-message  [string] -  when the  following message  string is
    received,  reject  the   incoming  message  with  CONSUMER_REJECT.
    Default  Value: "Reject."  (Note:  RabbitMQ does  not support  the
    no-local flag: http://www.rabbitmq.com/interoperability.html)

  --drop-message  [string]  - when  the  following  message string  is
    received, reject the incoming message with CONSUMER_DROP.  Default
    Value: "Drop."


    You  can  add  more than  one  consumer  by  using more  than  one
    --consumer option.


Example:
  This should work "out of the box":

php consumer.php --strat "cond" \
                 --strat "trel 5 0" \
                 --consumer "most-basic-q"
',
DEFAULT_CONF,
implode(', ', array_keys(MultiConsumer::$StratMap)));



/** Some output functions to write messages to stdout. */
function info () {
    $args = func_get_args();
    if (! $fmt = array_shift($args)) {
        return;
    }
    $fmt = sprintf("[INFO] %s\n", $fmt);
    vprintf($fmt, $args);
}

function warn() {
    $args = func_get_args();
    if (! $fmt = array_shift($args)) {
        return;
    }
    $fmt = sprintf("[WARN] %s\n", $fmt);
    vprintf($fmt, $args);
}




/** Create the demo client and configure it as per CLI args. */

$opts = getopt('', array('help', 'strat:', 'consumer:', 'exit-message:', 'reject-message:', 'drop-message:', 'config:'));
if (array_key_exists('help', $opts)) {
    echo $USAGE;
    die;
}

if (array_key_exists('config', $opts)) {
    if (is_array($opts['config'])) {
        warn("Too many config options, discarding all but the first.");
        $opts['config'] = array_shift($opts['config']);
    }
} else {
    $opts['config'] = DEFAULT_CONF;
}



// Load consumers from the command line args
$consumeSessions = array();
if (! array_key_exists('consumer', $opts)) {
    printf("Error: you must specify at least one --consumer option\n");
    die;
}
foreach ((array) $opts['consumer'] as $cOpt) {
    $bits = explode(' ', $cOpt);
    $queue = $bits[0];
    $cFlags = array_key_exists(1, $bits)
        ? $bits[1]
        : 'fff';
    if (strlen($cFlags) != 3) {
        print("Error: invalid consumer switch, consume params option must contain exactly 3 characters.\n");
        die;
    }
    $consumeSessions[] = array($queue,
                               $cFlags[0] == 't',
                               $cFlags[1] == 't',
                               $cFlags[2] == 't');
}




info("Start demo from config %s", $opts['config']);
$exd = new \MultiConsumer($opts['config']);



// Apply exit strategies from the command line
if (array_key_exists('strat', $opts)) {
    foreach ((array) $opts['strat'] as $strat) {
        $exd->addExitStrategy($strat);
        info("Added exit strategy %s", $strat);
    }
}

// Create consumers
foreach ($consumeSessions as $cs) {
    info("Add consume session: queue=%s, no-local=%s, no-ack=%s, exclusive=%s",
         $cs[0],
         ($cs[1] ? 't' : 'f'),
         ($cs[2] ? 't' : 'f'),
         ($cs[3] ? 't' : 'f'));
    call_user_func_array(array($exd, 'addConsumeSession'), $cs);
}

if (array_key_exists('exit-message', $opts)) {
    $exd->exitMessage = $opts['exit-message'];
}
if (array_key_exists('reject-message', $opts)) {
    $exd->rejectMessage = $opts['reject-message'];
}
if (array_key_exists('drop-message', $opts)) {
    $exd->dropMessage = $opts['drop-message'];
}

try {
    $exd->runDemo();
} catch (\Exception $e) {
    printf("Exception caught at root level of consumer script:\n%s\n%s",
           $e->getMessage(), $e->getTraceAsString());
}