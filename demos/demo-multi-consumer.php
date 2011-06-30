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

$EX_NAME = 'most-basic';
$EX_TYPE = 'direct';
$Q = 'most-basic';

// Basic RabbitMQ connection settings
$conConfigs = array();
/*
$conConfigs[] = array(
    'username' => 'testing',
    'userpass' => 'letmein',
    'vhost' => 'robin',
    'consumerName' => 'C1',
    'heartbeat' => 5,
    'socketParams' => array('host' => 'rabbit1', 'port' => 5672));
$conConfigs[] = array(
    'username' => 'testing',
    'userpass' => 'letmein',
    'vhost' => 'robin',
    'consumerName' => 'C2',
    'heartbeat' => 5,
    'socketParams' => array('host' => 'rabbit2', 'port' => 5672));

*/

$conConfigs[] = array(
    'username' => 'testing',
    'userpass' => 'letmein',
    'vhost' => 'robin',
    'consumerName' => 'C1',
    'heartbeat' => 5,
    'socketImpl' => '\amqphp\StreamSocket',
    'socketParams' => array('url' => 'tcp://rabbit1:5672'));

$conConfigs[] = array(
    'username' => 'testing',
    'userpass' => 'letmein',
    'vhost' => 'robin',
    'consumerName' => 'C2',
    'heartbeat' => 5,
    'socketImpl' => '\amqphp\StreamSocket',
    'socketParams' => array('url' => 'tcp://rabbit1:5672'));




// A class to use as the consumer
class DemoConsumer extends amqp\SimpleConsumer
{
    private $name;
    function __construct ($name) {
        $this->name = $name;
    }


    function handleDelivery (wire\Method $meth, amqp\Channel $chan) {
        printf("[recv:%s]\n%s\n", $this->name, substr($meth->getContent(), 0, 10));
        return amqp\CONSUMER_ACK;
    }
}

$cons = array();
foreach ($conConfigs as $i => $conf) {
    $conn = new amqp\Connection($conf);
    $conn->connect();
    $chan = $conn->getChannel();
    initialiseDemo($chan);

    $qosParams = array('prefetch-count' => 1,
                       'global' => false);
    $qOk = $chan->invoke($chan->basic('qos', $qosParams));



    $receiver = new DemoConsumer($conf['consumerName']);

    // Attach our consumer receiver object to the channel
    $chan->addConsumer($receiver);



    if (0) {
    } else if (0) {
        list($uSecs, $secs) = explode(' ', microtime());
        $uSecs = bcmul($uSecs, '1000000');
        $conn->setSelectMode(amqp\SELECT_TIMEOUT_ABS,
                             bcadd($secs, '1'),
                             bcadd($uSecs, '500000'));
    } else if (0) {
        $conn->setSelectMode(amqp\SELECT_TIMEOUT_REL, 1, 500000);
    } else if ($i == 1) {
        echo "Second connection has callback exit function\n";
        $conn->setSelectMode(amqp\SELECT_CALLBACK,
                             function () {
                                 static $i = 0;
                                 $ret = ($i < 1500);
                                 $i++;
                                 echo $ret ? "Going to loop more\n" : "Going to exit\n";
                                 return $ret;
                             });
    } else {
        $conn->setSelectMode(amqp\SELECT_INFINITE);
    }

    $cons[] = array($conn, $chan);
}


// Create an event loop to catch incoming messages
$el = new amqp\EventLoop;
foreach ($cons as $conn) {
    $el->addConnection($conn[0]);
}
echo "Enter select loop\n";
$el->select();



foreach ($cons as $con) {
    if ($unDel = $conn->getUndeliveredMessages()) {
        printf("You have undelivered messages!\n");
        foreach ($unDel as $d) {
            printf(" Undelivered %s.%s\n", $d->getClassProto()->getSpecName(), $d->getMethodProto()->getSpecName());
        }
    }
    $chan->shutdown();
    $conn->shutdown();
}

echo "Script ends\n";
die;

//
// !!Script ends!!
//





/**
 * Sets up the queues and bindings needed for the demo
 */
function initialiseDemo ($chan) {
    global $EX_TYPE, $EX_NAME, $Q;
    $excDecl = $chan->exchange('declare', array(
                                   'type' => $EX_TYPE,
                                   'durable' => true,
                                   'exchange' => $EX_NAME));
    $eDeclResponse = $chan->invoke($excDecl); // Declare the queue


    if (false) {
        /**
         * This code demonstrates  how to set a TTL  value on a queue.
         * You have to  manually specify the table field  type using a
         * TableField  object  because  RabbitMQ expects  the  integer
         * x-message-ttl value to be  a long-long int (that's what the
         * second parameter, 'l', in  the constructore means).  If you
         * don't use a TableField,  Amqphp guesses the integer type by
         * choosing the smallest possible storage type.
         */
        $args = new \amqphp\wire\Table;
        $args['x-message-ttl'] = new \amqphp\wire\TableField(5000, 'l');

        $qDecl = $chan->queue('declare', array(
                                  'queue' => $Q,
                                  'arguments' => $args)); // Declare the Queue
    } else {
        $qDecl = $chan->queue('declare', array('queue' => $Q));
    }

    $chan->invoke($qDecl);

    $qBind = $chan->queue('bind', array(
                              'queue' => $Q,
                              'routing-key' => '',
                              'exchange' => $EX_NAME));
    $chan->invoke($qBind);// Bind Q to EX

}