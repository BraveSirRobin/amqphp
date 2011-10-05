<?php

require __DIR__ . '/../Consumer.php';


define('BROKER_CONFIG', realpath(__DIR__ . '/../config/qs-broker-setup.xml'));

$myConsumer = function($msg)
{
  echo $msg, "\n";
};

$consumer = new Consumer(realpath(__DIR__ . '/../config/connection.xml'));
$consumer->setCallback($myConsumer); //myConsumer could be any valid PHP callback
$consumer->consume(5); //5 is the number of messages to consume
