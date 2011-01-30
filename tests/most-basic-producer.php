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
 * This file shows the most basic implementation of an Amqp producer.
 */
use amqp_091 as amqp;
use amqp_091\protocol;
use amqp_091\wire;

require __DIR__ . '/../amqp.php';

// Messages will be sent to this exchange
$EX = 'unit-test-basic';

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

// Prepare the 'header parameters' and message content - these will
// be sent to RabbitMQ
$publishParams = array('content-type' => 'text/plain',
                       'content-encoding' => 'UTF-8',
                       'routing-key' => '',
                       'mandatory' => false,
                       'immediate' => false,
                       'exchange' => $EX);
$message = "You payload goes here!";

// Create a Message object
$basicP = $chan->basic('publish', $publishParams, $message);

// Send the Message to the RabbitMQ broker using the channel set up earlier.
$chan->invoke($basicP);


$chan->shutdown();
$conn->shutdown();
echo "Test complete!\n";