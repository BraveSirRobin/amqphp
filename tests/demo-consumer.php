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

// Define a very simple Class to receive messages.
class DemoConsumer extends amqp\SimpleConsumer
{
    // Print the message we've received and send an Ack back to the broker
    function handleDelivery (wire\Method $meth, amqp\Channel $chan) {
        printf("[message received]\n%s\n", $meth->getContent());
        if ($meth->getContent() == 'end') {
            $chan->removeConsumer($this);
            return array($this->ack($meth), $this->cancel($meth));
        } else if ($meth->getContent() == 'reject') {
            // Reject the message and instruct the broker NOT to requeue it
            return $this->reject($meth, false);
        } else {
            return $this->ack($meth);
        }
    }
}


// Demo script configuration
$EX_NAME = 'most-basic';
$EX_TYPE = 'direct';
$Q = 'most-basic';

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

// Send commands to the RabbitMQ server to set up the exchange, queue and
// queue binding that we will listen to.
$excDecl = $chan->exchange('declare', array('type' => $EX_TYPE,
                                            'durable' => true,
                                            'exchange' => $EX_NAME));
$eDeclResponse = $chan->invoke($excDecl); // Declare the queue


$qDecl = $chan->queue('declare', array('queue' => $Q)); // Declare the Queue
$chan->invoke($qDecl);

$qBind = $chan->queue('bind', array('queue' => $Q,
                                    'routing-key' => '',
                                    'exchange' => $EX_NAME));
$chan->invoke($qBind);// Bind Q to EX


// Create a basic.consume Method - this tells the broker that we 
// want to consume messages from the given $Q
$basicC = $chan->basic('consume', array('queue' => $Q,
                                        'no-local' => true,
                                        'no-ack' => false,
                                        'exclusive' => false,
                                        'no-wait' => false));

// Create a DemoConsumer object to receive messages
$receiver = new DemoConsumer($basicC);
echo "Start Consume\n";
// Attach our consumer receiver object to the channel
$chan->addConsumer($receiver);

// Instruct the connection object to begin listening for messages
$conn->startConsuming();