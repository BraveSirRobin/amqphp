<?php

use amqphp as amqp;
use amqphp\protocol;
use amqphp\wire;

require __DIR__ . '/demo-loader.php';

$EX_NAME = 'most-basic';
$EX_TYPE = 'direct';
$Q = 'most-basic';

// Basic RabbitMQ connection settings
$conConfigs = array();
$conConfigs[] = array(
    'username' => 'testing',
    'userpass' => 'letmein',
    'vhost' => 'robin',
    'consumerName' => 'C1',
    'socketParams' => array('host' => 'rabbit1', 'port' => 5672));
$conConfigs[] = array(
    'username' => 'testing',
    'userpass' => 'letmein',
    'vhost' => 'robin',
    'consumerName' => 'C2',
    'socketParams' => array('host' => 'rabbit2', 'port' => 5672));


$publishParams = array(
    'content-type' => 'text/plain',
    'content-encoding' => 'UTF-8',
    'routing-key' => '',
    'mandatory' => false,
    'immediate' => false,
    'exchange' => $EX_NAME);


$cons = array();
foreach ($conConfigs as $conf) {
    $conn = new amqp\Connection($conf);
    $conn->connect();
    $chan = $conn->getChannel();
    //initialiseDemo($chan);
    // Create a Message object
    $basicP = $chan->basic('publish', $publishParams);
    $cons[] = array($conn, $chan, $basicP);
}




$content = "My god, sending the same message thousands of times?  How dull!";
$n = 0;
for ($i = 0; $i < 5000; $i++) {
    foreach ($cons as $stuff) {
        $stuff[2]->setContent($content);
        $stuff[1]->invoke($stuff[2]);
        $n++;
    }
}


foreach ($cons as $stuff) {
    $stuff[1]->shutdown();
    $stuff[0]->shutdown();
}

printf("Test complete, published %d messages\n", $n);