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
 * TODO:  Investigate persistent  streams  in different  environments.
 * Note  that   connecting  to  the   same  broker  twice   fails  for
 * PConnections - you  end up trying to reconnect  the same connection
 * and triggering errors.
 */
class StreamSocket
{
    const READ_SELECT = 1;
    const WRITE_SELECT = 2;
    const READ_LENGTH = 4096;

    /** A store of all connected instances */
    private static $All = array();

    private static $Counter = 0;

    private $host;
    private $id;
    private $port;
    private $connected;
    private $interrupt = false;
    private $flags;
    private $vhost;
    private $stfp;

    function __construct ($params, $flags, $vhost) {
        $this->url = $params['url'];
        $this->context = isset($params['context']) ? $params['context'] : array();
        $this->flags = $flags ? $flags : array();
        $this->id = ++self::$Counter;
        $this->vhost = $vhost;
    }

    function getVHost () {
        return $this->vhost;
    }

    /** Return a cache key for this socket's address */
    function getCK () {
        return md5(sprintf("%s:%s:%s", $this->url, $this->getFlags(), $this->vhost));
    }


    private function getFlags () {
        $flags = STREAM_CLIENT_CONNECT;
        foreach ($this->flags as $f) {
            $flags |= constant($f);
        }
        return $flags;
    }


    /**
     * Connect  to  the  given  URL  with the  given  flags.   If  the
     * connection is  persistent, check  that the stream  socket isn't
     * shared between this and another StreamSocket object
     * @throws \Exception
     */
    function connect () {
        $context = stream_context_create($this->context);
        $flags = $this->getFlags();

        $this->sock = stream_socket_client($this->url, $errno, $errstr, 
                                           ini_get("default_socket_timeout"), 
                                           $flags, $context);

        $this->stfp = ftell($this->sock);

        if (! $this->sock) {
            throw new \Exception("Failed to connect stream socket {$this->url}, ($errno, $errstr): flags $flags", 7568);
        } else if (($flags & STREAM_CLIENT_PERSISTENT) && $this->stfp > 0) {
            foreach (self::$All as $sock) {
                if ($sock !== $this && $sock->getCK() == $this->getCK()) {
                    /* TODO: Investigate whether mixing persistent and
                     * non-persistent connections to  the same URL can
                     * provoke errors. */
                    $this->sock = null;
                    throw new \Exception(sprintf("Stream socket connection created a new wrapper object for " .
                                                 "an existing persistent connection on URL %s", $this->url), 8164);
                }
            }
        }
        if (! stream_set_blocking($this->sock, 0)) {
            throw new \Exception("Failed to place stream connection in non-blocking mode", 2795);
        }
        $this->connected = true;
        self::$All[] = $this;
    }

    /**
     * Use tell to figure out if  the socket has been newly created or
     * if it's a persistent socket which has been re-used.
     */
    function isReusedPSock () {
        return ($this->stfp > 0);
    }

    /**
     * Return the  ftell() value  that was recorded  immediately after
     * the underlying connection was opened.
     */
    function getConnectionStartFP () {
        return $this->stfp;
    }


    /**
     * Call ftell on the underlying stream and return the result
     */
    function tell () {
        return ftell($this->sock);
    }


    /**
     * A wrapper for the stream_socket function.
     */
    function select ($tvSec, $tvUsec = 0, $rw = self::READ_SELECT) {
        $read = $write = $ex = null;
        if ($rw & self::READ_SELECT) {
            $read = $ex = array($this->sock);
        }
        if ($rw & self::WRITE_SELECT) {
            $write = array($this->sock);
        }
        if (! $read && ! $write) {
            throw new \Exception("Select must read and/or write", 9864);
        }
        $this->interrupt = false;
        $ret = stream_select($read, $write, $ex, $tvSec, $tvUsec);
        if ($ret === false) {
            // A bit of an assumption here, but I can't see any more reliable method to
            // detect an interrupt using the streams API (unlike SOCKET_EINTR with the
            // sockets API)
            $this->interrupt = true;
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
            $ret = stream_select($read, $write, $ex, $tvSec, $tvUsec);
        }
        if ($ret === false) {
            // A bit of an assumption here, but I can't see any more reliable method to
            // detect an interrupt using the streams API (unlike SOCKET_EINTR with the
            // sockets API)
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


    /** Return true if the last call to select was interrupted */
    function selectInterrupted () {
        return $this->interrupt;
    }


    function lastError () {
        return 0;
    }

    function strError () {
        return '(\amqphp\StreamSocket->strError -- not implemented)';
    }

    function readAll ($readLen = self::READ_LENGTH) {
        $buff = '';
        do {
            $buff .= fread($this->sock, $readLen);
            $smd = stream_get_meta_data($this->sock);
            $readLen = min($smd['unread_bytes'], $readLen);
        } while ($smd['unread_bytes'] > 0);
        if (DEBUG) {
            echo "\n<read>\n";
            echo wire\Hexdump::hexdump($buff);
        }
        return $buff;
    }

    /**
     * Blocking read, calls select before attempting to read.
     */
    function read () {
        $buff = '';
        $select = $this->select(5);
        if ($select === false) {
            return false;
        } else if ($select > 0) {
            $buff = $this->readAll();
        }
        return $buff;
    }


    /**
     * Return the number of unread bytes, or false
     * @return  mixed    Int = number of bytes, False = error
     */
    function getUnreadBytes () {
        return ($smd = stream_get_meta_data($this->sock))
            ? $smd['unread_bytes']
            : false;
    }


    function eof () {
        return feof($this->sock);
    }


    function write ($buff) {
        $bw = 0;
        $contentLength = strlen($buff);
        if ($contentLength == 0) {
            return 0;
        }
        while (true) {
            if (DEBUG) {
                echo "\n<write>\n";
                echo wire\Hexdump::hexdump($buff);
            }
            if (($tmp = fwrite($this->sock, $buff)) === false) {
                throw new \Exception(sprintf("\nStream write failed (error): %s\n",
                                             $this->strError()), 7854);
            } else if ($tmp === 0) {
                throw new \Exception(sprintf("\nStream write failed (zero bytes written): %s\n",
                                             $this->strError()), 7855);
            }
            $bw += $tmp;
            if ($bw < $contentLength) {
                $buff = substr($buff, $bw);
            } else {
                break;
            }
        }
        fflush($this->sock);
        return $bw;
    }

    function close () {
        $this->connected = false;
        fclose($this->sock);
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
