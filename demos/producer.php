<?php

use amqphp as amqp;
use amqphp\protocol;
use amqphp\wire;

require __DIR__ . '/class-loader.php';

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
    Sets the body of the message

  --repeat [integer]
    How many times to send the message

  --confirms
    Switch on the streaming confirms feature (default false)

  --mandatory
    Publish messages with mandatory=true (default false)

  --immediate
    Publish messages with immediate=true (default false)

  --exchange (most-basic-ex)
    Publish to this exchange

  --routing-key
    Use this routing key
", basename(__FILE__));

/** Grab run options from the command line. */
$conf = getopt('', array('help', 'message:', 'repeat:', 'confirms', 'mandatory',
                         'immediate', 'exchange:', 'routing-key:', 'sleep:', 'ticker:'));

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

if (array_key_exists('message', $conf)) {
    $content = $conf['message'];
} else {
    $content = "Default messages from demo-multi-producer!";
}

if (array_key_exists('repeat', $conf) && is_numeric($conf['repeat'])) {
    $N = (int) $conf['repeat'];
} else {
    $N = 1;
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

/**
 * A  Very simple  channel event  handler, simply  saves  all incoming
 * events to be reported on later.
 */
class DemoCEH implements amqp\ChannelEventHandler
{
    public $confirms = array();
    public $returns = array();
    public $nacks = array();

    function publishConfirm (wire\Method $meth) {
        $this->confirms[] = $meth->getField('delivery-tag');
    }

    function publishReturn (wire\Method $meth) {
        $this->returns[] = $meth->getField('delivery-tag');
    }

    function publishNack (wire\Method $meth) {
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


/** Confirm selected options to the user */
info("Ready to publish:\n Message '%s..' \n Send %d times\n mandatory: %d\n" .
       " immediate: %d\n confirms: %d\n routing-key: %s\n exchange: %s\n sleep: %d\n ticker: %d", substr($content, 0, 24),
     $N, $mandatory, $immediate, $confirms, $routingKey, $exchange, $sleep, $ticks);


/** Initialise the broker connection and send the messages. */
$publishParams = array(
    'content-type' => 'text/plain',
    'content-encoding' => 'UTF-8',
    'routing-key' => $routingKey,
    'mandatory' => $mandatory,
    'immediate' => $immediate,
    'exchange' => $exchange);


$su = new amqp\Factory(__DIR__ . '/configs/basic-connection.xml');
$conn = $su->getConnections();
$conn = array_pop($conn);
$chan = $conn->getChannel(1);


$ceh = new DemoCEH;
$chan->setEventHandler($ceh);

if ($confirms) {
    $chan->setConfirmMode();
}


$basicP = $chan->basic('publish', $publishParams);
$basicP->setContent($content);

$n = $tc = 0;
$tc = 1;
for ($i = 0; $i < $N; $i++) {
    $chan->invoke($basicP);
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

