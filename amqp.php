<?php

namespace amqp_091;

use amqp_091\protocol;
use amqp_091\wire;

require('amqp.wire.php');
require('amqp.protocol.abstrakt.php');
require('gencode/amqp.0_9_1.php');




const DEBUG = true;


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
        if ($tmp = wire\ExtractFrame($raw)) {
            list($type, $chan, $size, $r) = $tmp;
            $meth = new wire\Method($r);
        } else {
            throw new \Exception("Connection initialisation failed (4)", 9876);
        }

        // Expect start
        if ($meth->getMethodProto()->getSpecIndex() == 10 && $meth->getClassProto()->getSpecIndex() == 10) {
            $resp = $meth->getMethodProto()->getResponses();
            $meth = new wire\Method(new wire\Writer(), $resp[0]);
        } else {
            throw new \Exception("Connection initialisation failed (5)", 9877);
        }
        $meth->setField($this->getClientProperties(), 'client-properties');
        $meth->setField('AMQPLAIN', 'mechanism');
        $meth->setField($this->saslHack(), 'response');
        $meth->setField('en_US', 'locale');
        // Send start-ok
        if (! ($this->write(wire\GetFrameBin(1, 0, $meth)))) {
            throw new \Exception("Connection initialisation failed (6)", 9878);
        }

        if (! ($raw = $this->read())) {
            throw new \Exception("Connection initialisation failed (7)", 9879);
        }
        if ($tmp = wire\ExtractFrame($raw)) {
            list($type, $chan, $size, $r) = $tmp;
            $meth = new wire\Method($r);
        } else {
            throw new \Exception("Connection initialisation failed (8)", 9880);
        }
        $this->chanMax = $meth->getField('channel-max');
        $this->frameMax = $meth->getField('frame-max');
        //printf("Got  channel %d, frameMax %d\n", $this->chanMax, $this->frameMax);


        // Expect tune
        if ($meth->getMethodProto()->getSpecIndex() == 30 && $meth->getClassProto()->getSpecIndex() == 10) {
            $resp = $meth->getMethodProto()->getResponses();
            $meth = new wire\Method(new wire\Writer(), $resp[0]);
        } else {
            throw new \Exception("Connection initialisation failed (9)", 9881);
        }
        $meth->setField($this->chanMax, 'channel-max');
        $meth->setField($this->frameMax, 'frame-max');
        $meth->setField(0, 'heartbeat');
        // Send tune-ok
        if (! ($this->write(wire\GetFrameBin(1, 0, $meth)))) {
            throw new \Exception("Connection initialisation failed (10)", 9882);
        }

        // Now call connection.open
        $meth = new wire\Method(new wire\Writer, protocol\ClassFactory::GetClassByName('connection')->getMethodByName('open'));
        $meth->setField($this->vhost, 'virtual-host');
        $meth->setField('', 'reserved-1');
        $meth->setField('', 'reserved-2');

        if (! ($this->write(wire\GetFrameBin(1, 0, $meth)))) {
            throw new \Exception("Connection initialisation failed (10)", 9882);
        }

        // Expect open-ok
        if (! ($raw = $this->read())) {
            throw new \Exception("Connection initialisation failed (11)", 9883);
        }
        if ($tmp = wire\ExtractFrame($raw)) {
            list($type, $chan, $size, $r) = $tmp;
            $meth = new wire\Method($r);
        } else {
            throw new \Exception("Connection initialisation failed (12)", 9884);
        }
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
        return ($this->chans[$newChan] = new Channel($this, $newChan));
    }



    private function read () {
        $ret = '';
        while ($tmp = socket_read($this->sock, self::READ_LEN)) {
            $ret .= $tmp;
            $this->br += strlen($tmp);
            if (substr($tmp, -1) === protocol\FRAME_END) {
                break;
            }
        }
        return $ret;
    }
    private function write ($buff) {
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

    /** KISS: Send $meth as wait for and return it's response (if any)  */
    function sendMethod(wire\Method $meth, $chan) {
        if (! ($this->write(wire\GetFrameBin(1, $chan, $meth)))) {
            throw new \Exception("Send message failed (1)", 5623);
        }

        if (! ($raw = $this->read())) {
            throw new \Exception("Send message failed (2)", 5624);
        }
        if ($tmp = wire\ExtractFrame($raw)) {
            list($type, $chan, $size, $r) = $tmp;
            $meth = new wire\Method($r);
        } else {
            throw new \Exception("Send message failed (3)", 5625);
        }
        return $meth;
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

    function __construct (Connection $rConn, $chanId) {
        $this->myConn = $rConn;
        $this->chanId = $chanId;

        $meth = new wire\Method(new wire\Writer, protocol\ClassFactory::GetClassByName('channel')->getMethodByName('open'));
        $meth->setField('', 'reserved-1');
        $resp = $this->myConn->sendMethod($meth, $this->chanId);
        if (! $meth->isResponse($resp)) {
            throw new \Exception("Channel setup failed (1)", 9856);
        }
        $meth = new wire\Method(new wire\Writer, protocol\ClassFactory::GetClassByName('access')->getMethodByName('request'));
        $meth->setField('/data', 'realm');
        $meth->setField(false, 'exclusive');
        $meth->setField(true, 'passive');
        $meth->setField(true, 'active');
        $meth->setField(true, 'write');
        $meth->setField(true, 'read');

        $resp = $this->myConn->sendMethod($meth, $this->chanId);
        if (! $meth->isResponse($resp)) {
            throw new \Exception("Channel setup failed (2)", 9857);
        } else if (! ($this->ticket = $resp->getField('ticket'))) {
            throw new \Exception("Channel setup failed (3)", 9858);
        }
        echo "Channel is set up\n";
    }

    function exchange () {
    }

    function basic () {
    }

    function queue () {
    }

    function tx () {
    }
}