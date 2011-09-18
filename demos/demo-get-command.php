<?php
/**
 * 
 * Copyright (C) 2010, 2011  Robin Harvey (harvey.robin@gmail.com)
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.

 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.

 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 */
/**
 * This file demonstrates the use  of basic.get, a much simpler way to
 * read messages from a Broker then writing a full consumer
 */
use amqphp as amqp;
use amqphp\protocol;
use amqphp\wire;

require __DIR__ . '/demo-loader.php';


$su = new amqp\Factory(__DIR__ . '/configs/multi-producer.xml');
$cons = array();
foreach ($su->run() as $res) {
    if ($res instanceof amqp\Connection) {
        $cons[] = $res;
    }
}

$conn = reset($cons);
$chans = $conn->getChannels();
$chan = reset($chans);

$Q = 'most-basic-q'; // Must match Q in broker-common-setup.xml


// Now, we're ready to read a message
$getParams = array('queue' => $Q, // Must match Q in broker-common-setup.xml
                   'no-ack' => false);
$getCommand = $chan->basic('get', $getParams);

// Send our command
$response = $chan->invoke($getCommand);

// Examine the response to see what we've got...
if ($response->getClassProto()->getSpecName() == 'basic' && 
    $response->getMethodProto()->getSpecName() == 'get-empty') {
    printf("There are no messages on the queue %s, try running the demo-producer script to add some first!\n", $Q);
} else if ($response->getClassProto()->getSpecName() == 'basic' && 
           $response->getMethodProto()->getSpecName() == 'get-ok') {
    printf("1 Message read from %s (%d messages remain on the queue):\n%s\n", $Q, $response->getField('message-count'), $response->getContent());
    // Now, remember that unless you 'ack' this message, it'll stay on the broker
    // Alternatively, you can choose to reject a message with basic.reject
    $ackParams = array('delivery-tag' => $response->getField('delivery-tag'),
                       'multiple' => false);
    $ack = $chan->basic('ack', $ackParams);
    $chan->invoke($ack);
}


// Gracefully close the connection
foreach ($cons as $conn) {
    $conn->shutdown();
}

echo "\nDemo script complete\n";