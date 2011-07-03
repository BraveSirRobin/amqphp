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


/**
 * This  class is  intended as  a helper  class to  make  dealing with
 * persistent connections easier.
 */
class PConnection extends Connection
{

    /**
     * Check that the given parameters make sense, throw exceptions if
     * an  illegal param  is found.   Delegate to  parent  to complete
     * object setup.
     * @throws \Exception
     */
    function __construct (array $params = array()) {
        // Make sure that heartbeat is set to zero.
        if (isset($params['heartbeat']) && $params['heartbeat'] > 0) {
            throw new \Exception("Persistent connections cannot use a heatbeat", 24803);
        }
        // Make sure that the StreamSocket implementation is being used.
        if ($params['socketImpl'] != '\amqphp\StreamSocket') {
            throw new \Exception("Persistent connections must use the StreamSocket socket implementation", 24804);
        }
        // Make sure that the persistent flag is set.
        if (! is_array($params['socketFlags'])) {
            $params['socketFlags'] = array('STREAM_CLIENT_PERSISTENT');
        } else if ( ! in_array('STREAM_CLIENT_PERSISTENT', $params['socketFlags'])) {
            $params['socketFlags'][] = 'STREAM_CLIENT_PERSISTENT';
        }
        parent::__construct($params);
    }



    /**
     * Over-ride the  connect method  so that we  can avoid  the setup
     * procedure for re-used sockets.
     * @throws \Exception
     */
    function connect () {
        if ($this->connected) {
            trigger_error("PConnection is connected already", E_USER_WARNING);
            return;
        }
        // Backward compat: if connection params are passed here, deal with them and emit a deprecated warning.
        if (($args = func_get_args()) && is_array($args[0])) {
            trigger_error("Setting connection parameters via. the connect method is deprecated, please specify " .
                          "these parameters in the Connection class constructor instead.", E_USER_DEPRECATED);
            $this->setConnectionParams($args[0]);
        }


        $this->initSocket();
        $this->sock->connect();

        if ($this->sock->isReusedPSock()) {
            // Assume that a re-used persistent socket has already gone through the handshake procedure.
            $this->connected = true;
            /**
             * Note that the setup code initialises the following:
             *  $this->capabilities
             *  $this->chanMax
             *  $this->frameMax
             * TODO: Set up a framework to persist and reload these settings.
             */
            echo "<pre>Re-use connection</pre>";
            return;
        }
        echo "<pre>Create new connection</pre> ";
        $this->doConnectionStartup();
    }
}