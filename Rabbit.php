<?php

class Rabbit
{
    const PROTO_HEADER = "AMQP\x01\x01\x09\x01";
    const PROTO_FRME = "\xCE"; // Frame end marker
    const READ_LEN = 1024;
    const WRITE_LEN = 1024;

    private $sock;
    private $host;
    private $port;

    private $bw = 0;
    private $br = 0;

    function __construct($host, $port) {
        $this->host = $host;
        $this->port = $port;

        if (! ($this->sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP))) {
            throw new Exception("Failed to create inet socket", 7895);
        } else if (! socket_connect($this->sock, $host, $port)) {
            throw new Exception("Failed to connect inet socket", 7564);
        }
    }

    function read() {
        $ret = '';
        while ($tmp = socket_read($this->sock, self::READ_LEN)) {
            $ret .= $tmp;
            $this->br += strlen($tmp);
            if (substr($tmp, -1) === self::PROTO_FRME) {
                //info("(read) Ending detected - bail");
                break;
            }
            //info("(read) Read %d bytes", strlen($tmp));
            //info("(Hex Ending):\n%s", $this->hexdump(substr($tmp, -1)));
        }
        return $ret;
    }

    function getBytesRead() {
        return $this->br;
    }

    function write($buff) {
        $bw = 0;
        $contentLength = strlen($buff);
        while ($bw < $contentLength) {
            if (($tmp = socket_write($this->sock, $buff, $contentLength)) === false) {
                throw new Exception(sprintf("\nSocket write failed: %s\n",
                                            socket_strerror(socket_last_error())), 7854);
            }
            $bw += $tmp;
            $this->bw += $tmp;
        }
    }

    function getBytesWritten() {
        return $this->bw;
    }

    function close() {
        socket_shutdown($this->sock);
        socket_close($this->sock);
    }

    /**
     * Hexdump provided by system hexdump command via. proc_open
     */
    private $hexdumpBin = '/usr/bin/hexdump -C';
    function hexdump($subject) {
        if ($subject === '') {
            error("Can't hexdump nothing");
            return;
        }
        $pDesc = array(
                       array('pipe', 'r'),
                       array('pipe', 'w'),
                       array('pipe', 'r')
                       );
        $pOpts = array('binary_pipes' => true);
        if (($proc = proc_open($this->hexdumpBin, $pDesc, $pipes, null, null, $pOpts)) === false) {
            throw new Exception("Failed to open hexdump proc!", 675);
        }
        fwrite($pipes[0], $subject);
        fclose($pipes[0]);
        $ret = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $errs = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        if ($errs) {
            printf("[ERROR] Stderr content from hexdump pipe: %s\n", $errs);
        }
        proc_close($proc);
        return $ret;
    }


    function packInt64($n) {
        static $lbMask = null;
        if (is_null($lbMask)) {
            $lbMask = (pow(2, 32) - 1);
        }
        $hb = $n >> 16;
        $lb = $n & $lbMask;
        return pack('N', $hb) . pack('N', $lb);
    }

    function unpackInt64($pInt) {
        $plb = substr($pInt, 0, 2);
        $phb = substr($pInt, 2, 2);
        $lb = (int) array_shift(unpack('N', $plb));
        $hb = (int) array_shift(unpack('N', $phb));
        return (int) $hb + (((int) $lb) << 16);
    }

    function extractFrame($buff) {
        $rawType = substr($buff, 0, 1);
        $rawChan = substr($buff, 1, 2);
        $rawLen = substr($buff, 3, 4);

        $type = array_pop(unpack('c', $rawType));
        $chan = array_pop(unpack('n', $rawChan));
        $len = array_pop(unpack('N', $rawLen));
        $pl = substr($buff, 7, $len);
        return array($type, $chan, $len, $pl);
    }


    function extractMethod($buff) {
        list($type, $chan, $len, $pl) = $this->extractFrame($buff);
        if ($type != 1) {
            error("Frame is not a method: (type, chan, len) = (%d, $d, %d)", $type, $chan, $len);
            return false;
        }
        /*
        $classId = array_pop(unpack('n', substr($pl, 0, 2)));
        $methodId = array_pop(unpack('n', substr($pl, 2, 2)));
        */
        $bits = unpack('n2', substr($pl, 0, 4));
        $classId = $bits[1];
        $methodId = $bits[2];
        $pl = substr($pl, 4);
        return array($type, $chan, $len, $classId, $methodId, $pl);
    }
}

function info() {
    $args = func_get_args();
    $msg = array_shift($args);
    vprintf("[INFO] $msg\n", $args);
}
function error() {
    $args = func_get_args();
    $msg = array_shift($args);
    vprintf("[ERROR] $msg\n", $args);
}


info("Begin.");
$r = new Rabbit('localhost', 5672);

info("Write protocol header");
$r->write(Rabbit::PROTO_HEADER);
info("Header written, now read");
$response =$r->read();
info("Response recieved:\n%s", $r->hexdump($response));
$meth = $r->extractMethod($response);
info("FRAME:\nType: %d\nChan: %d\nLen:  %d\nCls:  %d\nMeth: %d\n%s", $meth[0], $meth[1], $meth[2], $meth[3], $meth[4], $r->hexdump($meth[5]));

info("Close socket");
$r->close();
info("Complete.");