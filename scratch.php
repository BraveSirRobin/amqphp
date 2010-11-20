<?php

$tests = array(0,1,2,3,4,5);


//echo "It is..." . array_reduce($tests, function ($a, $b) { printf("Look for %d, %d\n", $a, $b); return 1; });

//echo $bn = getNByteInt(25, 4);
//echo pack('i', -2);
//printf("Read back binary int: %d\n", readNByteInt($bn, 8));
//echo getIntSize();

//echo pack('N', 257);



// Multiplexing connections
class MplxConnection
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

    private $recvQ = array();
    private $sendQ = array();


    // Does Amqp connection negotiation
    private function initConnection() {
        $this->write(wire\PROTOCOL_HEADER);
        if ($tmp = $this->readFrame()) {
            list($type, $chan, $size, $r) = $tmp;
            $meth = new wire\Method($tmp);
        } else {
            throw new \Exception("Connection initialisation failed (1)", 9875);
        }
    }



    // Suck a frame from the wire and extract the top level fram elements
    private function readFrame() {
        if ($buff = $this->read()) {
            $r = new wire\Reader($buff);
            if (! ($type = $r->read('octet'))) {
                trigger_error('Failed to read type from frame', E_USER_WARNING);
            } else if (! ($chan = $r->read('short'))) {
                trigger_error('Failed to read channel from frame', E_USER_WARNING);
            } else if (! ($size = $r->read('long'))) {
                trigger_error('Failed to read size from frame', E_USER_WARNING);
            } else {
                return array($type, $chan, $size, $r);
            }
        } else {
            trigger_error('No Frame was read', E_USER_WARNING);
        }
        return null;
    }

    private function read() {
        // Low level - read a single frame from the wire
    }

    private function write() {
        // Low level - write raw content to the wire.
    }


    function send(wire\Method $meth) {
        $this->sendQ[] = $meth;
        $this->write($meth->toBin());
    }

    function recv(protocol\XmlSpecMethod $methProto) {
        $ths->recvQ[] = $methProto;
    }
}






function getSignedNByteInt($i, $nBytes) {
    //
}


// Convert $i to a binary ($nBytes)-byte integer
function getNByteInt($i, $nBytes) {
    return array_reduce(bytesplit($i, $nBytes), function ($buff, $item) {
            return $buff . chr($item);
        });
}

// Read an ($nBytes)-byte integer from $bin
function readNByteInt($bin, $nBytes) {
    $byte = substr($bin, 0, 1);
    $ret = ord($byte);
    for ($i = 1; $i < $nBytes; $i++) {
        $ret = ($ret << 8) + ord(substr($bin, $i, 1));
    }
    return $ret;
}

function getIntSize() {
    // Choose between 32 and 64 only
    return (gettype(pow(2, 31)) == 'integer') ? 64 : 32;
}

function bytesplit($x, $bytes) {
    // Needed as stand-alone func?
    $ret = array();
    for ($i = 0; $i < $bytes; $i++) {
        $ret[] = $x & 255;
        $x = ($x >> 8);
    }
    return array_reverse($ret);
}
