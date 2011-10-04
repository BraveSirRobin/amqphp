<?php

require __DIR__ . '/../RpcServer.php';

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