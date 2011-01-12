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
 *  (1) Reproduce interleaved messages (lots of channels, large messages, small frame size)
 *  (2) Test with split method, content header frames (maybe set the frameMax to silly value in setup?)
 *  (3) Implement exceptions for Amqp 'events', i.e. channel / connection exceptions, etc.
 *  (4) Consider switching to use the higher level stream socket PHP funcs - could
 *      be helpful to be able to use built-in SSL (+ client certs?!)
 *  (5) Implement default profiles - probably best to use a code generation approach
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

    private $sock;
    private $connected = false;

    function __construct ($host, $port) {
        $this->host = $host;
        $this->port = $port;
    }

    function connect () {
        if (! ($this->sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP))) {
            throw new \Exception("Failed to create inet socket", 7895);
        } else if (! socket_connect($this->sock, $this->host, $this->port)) {
            throw new \Exception("Failed to connect inet socket ({$this->host}, {$this->port})", 7564);
        }
        $this->connected = true;
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
        return socket_select($read, $write, $ex, $tvSec, $tvUsec);
    }

    /** Call select to wait for content then read and return it all */
    function read () {
        $select = $this->select(5);
        if ($select === false) {
            return false;
        } else if ($select > 0) {
            $buff = $this->readAll();
        }
        if (DEBUG) {
            echo "\n<read>\n";
            echo wire\hexdump($buff);
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
        return $buff;
    }

    function write ($buff) {
        $bw = 0;
        $contentLength = strlen($buff);
        while (true) {
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
    }
}


class StreamSocket
{
    const READ_SELECT = 1;
    const WRITE_SELECT = 2;
    const READ_LENGTH = 4096;


    function __construct ($host, $port) {
        $this->host = $host;
        $this->port = $port;
    }

    function connect () {
        //$this->sock = stream_socket_client("tcp://{$this->host}:{$this->port}", $errno, $errstr, 30);
        $this->sock = fsockopen($this->host, $this->port, $errno, $errstr);
        if (! $this->sock) {
            throw new \Exception("Failed to connect stream socket {$this->host}:{$this->port}, ($errno, $errstr)", 7568);
        }
    }

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
        //echo "  Call Select\n";
        $ret = stream_select($read, $write, $ex, $tvSec, $tvUsec);
        //echo "   SELECT ($tvSec, $tvUsec) -> $ret\n";
        return $ret;
    }

    function lastError () {
        return 0;
    }

    function strError () {
        return '';
    }

    function readAll ($readLen = self::READ_LENGTH) {
        $buff = '';
        //printf("Read - EOF: %b", feof($this->sock));
        do {
            $stream_meta_data = stream_get_meta_data($this->sock); //Added line
            //printf("READ START, (foef=%b) (bytes remain=%d)\n", feof($this->sock), $stream_meta_data['unread_bytes']);
            $buff .= fread($this->sock, $readLen);
            $stream_meta_data = stream_get_meta_data($this->sock); //Added line
            //printf("READ END, (foef=%b) (bytes remain=%d)\n", feof($this->sock), $stream_meta_data['unread_bytes']);
            $readLen = min($stream_meta_data['unread_bytes'], $readLen);
        } while ($stream_meta_data['unread_bytes'] > 0);
        //echo "~~~(read returns)\n";
        return $buff;
    }

    function read () {
        return $this->readAll();
    }

    function write ($buff) {
        $bw = 0;
        $contentLength = strlen($buff);
        while (true) {
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
        return $bw;

    }

    function close () {
        fclose($this->sock);
    }

}


class Connection
{
    /** Default client-properties field used during connection setup */
    private static $ClientProperties = array('product' => 'RobinTheBrave-amqphp',
                                             'version' => '0.5',
                                             'platform' => 'Linux baby!',
                                             'copyright' => 'Copyright (c) 2010,2011 Robin Harvey (harvey.robin@gmail.com)',
                                             'information' => 'This software is released under the terms of the GNU LGPL: http://www.gnu.org/licenses/lgpl-3.0.txt');

    /** List of class fields that are settable connection params */
    private static $CProps = array('host', 'port', 'username', 'userpass', 'vhost', 'frameMax', 'chanMax', 'signalDispatch');

    /** Connection params */
    private $sock; // Socket class
    private $host = 'localhost';
    private $port = 5672;
    private $username;
    private $userpass;
    private $vhost;
    private $frameMax = 65536; // Negotated during setup.
    private $chanMax = 50; // Negotated during setup.
    private $signalDispatch = true;

    /** Bytes written, received through this connection */
    private $bw = 0;
    private $br = 0;

    private $chans = array(); // Format: array(<chan-id> => Channel)
    private $nextChan = 1;


    /** Flag set when connection is in read blocking mode, waiting for messages */
    private $blocking = false;

    /** Flag is picked up in consume loop and causes it to exit immediately */
    private $consumeHalt = false;



    private $connected = false;


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

        if (! ($meth = new wire\Method($raw)) &&
            $meth->getClassProto() &&
            $meth->getClassProto()->getSpecName() == 'connection' &&
            $meth->getMethodProto() &&
            $meth->getMethodProto()->getSpecName() == 'close-ok') {
            trigger_error("Channel protocol shudown fault", E_USER_WARNING);
        }
        $this->sock->close();
        $this->connected = false;
    }


    /** If not already connected, connect to the target broker and do Amqp connection setup */
    function connect (array $params = array()) {
        if ($this->connected) {
            trigger_error("", E_USER_WARNING);
            return;
        }
        $this->setConnectionParams($params);
        // Establish the TCP connection
        $this->sock = new Socket($this->host, $this->port);
        $this->sock->connect();
        if (! ($this->write(wire\PROTOCOL_HEADER))) {
            // No bytes written?
            throw new \Exception("Connection initialisation failed (1)", 9873);
        }
        if (! ($raw = $this->read())) {
            throw new \Exception("Connection initialisation failed (2)", 9874);
        }
        if (substr($raw, 0, 4) == 'AMQP' && $raw !== wire\PROTOCOL_HEADER) {
            // Unexpected AMQP version
            throw new \Exception("Connection initialisation failed (3)", 9875);
        }
        $meth = new wire\Method($raw);

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
        $meth = new wire\Method($raw);

        $chanMax = $meth->getField('channel-max');
        $frameMax = $meth->getField('frame-max');
        //printf("\nBefore-Negotiate: (chanMax, frameMax):\nServer: (%d, %d)\nClient: (%d, %d)\n",
        //$chanMax, $frameMax, $this->chanMax, $this->frameMax);
        // TODO: Add API stuff to allow negoptiation policies
        $this->chanMax = ($chanMax < $this->chanMax) ? $chanMax : $this->chanMax;
        $this->frameMax = ($frameMax < $this->frameMax) ? $frameMax : $this->frameMax;
        //printf("\nAfter-Negotiate: (chanMax, frameMax):\nClient: (%d, %d)\n",
        //$this->chanMax, $this->frameMax);


        // Expect tune
        if ($meth->getMethodProto()->getSpecIndex() == 30 && $meth->getClassProto()->getSpecIndex() == 10) {
            $resp = $meth->getMethodProto()->getResponses();
            $meth = new wire\Method($resp[0]);
        } else {
            throw new \Exception("Connection initialisation failed (9)", 9881);
        }
        $meth->setField('channel-max', $this->chanMax);
        $meth->setField('frame-max', $this->frameMax);
        $meth->setField('heartbeat', 0);
        // Send tune-ok
        if (! ($this->write($meth->toBin()))) {
            throw new \Exception("Connection initialisation failed (10)", 9882);
        }

        // Now call connection.open
        $meth = new wire\Method(protocol\ClassFactory::GetMethod('connection', 'open'));
        $meth->setField('virtual-host', $this->vhost);
        $meth->setField('reserved-1', '');
        $meth->setField('reserved-2', '');

        if (! ($this->write($meth->toBin()))) {
            throw new \Exception("Connection initialisation failed (10)", 9883);
        }

        // Expect open-ok
        if (! ($raw = $this->read())) {
            throw new \Exception("Connection initialisation failed (11)", 9884);
        }
        $meth = new wire\Method($raw);
        if (! ($meth->getMethodProto()->getSpecIndex() == 41 && $meth->getClassProto()->getSpecIndex() == 10)) {
            throw new \Exception("Connection initialisation failed (13)", 9885);
        }
        $this->connected = true;
        echo "  Connection setup complete!\n";
    }

    private function getClientProperties () {
        /* Build table to use long strings - RMQ seems to require this. */
        $t = new wire\Table;
        foreach (self::$ClientProperties as $pn => $pv) {
            $t[$pn] = new wire\TableField($pv, 'S');
        }
        return $t;
    }

    private function getSaslResponse () {
        $t = new wire\Table();
        $t['LOGIN'] = new wire\TableField($this->username, 'S');
        $t['PASSWORD'] = new wire\TableField($this->userpass, 'S');
        $w = new wire\Writer();
        $w->write($t, 'table');
        return substr($w->getBuffer(), 4);
    }


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
        // HERE:  Channel setup code calls back to connection, which can't find channel.  Chicken / Egg
        $this->chans[$newChan] = new Channel($this, $newChan, $this->frameMax);
        $this->chans[$newChan]->initChannel();
        return $this->chans[$newChan];
    }


    function getVHost() { return $this->vhost; }


    /** Still 'synchronous', doesn't rely on frame end at end of buffer 
    private function read () {
        //return $this->sock->readAll();

        $select = $this->sock->select(5);
        if ($select === false) {
            $errNo = $this->sock->lastError();
            if ($this->signalDispatch && $errNo == SOCKET_EINTR) {
                // Select returned because we received a signal, dispatch to signal handlers, if present
                pcntl_signal_dispatch();
            }
            $errStr = $this->sock->strError();
            throw new \Exception ("[1] Read block select produced an error: [$errNo] $errStr", 9963);
        } else if ($select > 0) {
            $buff = $this->sock->readAll();
        }
        if (DEBUG) {
            echo "\n<read>\n";
            echo wire\hexdump($buff);
        }
        return $buff;
    }
    */


    private function read () {
        $ret = $this->sock->read();
        if ($ret === false) {
            $errNo = $this->sock->lastError();
            if ($this->signalDispatch && $errNo == SOCKET_EINTR) {
                pcntl_signal_dispatch();
            }
            $errStr = $this->sock->strError();
            throw new \Exception ("[1] Read block select produced an error: [$errNo] $errStr", 9963);
        }
        if (DEBUG) {
            echo "\n<read>\n";
            echo wire\hexdump($ret);
        }
        return $ret;
    }



    /** Low level protocol write function.  Accepts either single values or
        arrays of content */
    private function write ($buffs) {
        foreach ((array) $buffs as $buff) {
            if (DEBUG) {
                echo "\n<write>\n";
                echo wire\hexdump($buff);
            }
            $bw = $this->sock->write($buff);
        }
        return $bw;
    }


    function getBytesWritten () {
        return $this->bw;
    }
    function getBytesRead () {
        return $this->br;
    }


    /**
     * Handle global connection messages.
     *  The channel number is 0 for all frames which are global to the connection (4.2.3)
     */
    private function handleConnectionMessage (wire\Method $meth) {
        if ($meth->getClassProto()->getSpecName() == 'connection' &&
            $meth->getMethodProto()->getSpecName() == 'close') {
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
            $this->close();
            throw new \Exception($em, $n);
        } else {
            $this->close();
            throw new \Exception(sprintf("Unexpected channel message (%s.%s), connection closed",
                                         $meth->getClassProto()->getSpecName(), $meth->getMethodProto()->getSpecName()), 96356);
        }
    }


    function isBlocking () { return $this->isBlocking; }

    private $blockTmSecs = null;
    private $blockTmMillis = 0;

    function setBlockingTimeoutSecs ($nSecs) {
        if (is_null($nSecs)) {
            $this->blockTmSecs = null;
        } else {
            $this->blockTmSecs = (int) $nSecs;
        }
    }

    function setBlockingTimeoutMillis ($nMillis) {
        $this->blockTmMillis = (int) $nMillis;
    }


    private $unDelivered = array();
    private $unDeliverable = array();
    private $incompleteMethod = null;


    /**
     * Start unstarted Consumers on all channels, then go in to an endless select
     * loop, dispatching incoming message deliveries in order.  Can be called from
     * inside the consume loop, in this case the function will return immediately
     */
    function startConsuming () {
        $a = false;
        foreach ($this->chans as $chan) {
            $a = $chan->onConsumeStart() || $a;
        }
        if (! $a) {
            throw new \Exception("No consumers found in attached channels", 8755);
        }
        if (! $this->blocking) {
            $this->consumeSelectLoop();
            foreach ($this->chans as $chan) {
                $chan->onConsumeEnd();
            }
        }
    }

    /**
     * Flips a flag which triggers an unconditional exit from the consume loop.
     * NOTE: active consumers will not be triggered to send basic.cancel just
     * by calling this method!
     */
    function stopConsuming () {
        if (! $this->blocking) {
            return false;
        }
        $this->consumeHalt = true;
        return true;
    }


    /**
     * Blocks indefinitely waiting for messages to consume.  This routine is designed to
     * handle large prefetch count values by reading all wire content each time it becomes
     * available then delivereing in order.
     */
    private function consumeSelectLoop () {
        if ($this->blocking) {
            throw new \Exception("Multiple simultaneous read blocking not supported", 6362);
        }
        $this->blocking = true;

        while (true) {
            $this->deliverAll();
            if ($this->consumeHalt) {
                $this->consumeHalt = false;
                break;
            } else if ($this->signalDispatch) {
                pcntl_signal_dispatch();
                if (! $this->connected) {
                    trigger_error("Connection is no longer connected, force exit of consume loop.", E_USER_WARNING);
                    return;
                }
            }
            $select = is_null($this->blockTmSecs) ?
                $this->sock->select(null)
                : $this->sock->select($this->blockTmSecs, $this->blockTmMillis);
            if ($select === false) {
                $errNo = $this->sock->lastError();
                if ($this->signalDispatch && $errNo == SOCKET_EINTR) {
                    pcntl_signal_dispatch();
                }
                $errStr = $this->sock->strError();
                throw new \Exception ("[2] Read block select produced an error: [$errNo] $errStr", 9963);
            } else if ($select > 0) {
                $buff = $this->sock->readAll();
                if ($buff && ($meths = $this->readMessages($buff))) {
                    $this->unDelivered = array_merge($this->unDelivered, $meths);
                } else if (! $buff) {
                    throw new \Exception("Empty read in blocking select loop : " . strlen($buff), 9864);
                }
            }
            $this->deliverAll();
        }
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
    function sendMethod (wire\Method $inMeth, $noWait=false) {
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
     * TODO: Consider changing the code to stop using Method->getReader()->getRemainingBuffer()
     * - I suspect this is not a very memory-efficient implementation.
     */
    private function readMessages ($buff) {
        if (is_null($this->incompleteMethod)) {
            $meth = new wire\Method($buff);
        } else {
            $meth = $this->incompleteMethod;
            $meth->readContruct($buff);
            $this->incompleteMethod = null;
        }
        $allMeths = array(); // Collect all method here
        while (true) {
            if ($meth->readConstructComplete()) {
                if ($meth->getWireChannel() == 0) {
                    // Deliver Connection messages immediately
                    $this->handleConnectionMessage($meth);
                } else if ($meth->getWireClassId() == 20 &&
                           ($chan = $this->chans[$meth->getWireChannel()])) {
                    // Deliver Channel messages immediately
                    $chanR = $chan->handleChannelMessage($meth);
                    if ($chanR instanceof wire\Method) {
                        $this->sendMethod($chanR, true); // SMR
                    } else if ($chanR === true) {
                        // This is required to support sending channel messages
                        $allMeths[] = $meth;
                    }
                } else {
                    $allMeths[] = $meth;
                }
            } else {
                // Special case for a split message, return here so that $this->incompleteMethod remains set
                $this->incompleteMethod = $meth;
                break;
            }
            if (! $meth->getReader()->isSpent()) {
                $meth = new wire\Method($meth->getReader()->getRemainingBuffer());
            } else {
                break;
            }
        }
        return $allMeths;
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
                if (($resp = $this->chans[$meth->getWireChannel()]->handleChannelMessage($meth)) instanceof wire\Method) {
                    $this->sendMethod($resp, true);
                }
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

    /** Consumers for this channel, format array(array(<Consumer>, <consumer-tag OR false>)+) */
    private $consumers = array();


    function __construct (Connection $rConn, $chanId, $frameMax) {
        $this->myConn = $rConn;
        $this->chanId = $chanId;
        $this->frameMax = $frameMax;
    }


    function initChannel () {
        $meth = new wire\Method(protocol\ClassFactory::GetMethod('channel', 'open'), $this->chanId);
        $meth->setField('reserved-1', '');
        $resp = $this->myConn->sendMethod($meth);
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
        $method = (isset($_args[0])) ? $_args[0] : null;
        $args = (isset($_args[1])) ? $_args[1] : array();
        $content = (isset($_args[2])) ? $_args[2] : null;

        if (! ($cls = protocol\ClassFactory::GetClassByName($class))) {
            throw new \Exception("Invalid Amqp class or php method", 8691);
        } else if (! ($meth = $cls->getMethodByName($method))) {
            throw new \Exception("Invalid Amqp method", 5435);
        }

        $m = new wire\Method($meth, $this->chanId);
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
        $m->setMaxFrameSize($this->frameMax);
        //$m->setMaxFrameSize(10);
        return $m;
    }

    function invoke (wire\Method $m) {
        if ($this->destroyed) {
            trigger_error("Channel is destroyed", E_USER_WARNING);
        } else if (! $this->flow) {
            trigger_error("Channel is closed", E_USER_WARNING);
            return;
        }
        if ($this->destroyed) {
            throw new \Exception("Attempting to use a destroyed channel", 8767);
        }
        return $this->myConn->sendMethod($m);
    }

    /**
     * Callback from the Connection object for channel frames
     * @param   $meth           A channel method for this channel
     * @return  mixed           If a method is returned it will be sent by the channel
     *                          If true, the message will be delivered as normal by the Connection
     *                          Else, $meth will be removed from delivery queue by the Connection
     */
    function handleChannelMessage (wire\Method $meth) {
        $sid = "{$meth->getClassProto()->getSpecName()}.{$meth->getMethodProto()->getSpecName()}";

        switch ($sid) {
        case 'channel.flow':
            // TODO: Make sure that when shut off, the current message send is cancelled
            $this->flow = ! $this->flow;
            if ($r = $meth->getMethodProto()->getResponses()) {
                return $r[0];
            }
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
            $b1 = $this->myConn->getBytesWritten();
            $this->myConn->sendMethod($closeOk);
            if ($this->myConn->getBytesWritten() > $b1) {
                $em .= " Channel closed OK";
                $n = 3687;
            } else {
                $em .= " Additionally, channel closure ack send failed";
                $n = 2435;
            }
            throw new \Exception($em, $n);
        case 'channel.close-ok':
        case 'channel.open-ok':
            return true;
        case 'basic.deliver':
            if ($cons = $this->getConsumerForTag($meth->getField('consumer-tag'))) {
                return $cons->handleDelivery($meth, $this);
            }
            throw new \Exception("Unknown consumer tag (1) {$meth->getField('consumer-tag')}", 9684);
        case 'basic.cancel-ok':
            if ($cons = $this->getConsumerForTag($meth->getField('consumer-tag'))) {
                return $cons->handleCancelOk($meth, $this);
            }
            throw new \Exception("Unknown consumer tag (2)", 9685);
        case 'basic.recover-ok':
            if ($cons = $this->getConsumerForTag($meth->getField('consumer-tag'))) {
                return $cons->handleRecoveryOk($meth, $this);
            }
            throw new \Exception("Unknown consumer tag (3)", 9686);
        default:
            $hd = '';
            foreach ($meth->toBin() as $i => $bin) {
                $hd .= sprintf("  --(part %d)--\n%s\n", $i+1, wire\hexdump($bin));
            }
            throw new \Exception("Received unexpected channel method:\n$hd", 8795);
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
        $this->consumers[] = array($cons, false);
    }

    private function getConsumerForTag ($tag) {
        foreach ($this->consumers as $c) {
            if ($c[1] == $tag) {
                return $c[0];
            }
        }
        return null;
    }

    /**
     * Channel callback from Connection->startConsuming() - prepare consumers to receive
     * @return  boolean         Return true if there are consumers present
     */
    function onConsumeStart () {
        if (! $this->consumers) {
            return false;
        }
        foreach (array_keys($this->consumers) as $cnum) {
            if (false === $this->consumers[$cnum][1]) {
                $consChan = $this->consumers[$cnum][0]->getConsumeMethod()->getWireChannel();
                if ($this->chanId !== $consChan) {
                    throw new \Exception("Consumer has wrong channel", 8734);
                }
                $cOk = $this->invoke($this->consumers[$cnum][0]->getConsumeMethod());
                $this->consumers[$cnum][0]->handleConsumeOk($cOk, $this);
                $this->consumers[$cnum][1] = $cOk->getField('consumer-tag');
            }
        }
        return true;
    }

    function onConsumeEnd () {
        // TODO: Call this?!
        $this->consuming = false;
    }
}

// Interface for a simple blocking consumer - modelled on the RMQ Java here:
// http://www.rabbitmq.com/releases/rabbitmq-java-client/v2.2.0/rabbitmq-java-client-javadoc-2.2.0/com/rabbitmq/client/Consumer.html
interface Consumer
{
    function handleCancelOk (wire\Method $meth, Channel $chan);

    function handleConsumeOk (wire\Method $meth, Channel $chan);

    function handleDelivery (wire\Method $meth, Channel $chan);

    function handleRecoveryOk (wire\Method $meth, Channel $chan);

    function handleShutdownSignal (Channel $chan);

    function getConsumeMethod ();
}

class SimpleConsumer implements Consumer
{
    protected $consMeth;
    protected $consuming = false;

    function __construct (wire\Method $consume = null) {
        $this->consMeth = $consume ?
            $consume
            : protocol\ClassFactory::GetMethod('basic', 'consume');
    }

    function handleCancelOk (wire\Method $meth, Channel $chan) {}

    function handleConsumeOk (wire\Method $meth, Channel $chan) { $this->consuming = true; }

    function handleDelivery (wire\Method $meth, Channel $chan) {}

    function handleRecoveryOk (wire\Method $meth, Channel $chan) {}

    function handleShutdownSignal (Channel $chan) { $this->consuming = false; }

    function getConsumeMethod () { return $this->consMeth; }

    /**
     * Helper: return a basic.reject method which rejects the input
     * @param  wire\Method     $meth      A method created from basic.deliver
     * @param  boolean         $requeue   Flag on the returned method, see docs for basic.reject field "requeue"
     * @return wire\Method                A method which rejects the input
     */
    protected function reject (wire\Method $meth, $requeue=true) {
        $resp = new wire\Method(protocol\ClassFactory::GetMethod('basic', 'reject'), $meth->getWireChannel());
        $resp->setField('delivery-tag', $meth->getField('delivery-tag'));
        $resp->setField('requeue', $requeue);
        return $resp;
    }

    /**
     * Helper: return a basic.ack method which acks the input
     * @param  wire\Method     $meth      A method created from basic.deliver
     * @param  boolean         $multiple  A flag for the returned method, see docs for basic.ack field "multiple"
     * @return wire\Method                A method which acks the input
     */
    protected function ack (wire\Method $meth, $multiple=false) {
        $resp = new wire\Method(protocol\ClassFactory::GetMethod('basic', 'ack'), $meth->getWireChannel());
        $resp->setField('delivery-tag', $meth->getField('delivery-tag'));
        $resp->setField('multiple', $multiple);
        return $resp;
    }

    /**
     * Helper: return a basic.cancel method which stops consuming for the input's consumer-tag
     * @param  wire\Method     $meth      A method created from basic.deliver
     * @param  boolean         $noWait    A flag for the returned method, see docs for basic.cancel field "no-wait"
     * @return wire\Method                A method which cancels consuming for the consumer that received the input message
     */
    protected function cancel (wire\Method $meth, $noWait=false) {
        $resp = new wire\Method(protocol\ClassFactory::GetMethod('basic', 'cancel'), $meth->getWireChannel());
        $resp->setField('consumer-tag', $meth->getField('consumer-tag'));
        $resp->setField('no-wait', $noWait);
        return $resp;
    }
}