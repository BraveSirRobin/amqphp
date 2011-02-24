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
 * This file shows the most basic implementation of an Amqp consumer
 */
use amqp_091 as amqp;
use amqp_091\protocol;
use amqp_091\wire;

require __DIR__ . '/../amqp.php';
require __DIR__ . '/demo-common.php';

// Define a very simple Class to receive messages.
class DemoConsumer extends amqp\SimpleConsumer
{
    // Print the message we've received and send an Ack back to the broker
    function handleDelivery (wire\Method $meth, amqp\Channel $chan) {
        printf("[message received]\n%s\n", $meth->getContent());
        if ($meth->getContent() == 'end') {
            return array(amqp\CONSUMER_CANCEL, amqp\CONSUMER_ACK);
        } else if ($meth->getContent() == 'reject') {
            return amqp\CONSUMER_REJECT;
        } else {
            return amqp\CONSUMER_ACK;
        }
    }
}


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

// This step is optional, but recommended for beginners, we're telling
// RMQ to "only send us one message at once".
$qosParams = array('prefetch-count' => 1,
                   'global' => false);
$qOk = $chan->invoke($chan->basic('qos', $qosParams));


// Create a basic.consume Method - this tells the broker that we 
// want to consume messages from the given $Q

// Create a DemoConsumer object to receive messages
$receiver = new DemoConsumer(array('queue' => $Q,
                                   'no-local' => true,
                                   'no-ack' => false,
                                   'exclusive' => false,
                                   'no-wait' => false));
echo "Start Consume\n";
// Attach our consumer receiver object to the channel
$chan->addConsumer($receiver);


// To consume messages you need to go in to a select loop, one issue
// you've got (esp. in web programming) is how to exit the loop safely.
// You can use the Connection->setSelectMode() method to help, like this:

if (1) {
    // The default exit mode is "Conditional exit" - in this mode the
    // Connection objects calls to each connected channel every time
    // through the loop to see if there's anything still listening.  You
    // can use this to automatically exit the loop by disconnecting
    // Consumers by returning amqp\CONSUMER_CANCEL.  The channel will
    // stay connected if it either has connected Consumers, or there
    // are pending Publish confirms.
} else if (0) {
    // Set an absolute timeout in the params are epoch, millis
    $conn->setSelectMode(amqp\Connection::SELECT_TIMEOUT_ABS, time() + 5, 0.1246);
} else if (0) {
    // Set an relative timeout in the params are seconds, millis.
    // The "start point" is set right at the top of the select loop
    $conn->setSelectMode(amqp\Connection::SELECT_TIMEOUT_REL, 5, 0.1246);
} else if (0) {
    $conn->setSelectMode(amqp\Connection::SELECT_CALLBACK,
                         function () {
                             $ret = (rand(0,10) != 5);
                             echo $ret ? "Going to loop more\n" : "Going to exit\n";
                             return $ret;
                         });
} else {
    $conn->setSelectMode(amqp\Connection::SELECT_INFINITE);
}


// Instruct the connection object to begin listening for messages
$conn->select();




if ($unDel = $conn->getUndeliveredMessages()) {
    printf("You have undelivered messages!\n");
    foreach ($unDel as $d) {
        printf(" Undelivered %s.%s\n", $d->getClassProto()->getSpecName(), $d->getMethodProto()->getSpecName());
    }
}


$chan->shutdown();
$conn->shutdown();