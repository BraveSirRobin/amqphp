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
 * This library is intended to be as "procedural" as possible - all
 * methods return just like regular functions, including those which
 * carry content.
 */

/**
 * TODO:
 *  (2) Implement exceptions for Amqp 'events', i.e. channel / connection exceptions, etc.
 *  (3) Implement default profiles - probably best to use a code generation approach
 */

namespace amqp_091;

use amqp_091\protocol;
use amqp_091\wire;

require('amqp.wire.php');
require('amqp.protocol.abstrakt.php');
require('gencode/amqp.0_9_1.php');



const DEBUG = false;



/**
 * Wrapper for a _single_ socket
 */
class Socket
{
    const READ_SELECT = 1;
    const WRITE_SELECT = 2;
    const READ_LENGTH = 4096;


    /** A store of all connected instances */
    private static $All = array();


    private $sock;
    private $connected = false;
    private $interrupt = false;

    //function __construct ($host, $port) {
    function __construct ($params) {
        $this->host = $params['host'];
        $this->port = $params['port'];
    }

    function connect () {
        if (! ($this->sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP))) {
            throw new \Exception("Failed to create inet socket", 7895);
        } else if (! socket_connect($this->sock, $this->host, $this->port)) {
            throw new \Exception("Failed to connect inet socket ({$this->host}, {$this->port})", 7564);
        }
        $this->connected = true;
        self::$All[] = $this;
    }

    /** TODO: this function can't be used for read/write, only one at a time! */
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
        $this->interrupt = false;
        $ret = socket_select($read, $write, $ex, $tvSec, $tvUsec);
        if ($ret === false && $this->lastError() == SOCKET_EINTR) {
            $this->interrupt = true;
        }
        return $ret;
    }

    // Does a read select on all statically referenced instances
    function Zelekt () {
        $write = null;
        $read = array();
        foreach (self::$All as $i => $o) {
            $read[$i] = $o->sock;
        }
        $ex = $read;

        $ret = socket_select($read, $write, $ex, null, 0);
        if ($ret === false) {
            // A bit of an assumption here, but I can't see any more reliable method to
            // detect an interrupt using the streams API (unlike SOCKET_EINTR with the
            // sockets API)
            return false;
        }
        $_read = $_ex = array();
        foreach ($read as $k => $sock) {
            $_read[] = self::$All[$k];
        }
        foreach ($ex as $k => $sock) {
            $_ex[] = self::$All[$k];
        }
        return array($ret, $_read, $_ex);
    }



    /** Return true if the last call to select was interrupted */
    function selectInterrupted () {
        return $this->interrupt;
    }

    /** Call select to wait for content then read and return it all */
    function read () {
        $select = $this->select(5); // SL1
        if ($select === false) {
            return false;
        } else if ($select > 0) {
            $buff = $this->readAll();
        }
        return $buff;
    }


    function lastError () {
        return socket_last_error();
    }

    function strError () {
        return socket_strerror($this->lastError());
    }

    function readAll ($readLen = self::READ_LENGTH) {
        $buff = '';
        while (@socket_recv($this->sock, $tmp, $readLen, MSG_DONTWAIT)) {
            $buff .= $tmp;
        }
        if (DEBUG) {
            echo "\n<read>\n";
            echo wire\hexdump($buff);
        }
        return $buff;
    }

    function write ($buff) {
        $bw = 0;
        $contentLength = strlen($buff);
        while (true) {
            if (DEBUG) {
                echo "\n<write>\n";
                echo wire\hexdump($buff);
            }
            if (($tmp = socket_write($this->sock, $buff)) === false) {
                throw new \Exception(sprintf("\nSocket write failed: %s\n",
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
            unset($All[$k]);
        }
    }
}

/*
For SSL w. client certs:

Set up your certca, server and client client certs exactly as per this:
               http://www.rabbitmq.com/ssl.html

..then some additional steps for the php client cert..

cat client/key.pem > phpcert.pem
cat client/cert.pem >> phpcert.pem
cat testca/cacert.pem >> phpcert.pem
as per the this:
  http://uk2.php.net/manual/en/function.stream-socket-client.php#77497

.. similarly for the php server cert..
server/key.pem > php-server-cert.pem
server/cert.pem >> php-server-cert.pem
testca/cacert.pem >> php-server-cert.pem

Now, use phpcert.pem for the php client cert, and php-server-cert.pem
for the php server cert.  You can now do client authentication and
peer verification!

 */
class StreamSocket
{
    const READ_SELECT = 1;
    const WRITE_SELECT = 2;
    const READ_LENGTH = 4096;

    /** A store of all connected instances */
    private static $All = array();

    private $host;
    private $port;
    private $connected;
    private $interrupt = false;

    function __construct ($params) {
        $this->url = $params['url'];
        $this->context = isset($params['context']) ? $params['context'] : array();
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

    // Does a read select on all statically referenced instances
    function Zelekt () {
        $write = null;
        $read = array();
        foreach (self::$All as $i => $o) {
            $read[$i] = $o->sock;
        }
        $ex = $read;

        $ret = stream_select($read, $write, $ex, null, 0);
        if ($ret === false) {
            // A bit of an assumption here, but I can't see any more reliable method to
            // detect an interrupt using the streams API (unlike SOCKET_EINTR with the
            // sockets API)
            return false;
        }
        $_read = $_ex = array();
        foreach ($read as $k => $sock) {
            $_read[] = self::$All[$k];
        }
        foreach ($ex as $k => $sock) {
            $_ex[] = self::$All[$k];
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
            unset($All[$k]);
        }
    }
}







/**
 * Wraps  a  single TCP  connection  to the  amqp  broker,  acts as  a
 * demultiplexer for many channels.   Event looping behaviours are set
 * here,   and   there    is   a   simple   single-connection   select
 * implementation.
 */
class Connection
{
    const SELECT_TIMEOUT_ABS = 1;
    const SELECT_TIMEOUT_REL = 2;
    const SELECT_MAXLOOPS = 3;
    const SELECT_CALLBACK = 4;
    const SELECT_COND = 5;
    const SELECT_INFINITE = 6;

    /** Default client-properties field used during connection setup */
    private static $ClientProperties = array(
        'product' => ' BraveSirRobin/amqphp',
        'version' => '0.6',
        'platform' => 'PHP 5.3 +',
        'copyright' => 'Copyright (c) 2010,2011 Robin Harvey (harvey.robin@gmail.com)',
        'information' => 'This software is released under the terms of the GNU LGPL: http://www.gnu.org/licenses/lgpl-3.0.txt');

    /** For RMQ 2.4.0+, server capabilites are stored here, as a plain array */
    public $capabilities;

    /** List of class fields that are settable connection params */
    private static $CProps = array(
        'socketImpl', 'socketParams', 'username', 'userpass', 'vhost', 'frameMax', 'chanMax', 'signalDispatch', 'heartbeat');
    //'blockTmSecs', 'blockTmMillis');

    /** Connection params */
    private $sock; // Socket wrapper object
    private $socketImpl = '\amqp_091\Socket'; // Socket impl class name
    private $socketParams = array('host' => 'localhost', 'port' => 5672); // Construct params for $socketImpl
    private $username;
    private $userpass;
    private $vhost;
    private $frameMax = 65536; // Negotated during setup.
    private $chanMax = 50; // Negotated during setup.
    private $heartbeat = 0; // Negotated during setup.
    private $signalDispatch = true;


    private $chans = array(); // Format: array(<chan-id> => Channel)
    private $nextChan = 1;


    /** Flag set when connection is in read blocking mode, waiting for messages */
    private $blocking = false;


    private $unDelivered = array(); // List of undelivered messages, Format: array(<wire\Method>)
    private $unDeliverable = array(); // List of undeliverable messages, Format: array(<wire\Method>)
    private $incompleteMethods = array(); // List of partial messages, Format: array(<wire\Method>)
    private $readSrc = null; // wire\Reader, used between reads when partial frames are read from the wire

    private $connected = false; // Flag flipped after protcol connection setup is complete

    /** Control variables for select loop parmeters */
    private $selectMode = self::SELECT_COND;
    private $selectParam;



    function __construct (array $params = array()) {
        $this->setConnectionParams($params);
    }

    /**
     * Assoc array sets the connection parameters
     */
    function setConnectionParams (array $params) {
        foreach (self::$CProps as $pn) {
            if (isset($params[$pn])) {
                $this->$pn = $params[$pn];
            }
        }
    }


    /** Shutdown child channels and then the connection  */
    function shutdown () {
        if (! $this->connected) {
            trigger_error("Cannot shut a closed connection", E_USER_WARNING);
            return;
        }
        foreach (array_keys($this->chans) as $cName) {
            $this->chans[$cName]->shutdown();
        }
        $meth = new wire\Method(protocol\ClassFactory::GetMethod('connection', 'close'));
        $meth->setField('reply-code', '');
        $meth->setField('reply-text', '');
        $meth->setField('class-id', '');
        $meth->setField('method-id', '');
        if (! $this->write($meth->toBin())) {
            trigger_error("Unclean connection shutdown (1)", E_USER_WARNING);
            return;
        }
        if (! ($raw = $this->read())) {
             trigger_error("Unclean connection shutdown (2)", E_USER_WARNING);
             return;
        }

        $meth = new wire\Method();
        $meth->readConstruct(new wire\Reader($raw));
        if (! ($meth->getClassProto() &&
               $meth->getClassProto()->getSpecName() == 'connection' &&
               $meth->getMethodProto() &&
               $meth->getMethodProto()->getSpecName() == 'close-ok')) {
            trigger_error("Channel protocol shudown fault", E_USER_WARNING);
        }
        $this->sock->close();
        $this->connected = false;
    }


    private function initSocket () {
        if (! isset($this->socketImpl)) {
            throw new \Exception("No socket implementation specified", 7545);
        }
        $this->sock = new $this->socketImpl($this->socketParams);
    }


    /** If not already connected, connect to the target broker and do Amqp connection setup */
    function connect (array $params = array()) {
        if ($this->connected) {
            trigger_error("Connection is connected already", E_USER_WARNING);
            return;
        }
        $this->setConnectionParams($params);
        $this->initSocket();
        $this->sock->connect();
        if (! $this->write(wire\PROTOCOL_HEADER)) {
            throw new \Exception("Connection initialisation failed (1)", 9873);
        }
        if (! ($raw = $this->read())) {
            throw new \Exception("Connection initialisation failed (2)", 9874);
        }
        if (substr($raw, 0, 4) == 'AMQP' && $raw !== wire\PROTOCOL_HEADER) {
            // Unexpected AMQP version
            throw new \Exception("Connection initialisation failed (3)", 9875);
        }
        $meth = new wire\Method();
        $meth->readConstruct(new wire\Reader($raw));
        if (($startF = $meth->getField('server-properties'))
            && isset($startF['capabilities'])
            && ($startF['capabilities']->getType() == 'F')) {
            // Captures RMQ 2.4.0+ capabilities
            $this->capabilities = $startF['capabilities']->getValue()->getArrayCopy();
        }

        // Expect start
        if ($meth->getMethodProto()->getSpecIndex() == 10 && $meth->getClassProto()->getSpecIndex() == 10) {
            $resp = $meth->getMethodProto()->getResponses();
            $meth = new wire\Method($resp[0]);
        } else {
            throw new \Exception("Connection initialisation failed (5)", 9877);
        }
        $meth->setField('client-properties', $this->getClientProperties());
        $meth->setField('mechanism', 'AMQPLAIN');
        $meth->setField('response', $this->getSaslResponse());
        $meth->setField('locale', 'en_US');
        // Send start-ok
        if (! ($this->write($meth->toBin()))) {
            throw new \Exception("Connection initialisation failed (6)", 9878);
        }

        if (! ($raw = $this->read())) {
            throw new \Exception("Connection initialisation failed (7)", 9879);
        }
        $meth = new wire\Method();
        $meth->readConstruct(new wire\Reader($raw));

        $chanMax = $meth->getField('channel-max');
        $frameMax = $meth->getField('frame-max');

        $this->chanMax = ($chanMax < $this->chanMax) ? $chanMax : $this->chanMax;
        $this->frameMax = ($this->frameMax == 0 || $frameMax < $this->frameMax) ? $frameMax : $this->frameMax;

        // Expect tune
        if ($meth->getMethodProto()->getSpecIndex() == 30 && $meth->getClassProto()->getSpecIndex() == 10) {
            $resp = $meth->getMethodProto()->getResponses();
            $meth = new wire\Method($resp[0]);
        } else {
            throw new \Exception("Connection initialisation failed (9)", 9881);
        }
        $meth->setField('channel-max', $this->chanMax);
        $meth->setField('frame-max', $this->frameMax);
        $meth->setField('heartbeat', $this->heartbeat);
        // Send tune-ok
        if (! ($this->write($meth->toBin()))) {
            throw new \Exception("Connection initialisation failed (10)", 9882);
        }

        // Now call connection.open
        $meth = $this->constructMethod('connection', array('open', array('virtual-host' => $this->vhost)));
        $meth = $this->invoke($meth);
        if (! $meth || ! ($meth->getMethodProto()->getSpecIndex() == 41 && $meth->getClassProto()->getSpecIndex() == 10)) {
            throw new \Exception("Connection initialisation failed (13)", 9885);
        }
        $this->connected = true;
    }

    /** Helper: return the client properties parameter used in connection setup. */
    private function getClientProperties () {
        /* Build table to use long strings - RMQ seems to require this. */
        $t = new wire\Table;
        foreach (self::$ClientProperties as $pn => $pv) {
            $t[$pn] = new wire\TableField($pv, 'S');
        }
        return $t;
    }

    /** Helper: return the Sasl response parameter used in connection setup. */
    private function getSaslResponse () {
        $t = new wire\Table();
        $t['LOGIN'] = new wire\TableField($this->username, 'S');
        $t['PASSWORD'] = new wire\TableField($this->userpass, 'S');
        $w = new wire\Writer();
        $w->write($t, 'table');
        return substr($w->getBuffer(), 4);
    }

    /**
     * Channel accessor / factory method, call with no params to create a new channel, or with
     * a channel number to access an existing channel by number
     */
    function getChannel ($num = false) {
        return ($num === false) ? $this->initNewChannel() : $this->chans[$num];
    }

    /** Flip internal flag the decides if pcntl_signal_dispatch() gets called in consume loop */
    function setSignalDispatch ($val) {
        $this->signalDispatch = (boolean) $val;
    }

    function removeChannel (Channel $chan) {
        if (false !== ($k = array_search($chan, $this->chans))) {
            unset($this->chans[$k]);
        } else {
            trigger_error("Channel not found", E_USER_WARNING);
        }
    }

    private function initNewChannel () {
        if (! $this->connected) {
            trigger_error("Connection is not connected - cannot create Channel", E_USER_WARNING);
            return null;
        }
        $newChan = $this->nextChan++;
        if ($this->chanMax > 0 && $newChan > $this->chanMax) {
            throw new \Exception("Channels are exhausted!", 23756);
        }
        $this->chans[$newChan] = new Channel($this, $newChan, $this->frameMax);
        $this->chans[$newChan]->initChannel();
        return $this->chans[$newChan];
    }


    function getVHost () { return $this->vhost; }


    function getSocketImplClass () { return $this->socketImpl; }

    /**
     * Returns  the  status of  the  connection  class protocol  state
     * tracking flag.  Note: doesn't not check the underlying socket.
     */
    function isConnected () { return $this->connected; }


    /** Read all available content from the wire, if an error / interrupt is
        detected, dispatch signal handlers and raise an exception */
    private function read () {
        $ret = $this->sock->read();
        if ($ret === false) {
            $errNo = $this->sock->lastError();
            if ($this->signalDispatch && $this->sock->selectInterrupted()) {
                pcntl_signal_dispatch();
            }
            $errStr = $this->sock->strError();
            throw new \Exception ("[1] Read block select produced an error: [$errNo] $errStr", 9963);
        }
        return $ret;
    }



    /** Low level protocol write function.  Accepts either single values or arrays of content */
    private function write ($buffs) {
        $bw = 0;
        foreach ((array) $buffs as $buff) {
            $bw += $this->sock->write($buff);
        }
        return $bw;
    }



    /**
     * Handle global connection messages.
     *  The channel number is 0 for all frames which are global to the connection (4.2.3)
     */
    private function handleConnectionMessage (wire\Method $meth) {
        if ($meth->isHeartbeat()) {
            $resp = "\x08\x00\x00\x00\x00\x00\x00\xce";
            $this->write($resp);
            return;
        }
        $clsMth = "{$meth->getClassProto()->getSpecName()}.{$meth->getMethodProto()->getSpecName()}";
        switch ($clsMth) {
        case 'connection.close':
            if ($culprit = protocol\ClassFactory::GetMethod($meth->getField('class-id'), $meth->getField('method-id'))) {
                $culprit = "{$culprit->getSpecClass()}.{$culprit->getSpecName()}";
            } else {
                $culprit = '(Unknown or unspecified)';
            }
            // Note: ignores the soft-error, hard-error distinction in the xml
            $errCode = protocol\Konstant($meth->getField('reply-code'));
            $eb = '';
            foreach ($meth->getFields() as $k => $v) {
                $eb .= sprintf("(%s=%s) ", $k, $v);
            }
            $tmp = $meth->getMethodProto()->getResponses();
            $closeOk = new wire\Method($tmp[0]);
            $em = "[connection.close] reply-code={$errCode['name']} triggered by $culprit: $eb";
            if ($this->write($closeOk->toBin())) {
                $em .= " Connection closed OK";
                $n = 7565;
            } else {
                $em .= " Additionally, connection closure ack send failed";
                $n = 7566;
            }
            $this->sock->close();
            throw new \Exception($em, $n);
        default:
            $this->sock->close();
            throw new \Exception(sprintf("Unexpected channel message (%s.%s), connection closed",
                                         $meth->getClassProto()->getSpecName(), $meth->getMethodProto()->getSpecName()), 96356);
        }
    }


    function isBlocking () { return $this->blocking; }


    /**
     * Enter a select loop in order to receive messages from the broker.
     * Use setSelectMode() to set an exit strategy for the loop.  Do not call
     * concurrently, this will raise an exception.  Use isBlocking() to test
     * whether select() should be called.
     * @throws Exception
     */
    function select () {
        if ($this->blocking) {
            throw new \Exception("Stream blocking is already in progress.", 8756);
        }
        $this->_select();
        foreach ($this->chans as $chan) {
            $chan->onSelectEnd();
        }

    }


    /**
     * Set parameters that control how the connection select loop behaves, implements
     * the following exit strategies:
     *  1) Absolute timeout - specify a {usec epoch} timeout, loop breaks after this.
     *     See the PHP man page for microtime(false).  Example: "0.025 1298152951"
     *  2) Relative timeout - same as Absolute timeout except the args are specified
     *     relative to microtime() at the start of the select loop.  Example: "0.75 2"
     *  3) Max loops
     *  4) Conditional exit (callback)
     *  5) Conditional exit (automatic) (current impl)
     *  6) Infinite

     * @param   integer    $mode      One of the SELECT_XXX consts.
     * @param   ...                   Following 0 or more params are $mode dependant
     * @return  boolean               True if the mode was set OK
     */
    function setSelectMode () {
        if ($this->blocking) {
            trigger_error("Select mode - cannot switch mode whilst blocking", E_USER_WARNING);
            return false;
        }
        $_args = func_get_args();
        if (! $_args) {
            trigger_error("Select mode - no select parameters supplied", E_USER_WARNING);
            return false;
        }
        switch ($mode = array_shift($_args)) {
        case self::SELECT_TIMEOUT_ABS:
        case self::SELECT_TIMEOUT_REL:
            @list($epoch, $usecs) = $_args;
            if (! $epoch || $usecs >= 1) {
                trigger_error("Select mode - invalid timeout params", E_USER_WARNING);
                return false;
            } else {
                if (preg_match("/[^0-9\.]/", (string) (float) $usecs)) {
                    trigger_error("Select mode - timeout precision not available", E_USER_WARNING);
                    return false;
                }
                $this->selectParam = array((string) $usecs, $epoch);
                $this->selectMode = $mode;
                return true;
            }
        case self::SELECT_MAXLOOPS:
            if (! is_int($ml = array_shift($_args)) || $ml == 0) {
                trigger_error("Select mode - invalid maxloops params", E_USER_WARNING);
                return false;
            } else {
                $this->selectParam = $ml;
                $this->selectMode = self::SELECT_MAXLOOPS;
                return true;
            }
        case self::SELECT_CALLBACK:
            if (! (($cb = array_shift($_args)) instanceof \Closure) ) {
                trigger_error("Select mode - invalid callback params", E_USER_WARNING);
                return false;
            } else {
                $this->selectParam = array($cb, $_args);
                $this->selectMode = self::SELECT_CALLBACK;
                return true;
            }
        case self::SELECT_COND:
            $this->selectMode = self::SELECT_COND;
            $this->selectParam = null;
            return true;
        case self::SELECT_INFINITE:
            $this->selectMode = self::SELECT_INFINITE;
            $this->selectParam = null;
            return true;
        default:
            trigger_error("Select mode - mode not found", E_USER_WARNING);
            return false;
        }
    }


    /**
     * The sole system select loop implementation.  All configuration is via. setSelectMode()
     */
    private function _select () {
        if ($this->blocking) {
            throw new \Exception("Multiple simultaneous read blocking not supported", 6362);
        }
        $this->blocking = true;

        // Notify all channels
        foreach ($this->chans as $chan) {
            $chan->onSelectStart();
        }

        // Indefinite system select by default
        $blockTmSecs = null;
        $blockTmMillis = 0;

        // Initialise exit strategy
        switch ($this->selectMode) {
        case self::SELECT_TIMEOUT_ABS:
            list($exUsecs, $exEpoch) = $this->selectParam;
            break;
        case self::SELECT_TIMEOUT_REL:
            list($uSecs, $epoch) = explode(' ', microtime());
            $exUsecs = bcadd($this->selectParam[0], $uSecs, 5);
            $exEpoch = bcadd($this->selectParam[1], $epoch, 0);
            break;
        case self::SELECT_MAXLOOPS:
            $loopNum = 0;
        }


        while (true) {
            $this->deliverAll();

            // Check the select parameters to see whether to return this time.
            switch ($this->selectMode) {
            case self::SELECT_TIMEOUT_ABS:
            case self::SELECT_TIMEOUT_REL:
                list($uSecs, $epoch) = explode(' ', microtime());
                $epDiff = bccomp($epoch, $exEpoch, 0);
                if ($epDiff > 0 || ($epDiff == 0 && bccomp($uSecs, $exUsecs, 5) >= 0)) {
                    goto select_end;
                } else {
                    // Calculate select blockout values that expire at the same as the target exit time
                    $udiff = bcsub($exUsecs, $uSecs, 5);
                    if (substr($udiff, 0, 1) == '-') {
                        $blockTmSecs = (int) bcsub($exEpoch, $epoch, 0) - 1;
                        $udiff = substr($udiff, 1);
                    } else {
                        $blockTmSecs = (int) bcsub($exEpoch, $epoch, 0);
                    }

                    $blockTmMillis = bcmul('1000000', $udiff);
                }
                break;
            case self::SELECT_MAXLOOPS:
                if (++$loopNum > $this->selectParam) {
                    goto select_end;
                }
                break;
            case self::SELECT_CALLBACK:
                $fn = $this->selectParam;
                if (true !== call_user_func_array($this->selectParam[0], $this->selectParam[1])) {
                    goto select_end;
                }
                break;
            case self::SELECT_COND:
                // Ensure there are local components listening
                $hasConsumers = false;
                foreach ($this->chans as $chan) {
                    if ($chan->canListen()) {
                        $hasConsumers = true;
                        break;
                    }
                }
                if (! $hasConsumers) {
                    goto select_end;
                }
                break;
            }

            if ($this->signalDispatch) {
                pcntl_signal_dispatch();
                if (! $this->connected) {
                    trigger_error("Connection is no longer connected, force exit of consume loop.", E_USER_WARNING);
                    goto select_end;
                }
            }

            $select = is_null($blockTmSecs) ?
                $this->sock->select(null) // SL1
                : $this->sock->select($blockTmSecs, $blockTmMillis); // SL1
            if ($select === false) {
                $errNo = $this->sock->lastError();
                if ($this->signalDispatch && $this->sock->selectInterrupted()) {
                    pcntl_signal_dispatch();
                }
                $errStr = $this->sock->strError();
                $this->blocking = false;
                throw new \Exception ("[2] Read block select produced an error: [$errNo] $errStr", 9963);
            } else if ($select > 0) {
                $buff = $this->sock->readAll();
                if ($buff && ($meths = $this->readMessages($buff))) {
                    $this->unDelivered = array_merge($this->unDelivered, $meths);
                } else if (! $buff) {
                    $this->blocking = false;
                    throw new \Exception("Empty read in blocking select loop : " . strlen($buff), 9864);
                }
            }
            $this->deliverAll();
        }
    select_end: // Evil goto!
        $this->blocking = false;
    }


    /**
     * Send the given method immediately, optionally wait for the response.
     * @arg  Method     $inMeth         The method to send
     * @arg  boolean    $noWait         Flag that prevents the default behaviour of immediately
     *                                  waiting for a response - used mainly during consume.  NOTE
     *                                  that this mechanism can also be triggered via. the use of
     *                                  an Amqp no-wait domain field set to true
     */
    function invoke (wire\Method $inMeth, $noWait=false) {
        if (! ($this->write($inMeth->toBin()))) {
            throw new \Exception("Send message failed (1)", 5623);
        }
        if (! $noWait && $inMeth->getMethodProto()->getSpecResponseMethods()) {
            if ($inMeth->getMethodProto()->hasNoWaitField()) {
                foreach ($inMeth->getMethodProto()->getFields() as $f) {
                    if ($f->getSpecDomainName() == 'no-wait' && $inMeth->getField($f->getSpecFieldName())) {
                        return;
                    }
                }
            }
            while (true) {
                if (! ($buff = $this->read())) {
                    throw new \Exception(sprintf("(2) Send message failed for %s.%s:\n",
                                                 $inMeth->getClassProto()->getSpecName(),
                                                 $inMeth->getMethodProto()->getSpecName()), 5624);
                }

                $meths = $this->readMessages($buff);
                foreach (array_keys($meths) as $k) {
                    $meth = $meths[$k];
                    unset($meths[$k]);
                    if ($inMeth->isResponse($meth)) {
                        if ($meths) {
                            $this->unDelivered = array_merge($this->unDelivered, $meths);
                        }
                        return $meth;
                    } else {
                        $this->unDelivered[] = $meth;
                    }
                }
            }
        }
    }




    /**
     * Convert the given raw wire content in to Method objects.  Connection and channel
     * messages are delivered immediately and not returned.
     */
    private function readMessages ($buff) {
        if (is_null($this->readSrc)) {
            $src = new wire\Reader($buff);
        } else {
            $src = $this->readSrc;
            $src->append($buff);
            $this->readSrc = null;
        }

        $allMeths = array(); // Collect all method here
        while (true) {
            $meth = null;
            // Check to see if the content can complete any locally held incomplete messages
            if ($this->incompleteMethods) {
                foreach ($this->incompleteMethods as $im) {
                    if ($im->canReadFrom($src)) {
                        $meth = $im;
                        $rcr = $meth->readConstruct($src);
                        break;
                    }
                }
            }
            if (! $meth) {
                $meth = new wire\Method;
                $this->incompleteMethods[] = $meth;
                $rcr = $meth->readConstruct($src);
            }

            if ($meth->readConstructComplete()) {
                if (false !== ($p = array_search($meth, $this->incompleteMethods, true))) {
                    unset($this->incompleteMethods[$p]);
                }
                if ($this->connected && $meth->getWireChannel() == 0) {
                    // Deliver Connection messages immediately, but only if the connection
                    // is already set up.
                    $this->handleConnectionMessage($meth);
                } else if ($meth->getWireClassId() == 20 &&
                           ($chan = $this->chans[$meth->getWireChannel()])) {
                    // Deliver Channel messages immediately
                    $chanR = $chan->handleChannelMessage($meth);
                    if ($chanR === true) {
                        $allMeths[] = $meth;
                    }
                } else {
                    $allMeths[] = $meth;
                }
            }

            if ($rcr === wire\Method::PARTIAL_FRAME) {
                $this->readSrc = $src;
                break;
            } else if ($src->isSpent()) {
                break;
            }
        }
        return $allMeths;
    }


    function getUndeliveredMessages () {
        return $this->unDelivered;
    }


    /**
     * Deliver all undelivered messages, collect and send all responses after incoming
     * messages are all dealt with.
     * NOTE: while / array_shift loop is used in case onDelivery call causes more messages to
     * be placed in local queue
     */
    private function deliverAll () {
        while ($this->unDelivered) {
            $meth = array_shift($this->unDelivered);
            if (isset($this->chans[$meth->getWireChannel()])) {
                $this->chans[$meth->getWireChannel()]->handleChannelDelivery($meth);
            } else {
                trigger_error("Message delivered on unknown channel", E_USER_WARNING);
                $this->unDeliverable[] = $meth;
            }
        }
    }

    function getUndeliverableMessages ($chan) {
        $r = array();
        foreach (array_keys($this->unDeliverable) as $k) {
            if ($this->unDeliverable[$k]->getWireChannel() == $chan) {
                $r[] = $this->unDeliverable[$k];
            }
        }
        return $r;
    }

    /** Remove all undeliverable messages for the given channel */
    function removeUndeliverableMessages ($chan) {
        foreach (array_keys($this->unDeliverable) as $k) {
            if ($this->unDeliverable[$k]->getWireChannel() == $chan) {
                unset($this->unDeliverable[$k]);
            }
        }
    }


    /**
     * Factory method creates wire\Method objects based on class name and parameters.
     *
     * @arg  string   $class       Amqp class
     * @arg  array    $_args       Format: array (<Amqp method name>, <Assoc method/class mixed field array>, <method content>)
     */
    function constructMethod ($class, $_args) {
        $method = (isset($_args[0])) ? $_args[0] : null;
        $args = (isset($_args[1])) ? $_args[1] : array();
        $content = (isset($_args[2])) ? $_args[2] : null;

        if (! ($cls = protocol\ClassFactory::GetClassByName($class))) {
            throw new \Exception("Invalid Amqp class or php method", 8691);
        } else if (! ($meth = $cls->getMethodByName($method))) {
            throw new \Exception("Invalid Amqp method", 5435);
        }

        $m = new wire\Method($meth);
        $clsF = $cls->getSpecFields();
        $mthF = $meth->getSpecFields();

        if ($meth->getSpecHasContent() && $clsF) {
            foreach (array_merge(array_combine($clsF, array_fill(0, count($clsF), null)), $args) as $k => $v) {
                $m->setClassField($k, $v);
            }
        }
        if ($mthF) {
            foreach (array_merge(array_combine($mthF, array_fill(0, count($mthF), '')), $args) as $k => $v) {
                $m->setField($k, $v);
            }
        }
        $m->setContent($content);
        return $m;
    }
}



final class EventLoop
{
    private static $Conns = array();
    private $In = false;

    final static function AddConnection (Connection $conn) {
        if (($k = array_search($conn, self::$Conns, true)) !== false) {
            trigger_error("Connection added to event loop twice", E_USER_WARNING);
            return false;
        }
        self::$Conns[] = $conn;
    }

    final static function RemoveConnection (Connection $conn) {
        if (($k = array_search($conn, self::$Conns, true)) !== false) {
            unset(self::$Conns[$k]);
        } else {
            trigger_error("No such connection", E_USER_WARNING);
            return false;
        }
    }

    final static function Select () {
        $sockImpl = false;
        foreach (self::$Conns as $c) {
            if ($c->isBlocking()) {
                throw new \Exception("Event loop cannot start - connection is already blocking", 3267);
            }
            if ($sockImpl === false) {
                $sockImpl = $c->getSocketImplClass();
            } else if ($sockImpl != $c->getSocketImplClass()) {
                throw new \Exception("Event loop doesn't support mixed socket implementations", 2678);
            }
            if (! $c->isConnected()) {
                throw new \Exception("Connection is not connected", 2174);
            }
        }
        
    }
}



class Channel
{
    /** The parent Connection object */
    private $myConn;

    /** The channel ID we're linked to */
    private $chanId;

    /** As set by the channel.flow Amqp method, controls whether content can be sent or not */
    private $flow = true;

    /** Flag set when the underlying Amqp channel has been closed due to an exception */
    private $destroyed = false;

    /** Set by negotiation during channel setup */
    private $frameMax;

    /** Used to track whether the channel.open returned OK. */
    private $isOpen = false;

    /**
     * Consumers for this channel, format array(array(<Consumer>, <consumer-tag OR false>, <#FLAG#>)+) 
     * #FLAG# is the consumer status, this is:
     *  'READY_WAIT' - not yet started, i.e. before basic.consume/basic.consume-ok
     *  'READY' - started and ready to recieve messages
     *  'CLOSED' - previously live but now closed, receiving a basic.cancel-ok triggers this.
     */
    private $consumers = array();

    /** Channel level callbacks for basic.ack (RMQ confirm feature) and basic.return */
    private $callbacks = array('publishConfirm' => null,
                               'publishReturn' => null,
                               'publishNack' => null);

    /** Store of basic.publish sequence numbers. */
    private $confirmSeqs = array();
    private $confirmSeq = 0;

    /** Flag set during RMQ confirm mode */
    private $confirmMode = false;


    function setPublishConfirmCallback (\Closure $c) {
        $this->callbacks['publishConfirm'] = $c;
    }


    function setPublishReturnCallback (\Closure $c) {
        $this->callbacks['publishReturn'] = $c;
    }

    function setPublishNackCallback (\Closure $c) {
        $this->callbacks['publishNack'] = $c;
    }


    function hasOutstandingConfirms () {
        return (bool) $this->confirmSeqs;
    }

    function setConfirmMode () {
        if ($this->confirmMode) {
            return;
        }
        $confSelect = $this->confirm('select');
        $confSelectOk = $this->invoke($confSelect);
        if (! ($confSelectOk instanceof wire\Method) || 
            ! ($confSelectOk->getClassProto()->getSpecName() == 'confirm' &&
               $confSelectOk->getMethodProto()->getSpecName() == 'select-ok')) {
            throw new \Exception("Failed to selectg confirm mode", 8674);
        }
        $this->confirmMode = true;
    }


    function __construct (Connection $rConn, $chanId, $frameMax) {
        $this->myConn = $rConn;
        $this->chanId = $chanId;
        $this->frameMax = $frameMax;
        $this->callbacks['publishConfirm'] = $this->callbacks['publishReturn'] = function () {};
    }


    function initChannel () {
        $meth = new wire\Method(protocol\ClassFactory::GetMethod('channel', 'open'), $this->chanId);
        $meth->setField('reserved-1', '');
        $resp = $this->myConn->invoke($meth);
    }

    /**
     * Factory method creates wire\Method objects based on class name and parameters.
     *
     * @arg  string   $class       Amqp class
     * @arg  array    $_args       Format: array (<Amqp method name>, <Assoc method/class mixed field array>, <method content>)
     */
    function __call ($class, $_args) {
        if ($this->destroyed) {
            throw new \Exception("Attempting to use a destroyed channel", 8766);
        }
        $m = $this->myConn->constructMethod($class, $_args);
        $m->setWireChannel($this->chanId);
        $m->setMaxFrameSize($this->frameMax);
        return $m;
    }

    /**
     * A wrapper for Connection->invoke() specifically for messages on this channel.
     */
    function invoke (wire\Method $m) {
        if ($this->destroyed) {
            throw new \Exception("Attempting to use a destroyed channel", 8767);
        } else if (! $this->flow) {
            trigger_error("Channel is closed", E_USER_WARNING);
            return;
        } else if (is_null($tmp = $m->getWireChannel())) {
            $m->setWireChannel($this->chanId);
        } else if ($tmp != $this->chanId) {
            throw new \Exception("Method is invoked through the wrong channel", 7645);
        }

        // Do numbering of basic.publish during confirm mode
        if ($this->confirmMode && $m->getClassProto()->getSpecName() == 'basic'
            && $m->getMethodProto()->getSpecName() == 'publish') {
            $this->confirmSeq++;
            $this->confirmSeqs[] = $this->confirmSeq;
        }


        return $this->myConn->invoke($m);
    }

    /**
     * Callback from the Connection object for channel frames and messages.  Only channel
     * class methods should be delivered here.
     * @param   $meth           A channel method for this channel
     * @return  boolean         True:  Add message to internal queue for regular delivery
     *                          False: Remove message from internal queue
     */
    function handleChannelMessage (wire\Method $meth) {
        $sid = "{$meth->getClassProto()->getSpecName()}.{$meth->getMethodProto()->getSpecName()}";

        switch ($sid) {
        case 'channel.flow':
            $this->flow = ! $this->flow;
            if ($r = $meth->getMethodProto()->getResponses()) {
                $meth = new wire\Method($r[0]);
                $meth->setWireChannel($this->chanId);
                $this->invoke($meth);
            }
            return false;
            break;
        case 'channel.close':
            if ($culprit = protocol\ClassFactory::GetMethod($meth->getField('class-id'), $meth->getField('method-id'))) {
                $culprit = "{$culprit->getSpecClass()}.{$culprit->getSpecName()}";
            } else {
                $culprit = '(Unknown or unspecified)';
            }
            // Note: ignores the soft-error, hard-error distinction in the xml
            $errCode = protocol\Konstant($meth->getField('reply-code'));
            $eb = '';
            foreach ($meth->getFields() as $k => $v) {
                $eb .= sprintf("(%s=%s) ", $k, $v);
            }
            $tmp = $meth->getMethodProto()->getResponses();
            $closeOk = new wire\Method($tmp[0]);
            $em = "[channel.close] reply-code={$errCode['name']} triggered by $culprit: $eb";

            try {
                $this->myConn->invoke($closeOk);
                $em .= " Channel closed OK";
                $n = 3687;
            } catch (\Exception $e) {
                $em .= " Additionally, channel closure ack send failed";
                $n = 2435;
            }
            throw new \Exception($em, $n);
        case 'channel.close-ok':
        case 'channel.open-ok':
            return true;
        default:
            throw new \Exception("Received unexpected channel message: $sid", 8795);
        }
    }


    /**
     * Delivery handler for all non-channel class input messages.
     */
    function handleChannelDelivery (wire\Method $meth) {
        $sid = "{$meth->getClassProto()->getSpecName()}.{$meth->getMethodProto()->getSpecName()}";

        switch ($sid) {
        case 'basic.deliver':
            return $this->deliverConsumerMessage($meth, $sid);
        case 'basic.return':
            $cb = $this->callbacks['publishReturn'];
            return false;
        case 'basic.ack':
            $cb = $this->callbacks['publishConfirm'];
            $this->removeConfirmSeqs($meth, $cb);
            return false;
        case 'basic.nack':
            $cb = $this->callbacks['publishNack'];
            $this->removeConfirmSeqs($meth, $cb);
            return false;
        default:
            throw new \Exception("Received unexpected channel delivery:\n$sid", 87998);
        }
    }


    /** Delivers 'Consume Session' messages to channels consumers, and handles responses. */
    private function deliverConsumerMessage ($meth, $sid) {
        // Look up the target consume handler and invoke the callback
        $ctag = $meth->getField('consumer-tag');
        list($cons, $status) = $this->getConsumerAndStatus($ctag);
        $response = $cons->handleDelivery($meth, $this);

        // Handle callback response signals, i.e the CONSUMER_XXX API messages, but only
        // for API responses to the basic.deliver message
        if ($sid !== 'basic.deliver' || ! $response) {
            return false;
        }

        if (! is_array($response)) {
            $response = array($response);
        }
        foreach ($response as $resp) {
            switch ($resp) {
            case CONSUMER_ACK:
                $ack = $this->basic('ack', array('delivery-tag' => $meth->getField('delivery-tag'),
                                                 'multiple' => false));
                $this->invoke($ack);
                break;
            case CONSUMER_DROP:
            case CONSUMER_REJECT:
                $rej = $this->basic('reject', array('delivery-tag' => $meth->getField('delivery-tag'),
                                                    'requeue' => ($resp == CONSUMER_REJECT)));
                $this->invoke($rej);
                break;
            case CONSUMER_CANCEL:
                // Basic.cancel this consumer, then change the it's status flag
                $cnl = $this->basic('cancel', array('consumer-tag' => $ctag, 'no-wait' => false));
                $cOk = $this->invoke($cnl);
                if ($cOk && ($cOk->getClassProto()->getSpecName() == 'basic'
                             && $cOk->getMethodProto()->getSpecName() == 'cancel-ok')) {
                    $this->setConsumerStatus($ctag, 'CLOSED') OR
                        trigger_error("Failed to set consumer status flag", E_USER_WARNING);

                } else {
                    throw new \Exception("Failed to cancel consumer - bad broker response", 9768);
                }
                $cons->handleCancelOk($cOk, $this);
                break;
            }
        }

        return false;
    }


    /** Helper: remove message sequence record(s) for the given basic.{n}ack (RMQ Confirm key) */
    private function removeConfirmSeqs (wire\Method $meth, \Closure $handler = null) {
        if ($meth->getField('multiple')) {

            $dtag = $meth->getField('delivery-tag');
            $this->confirmSeqs = array_filter($this->confirmSeqs, 
                                              function ($id) use ($dtag, $handler, $meth) {
                                                  if ($id <= $dtag) {
                                                      if ($handler) {
                                                          $handler($meth);
                                                      }
                                                      return false;
                                                  } else {
                                                      return true;
                                                  }
                                              });
        } else {
            $dt = $meth->getField('delivery-tag');
            if (isset($this->confirmSeqs)) {
                if ($handler) {
                    $handler($meth);
                }
                unset($this->confirmSeqs[array_search($dt, $this->confirmSeqs)]);
            }
        }
    }


    /** Perform a protocol channel shutdown and remove self from containing Connection  */
    function shutdown () {
        if (! $this->invoke($this->channel('close', array('reply-code' => '', 'reply-text' => '')))) {
            trigger_error("Unclean channel shutdown", E_USER_WARNING);
        }
        $this->myConn->removeChannel($this);
        $this->destroyed = true;
        $this->myConn = $this->chanId = $this->ticket = null;
    }

    function addConsumer (Consumer $cons) {
        foreach ($this->consumers as $c) {
            if ($c === $cons) {
                throw new \Exception("Consumer can only be added to channel once", 9684);
            }
        }
        $this->consumers[] = array($cons, false, 'READY_WAIT');
    }


    /**
     * Called from select loop to see whether this object wants to continue looping.
     * @return  boolean      True:  Request Connection stays in select loop
     *                       False: Confirm to connection it's OK to exit from loop
     */
    function canListen (){
        return $this->hasListeningConsumers() || $this->hasOutstandingConfirms();
    }

    function removeConsumer (Consumer $cons) {
        trigger_error("Consumers can no longer be directly removed", E_USER_DEPRECATED);
        return;
    }

    private function setConsumerStatus ($tag, $status) {
        foreach ($this->consumers as $k => $c) {
            if ($c[1] === $tag) {
                $this->consumers[$k][2] = $status;
                return true;
            }
        }
        return false;
    }


    private function getConsumerAndStatus ($tag) {
        foreach ($this->consumers as $c) {
            if ($c[1] == $tag) {
                return array($c[0], $c[2]);
            }
        }
        return array(null, 'INVALID');
    }


    function hasListeningConsumers () {
        foreach ($this->consumers as $c) {
            if ($c[2] === 'READY') {
                return true;
            }
        }
        return false;
    }



    /**
     * Channel callback from Connection->select() - prepare signal raised just
     * before entering the select loop.
     * @return  boolean         Return true if there are consumers present
     */
    function onSelectStart () {
        if (! $this->consumers) {
            return false;
        }
        foreach (array_keys($this->consumers) as $cnum) {
            if (false === $this->consumers[$cnum][1]) {
                $consume = $this->consumers[$cnum][0]->getConsumeMethod($this);
                $cOk = $this->invoke($consume);
                $this->consumers[$cnum][0]->handleConsumeOk($cOk, $this);
                $this->consumers[$cnum][2] = 'READY';
                $this->consumers[$cnum][1] = $cOk->getField('consumer-tag');
            }
        }
        return true;
    }

    function onSelectEnd () {
        $this->consuming = false;
    }
}

/**
 * Standard "consumer signals" - these can be returned from Consumer->handleDelivery method
 * and trigger the API to send the corresponding messages.
 */
const CONSUMER_ACK = 1; // basic.ack (multiple=false)
const CONSUMER_REJECT = 2; // basic.reject (requeue=true)
const CONSUMER_DROP = 3; // basic.reject (requeue=false)
const CONSUMER_CANCEL = 4; // basic.cancel (no-wait=false)


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

class SimpleConsumer implements Consumer
{
    protected $consumeParams;
    protected $consuming = false;

    function __construct (array $consumeParams) {
        $this->consumeParams = $consumeParams;
    }

    function handleCancelOk (wire\Method $meth, Channel $chan) {}

    function handleConsumeOk (wire\Method $meth, Channel $chan) { $this->consuming = true; }

    function handleDelivery (wire\Method $meth, Channel $chan) {}

    function handleRecoveryOk (wire\Method $meth, Channel $chan) {} // TODO: This is unreachable - use or lose!!

    function getConsumeMethod (Channel $chan) {
        return $chan->basic('consume', $this->consumeParams);
    }
}