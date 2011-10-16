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
require __DIR__ . '/demo-loader.php';


define("CONNECTION_CONF", realpath(__DIR__ . '/configs/exstrats.xml'));


if (! is_file(CONNECTION_CONF)) {
    printf("Fatal: cannnot find connection config %s\n", CONNECTION_CONF);
    die;
}


/** Demo implementation class, is both  a consumer and a channel event
 * handler. */
class ExitStratDemo implements amqp\Consumer, amqp\ChannelEventHandler
{
    /** CLI Exit strategy identifier map */
    public static $StratMap = array(
        'cond' => amqp\STRAT_COND,
        'trel' => amqp\STRAT_TIMEOUT_REL,
        'tabs' => amqp\STRAT_TIMEOUT_ABS,
        'maxl' => amqp\STRAT_MAXLOOPS,
        'callb' => amqp\STRAT_CALLBACK
        );

    /* If a  consumer receives  this as a  message, the  consumer will
     * detatch itself. */
    public $exitMessage = 'exit.';

    private $connection;
    private $channel;


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


    /** Create a consume session with this object as the handler. */
    function addConsumeSession () {
        $this->channel->addConsumer($this);
    }


    /**
     * Starts a consume session.
     */
    function runDemo () {
        info("Start consuming...");
        $evl = new amqp\EventLoop;
        $evl->addConnection($this->connection);
        $evl->select();
        info("Event loop exits.");
    }



    /** @override \amqphp\Consumer */
    function handleCancelOk (wire\Method $m, amqp\Channel $chan) { }

    /** @override \amqphp\Consumer */
    function handleConsumeOk (wire\Method $m, amqp\Channel $chan) {
        info("Consume session started, ctag %s", $m->getField('consumer-tag'));
    }

    /** @override \amqphp\Consumer */
    function handleDelivery (wire\Method $m, amqp\Channel $chan) {
        $content = $m->getContent();
        info("Message received on consumer tag %s\n  %s", $m->getField('consumer-tag'), substr($content, 0, 10));
        if ($content == $this->exitMessage) {
            return array(amqp\CONSUMER_ACK, amqp\CONSUMER_CANCEL);
        } else {
            return amqp\CONSUMER_ACK;
        }
    }

    /** @override \amqphp\Consumer */
    function handleRecoveryOk (wire\Method $m, amqp\Channel $chan) { }

    /** @override \amqphp\Consumer */
    function getConsumeMethod (amqp\Channel $chan) {
        $cps = array('queue' => 'most-basic-q',
                     'no-local' => false,
                     'no-ack' => false,
                     'exclusive' => false,
                     'no-wait' => false);
        return $chan->basic('consume', $cps);
    }

    /** @override \amqphp\ChannelEventHandler */
    public function publishConfirm (wire\Method $m) { }

    /** @override \amqphp\ChannelEventHandler */
    public function publishReturn (wire\Method $m) {
        printf("Your message was rejected: %s [%d]\n", $m->getField('reply-text'), $m->getField('reply-code'));
        $this->requests--;
    }

    /** @override \amqphp\ChannelEventHandler */
    public function publishNack (wire\Method $m) { }
}

$USAGE = sprintf("Usage: php demo-exit-strategies.php [switches]

Starts a consumer  with a variable number of exit  strategies that are
added from the command line.  Used  to test exit strategies and chains
of exit strategies.

Switches are:

  --strat  name [args,]  Adds a  strategy to  the connection  strategy
    chain, you  can specify  multiple strategies

    name = {%s}
    arg =  A whitespace separated list of strategy parameters

  --exit-message message
    when the following message string  is received, exit the receiving
    consumer

  --num-cons  Sets up this many consumers (all bound to one object)
", implode(', ', array_keys(ExitStratDemo::$StratMap)));



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

$opts = getopt('', array('help', 'strat:', 'num-cons:', 'exit-message:'));
if (array_key_exists('help', $opts)) {
    echo $USAGE;
    die;
}

info("Create demo object and connection..");
$exd = new \ExitStratDemo(CONNECTION_CONF);



// Load exit strategies from the command line args
if (array_key_exists('strat', $opts)) {
    foreach ((array) $opts['strat'] as $strat) {
        $exd->addExitStrategy($strat);
        info("Added exit strategy %s", $strat);
    }
}

if (array_key_exists('num-cons', $opts)) {
    $numCons = (int) $opts['num-cons'];
    info(" * Add %d consumers\n", $opts['num-cons']);
} else {
    $numCons = 1;
}

for ($i = 0; $i < $numCons; $i++) {
    $exd->addConsumeSession();
}
info("Initialised %d consumer sessions", $numCons);

if (array_key_exists('exit-message', $opts)) {
    $exd->exitMessage = $opts['exit-message'];
}

$exd->runDemo();