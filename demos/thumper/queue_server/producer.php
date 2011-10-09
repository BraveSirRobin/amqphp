<?php

use amqphp as amqp;

require __DIR__ . '/../Consumer.php';

$f = new amqp\Factory(realpath(__DIR__ . '/../config/connection.xml'));
$tmp = $f->getConnections();
$conn = reset($tmp);
$tmp = $conn->getChannels();
$chan = reset($tmp);

$bp = $chan->basic('publish', array('exchange' => 'hello-exchange'), $argv[1]);
$chan->invoke($bp);

echo "Done.\n";