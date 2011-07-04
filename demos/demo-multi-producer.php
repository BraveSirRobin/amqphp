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
/*
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
*/


$conConfigs[] = array(
    'username' => 'testing',
    'userpass' => 'letmein',
    'vhost' => 'robin',
    'consumerName' => 'C1',
    'socketImpl' => '\amqphp\StreamSocket',
    'socketParams' => array('url' => 'tcp://rabbit1:5672'),
    'socketFlags' => array('STREAM_CLIENT_PERSISTENT'));

$conConfigs[] = array(
    'username' => 'testing',
    'userpass' => 'letmein',
    'vhost' => 'robin',
    'consumerName' => 'C2',
    'socketImpl' => '\amqphp\StreamSocket',
    'socketParams' => array('url' => 'tcp://rabbit2:5672'),
    'socketFlags' => array('STREAM_CLIENT_PERSISTENT'));




$publishParams = array(
    'content-type' => 'text/plain',
    'content-encoding' => 'UTF-8',
    'routing-key' => '',
    'mandatory' => false,
    'immediate' => false,
    'exchange' => $EX_NAME);


$cons = array();
foreach ($conConfigs as $conf) {
    $conn = new amqp\PConnection($conf);
    $conn->setPersistenceHelperImpl('\\amqphp\\FilePersistenceHelper');
    $conn->connect();
    $chan = $conn->getChannel();
    //initialiseDemo($chan);
    // Create a Message object
    $basicP = $chan->basic('publish', $publishParams);
    $cons[] = array($conn, $chan, $basicP);
}




$content = "My god, sending the same message thousands of times?  How dull!";
$n = 0;
for ($i = 0; $i < 500; $i++) {
    foreach ($cons as $stuff) {
        $stuff[2]->setContent($content);
        $stuff[1]->invoke($stuff[2]);
        $n++;
    }
}


foreach ($cons as $stuff) {
    $stuff[1]->shutdown(); // Shut down channel only.
    $stuff[0]->sleep();
    //$stuff[0]->shutdown();
}

printf("<pre>Test complete, published %d messages</pre>", $n);
