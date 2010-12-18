<?php

/**
 * This library is intended to be as "procedural" as possible - all
 * methods return just like regular functions, including those which
 * carry content.
 */

/**
 * TODO:
 *  (1) Implement exceptions for Amqp 'events', i.e. channel / connection exceptions, etc.
 *  (2) WRT REF_NOTE_1 : the loop exit tracking at REF_NOTE_1 duplicates the wire\Reader::isSpent()
 *      functionality.  Consider switching the code so that the wire\Method is either passed in to the
 *      constructor OR made available via. an accessor so that the duplication can be removed
 *  (3) Fix the situation where Connection->sendMethod() gets confused by messages received due
 *      to Connection->readBlock().  Deliver all unexpeected channel messages to the channel objects
 *  (4) Support concurrent per-channel consumers, enforce the per-channel-ness
 *  (4.1) Refactor Connection->readBlock to be static, support round-robin concurrent content publication,
 *        reads will still be synchronous.
 *  (4.2) Create a more defined API contract between Channel and Consumer - don't pass Method objects back
 *        from Consumer, define signals that the Consumer returns to Channel that trigger sending methods.
 *  (4.3) Have some (4.2) return signals to do the following: "ack message", "reject message",  "cancel and 
 *        reject all messages", "cancel and ack all messages"
 *  (4.1) Prevent simultaneous use of synchronous and asynchronous API - make this an "API simplification
 *        feature"
 *  (5) Test sending/receiving large messages in multiple frames
 */

namespace amqp_091;

use amqp_091\protocol;
use amqp_091\wire;

require('amqp.wire.php');
require('amqp.protocol.abstrakt.php');
require('gencode/amqp.0_9_1.php');




const DEBUG = false;

/** Flag is passed by a Consumer to a Connection (via. a channel) to indicate that it wants to stop consuming */
const CONSUME_HALT = 1;


/**
 * Class to create connections to a single RabbitMQ endpoint.  If connections
 * to multiple servers are required, use multiple factories
 */
class ConnectionFactory
{
    private $host = 'localhost'; // cannot vary after instantiation
    private $port = 5672; // cannot vary after instantiation
    private $username = 'guest';
    private $userpass = 'guest';
    private $vhost = '/';
    private $sReadTimeoutUSecs = 500;
    private $sReadTimeoutSecs = 2;


    function __construct (array $params = array()) {
        foreach ($params as $pname => $pval) {
            switch ($pname) {
            case 'host':
            case 'port':
            case 'username':
            case 'userpass':
            case 'vhost':
            case 'sReadTimeoutSecs':
            case 'sReadTimeoutUSecs':
                $this->{$pname} = $pval;
                break;
            default:
                throw new \Exception("Invalid connection factory parameter", 8654);
            }
        }
    }

    function newConnection () {
        if (! ($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP))) {
            throw new \Exception("Failed to create inet socket", 7895);
        } else if (! socket_connect($sock, $this->host, $this->port)) {
            throw new \Exception("Failed to connect inet socket {$sock}, {$this->host}, {$this->port}", 7564);
        }
        if (false === socket_set_option($sock, SOL_SOCKET, SO_RCVTIMEO,
                                        array('sec' => $this->sReadTimeoutSecs, 'usec'=> $this->sReadTimeoutUSecs))) {
            throw new Exception("Failed to set read timeout on socket", 9756);
        }
        return new Connection($sock, $this->username, $this->userpass, $this->vhost);
    }
}

class Connection
{
    const READ_LEN = 4096;

    private static $ClientProperties = array('product' => 'My Amqp implementation',
                                             'version' => '0.01',
                                             'platform' => 'Linux baby!',
                                             'copyright' => 'Copyright (c) 2010 Robin Harvey (harvey.robin@gmail.com)',
                                             'information' => 'Property of Robin Harvey');

    private $sock; // TCP socket
    private $bw = 0;
    private $br = 0;

    private $chans = array(); // Format: array(<chan-id> => Channel)
    private $nextChan = 1;
    private $chanMax; // Negotated during setup.
    private $frameMax; // Negotated during setup.

    /** Broker connection params, specified in constructor */
    private $username;
    private $userpass;
    private $vhost;

    /** Flag set when connection is in read blocking mode, waiting for messages */
    private $blocking = false;

    function __construct ($sock, $username, $userpass, $vhost) {
        $this->sock = $sock;
        $this->username = $username;
        $this->userpass = $userpass;
        $this->vhost = $vhost;
        $this->initConnection();
    }


    /** Shutdown child channels and then the connection  */
    function shutdown () {
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
        $this->close();
    }



    private function initConnection () {
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

        $this->chanMax = $meth->getField('channel-max');
        $this->frameMax = $meth->getField('frame-max');
        //printf("Got  channel %d, frameMax %d\n", $this->chanMax, $this->frameMax);


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

    function removeChannel (Channel $chan) {
        if (false !== ($k = array_search($chan, $this->chans))) {
            unset($this->chans[$k]);
        } else {
            trigger_error("Channel not found", E_USER_WARNING);
        }
    }

    private function initNewChannel () {
        $newChan = $this->nextChan++;
        if ($this->chanMax > 0 && $newChan > $this->chanMax) {
            throw new \Exception("Channels are exhausted!", 23756);
        }
        return ($this->chans[$newChan] = new Channel($this, $newChan, $this->vhost, $this->frameMax));
    }


    function getVHost() { return $this->vhost; }


    /**  Low level protocol read function */
    private function read () {
        $ret = '';
        while ($tmp = socket_read($this->sock, self::READ_LEN)) {
            $ret .= $tmp;
            $this->br += strlen($tmp);
            if (substr($tmp, -1) === protocol\FRAME_END) {
                break;
            }
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
        $bw = 0;
        foreach ((array) $buffs as $buff) {
            if (DEBUG) {
                echo "\n<write>\n";
                echo wire\hexdump($buff);
            }
            $contentLength = strlen($buff);
            while ($bw < $contentLength) {
                if (($tmp = socket_write($this->sock, $buff, $contentLength)) === false) {
                    throw new \Exception(sprintf("\nSocket write failed: %s\n",
                                                 socket_strerror(socket_last_error())), 7854);
                }
                $bw += $tmp;
                $this->bw += $tmp;
            }
        }
        return $bw;
    }
    /**  Low level socket close function */
    private function close () {
        socket_close($this->sock);
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



    /**
     * Write the given method to the wire and loop waiting for the response.
     * This method is not suitable for use with consumers, because it can't
     * handle additional frames after the response method.
     */
    function sendMethod (wire\Method $meth) {
        // TODO: Examine $blocking flag, might need to warn / error
        // Check for incoming data, if found, process in case it's an exception
        if (! ($this->write($meth->toBin()))) {
            throw new \Exception("Send message failed (1)", 5623);
        }
        if ($rTypes = $meth->getMethodProto()->getSpecResponseMethods()) {
            // Allow other traffic through - content, heartbeats, etc.
            while (true) { // TODO: Limit counter?
                if (! ($raw = $this->read())) {
                    throw new \Exception(sprintf("(2) Send message failed for %s.%s:\n",
                                                 $meth->getClassProto()->getSpecName(),
                                                 $meth->getMethodProto()->getSpecName()), 5624);
                }
                $newMeth = new wire\Method($raw);
                while (! $newMeth->readConstructComplete()) {
                    $newMeth->debug_TODO_deleteme();
                    if (! ($raw = $this->read())) {
                        throw new \Exception("Send message failed (3)", 5625);
                    }
                    $newMeth->readContruct($raw);
                }

                if ($meth->isResponse($newMeth)) {
                    return $newMeth;
                } else if ($newMeth->getWireChannel() == 0) {
                    // The channel number is 0 for all frames which are global to the connection
                    $this->handleConnectionMessage($newMeth);
                } else if ($newMeth->getWireClassId() == 20 &&
                           ($chan = $this->chans[$newMeth->getWireChannel()])) {
                    $chan->handleChannelMessage($newMeth);
                } else {
                    // Unexpected. ???
                    printf("Unexpected method object:\n%s\n", methodToXml($newMeth));
                    throw new \Exception(sprintf("Received unexpected response in sendMethod for %s.%s:\n%s",
                                                 $meth->getClassProto()->getSpecName(),
                                                 $meth->getMethodProto()->getSpecName(),
                                                 wire\hexdump($raw)), 8795);
                }
            }
        } else {
            return null;
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

    /* Set a read block on $sock in order to receive message via. basic.consume 
       WARNING: By default, this method will block indefinitely! */
    function readBlock (Consumer $cons) {
        if ($this->blocking) {
            throw new \Exception("Multiple simultaneous read blocking not supported (TODO?)", 6362);
        }
        $this->blocking = true;
        $meth = null;
        while (true) {
            $read = $ex = array($this->sock);
            $write = null;
            $select = is_null($this->blockTmSecs) ?
                @socket_select($read, $write, $exc, null)
                : @socket_select($read, $write, $ex, $this->blockTmSecs, $this->blockTmMillis);
            if ($select === false) {
                $errNo = socket_last_error();
                if ($errNo = SOCKET_EINTR) {
                    // If shutdown signal handlers have been set up, allow these the chance to shutdown
                    // gracefully before throwing the exception
                    pcntl_signal_dispatch();
                }
                $errStr = socket_strerror($errNo);
                throw new \Exception ("Read block select produced an error: [$errNo] $errStr", 9963);
            } else if ($select > 0) {
                // Check for content or exceptions
                if ($read) {
                    // Read content, construct a message and deliver it to appropriate callback
                    $buff = $tmp = '';
                    $br = 0;
                    while ($brNow = @socket_recv($this->sock, $tmp, self::READ_LEN, MSG_DONTWAIT)) {
                        $buff .= $tmp;
                        $br += $brNow;
                    }
                    //echo "\n\nRead content from wire:\n" . wire\hexdump($buff);
                    if (! $meth) {
                        $meth = new wire\Method($buff);
                    } else {
                        $meth->readContruct($buff);
                    }
                    try {
                        $readLen = strlen($buff);
                        $br = 0;
                        $break = false;
                        /** REF_NOTE_1  */
                        while (true) {
                            if ($meth->readConstructComplete()) {
                                if ($meth->getWireChannel() == 0) {
                                    $this->handleConnectionMessage($meth);
                                } else if ($meth->getWireChannel() && isset($this->chans[$meth->getWireChannel()])) {
                                    // We expect the Consumer to be called here, if appropriate
                                    $response = $this->chans[$meth->getWireChannel()]->handleChannelMessage($meth);
                                    if ($response instanceof wire\Method) {
                                        $this->sendMethod($response);
                                    } else if ($response == CONSUME_HALT) {
                                        printf("\n\nConsume end here!!!!\n\n");
                                        break 2;
                                    }
                                } else {
                                    throw new \Exception("Failed to deliver incoming message", 9045);
                                }
                            }
                            $br += $meth->getBytesRead();
                            if ($break) {
                                break;
                            }
                            if ($br < $readLen) {
                                // There's more frames to be had so eat them
                                $meth = new wire\Method(substr($buff, $br));
                            } else {
                                //$break = true;
                                break;
                            }
                        }
                        $meth = null;
                    } catch (\Exception $e) {
                        $this->blocking = false;
                        throw $e;
                    }
                }
            }
        }
        $this->blocking = false;
    }
}


class Channel
{
    /** The parent Connection object */
    private $myConn;

    /** The channel ID we're linked to */
    private $chanId;

    /** As set by the channel.flow Amqp method, controls whether content can
        be sent or not */
    private $flow = true;

    /** Required for RMQ */
    private $ticket;

    /** Flag set when the underlying Amqp channel has been closed due to an exception */
    private $destroyed = false;

    /** Set by negotiation during channel setup */
    private $frameMax;

    /** A Consumer instance to deliver incoming messages to */
    private $consumer;

    function __construct (Connection $rConn, $chanId, $frameMax) {
        $this->myConn = $rConn;
        $this->chanId = $chanId;

        $meth = new wire\Method(protocol\ClassFactory::GetMethod('channel', 'open'), $this->chanId);
        $meth->setField('reserved-1', '');
        $resp = $this->myConn->sendMethod($meth);

        $meth = new wire\Method(protocol\ClassFactory::GetMethod('access', 'request'), $this->chanId);
        $meth->setField('realm', $this->myConn->getVHost());
        $meth->setField('exclusive', false);
        $meth->setField('passive', true);
        $meth->setField('active', true);
        $meth->setField('write', true);
        $meth->setField('read', true);

        $resp = $this->myConn->sendMethod($meth);
        if (! ($this->ticket = $resp->getField('ticket'))) {
            throw new \Exception("Channel setup failed (3)", 9858);
        }
    }

    /**
     * Implement Amqp protocol methods with method name as Amqp class name.
     * TODO: Implement defaults profiles
     * @arg  string   $class       Amqp class
     * @arg  array    $_args       Format: array (<Amqp method name>, <Assoc method/class mixed field array>, <method content>)
     */
    function __call ($class, $_args) {
        if ($this->destroyed) {
            throw new \Exception("Attempting to use a destroyed channel", 8766);
        }
        $method = (isset($_args[0])) ? $_args[0] : null;
        $args = (isset($_args[1])) ? $_args[1] : null;
        $content = (isset($_args[2])) ? $_args[2] : null;

        if (! ($cls = protocol\ClassFactory::GetClassByName($class))) {
            throw new \Exception("Invalid Amqp class or php method", 8691);
        } else if (! ($meth = $cls->getMethodByName($method))) {
            throw new \Exception("Invalid Amqp method", 5435);
        }

        $m = new wire\Method($meth, $this->chanId);
        if ($meth->getSpecHasContent() && $cls->getSpecFields()) {
            foreach (array_merge(array_combine($cls->getSpecFields(), array_fill(0, count($cls->getSpecFields()), null)), $args) as $k => $v) {
                $m->setClassField($k, $v);
            }
        }
        if ($meth->getSpecFields()) {
            foreach (array_merge(array_combine($meth->getSpecFields(), array_fill(0, count($meth->getSpecFields()), '')), $args) as $k => $v) {
                $m->setField($k, $v);
            }
        }
        $m->setContent($content);
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

    function getTicket () { return $this->ticket; }


    /**
     * Callback from the Connection object for channel frames
     * @param   $meth           A channel method for this channel
     * @return  mixed           If a method is returned it will be sent by the channel
     */
    function handleChannelMessage (wire\Method $meth) {
        switch ($meth->getMethodProto()->getSpecName()) {
        case 'flow':
            // TODO: Make sure that when shut off, the current message send is cancelled
            $this->flow = ! $this->flow;
            if ($r = $meth->getMethodProto()->getResponses()) {
                return $r[0];
            }
            break;
        case 'close':
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
        case 'deliver':
            if ($this->consumer) {
                return $this->consumer->handleDelivery($meth);
            } else {
                throw new \Exception("Unexpected message received", 9875);
            }
            break;
        case 'cancel-ok':
            if ($this->consumer) {
                return $this->consumer->handleCancelOk($meth);
            } else {
                throw new \Exception("Unexpected message received", 9875);
            }
            break;
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

    /** Wrapper for basic.consume, uses Connection::readBlock() to receive messages */
    function consume (Consumer $cons, array $consumeParams) {
        if ($this->consumer) {
            throw new \Exception("Multiple concurrent consumes are not supported", 8056);
        }
        $this->consumer = $cons;
        $cOk = $this->invoke($this->basic('consume', $consumeParams));
        $cons->handleConsumeOk($cOk, $this);
        /** Calling readBlock means the code goes in to an indefinite read loop */
        $this->myConn->readBlock($this->consumer);

        $this->invoke($this->basic('cancel', array('consumer-tag' => $cOk->getField('consumer-tag'))));
        $this->consumer = null;
    }
}

// Interface for a simple blocking consumer - modelled on the RMQ Java here:
// http://www.rabbitmq.com/releases/rabbitmq-java-client/v2.2.0/rabbitmq-java-client-javadoc-2.2.0/com/rabbitmq/client/Consumer.html
interface Consumer
{
    function handleCancelOk ();

    function handleConsumeOk (wire\Method $meth, Channel $chan);

    function handleDelivery (wire\Method $meth);

    function handleRecoveryOk ();

    function handleShutdownSignal ();
}