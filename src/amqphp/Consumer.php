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

namespace amqphp;

use amqphp\protocol;
use amqphp\wire;


// Interface for a consumer callback handler object, based on the RMQ java on here:
// http://www.rabbitmq.com/releases/rabbitmq-java-client/v2.2.0/rabbitmq-java-client-javadoc-2.2.0/com/rabbitmq/client/Consumer.html
interface Consumer
{
    function handleCancelOk (wire\Method $meth, Channel $chan);

    function handleConsumeOk (wire\Method $meth, Channel $chan);

    function handleDelivery (wire\Method $meth, Channel $chan);

    function handleRecoveryOk (wire\Method $meth, Channel $chan);

    function getConsumeMethod (Channel $chan);
}
