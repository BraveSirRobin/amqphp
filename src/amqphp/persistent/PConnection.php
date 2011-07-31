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
 * A  persistence  manager   wrapper  around  a  standard  connection.
 * Invokes  the  amqp  protocol  handshake only  when  the  underlying
 * connection has just been opened, for other requests Connection (and
 * possibly Channel  and Connection)  metadata is loaded  from storage
 * and re-used.   Requires the use of  the StreamSocket implementation
 * and forces the STREAM_CLIENT_PERSISTENT  flag to be passed when the
 * socket is opened.
 *
 */
class PConnection extends \amqphp\Connection implements \Serializable
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
     * List of Connection (super class) properties to be persisted.
     */
    private static $BasicProps = array('capabilities', 'chanMax','frameMax', 
                                       'vhost', 'nextChan', 'socketParams',
                                       'socketImpl', 'protoImpl', 'signalDispatch');

    private $sleepMode = self::PERSIST_CHANNELS;

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
    private $wakeupFlag = false; // TODO : Remove, use $stateFlag instead
    private $stateFlag = 0;

    const ST_CONSTR = 1;
    const ST_UNSER = 2;
    const ST_SER = 4;


    /**
     * Check that the given parameters make sense, throw exceptions if
     * an illegal param  is found then delegate to  parent to complete
     * object setup.
     *
     * @override
     * @throws \Exception
     */
    final function __construct (array $params = array()) {
        $this->stateFlag |= self::ST_CONSTR;
        // Make sure that heartbeat is set to zero.
        if (isset($params['heartbeat']) && $params['heartbeat'] > 0) {
            throw new \Exception("Persistent connections cannot use a heatbeat", 24803);
        }
        // Make sure that the StreamSocket implementation is being used.
        if (! array_key_exists('socketImpl', $params)) {
            $params['socketImpl'] = '\\amqphp\\StreamSocket';
        } else if ($params['socketImpl'] != '\\amqphp\\StreamSocket') {
            throw new \Exception("Persistent connections must use the StreamSocket socket implementation", 24804);
        }
        // Make sure that the persistent flag is set.
        if (! array_key_exists('socketFlags', $params)) {
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
            // Assume  that a  re-used persistent  socket  has already
            // gone through the handshake procedure.
            $this->wakeup();
        } else {
            $this->doConnectionStartup();
            if ($ph = $this->getPersistenceHelper()) {
                $ph->destroy();
            }
        }
    }



    /**
     * Destroy the  persistence data  after the connection  is closed.
     * @override
     */
    function shutdown () {
        $ph = $this->getPersistenceHelper();
        parent::shutdown();
        if ($ph) {
            $ph->destroy();
        }
    }


    /**
     * This channel will load PChannels
     */
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


    /**
     * Must be called after connection
     */
    private function getPersistenceHelper () {
        if (! $this->connected) {
            throw new \Exception("PConnection persistence helper cannot be created before the connection is open", 3789);
        } else if (! $this->pHelperImpl) {
            return false;
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
        if (! ($m == self::PERSIST_CHANNELS || $m == self::PERSIST_CONNECTION)) {
            trigger_error("Invalid sleep mode - ignored.", E_USER_WARNING);
            return;
        }
        $this->sleepMode = $m;
    }

    /**
     * Run the  sleep process.  This  must be called  at the end  of a
     * request to put the connection in to sleep mode
     */
    function sleep () {
        if (! ($ph = $this->getPersistenceHelper())) {
            throw new \Exception("Failed to load a persistence helper during sleep", 10785);
        }
        $ph->setData($this->serialize());
        $ph->save();
    }


    /**
     * @override \Serializable
     */
    function serialize () {
        $data = array();
        foreach (self::$BasicProps as $k) {
            $data[$k] = $this->$k;
        }


        // Test code
        error_log("TEST SLEEP:");
        sleep(1);
        if ($left = $this->sock->nbReadAll()) {
            error_log(sprintf("Found %d unread bytes during shutdown\n%s", strlen($left), wire\Hexdump::hexdump($left)));
        } else {
            error_log("No unread bytes.");
        }



        $z = array();
        $z[0] = $this->sleepMode;
        $z[1] = $data;
        if ($this->sleepMode == self::PERSIST_CHANNELS) {
            $z[2] = $this->chans;
        }
        $this->stateFlag |= self::ST_SER;
        return serialize($z);
    }



    /**
     * Can  be called manually  or from  unserialize(), in  the latter
     * case the underlying connection is re-established.
     * @override \Serializable
     */
    function unserialize ($serialised) {
        $data = unserialize($serialised);
        $rewake = false;
        // Check the object state to see if the constructor needs to be called.
        if ($this->stateFlag & self::ST_UNSER) {
            throw new \Exception("PConnection is already unserialized", 2886);
        } else if (! ($this->stateFlag & self::ST_CONSTR)) {
            $this->__construct();
            $rewake = true;
        } else if ($data[0] != $this->sleepMode) {
            trigger_error("PConnection constructed in different state", E_USER_WARNING);
        }
        $this->sleepMode = $data[0];

        // Restore Connection state
        foreach (self::$BasicProps as $k) {
            $this->$k = $data[1][$k];
        }

        // Reconnect only if we're being unserialised
        if ($rewake) {
            $this->initSocket();
            $this->sock->connect();
            if (! $this->sock->isReusedPSock()) {
                throw new \Exception("Persisted connection woken up with a fresh socket connection", 9249);
            }

            foreach (self::$BasicProps as $k) {
                if ($k == 'vhost' && $data[1][$k] != $this->sock->getVHost()) {
                    throw new \Exception("Persisted connection woken up as different VHost", 9250);
                }
            }
            $this->connected = true;
        }

        // Reawake channels if required
        if ($this->sleepMode == self::PERSIST_CHANNELS && isset($data[2])) {
            $this->chans = $data[2];
            foreach ($this->chans as $chan) {
                // Can't persistent cyclical relationships!
                $chan->setConnection($this);
            }
        }
        $this->stateFlag |= self::ST_UNSER;
    }




    /**
     * Wakeup procedure is invoked by the connection opening.
     */
    private function wakeup () {
        $this->connected = true;
        $this->wakeupFlag = true;

        // Load data from persistence store.
        if (! ($ph = $this->getPersistenceHelper())) {
            throw new \Exception("Failed to load persistence helper during wakeup", 1798);
        }
        if (! $ph->load()) {
            // Also destroy the TCP connection.
            try {
                $e = null;
                $this->shutdown();
            } catch (\Exception $e) { }
            throw new \Exception('Failed to reload amqp connection cache during wakeup', 8543, $e);
        }
        $this->unserialize($ph->getData());
    }

}