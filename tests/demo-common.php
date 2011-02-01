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

$EX_NAME = 'most-basic';
$EX_TYPE = 'direct';
$Q = 'most-basic';


function initialiseDemo () {
    global $chan, $EX_TYPE, $EX_NAME, $Q;
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

}