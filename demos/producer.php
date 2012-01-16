<?php

use amqphp as amqp;
use amqphp\protocol;
use amqphp\wire;

require __DIR__ . '/class-loader.php';





/**
 * A  Very simple  channel event  handler, simply  saves  all incoming
 * events to be reported on later.
 */
class DemoCEH implements amqp\ChannelEventHandler
{
    public $confirms = array();
    public $returns = array();
    public $nacks = array();

    public $evOutput = false;

    function publishConfirm (wire\Method $meth) {
        if ($this->evOutput) {
            info("Channel event pubConf - dtag=%s", $meth->getField('delivery-tag'));
        }
        $this->confirms[] = $meth->getField('delivery-tag');
    }

    function publishReturn (wire\Method $meth) {
        if ($this->evOutput) {
            info("Channel event pubRet - dtag=%s", $meth->getField('delivery-tag'));
        }
        $this->returns[] = $meth->getField('delivery-tag');
    }

    function publishNack (wire\Method $meth) {
        if ($this->evOutput) {
            info("Channel event pubNack - dtag=%s", $meth->getField('delivery-tag'));
        }
        $this->nacks[] = $meth->getField('delivery-tag');
    }
}

function info () {
    $args = func_get_args();
    if (! $fmt = array_shift($args)) {
        return;
    }
    $fmt = sprintf("[INFO] %s\n", $fmt);
    vprintf($fmt, $args);
}

function warn () {
    $args = func_get_args();
    if (! $fmt = array_shift($args)) {
        return;
    }
    $fmt = sprintf("[WARN] %s\n", $fmt);
    vprintf($fmt, $args);
}





/**
 * Parse command line options
 */

$USAGE = sprintf("USAGE: php %s [arguments]

A simple  message producer,  messages can  be consumed  by any  of the
Amqphp demo consumers.

Paramers:

  --sleep [integer]
    Sets a publish loop sleep, value in milliseconds. (default 0)

  --ticker [integer]
    Output  a '.'  after  every N  messages,  set to  0  for no  ouput
    (default 0)

  --message [string]
    Sets  the  body  of  the  message.  If  more  than  one  supplied,
    round-robin the messages

  --message-file [file]
    Same as  --message, but takes  the message content from  the given
    file.

  --repeat [integer]
    How many times to send the message

  --confirms
    Switch on the streaming confirms feature (default false)

  --mandatory
    Publish messages with mandatory=true (default false)

  --immediate
    Publish messages with immediate=true (default false)

  --exchange [exchange=most-basic-ex]
    Publish to this exchange

  --routing-key [key]
    Use this routing key

  --show-events
    Display incoming channel events on StdOut
", basename(__FILE__));





/** Grab run options from the command line. */
$conf = getopt('', array('help', 'message:', 'repeat:', 'confirms', 'mandatory',
                         'immediate', 'exchange:', 'routing-key:', 'sleep:', 'ticker:',
                         'show-events', 'message-file:'));

if (array_key_exists('help', $conf)) {
    echo $USAGE;
    die;
}

if (array_key_exists('exchange', $conf)) {
    $exchange = $conf['exchange'];
} else {
    $exchange = 'most-basic-ex';
}

if (array_key_exists('routing-key', $conf)) {
    $routingKey = $conf['routing-key'];
} else {
    $routingKey = '';
}

$content = array();
if (array_key_exists('message', $conf)) {
    $content = (array) $conf['message'];
}

if (array_key_exists('message-file', $conf)) {
    foreach ((array) $conf['message-file'] as $fn) {
        if (is_file($fn)) {
            $content[] = file_get_contents($fn);
        } else {
            warn("Invalid message file %s, skip to next", $fn);
        }
    }
}
if (! $content) {
    $content = array('A test message from producer.php');
}

if (array_key_exists('repeat', $conf)) {
    $N = (array) $conf['repeat'];
} else {
    $N = array(1);
}

$sleep = array_key_exists('sleep', $conf)
    ? intval($conf['sleep'])
    : 0;

$ticks = array_key_exists('ticker', $conf)
    ? intval($conf['ticker'])
    : 0;

$confirms = array_key_exists('confirms', $conf);
$mandatory = array_key_exists('mandatory', $conf);
$immediate = array_key_exists('immediate', $conf);



/** Confirm selected options to the user */

info("Ready to publish:\n Message(s) '%s' \n Send Repeats: %s\n mandatory: %d\n" .
       " immediate: %d\n confirms: %d\n routing-key: %s\n exchange: %s\n sleep: %d\n ticker: %d",
     implode("','", array_map(function ($m) { return substr($m, 0, 8); }, $content)),
     implode(', ', $N), $mandatory, $immediate, $confirms, $routingKey, $exchange, $sleep, $ticks);


/** Initialise the broker connection and send the messages. */
$su = new amqp\Factory(__DIR__ . '/configs/basic-connection.xml');
$conn = $su->getConnections();
$conn = array_pop($conn);
$chan = $conn->getChannel(1);



$publishParams = array(
    'content-type' => 'text/plain',
    'content-encoding' => 'UTF-8',
    'routing-key' => $routingKey,
    'mandatory' => $mandatory,
    'immediate' => $immediate,
    'exchange' => $exchange);



$ceh = new DemoCEH;
$chan->setEventHandler($ceh);
$ceh->evOutput = array_key_exists('show-events', $conf);

if ($confirms) {
    $chan->setConfirmMode();
}


// Prepare a list of messages
for ($i = 0, $c = count($content); $i < $c; $i++) {
    $basicP = $chan->basic('publish', $publishParams);
    $basicP->setContent($content[$i]);
    $content[$i] = $basicP;
}

// Main loop
$nMessages = count($content);
$n = $tc = 0;
$tc = 1;
foreach ($N as $repNum => $repeat) {
    $m = $content[$repNum % $nMessages];
    for ($i = 0; $i < $repeat; $i++) {
        $chan->invoke($m);
        info("Sent message %s", $m->getContent());
    }
    $n++;

    if ($ticks && ! ($n % $ticks)) {
        if (! ($tc % 80)) {
            echo ".\n";
            $tc = 1;
        } else {
            echo '.';
            $tc++;
        }
    }
    if ($sleep) {
        usleep($sleep);
    }
}

info("Published %d messages", $n);

/** If  required, enter  a select  loop  in order  to receive  message
   responses. */
if ($confirms || $mandatory || $immediate) {
    /** Never wait more than 3 seconds for responses */
    $conn->pushExitStrategy(amqp\STRAT_TIMEOUT_REL, 3, 0);
    if ($confirms) {
        /** In confirm mode,  add an additional rule so  that the loop
           exits as soon as all confirms have returned. */
        $conn->pushExitStrategy(amqp\STRAT_COND);
    } else {
        /** Add a callback exit strategy  so that we can exit early if
           all messages are returned */
        $callback = function () use ($n, $ceh) {
            return (count($ceh->returns) < $n);
        };
        $conn->pushExitStrategy(amqp\STRAT_CALLBACK, $callback);
    }
    $evl = new amqp\EventLoop;
    $evl->addConnection($conn);
    info("Begin listening for responses...");
    $evl->select();
    info("Response receiving is complete, responses received are:\n " .
         "confirms: %d\n returns %d\n nacks: %d", count($ceh->confirms),
         count($ceh->returns), count($ceh->nacks));
}


$conn->shutdown();

info("Process is complete");

