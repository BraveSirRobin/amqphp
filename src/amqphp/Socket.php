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

use amqphp\protocol;
use amqphp\wire;


/**
 * Wrapper for a _single_ socket
 */
class Socket
{
    const READ_SELECT = 1;
    const WRITE_SELECT = 2;
    const READ_LENGTH = 4096;

    /** For blocking IO operations, the timeout buffer in seconds. */
    const BLOCK_TIMEOUT = 5;


    /** A store of all connected instances */
    private static $All = array();

    /** Assign each socket an ID */
    private static $Counter = 0;

    private $host;
    private $port;

    private $sock;
    private $id;
    private $vhost;
    private $connected = false;
    private static $interrupt = false;
    

    function __construct ($params, $flags, $vhost) {
        $this->host = $params['host'];
        $this->port = $params['port'];
        $this->id = ++self::$Counter;
        $this->vhost = $vhost;
    }

    function getVHost () {
        return $this->vhost;
    }

    /** Return a cache key for this socket's address */
    function getCK () {
        return sprintf("%s:%s:%s", $this->host, $this->port, md5($this->vhost));
    }

    function connect () {
        if (! ($this->sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP))) {
            throw new \Exception("Failed to create inet socket", 7895);
        } else if (! socket_connect($this->sock, $this->host, $this->port)) {
            throw new \Exception("Failed to connect inet socket ({$this->host}, {$this->port})", 7564);
        } else if (! socket_set_nonblock($this->sock)) {
            throw new \Exception("Failed to switch connection in to non-blocking mode.", 2357);
        }
        $this->connected = true;
        self::$All[] = $this;
    }


    /**
     * Used   only    for   compatibility   with    the   StreamSocket
     * implementation: persistent connections  aren't supported in the
     * sockets extension.
     */
    function isReusedPSock () {
        return false;
    }

    /**
     * Puts  the local  socket  in to  a  select loop  with the  given
     * timeout and returns the result
     */
    function select ($tvSec, $tvUsec = 0, $rw = self::READ_SELECT) {
        $read = $write = $ex = null;
        if ($rw & self::READ_SELECT) {
            $read = $ex = array($this->sock);
        }
        if ($rw & self::WRITE_SELECT) {
            $write = $ex = array($this->sock);
        }
        if (! $read && ! $write) {
            throw new \Exception("Select must read and/or write", 9864);
        }
        self::$interrupt = false;
        $ret = socket_select($read, $write, $ex, $tvSec, $tvUsec);
        if ($ret === false && $this->lastError() == SOCKET_EINTR) {
            self::$interrupt = true;
        }
        return $ret;
    }


    /**
     * Call select on the given stream objects
     * @param   array    $incSet       List of Socket Id values of sockets to include in the select
     * @param   array    $tvSec        socket timeout - seconds
     * @param   array    $tvUSec       socket timeout - milliseconds
     * @return  array                  array(<select return>, <Socket[] to-read>, <Socket[] errs>)
     */
    static function Zelekt (array $incSet, $tvSec, $tvUsec) {
        $write = null;
        $read = $all = array();
        foreach (self::$All as $i => $o) {
            if (in_array($o->id, $incSet)) {
                $read[$i] = $all[$i] = $o->sock;
            }
        }
        $ex = $read;
        $ret = false;
        if ($read) {
            $ret = socket_select($read, $write, $ex, $tvSec, $tvUsec);
        }
        if ($ret === false && socket_last_error() == SOCKET_EINTR) {
            self::$interrupt = true;
            return false;
        }
        $_read = $_ex = array();
        foreach ($read as $sock) {
            if (false !== ($key = array_search($sock, $all, true))) {
                $_read[] = self::$All[$key];
            }
        }
        foreach ($ex as $k => $sock) {
            if (false !== ($key = array_search($sock, $all, true))) {
                $_ex[] = self::$All[$key];
            }
        }
        return array($ret, $_read, $_ex);
    }



    /**
     * Return true if the last call to select was interrupted
     */
    function selectInterrupted () {
        return self::$interrupt;
    }

    /**
     * Blocking version of readAll()
     */
    function read () {
        $buff = '';
        $select = $this->select(self::BLOCK_TIMEOUT);
        if ($select === false) {
            return false;
        } else if ($select > 0) {
            $buff = $this->readAll();
        }
        return $buff;
    }


    /**
     * Wrapper for the socket_last_error  function - return codes seem
     * to be system- dependant
     */
    function lastError () {
        return socket_last_error($this->sock);
    }

    /**
     * Clear errors on the local socket
     */
    function clearErrors () {
        socket_clear_error($this->sock);
    }

    /**
     * Simple wrapper for socket_strerror
     */
    function strError () {
        return socket_strerror($this->lastError());
    }

    /**
     * Performs  a non-blocking read  and consumes  all data  from the
     * local socket, returning the contents as a string
     */
    function readAll ($readLen = self::READ_LENGTH) {
        $buff = '';
        while (@socket_recv($this->sock, $tmp, $readLen, MSG_DONTWAIT)) {
            $buff .= $tmp;
        }
        if (DEBUG) {
            echo "\n<read>\n";
            echo wire\Hexdump::hexdump($buff);
        }
        return $buff;
    }

    /**
     * Performs a blocking write
     */
    function write ($buff) {
        if (! $this->select(self::BLOCK_TIMEOUT, 0, self::WRITE_SELECT)) {
            trigger_error('Socket select failed for write (socket err: "' . $this->strError() . ')',
                          E_USER_WARNING);
            return 0;
        }
        $bw = 0;
        $contentLength = strlen($buff);
        while (true) {
            if (DEBUG) {
                echo "\n<write>\n";
                echo wire\Hexdump::hexdump($buff);
            }
            if (($tmp = socket_write($this->sock, $buff)) === false) {
                throw new \Exception(sprintf("Socket write failed: %s",
                                             $this->strError()), 7854);
            }
            $bw += $tmp;
            if ($bw < $contentLength) {
                $buff = substr($buff, $bw);
            } else {
                break;
            }
        }
        return $bw;
    }

    function close () {
        $this->connected = false;
        socket_close($this->sock);
        $this->detach();
    }

    /** Removes self from Static store */
    private function detach () {
        if (false !== ($k = array_search($this, self::$All))) {
            unset(self::$All[$k]);
        }
    }

    function getId () {
        return $this->id;
    }
}
