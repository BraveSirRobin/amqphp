<?php
/**
 *
 * Copyright (C) 2010, 2011, 2012  Robin Harvey (harvey.robin@gmail.com)
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

namespace amqphp;

use amqphp\wire;


// Interface for a consumer callback handler object, based on the RMQ java on here:
// http://www.rabbitmq.com/releases/rabbitmq-java-client/v2.2.0/rabbitmq-java-client-javadoc-2.2.0/com/rabbitmq/client/Consumer.html
interface Consumer
{
    /**
     * Must handle Cancel-OK responses
     * @return void
     */
    function handleCancelOk (wire\Method $meth, Channel $chan);

    /**
     * Must  handle incoming  Cancel  messages.  This  is a  RabbitMq-
     * specific extension
     * @return void
     */
    function handleCancel (wire\Method $meth, Channel $chan);

    /**
     * Must handle Consume-OK responses
     * @return void
     */
    function handleConsumeOk (wire\Method $meth, Channel $chan);

    /**
     * Must handle message deliveries 
     * @return array    A list of \amqphp\CONSUMER_* consts, these are
     *                  sent to the broker by $chan
     */
    function handleDelivery (wire\Method $meth, Channel $chan);

    /**
     * Must handle Recovery-OK responses 
     * @return void
     */
    function handleRecoveryOk (wire\Method $meth, Channel $chan);

    /**
     * Lifecycle callback  method - calleds by  the containing channel
     * so   that   the  consumer   can   provide   it's  own   consume
     * parameters.  See:
     * http://www.rabbitmq.com/amqp-0-9-1-quickref.html#basic.consume
     * @return \amqphp\wire\Method    An amqp basic.consume method
     */
    function getConsumeMethod (Channel $chan);
}
