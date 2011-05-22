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
 *  (1)  Sort out handling select errors.
 */

namespace amqphp;

use amqphp\protocol;
use amqphp\wire;

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

    /** Assign each socket an ID */
    private static $Counter = 0;


    private $sock;
    private $id;
    private $connected = false;
    private static $interrupt = false;

    function __construct ($params) {
        $this->host = $params['host'];
        $this->port = $params['port'];
        $this->id = ++self::$Counter;
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
     * Call select to wait for content then read and return it all
     */
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
            unset(self::$All[$k]);
        }
    }

    function getId () {
        return $this->id;
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

    // Does a read select on all statically referenced instances
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



const SELECT_TIMEOUT_ABS = 1;
const SELECT_TIMEOUT_REL = 2;
const SELECT_MAXLOOPS = 3;
const SELECT_CALLBACK = 4;
const SELECT_COND = 5;
const SELECT_INFINITE = 6;






/**
 * Wraps  a  single TCP  connection  to the  amqp  broker,  acts as  a
 * demultiplexer for many channels.   Event looping behaviours are set
 * here,   and   there    is   a   simple   single-connection   select
 * implementation.
 */
class Connection
{
    // DEPRECATED - these consts are now stand-alone consts as they are used by more than one class in this package.
    const SELECT_TIMEOUT_ABS = SELECT_TIMEOUT_ABS;
    const SELECT_TIMEOUT_REL = SELECT_TIMEOUT_REL;
    const SELECT_MAXLOOPS = SELECT_MAXLOOPS;
    const SELECT_CALLBACK = SELECT_CALLBACK;
    const SELECT_COND = SELECT_COND;
    const SELECT_INFINITE = SELECT_INFINITE;

    /** Default client-properties field used during connection setup */
    private static $ClientProperties = array(
        'product' => ' BraveSirRobin/amqphp',
        'version' => '0.9-beta',
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
    private $socketImpl = '\amqphp\Socket'; // Socket impl class name
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

    private $slHelper;



    function __construct (array $params = array()) {
        $this->setConnectionParams($params);
        $this->setSelectMode(SELECT_COND);
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


    /**
     * Shutdown child channels and then the connection 
     */
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


    /**
     * If not already  connected, connect to the target  broker and do
     * Amqp connection setup
     */
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

    /**
     * Helper:  return   the  client  properties   parameter  used  in
     * connection setup.
     */
    private function getClientProperties () {
        /* Build table to use long strings - RMQ seems to require this. */
        $t = new wire\Table;
        foreach (self::$ClientProperties as $pn => $pv) {
            $t[$pn] = new wire\TableField($pv, 'S');
        }
        return $t;
    }

    /**
     * Helper: return  the Sasl response parameter  used in connection
     * setup.
     */
    private function getSaslResponse () {
        $t = new wire\Table();
        $t['LOGIN'] = new wire\TableField($this->username, 'S');
        $t['PASSWORD'] = new wire\TableField($this->userpass, 'S');
        $w = new wire\Writer();
        $w->write($t, 'table');
        return substr($w->getBuffer(), 4);
    }

    /**
     * Channel  accessor /  factory  method, call  with  no params  to
     * create a  new channel,  or with a  channel number to  access an
     * existing channel by number
     */
    function getChannel ($num = false) {
        return ($num === false) ? $this->initNewChannel() : $this->chans[$num];
    }

    function getChannels () {
        return $this->chans;
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

    function getSocketId () {
        return $this->sock->getId();
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


    /**
     * Read  all  available content  from  the  wire,  if an  error  /
     * interrupt is  detected, dispatch  signal handlers and  raise an
     * exception
     **/
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

    function setBlocking ($b) { $this->blocking = (boolean) $b; }


    /**
     * Enter  a select  loop in  order  to receive  messages from  the
     * broker.  Use setSelectMode() to  set an  exit strategy  for the
     * loop.  Do not call  concurrently, this will raise an exception.
     * Use  isBlocking() to  test whether  select() should  be called.
     * @throws Exception
     */
    function select () {
        $evl = new EventLoop;
        $evl->addConnection($this);
        $evl->select();
    }

    /**
     * Set  parameters that  control  how the  connection select  loop
     * behaves, implements the following exit strategies:
     *  1)  Absolute timeout -  specify a  {usec epoch}  timeout, loop
     *  breaks after this.  See the PHP man page for microtime(false).
     *  Example: "0.025 1298152951"
     *  2) Relative timeout - same as Absolute timeout except the args
     *  are  specified relative  to microtime()  at the  start  of the
     *  select loop.  Example: "0.75 2"
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
        case SELECT_TIMEOUT_ABS:
        case SELECT_TIMEOUT_REL:
            @list($epoch, $usecs) = $_args;
            $this->slHelper = new TimeoutSelectHelper;
            return $this->slHelper->configure($mode, $epoch, $usecs);
        case SELECT_MAXLOOPS:
            $this->slHelper = new MaxloopSelectHelper;
            return $this->slHelper->configure(SELECT_MAXLOOPS, array_shift($_args));
        case SELECT_CALLBACK:
            $cb = array_shift($_args);
            $this->slHelper = new CallbackSelectHelper;
            return $this->slHelper->configure(SELECT_CALLBACK, $cb, $_args);
        case SELECT_COND:
            $this->slHelper = new ConditionalSelectHelper;
            return $this->slHelper->configure(SELECT_COND, $this);
        case SELECT_INFINITE:
            $this->slHelper = new InfiniteSelectHelper;
            return $this->slHelper->configure(SELECT_INFINITE);
        default:
            trigger_error("Select mode - mode not found", E_USER_WARNING);
            return false;
        }
    }


    /**
     * Internal - proxy EventLoop "notify pre-select" signal to select
     * helper
     */
    function notifyPreSelect () {
        return $this->slHelper->preSelect();
    }

    /**
     * Internal  -  proxy EventLoop  "select  init"  signal to  select
     * helper
     */
    function notifySelectInit () {
        $this->slHelper->init($this);
        // Notify all channels
        foreach ($this->chans as $chan) {
            $chan->onSelectStart();
        }
    }

    /**
     * Internal - proxy EventLoop "complete" signal to select helper
     */
    function notifyComplete () {
        $this->slHelper->complete();
    }


    /**
     * Internal - used by EventLoop to instruct the connection to read
     * and deliver incoming messages.
     */
    function doSelectRead () {
        $buff = $this->sock->readAll();
        if ($buff && ($meths = $this->readMessages($buff))) {
            $this->unDelivered = array_merge($this->unDelivered, $meths);
        } else if ($buff == '') {
            $this->blocking = false;
            throw new \Exception("Empty read in blocking select loop : " . strlen($buff), 9864);
        }
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
     * Convert  the  given raw  wire  content  in  to Method  objects.
     * Connection and  channel messages are  delivered immediately and
     * not returned.
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
     * Deliver  all   undelivered  messages,  collect   and  send  all
     * responses  after incoming  messages are  all dealt  with. NOTE:
     * while / array_shift loop is used in case onDelivery call causes
     * more messages to be placed in local queue
     */
    function deliverAll () {
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

    /**
     * Remove all undeliverable messages for the given channel
     */
    function removeUndeliverableMessages ($chan) {
        foreach (array_keys($this->unDeliverable) as $k) {
            if ($this->unDeliverable[$k]->getWireChannel() == $chan) {
                unset($this->unDeliverable[$k]);
            }
        }
    }


    /**
     * Factory method creates wire\Method  objects based on class name
     * and parameters.
     *
     * @arg  string   $class       Amqp class
     * @arg  array    $_args       Format: array (<Amqp method name>,
     *                                            <Assoc method/class mixed field array>,
     *                                            <method content>)
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



/**
 * @internal
 */
interface SelectLoopHelper
{
    /**
     * Called once when the  select mode is first configured, possibly
     * with other parameters
     */
    function configure ($sMode);

    /**
     * Called once  per select loop run, calculates  initial values of
     * select loop timeouts.
     */
    function init (Connection $conn);

    /**
     * Called  each time round  the select  loop, returns  select loop
     * timeout  values, or  false to  signal that  looping  should end
     *
     * @return   mixed    Either: (Tuple of tvSec, tvUsec) Or: (false)
     */
    function preSelect ();

    /**
     * Notification that the loop has exited
     */
    function complete ();
}


class MaxloopSelectHelper implements SelectLoopHelper
{
    /** Config param - max loops value */
    private $maxLoops;

    /** Runtime param */
    private $nLoops;

    function configure ($sMode, $ml=null) {
        if (! is_int($ml) || $ml == 0) {
            trigger_error("Select mode - invalid maxloops params", E_USER_WARNING);
            return false;
        } else {
            $this->maxLoops = $ml;
            return true;
        }
    }

    function init (Connection $conn) {
        $this->nLoops = 0;
    }

    function preSelect () {
        if (++$this->nLoops > $this->maxLoops) {
            return false;
        } else {
            return array(null, 0);
        }
    }

    function complete () {}
}


/**
 * Question: why use bcmath functions?
 */
class TimeoutSelectHelper implements SelectLoopHelper
{
    /** Config param, one of SELECT_TIMEOUT_ABS or SELECT_TIMEOUT_REL */
    private $toStyle;

    /** Config param */
    private $secs;

    /** Config / Runtime param */
    private $usecs;

    /** Runtime param */
    private $epoch;

    /**
     * @param   integer     $sMode      The select mode const that was passed to setSelectMode
     * @param   string      $secs       The configured seconds timeout value
     * @param   string      $usecs      the configured millisecond timeout value (1 millionth os a second)
     */
    function configure ($sMode, $secs=null, $usecs=null) {
        $this->toStyle = $sMode;
        $this->secs = (string) $secs;
        $this->usecs = (string) $usecs;
        return true;
    }

    function init (Connection $conn) {
        if ($this->toStyle == Connection::SELECT_TIMEOUT_REL) {
            list($uSecs, $epoch) = explode(' ', microtime());
            $uSecs = bcmul($uSecs, '1000000');
            $this->usecs = bcadd($this->usecs, $uSecs);
            $this->epoch = bcadd($this->secs, $epoch);
            if (! (bccomp($this->usecs, '1000000') < 0)) {
                $this->epoch = bcadd('1', $this->epoch);
                $this->usecs = bcsub($this->usecs, '1000000');
            }
        } else {
            $this->epoch = $this->secs;
        }
    }

    function preSelect () {
        list($uSecs, $epoch) = explode(' ', microtime());
        $epDiff = bccomp($epoch, $this->epoch);
        if ($epDiff == 1) {
            //$epoch is bigger
            return false;
        }
        $uSecs = bcmul($uSecs, '1000000');
        if ($epDiff == 0 && bccomp($uSecs, $this->usecs) >= 0) {
            // $usecs is bigger
            return false;
        }

        // Calculate select blockout values that expire at the same as the target exit time
        $udiff = bcsub($this->usecs, $uSecs);
        if (substr($udiff, 0, 1) == '-') {
            $blockTmSecs = (int) bcsub($this->epoch, $epoch) - 1;
            $udiff = bcadd($udiff, '1000000');
        } else {
            $blockTmSecs = (int) bcsub($this->epoch, $epoch);
        }
        //printf("(secs, usecs) = (%s, %s)\n", $blockTmSecs, $udiff);
        return array($blockTmSecs, $udiff);
    }

    function complete () {}
}



class CallbackSelectHelper implements SelectLoopHelper
{
    private $cb;
    private $args;

    function configure ($sMode, $cb=null, $args=null) {
        if (! is_callable($cb)) {
            trigger_error("Select mode - invalid callback params", E_USER_WARNING);
            return false;
        } else {
            $this->cb = $cb;
            $this->args = $args;
            return true;
        }
    }

    function init (Connection $conn) {}

    function preSelect () {
        if (true !== call_user_func_array($this->cb, $this->args)) {
            return false;
        } else {
            return array(null, 0);
        }
    }

    function complete () {}
}



class ConditionalSelectHelper implements SelectLoopHelper
{
    /** A copy of the Connection from the init callback */
    private $conn;

    function configure ($sMode) {}

    function init (Connection $conn) {
        $this->conn = $conn;
    }

    function preSelect () {
        $hasConsumers = false;
        foreach ($this->conn->getChannels() as $chan) {
            if ($chan->canListen()) {
                $hasConsumers = true;
                break;
            }
        }
        if (! $hasConsumers) {
            return false;
        } else {
            return array(null, 0);
        }
    }

    function complete () {
        $this->conn = null;
    }
}


class InfiniteSelectHelper implements SelectLoopHelper
{
    function configure ($sMode) {}

    function init (Connection $conn) {}

    function preSelect () {
        return array(null, 0);
    }

    function complete () {}
}

/**
 * Use the  low level Zelect method  to allow consumers  to connect to
 * more than one exchange.
 */
class EventLoop
{
    private $cons = array();
    private static $In = false;

    function addConnection (Connection $conn) {
        $this->cons[$conn->getSocketId()] = $conn;
    }

    function removeConnection (Connection $conn) {
        if (array_key_exists($conn->getSocketId(), $this->cons)) {
            unset($this->cons[$conn->getSocketId()]);
        }
    }

    function select () {
        $sockImpl = false;
        foreach ($this->cons as $c) {
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

        // Notify that the loop begins
        foreach ($this->cons as $c) {
            $c->setBlocking(true);
            $c->notifySelectInit();
        }

        // The loop
        while (true) {
            $tv = array();
            foreach ($this->cons as $cid => $c) {
                $c->deliverAll();
                $tv[] = array($cid, $c->notifyPreSelect());
            }
            $psr = $this->processPreSelects($tv); // Connections could be removed here.
            if (is_array($psr)) {
                list($tvSecs, $tvUsecs) = $psr;
            } else if (is_null($psr) && empty($this->cons)) {
                // All connections have finished litening.
                return;
            } else {
                throw new \Exception("Unexpected PSR response", 2758);
            }

            $this->signal();

            if (is_null($tvSecs)) {
                list($ret, $read, $ex) = call_user_func(array($sockImpl, 'Zelekt'),
                                                        array_keys($this->cons), null, 0);
            } else {
                list($ret, $read, $ex) = call_user_func(array($sockImpl, 'Zelekt'),
                                                        array_keys($this->cons), $tvSecs, $tvUsecs);
            }
            if ($ret === false) {
                $this->signal();
                $errNo = $errStr = array('??');
                if ($ex) {
                    $errNo = $errStr = array();
                    foreach ($ex as $sock) {
                        $errNo[] = $sock->lastError();
                        $errStr[] = $sock->strError();
                    }
                }
                $eMsg = sprintf("[2] Read block select produced an error: [%s] (%s)",
                                implode(",", $errNo), implode("),(", $errStr));
                throw new \Exception ($eMsg, 9963);

            } else if ($ret > 0) {
                foreach ($read as $sock) {
                    $c = $this->cons[$sock->getId()];
                    $c->doSelectRead();
                    $c->deliverAll();
                }
                foreach ($ex as $sock) {
                    printf("--(Socket Exception (?))--\n");
                }
            }
        }
    }

    /**
     * Process  preSelect  responses,   remove  connections  that  are
     * complete  and  filter  out  the "soonest"  timeout.   Call  the
     * 'complete' callback for connections that get removed
     *
     * @return  mixed   Array = (tvSecs, tvUsecs), False = loop complete
     *                  (no more listening connections)
     */
    private function processPreSelects (array $tvs) {
        $wins = null;
        foreach ($tvs as $tv) {
            $sid = $tv[0]; // Socket id
            $tv = $tv[1]; // Return value from preSelect()
            if ($tv === false) {
                $this->cons[$sid]->notifyComplete();
                $this->cons[$sid]->setBlocking(false);
                $this->removeConnection($this->cons[$sid]);
            } else if (is_null($wins)) {
                $wins = $tv;
                $winSum = is_null($tv[0]) ? 0 : bcadd($tv[0], $tv[1], 5);
            } else if (! is_null($tv[0])) {
                // A Specific timeout
                if (is_null($wins[0])) {
                    $wins = $tv;
                } else {
                    // TODO: compact this logic - too many continues!
                    $diff = bccomp($wins[0], $tv[0]);
                    if ($diff == -1) {
                        // $wins second timeout is smaller
                        continue;
                    } else if ($diff == 0) {
                        // seconds are the same, compare millis
                        $diff = bccomp($wins[1], $tv[1]);
                        if ($diff == -1) {
                            continue;
                        } else if ($diff == 0) {
                            continue;
                        } else {
                            $wins = $tv;
                        }
                    } else {
                        // $wins second timeout is bigger
                        $wins = $tv;
                    }
                }
            }
        }
        return $wins;
    }

    private function signal () {
        if (true) {
            /**
             * TODO:  Signal,  then  check  that at  least  once  more
             * Connection is connected.
             */
            pcntl_signal_dispatch();
        }
    }
}



class Channel
{
    /** The parent Connection object */
    private $myConn;

    /** The channel ID we're linked to */
    private $chanId;

    /**
     * As  set  by  the  channel.flow Amqp  method,  controls  whether
     * content can be sent or not
     */
    private $flow = true;

    /**
     * Flag set when  the underlying Amqp channel has  been closed due
     * to an exception
     */
    private $destroyed = false;

    /**
     * Set by negotiation during channel setup
     */
    private $frameMax;

    /**
     * Used to track whether the channel.open returned OK.
     */
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
     * Factory method creates wire\Method  objects based on class name
     * and parameters.
     *
     * @arg  string   $class       Amqp class
     * @arg  array    $_args       Format: array (<Amqp method name>,
     *                                            <Assoc method/class mixed field array>,
     *                                            <method content>)
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
     * A wrapper for Connection->invoke() specifically for messages on
     * this channel.
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
     * Callback  from the  Connection  object for  channel frames  and
     * messages.  Only channel class methods should be delivered here.
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


    /**
     * Delivers 'Consume Session'  messages to channels consumers, and
     * handles responses.
     */
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


    /**
     * Helper:  remove  message   sequence  record(s)  for  the  given
     * basic.{n}ack (RMQ Confirm key)
     */
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


    /**
     * Perform  a  protocol  channel  shutdown and  remove  self  from
     * containing Connection
     */
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
     * Called from  select loop  to see whether  this object  wants to
     * continue looping.
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
     * Channel  callback from  Connection->select()  - prepare  signal
     * raised just before entering the select loop.
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
 * Standard  "consumer   signals"  -   these  can  be   returned  from
 * Consumer->handleDelivery  method and  trigger the  API to  send the
 * corresponding messages.
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