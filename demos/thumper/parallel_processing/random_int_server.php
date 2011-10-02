<?php

require __DIR__ . '/../RpcServer.php';


$randomInt = function($data)
{
  sleep(5);
  $data = unserialize($data);
  return rand($data['min'], $data['max']);
};

$server = new RpcServer(__DIR__ . '/../config/rpc-client.xml');
$server->initServer('random-int-queue');
$server->setCallback($randomInt);
$server->start();

?>