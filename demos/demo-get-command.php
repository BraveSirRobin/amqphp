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
 * This file demonstrated the use of basic.get, a much simpler way to read messages
 * from a Broker then writing a full consumer.  Note that the exchange.declare, queue.declare,
 * queue.bind Amqp commands are needed only to create those broker object so as to prevent
 * this script from generating errors.  Declaring these broker objects when they already
 * exist is fine, provided *this* declaration doesn't clash with the object that already
 * exists on the broker.
 */
use amqp_091 as amqp;
use amqp_091\protocol;
use amqp_091\wire;

require __DIR__ . '/../amqp.php';
require __DIR__ . '/demo-common.php';


// Basic RabbitMQ connection settings
$config = array (
                 'username' => 'testing',
                 'userpass' => 'letmein',
                 'vhost' => 'robin'
                 );


// Connect to the RabbitMQ server, set up an Amqp channel
$conn = new amqp\Connection($config);
$conn->connect();
$chan = $conn->getChannel();

initialiseDemo();

// Now, we're ready to read a message
$getParams = array('queue' => $Q, 'no-ack' => false);
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
$chan->shutdown();
$conn->shutdown();