<?php

/**
 * This library is intended to be as "procedural" as possible - all
 * methods return just like regular functions, including those which
 * carry content.
 */

/**
 * TODO:
 *  (1) Implement exceptions for Amqp 'events', i.e. channel / connection exceptions, etc.
 */

namespace amqp_091;

use amqp_091\protocol;
use amqp_091\wire;

require('amqp.wire.php');
require('amqp.protocol.abstrakt.php');
require('gencode/amqp.0_9_1.php');




const DEBUG = false;


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

    const CLOSE_WAIT = 100; // Millis delay for graceful shutdown

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
    private $chanMax; // Set during setup.
    private $frameMax; // Set during setup.


    private $username;
    private $userpass;
    private $vhost;

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
            $methd->getMethodProto() &&
            $meth->getMethodProto()->getSpecName() == 'close-ok') {
            trigger_error("Channel protocol shudown fault", E_USER_WARNING);
        }
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


    // DELETEME!!!
    function cheatRead() {
        return $this->read();
    }


    /** In the general sense, this method is broken, because there could be many
        frame reads in a single 'session'.  For example, receiving a message with
        basic.consume which has been broken in to many frames. */
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

    private function close () {
        socket_shutdown($this->sock, 1); // Client connection can still read
        usleep(self::CLOSE_WAIT);
        @socket_shutdown($this->sock, 0); // Full disconnect
        $errNo = socket_last_error();
        if ($errNo) {
            trigger_error(sprintf("[client] socket error during socket close: %s", socket_strerror($errNo)), E_USER_WARNING);
        }
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
            $em = "[connection close] reply-code={$errCode['name']} triggered by $culprit: $eb";
            if ($this->write($closeOk->toBin())) {
                $em .= " Channel closed OK";
                $n = 7565;
            } else {
                $em .= " Additionally, channel closure failed";
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
     */
    function sendMethod (wire\Method $meth) {
        // Check for incoming data, if found, process in case it's an exception
        if (! ($this->write($meth->toBin()))) {
            throw new \Exception("Send message failed (1)", 5623);
        }
        if ($rTypes = $meth->getMethodProto()->getSpecResponseMethods()) {
            // Allow other traffic through - content, heartbeats, etc.
            while (true) { // TODO: Limit counter?
                if (! ($raw = $this->read())) {
                    throw new \Exception("Send message failed (2)", 5624);
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
                    $hd = wire\hexdump($meth->toBin());
                    throw new \Exception("Unexpected method:\n$hd", 8795);
                }
            }
        } else {
            return null;
        }
    }
}


class Channel
{

    private $myConn;
    private $chanId;
    private $flow = true;
    private $ticket;
    private $destroyed = false;
    private $frameMax;

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
            if ($this->write($closeOk->toBin())) {
                $em .= " Channel closed OK";
                $n = 3687;
            } else {
                $em .= " Additionally, channel closure failed";
                $n = 2435;
            }
            throw new \Exception($em, $n);
        default:
            $hd = wire\hexdump($meth->toBin());
            throw new \Exception("Unexpected method:\n$hd", 8795);
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
}