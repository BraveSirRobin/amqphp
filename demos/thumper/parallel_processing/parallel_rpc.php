<?php

require __DIR__ . '/../RpcClient.php';
$start = time();

$client = new RpcClient(__DIR__ . '/../config/rpc-client.xml');
$client->initClient();
$client->addRequest($argv[1], 'charcount', 'charcount'); //charcount is the request identifier
$client->addRequest(serialize(array('min' => 0, 'max' => (int) $argv[2])), 'random-int', 'random-int'); //random-int is the request identifier
echo "Waiting for replies…\n";
$replies = $client->getReplies();

var_dump($replies);

echo "Total time: ", time() - $start, "\n";

?>