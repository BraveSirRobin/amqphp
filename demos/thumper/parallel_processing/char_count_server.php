<?php

require __DIR__ . '/../RpcServer.php';

define('BROKER_CONFIG', realpath(__DIR__ . '/../config/pp-broker-setup.xml'));

$charCount = function($word)
{
  sleep(2);
  return strlen($word);
};

$server = new RpcServer(__DIR__ . '/../config/rpc-client.xml');
$server->setCallback($charCount);
$server->initServer('charcount');
$server->start();

?>