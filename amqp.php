<?php


/**
 * This library is intended to be as "procedural" as possible - all
 * methods return just like regular functions, including those which
 * carry content.
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
    const READ_LEN = 1024;

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

    private function initConnection() {
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
        $meth->setField('response', $this->saslHack());
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
            throw new \Exception("Connection initialisation failed (10)", 9882);
        }

        // Expect open-ok
        if (! ($raw = $this->read())) {
            throw new \Exception("Connection initialisation failed (11)", 9883);
        }
        $meth = new wire\Method($raw);
        if (! ($meth->getMethodProto()->getSpecIndex() == 41 && $meth->getClassProto()->getSpecIndex() == 10)) {
            throw new \Exception("Connection initialisation failed (13)", 9885);
        }
    }

    private function getClientProperties() {
        /* Build table to use long strings - RMQ seems to require this. */
        $t = new wire\Table;
        foreach (self::$ClientProperties as $pn => $pv) {
            $t[$pn] = new wire\TableField($pv, 'S');
        }
        return $t;
    }

    private function saslHack() {
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
    private function write ($buff) {
        if (DEBUG) {
            echo "\n<write>\n";
            echo wire\hexdump($buff);
        }
        $bw = 0;
        $contentLength = strlen($buff);
        while ($bw < $contentLength) {
            if (($tmp = socket_write($this->sock, $buff, $contentLength)) === false) {
                throw new \Exception(sprintf("\nSocket write failed: %s\n",
                                            socket_strerror(socket_last_error())), 7854);
            }
            $bw += $tmp;
            $this->bw += $tmp;
        }
        return $bw;
    }
    function getBytesWritten () {
        return $this->bw;
    }
    function getBytesRead () {
        return $this->br;
    }

    /** Use select to poll for incoming data 
    function canRead () {
        $orig = $read = array($this->sock);
        $selN = @socket_select($read, $written = array(), $except = array(), 0);
        if ($selN === false) {
            $errNo = socket_last_error();
            if ($errNo ===  SOCKET_EINTR) {
                pcntl_signal_dispatch(); // select returned false due to signal, handle it ASAP
            } else {
                $this->error("client read select failed:\n%s", socket_strerror());
            }
        } else {
            var_dump($read);
            return $selN > 0;
        }
        } */

    /**
     * Once implemented, this will be the method of delivering 'unsolicited' content
     * in the procedural model.  'Unsolicited' means "wire content which is read during 
     * a method invokation but is not directly related to the invoked
     * methods' response"
     */
    function unexpected (wire\Method $meth) {
        // TODO: channel flow -> deliver to channel!
        if ($meth->getWireType() == 1) {
            $todo = '';
            if ($meth->getWireClassId() == 10 && $meth->getWireMethodId() == 50) {
                $todo = 'connection-exception';
            } else if ($meth->getWireClassId() == 20 && $meth->getWireMethodId() == 40) {
                $todo = 'channel-exception';
            }

            if ($todo) {
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
                $em = "[$todo] reply-code={$errCode['name']} triggered by $culprit: $eb";
                if ($this->write($closeOk->toBin())) {
                    $em .= " Channel closed OK";
                    $n = 7565;
                } else {
                    $em .= " Additionally, channel closure failed";
                    $n = 7566;
                }
                if ($todo == 'connection-exception') {
                    // TODO:  CLOSE SOCKET PROPERLY!!!!
                } else if ($todo == 'channel-exception') {
                    $chan = $this->chans[$meth->getWireChannel()];
                    unset($this->chans[$meth->getWireChannel()]);
                    $chan->destroy();
                    $chan = null; // TODO: Test to make sure chan is suitably dead
                }
                throw new \Exception($em, $n);
            }
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
                    $newMeth->readBodyContent(new wire\Reader($raw));
                }
                if (! in_array($newMeth->getWireType(), array(1,2))
                    || $newMeth->getWireChannel() != $meth->getWireChannel()) {
                    $this->unexpected($newMeth);
                } else if ($meth->isResponse($newMeth)) {
                    return $newMeth;
                } else {
                    $this->unexpected($newMeth);
                }
            }
        } else {
            return null;
        }
    }
}


class Channel
{
    const FLOW_OPEN = 1;
    const FLOW_SHUT = 2;

    private $myConn;
    private $chanId;
    private $flow = self::FLOW_OPEN;
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
        foreach (array_merge(array_combine($meth->getSpecFields(), array_fill(0, count($meth->getSpecFields()), '')), $args) as $k => $v) {
            $m->setField($k, $v);
        }
        $m->setContent($content);
        return $m;
    }

    function invoke (wire\Method $m) {
        if ($this->destroyed) {
            throw new \Exception("Attempting to use a destroyed channel", 8767);
        }
        return $this->myConn->sendMethod($m);
    }

    function getTicket () { return $this->ticket; }

    // Used to prevent use after a channel exc.
    function destroy () {
        $this->destroyed = true;
        $this->myConn = $this->chanId = $this->ticket = null;
    }
}