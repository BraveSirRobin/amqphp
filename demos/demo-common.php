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
 * Shared code to define the exchange, queue and binding for all demos.
 */

require __DIR__ . '/demo-loader.php';

$EX_NAME = 'most-basic';
$EX_TYPE = 'direct';
$Q = 'most-basic';


function initialiseDemo () {
    global $chan, $EX_TYPE, $EX_NAME, $Q;
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