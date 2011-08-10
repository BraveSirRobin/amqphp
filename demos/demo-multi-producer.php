<?php

use amqphp as amqp;
use amqphp\protocol;
use amqphp\wire;

require __DIR__ . '/demo-loader.php';
require __DIR__ . '/Setup.php';



$publishParams = array(
    'content-type' => 'text/plain',
    'content-encoding' => 'UTF-8',
    'routing-key' => '',
    'mandatory' => false,
    'immediate' => false,
    'exchange' => 'most-basic-ex'); // Must match exchange in multi-producer.xml


$su = new Setup;
$conns = $su->getSetup(__DIR__ . '/multi-producer.xml');
$cons = array();
foreach ($conns as $con) {
    $chans = $con->getChannels();
    $chan = array_pop($chans);
    $basicP = $chan->basic('publish', $publishParams);
    $cons[] = array($con, $chan, $basicP);
}




$content = "My god, sending the same message thousands of times?  How dull!";
$n = 0;
$N = array_key_exists(1, $argv) && is_numeric($argv[1])
    ? (int) $argv[1]
    : 500;

for ($i = 0; $i < $N; $i++) {
    foreach ($cons as $stuff) {
        $stuff[2]->setContent($content);
        $stuff[1]->invoke($stuff[2]);
        $n++;
    }
}


foreach ($cons as $stuff) {
    $stuff[1]->shutdown(); // Shut down channel only.
    $stuff[0]->shutdown();
}

printf("Test complete, published %d messages\n", $n);
