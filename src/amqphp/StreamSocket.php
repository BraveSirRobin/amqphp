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

    function __construct ($params) {
        $this->url = $params['url'];
        $this->context = isset($params['context']) ? $params['context'] : array();
        $this->id = ++self::$Counter;
    }

    function connect () {
        $context = stream_context_create($this->context);
        $this->sock = stream_socket_client($this->url, $errno, $errstr, ini_get("default_socket_timeout"), STREAM_CLIENT_CONNECT, $context);
        if (! $this->sock) {
            throw new \Exception("Failed to connect stream socket {$this->url}, ($errno, $errstr)", 7568);
        }
        $this->connected = false;
        self::$All[] = $this;
    }

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
        return '';
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
            echo wire\hexdump($buff);
        }
        return $buff;
    }

    function read () {
        return $this->readAll();
    }


    function write ($buff) {
        $bw = 0;
        $contentLength = strlen($buff);
        while (true) {
            if (DEBUG) {
                echo "\n<write>\n";
                echo wire\hexdump($buff);
            }
            if (($tmp = fwrite($this->sock, $buff)) === false) {
                throw new \Exception(sprintf("\nStream write failed: %s\n",
                                             $this->strError()), 7854);
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
