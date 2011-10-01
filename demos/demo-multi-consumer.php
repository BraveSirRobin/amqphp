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

use amqphp as amqp;
use amqphp\protocol;
use amqphp\wire;

require __DIR__ . '/demo-loader.php';


// A class to use as the consumer
class DemoConsumer extends amqp\SimpleConsumer
{
    private $name;
    function __construct ($consParams) {
        parent::__construct($consParams);
        $this->name = "demo-consumer-" . rand(0, 1000);
    }


    function handleDelivery (wire\Method $meth, amqp\Channel $chan) {
        printf("[recv:%s]\n%s\n", $this->name, substr($meth->getContent(), 0, 10));
        return amqp\CONSUMER_ACK;
    }
}


// Create a connection and set up exchanges / queues / bindings, etc.
$su = new amqp\Factory(__DIR__ . '/configs/multi-consumer.xml');
$cons = $su->getConnections();

// Create an event loop to catch incoming messages
$el = new amqp\EventLoop;

// Set the select mode on the connections and add to the event loop
foreach ($cons as $conn) {
    $el->addConnection($conn);
}


echo "Enter select loop\n";
$el->select();


foreach ($cons as $con) {
    if ($unDel = $con->getUndeliveredMessages()) {
        printf("You have undelivered messages!\n");
        foreach ($unDel as $d) {
            printf(" Undelivered %s.%s\n", $d->getClassProto()->getSpecName(), $d->getMethodProto()->getSpecName());
        }
    }
    $con->shutdown();
}

echo "Script ends\n";
