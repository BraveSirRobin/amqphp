<?php

use amqphp as amqp;
use amqphp\protocol;
use amqphp\wire;

require __DIR__ . '/demo-loader.php';

$USAGE = "USAGE: php demo-multi-producer.php [arguments]

A simple  message producer,  messages can  be consumed  by any  of the
Amqphp demo consumers.

Paramers:

  --message [string]
    Sets the body of the message

  --repeat [integer]
    How many times to send the message

  --conn {0,1}
    Sets  target  connections,  by  default   you  can  publish  to  2
    connections,  use  multiple  arguments   to  publish  to  multiple
    connections.
";

// Grab run options from the command line.
$conf = getopt('', array('help', 'message:', 'repeat:', 'conn:'));

if (array_key_exists('help', $conf)) {
    echo $USAGE;
    die;
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

if (array_key_exists('conn', $conf)) {
    if (is_array($conf['conn'])) {
        $tcons = array_values($conf['conn']);
    } else {
        $tcons = array($conf['conn']);
    }
} else {
    $tcons = array(0);
}


$publishParams = array(
    'content-type' => 'text/plain',
    'content-encoding' => 'UTF-8',
    'routing-key' => '',
    'mandatory' => false,
    'immediate' => false,
    'exchange' => 'most-basic-ex'); // Must match exchange in multi-producer.xml


$su = new amqp\Factory(__DIR__ . '/configs/new/producer.xml');
$_conns = $su->getConnections();

$conns = array();
foreach ($_conns as $i => $c) {
    if (in_array($i, $tcons)) {
        $conns[] = $c;
    }
}

if (! $conns) {
    printf("No valid connections selected!\n");
    die;
}


printf("Ready: Publish message '%s..' to connection(s) [%s] %d times.\n",
       substr($content, 0, 8), implode(', ', array_keys($conns)), $N);



$cons = array();
foreach ($conns as $con) {
    $chans = $con->getChannels();
    $chan = array_pop($chans);
    $basicP = $chan->basic('publish', $publishParams);
    $cons[] = array($con, $chan, $basicP);
}




$n = 0;
for ($i = 0; $i < $N; $i++) {
    foreach ($cons as $c) {
        $c[2]->setContent($content);
        $c[1]->invoke($c[2]);
        $n++;
    }
}


foreach ($cons as $c) {
    $c[1]->shutdown(); // Shut down channel only.
    $c[0]->shutdown();
}

printf("Test complete, published %d messages\n", $n);
