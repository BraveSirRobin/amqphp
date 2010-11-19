<?php

namespace amqp_091;
require 'amqp.protocol.abstrakt.php';
require 'amqp.wire.php';
require 'gencode/amqp.0_9_1.php';
use amqp_091\wire;





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
        return new RabbitConnection($sock, $this->username, $this->userpass, $this->vhost);
    }
}

class Connection
{
    const READ_LEN = 1024;
    private $sock; // TCP socket
    private $bw = 0;
    private $br = 0;

    private $chans = array(); // Format: array(<chan-id> => RabbitChannel)
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

    /** TODO: Un-hard code the setup and use the request/response features of the
        protocol layer to manage the setup. */
    private function initConnection () {
        if (DEBUG) {
            echo "\nCreate new RabbitConnection object.\n-----------------------------------\n";
        }
        // Perform initial Amqp connection negotiation
        $this->write(\amqp_091\PROTO_HEADER);

        // Read connection.start from wire
        $resp = $this->read();
        $msg = \amqp_091\AmqpMessage::FromMessage($resp);
        $msg->parseMessage();
        if (! $msg || 
            $msg->getType() != \amqp_091\AmqpMessage::TYPE_METHOD ||
            $msg->getClassId() != 10 ||
            $msg->getMethodId() != 10) {
            throw new \Exception("Unexpected server response during connection setup", 964);
        }

        if (DEBUG) {
            debugShowMethod($msg, '[recv] ');
        }

        // Write connection.start-ok to wire.
        $msg = \amqp_091\AmqpMessage::NewMessage(\amqp_091\AmqpMessage::TYPE_METHOD, 0);
        $msg->setClassId(10);
        $msg->setMethodId(11);
        $t = new \amqp_091\wire\AmqpTable;
        $t['product'] = new \amqp_091\wire\AmqpTableField('My Amqp/Rmq implementation', 'S');
        $t['version'] = new \amqp_091\wire\AmqpTableField('0.01', 'S');
        $t['platform'] = new \amqp_091\wire\AmqpTableField('Linux baby', 'S');
        $t['copyright'] = new \amqp_091\wire\AmqpTableField('Copyright (c) 2010 Robin Harvey (harvey.robin@gmail.com)', 'S');
        $t['information'] = new \amqp_091\wire\AmqpTableField('Property of Robin Harvey', 'S');
        $msg['client-properties'] = $t;
        $msg['mechanism'] = 'AMQPLAIN';
        $msg['response'] = $this->saslHack();
        $msg['locale'] = 'en_US';
        if (DEBUG) {
            debugShowMethod($msg, '[send] ');
        }
        $this->write($msg->flush());

        // Read connection.tune
        $resp = $this->read();
        $msg = \amqp_091\AmqpMessage::FromMessage($resp);
        $msg->parseMessage();
        if (DEBUG) {
            debugShowMethod($msg, '[recv] ');
        }
        $this->chanMax = $msg['channel-max'];
        $this->frameMax = $msg['frame-max'];

        // write connection.tune-ok
        $msg = \amqp_091\AmqpMessage::NewMessage(\amqp_091\AmqpMessage::TYPE_METHOD, 0);
        $msg->setClassName('connection');
        $msg->setMethodName('tune-ok');
        $msg['channel-max'] = $this->chanMax;
        $msg['frame-max'] = $this->frameMax;
        $msg['heartbeat'] = 0;
        if (DEBUG) {
            debugShowMethod($msg, '[send] ');
        }
        $this->write($msg->flush());

        // Write connection.open
        $msg = \amqp_091\AmqpMessage::NewMessage(\amqp_091\AmqpMessage::TYPE_METHOD, 0);
        $msg->setClassName('connection');
        $msg->setMethodName('open');
        $msg['virtual-host'] = $this->vhost;
        $msg['reserved-1'] = '';
        $msg['reserved-2'] = '';
        if (DEBUG) {
            debugShowMethod($msg, '[send] ');
        }
        $b = $msg->flush();
        echo \amqp_091\hexdump($b);
        $this->write($msg->flush());

        // Read ...
        $resp = $this->read();
        $msg = \amqp_091\AmqpMessage::FromMessage($resp);
        $msg->parseMessage();
        if (DEBUG) {
            debugShowMethod($msg, '[recv] ');
        }

        if (DEBUG) {
            echo "\nRabbitConnection setup complete\n";
        }
    }

    private function saslHack() {
        $t = new \amqp_091\wire\AmqpTable();
        $t['LOGIN'] = new \amqp_091\wire\AmqpTableField($this->username, 'S');
        $t['PASSWORD'] = new \amqp_091\wire\AmqpTableField($this->userpass, 'S');
        $buff = new \amqp_091\wire\AmqpMessageBuffer('');
        \amqp_091\wire\writeTable($buff, $t);
        return substr($buff->getBuffer(), 4);
    }


    function getChannel ($num = false) {
        return ($num === false) ? $this->initNewChannel() : $this->chans[$num];
    }

    private function initNewChannel () {
        $newChan = $this->nextChan++;
        if ($this->chanMax > 0 && $newChan > $this->chanMax) {
            throw new \Exception("Channels are exhausted!", 23756);
        }
        return ($this->chans[$newChan] = new RabbitChannel($this, $newChan));
    }


    private function read () {
        $ret = '';
        while ($tmp = socket_read($this->sock, self::READ_LEN)) {
            $ret .= $tmp;
            $this->br += strlen($tmp);
            if (substr($tmp, -1) === \amqp_091\PROTO_FRME) {
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
    }
    function getBytesWritten () {
        return $this->bw;
    }
    function getBytesRead () {
        return $this->br;
    }

    /** KISS: Send $meth as wait for and return it's response (if any)  */
    function sendMessage(wire\Method $meth) {
        $mBuff = $meth->toBin();
    }

}


function debugShowMethod ($msg, $sr) {
    $s = sprintf("$sr, Method: (%s, %s, %d, %d):", $msg->getClassName(), $msg->getMethodName(),
                 $msg->getClassId(), $msg->getMethodId());
    printf("\n%s\n%s\n", $s, str_repeat('-', strlen($s)));
    foreach ($msg->getMethodData() as $k => $v) {
        if ($v instanceof \amqp_091\wire\AmqpTable) {
            echo "$k (table)\n";
            foreach ($v as $sk => $sv) {
                echo "  $sk = $sv\n";
            }
        } else {
            echo "$k = $v\n";
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

    function __construct (RabbitConnection $rConn, $chanId) {
        $this->myConn = $rConn;
        $this->chanId = $chanId;

        if (DEBUG) {
            echo "\nRabbitChannel setup\n-------------------\n";
        }
        // write channel.open
        $msg = \amqp_091\AmqpMessage::NewMessage(\amqp_091\AmqpMessage::TYPE_METHOD, 0);
        $msg->setClassName('channel');
        $msg->setMethodName('open');
        $msg->setChannel($this->chanId);
        $msg['reserved-1'] = '';
        if (DEBUG) {
            debugShowMethod($msg, '[send] ');
        }
        $this->myConn->write($msg->flush());

        // read channel.open-ok
        $resp = $this->myConn->read();
        $msg = \amqp_091\AmqpMessage::FromMessage($resp);
        $msg->parseMessage();
        if (DEBUG) {
            debugShowMethod($msg, '[recv] ');
        }


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
