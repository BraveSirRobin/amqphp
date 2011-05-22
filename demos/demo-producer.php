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
use amqphp as amqp;
use amqphp\protocol;
use amqphp\wire;

require __DIR__ . '/../amqp.php';
require __DIR__ . '/demo-common.php';

// Basic RabbitMQ connection settings
$config = array (
    'username' => 'testing',
    'userpass' => 'letmein',
    'vhost' => 'robin',
    'socketParams' => array('host' => 'rabbit1', 'port' => 5672)
    );

$config = array(
    'username' => 'testing',
    'userpass' => 'letmein',
    'vhost' => 'robin',
    'socketImpl' => '\amqphp\StreamSocket',
    'socketParams' => array('url' => 'tcp://rabbit2:5672'));

// Connect to the RabbitMQ server, set up an Amqp channel
$conn = new amqp\Connection($config);
$conn->connect();
$chan = $conn->getChannel();

initialiseDemo();

// Prepare the 'header parameters' and message content - these will
// be sent to RabbitMQ
$publishParams = array('content-type' => 'text/plain',
                       'content-encoding' => 'UTF-8',
                       'routing-key' => '',
                       'mandatory' => false,
                       'immediate' => false,
                       'exchange' => $EX_NAME);


// Create a Message object
$basicP = $chan->basic('publish', $publishParams);

// Send multiple messages to the RabbitMQ broker using the channel set up earlier.
$messages = array('Hi!', 'guten Tag', 'ciao', 'buenos días');
$eval = array(
              'class' => 'basic',
              'method' => 'publish',
              'args' => $publishParams,
              'payload' => 'FINGERS!'
              );

$messages[] = 'eval:' . serialize($eval);

$messages = array_merge($messages, $messages, $messages, $messages, $messages, $messages, $messages, $messages, $messages, $messages);


$eval = array(
              'class' => 'queue',
              'method' => 'declare',
              'args' => array('queue' => 'most-basic'),
              'payload' => null
              );

$messages[] = 'eval:' . serialize($eval);

$eval = array(
              'class' => 'basic',
              'method' => 'publish',
              'args' => $publishParams,
              'payload' => 'TOES!'
              );

$messages[] = 'eval:' . serialize($eval);

$messages[] = 'end';
//$messages = array_merge($messages, array('Hi!', 'guten Tag', 'ciao', 'buenos días'));

foreach ($messages as $m) {
    $basicP->setContent($m);
    $chan->invoke($basicP);
}


$chan->shutdown();
$conn->shutdown();
printf("Test complete, published %d messages\n", count($messages));