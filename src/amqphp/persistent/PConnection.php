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

namespace amqphp\persistent;

use amqphp\protocol;
use amqphp\wire;


/**
 * This  class is  intended as  a helper  class to  make  dealing with
 * persistent connections easier.
 *
 * TODO: When a  fresh connection is opened, clear  any old cache that
 * might be lying around before opening the connection.
 */
class PConnection extends \amqphp\Connection
{

    /**
     * At sleep time the connection will only persist connection-level
     * properties, channels will not be touched.
     */
    const PERSIST_CONNECTION = 1;

   /**
     * At sleep time, the connection will set the connection will call
     * channel.flow on  all open  channels, meaning that  the channels
     * remain  open  between  requests.   At wakeup  time,  previously
     * opened  channels  will  be  re-created as  Channel  connections
     * automatically.
     */
    const PERSIST_CHANNELS = 2;


    /**
     * Connection has been started during connect() sequence
     */
    const SOCK_NEW = 1;

    /**
     * Connection has been re-used,  having been started by a previous
     * request.
     */
    const SOCK_REUSED = 2;



    /**
     * List of object fields that are persisted in both modes.
     */
    private static $BasicProps = array('capabilities', 'chanMax', 'frameMax', 'vhost', 'nextChan');

    private $sleepMode = self::PERSIST_CONNECTION;

    /**
     * An instance of PersistenceHelper.
     */
    private $pHelper;

    /**
     * The PersistenceHelper implementation class.
     */
    private $pHelperImpl;

    /**
     * Flag to track whether the wakeup process has been triggered
     */
    private $wakeupFlag = false;



    /**
     * Check that the given parameters make sense, throw exceptions if
     * an  illegal param  is found.   Delegate to  parent  to complete
     * object setup.
     * @override
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
     * @override
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
            $this->wakeupFlag = true;
            return ($this->sleepMode == self::PERSIST_CONNECTION)
                ? $this->wakeupModeNone()
                : $this->wakeupModeAll();

        } else {
            $this->doConnectionStartup();
            $ph = $this->getPersistenceHelper();
            $ph->destroy();
        }
    }



    /**
     * Destroy the  persistence data  after the connection  is closed.
     * @override
     */
    function shutdown () {
        $ph = $this->getPersistenceHelper();
        parent::shutdown();
        $ph->destroy();
    }


    protected function initNewChannel () {
        $impl = __NAMESPACE__ . "\\PChannel";
        return parent::initNewChannel($impl);
    }


    /**
     * Sets the local data persistence helper implementation class.
     */
    function setPersistenceHelperImpl ($clazz) {
        $this->pHelperImpl = $clazz;
    }


    private function getPersistenceHelper () {
        if (! $this->connected) {
            throw new \Exception("PConnection persistence helper cannot be created before the connection is open", 3789);
        }
        if (is_null($this->pHelper)) {
            $c = $this->pHelperImpl;
            $this->pHelper = new $c;
            if (! ($this->pHelper instanceof PersistenceHelper)) {
                throw new \Exception("PConnection persistence helper implementation is invalid", 26934);
            }
            $this->pHelper->setUrlKey($this->sock->getCK());
        }
        return $this->pHelper;
    }



    /**
     * Return the persistence  status of this connection, or  0 if not
     * connected.
     */
    function getPersistenceStatus () {
        if (! $this->connected) {
            return 0;
        } else if ($this->wakeupFlag) {
            return self::SOCK_REUSED;
        } else {
            return self::SOCK_NEW;
        }
    }


    function setSleepMode ($m) {
        $this->sleepMode = $m;
    }

    /**
     * Run the  sleep process.  This  must be called  at the end  of a
     * request to put the connection in to sleep mode
     */
    function sleep () {
        return ($this->sleepMode == self::PERSIST_CONNECTION)
            ? $this->sleepModeNone()
            : $this->sleepModeAll();
    }

    /**
     * The wakeup process for PERSIST_CONNECTION
     */
    private function wakeupModeNone () {
        $ph = $this->getPersistenceHelper();
        if (! $ph->load()) {
            // Also destroy the TCP connection.
            try {
                $e = null;
                $this->shutdown();
            } catch (\Exception $e) { }
            throw new \Exception('Failed to reload amqp connection cache during wakeup', 8543, $e);
        }
        $data = $ph->getData();

        foreach (self::$BasicProps as $k) {
            if ($k == 'vhost' && $data[$k] != $this->sock->getVHost()) {
                throw new \Exception("Persisted connection woken up as different VHost", 9250);
            }
            $this->$k = $data[$k];
        }
    }

    private function wakeupModeAll () {
        trigger_error("All mode persistence not implemented", E_USER_ERROR);
    }

    /**
     * The sleep process for PERSIST_CONNECTION
     */
    private function sleepModeNone () {
        if (! $this->wakeupFlag) {
            $data = array();
            foreach (self::$BasicProps as $k) {
                $data[$k] = $this->$k;
            }
            $ph = $this->getPersistenceHelper();
            $ph->setData($data);
            $ph->save();
        }
    }

    private function sleepModeAll () {
        trigger_error("All mode persistence not implemented", E_USER_ERROR);
    }
}