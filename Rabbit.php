<?php

/**
 * Wraps low-level socket operations, current impl uses blocking socket read / write
 */
class RabbitSockHandler
{
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
            if (substr($tmp, -1) === AmqpMessage::PROTO_FRME) {
                break;
            }
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
}

/**
 * Low level Amqp protocol parsing operations
 */
abstract class AmqpCommunicator
{

    private $buff;
    protected $p = 0; // Position pointer within $buff


    // Mapping for property tables field types -> data extraction routines.
    protected static $FieldTypeMethodMap = array(
                                                 't' => 'readBoolean',
                                                 'b' => 'readShortShortInt',
                                                 'B' => 'readShortShortUInt',
                                                 'U' => 'readShortInt',
                                                 'u' => 'readShortUInt',
                                                 'I' => 'readLongInt',
                                                 'i' => 'readLongUInt',
                                                 'L' => 'readLongLongInt',
                                                 'l' => 'readLongLongUInt',
                                                 'f' => 'readFloat',
                                                 'd' => 'readDouble',
                                                 'D' => 'readDecimalValue',
                                                 's' => 'readShortString',
                                                 'S' => 'readLongString',
                                                 'A' => 'readFieldArray',
                                                 'T' => 'readTimestamp',
                                                 'F' => 'readFieldTable'
                                                 );


    protected function setBuffer($buff) {
        $this->buff = $buff;
    }

    protected function getBuffer() {
        return $this->buff;
    }

    // type 'F'
    function readFieldTable() {
        //info("Table start");
        $tableLen = $this->readLongUInt();
        $tableEnd = $this->p + $tableLen;
        //info("Table len: %d, table end %d", $tableLen, $tableEnd);
        $table = array();
        while ($this->p < $tableEnd) {
            $fieldName = $this->readShortString();
            $fieldType = chr($this->readShortShortUInt());
            $fieldValue = $this->readFieldType($fieldType);
            //            info(" (name, type, value) = (%s, %s, %s)", $fieldName, $fieldType, $fieldValue);
            $table[$fieldName] = $fieldValue;
        }
        //info("Table Ends at %d", $this->p);
        return $table;
    }



    // type 't'
    function readBoolean() {
        $i = array_pop(unpack('C', substr($this->buff, $this->p, 1)));
        $this->p++;
        return ($i !== 0);
    }
    function writeBoolean($val) {
    }

    // type 'b'
    function readShortShortInt() {
        $i = array_pop(unpack('c', substr($this->buff, $this->p, 1)));
        $this->p++;
        return $i;
    }
    function writeShortShortInt($val) {
    }

    // type 'B'
    function readShortShortUInt() {
        $i = array_pop(unpack('C', substr($this->buff, $this->p, 1)));
        $this->p++;
        return $i;
    }
    function writeShortShortUInt($val) {
    }

    // type 'U'
    function readShortInt() {
        $i = array_pop(unpack('s', substr($this->buff, $this->p, 2)));
        $this->p += 2;
        return $i;
    }
    function writeShortInt($val) {
    }


    // type 'u'
    function readShortUInt() {
        $i = array_pop(unpack('n', substr($this->buff, $this->p, 2)));
        $this->p += 2;
        return $i;
    }
    function writeShortUInt($val) {
    }

    // type 'I'
    function readLongInt() {
        $i = array_pop(unpack('L', substr($this->buff, $this->p, 4)));
        $this->p += 4;
        return $i;
    }
    function writeLongInt($val) {
    }

    // type 'i'
    function readLongUInt() {
        $i = array_pop(unpack('N', substr($this->buff, $this->p, 4)));
        $this->p += 4;
        return $i;
    }
    function writeLongUInt($val) {
    }

    // type 'L'
    function readLongLongInt() {
        error("Unimplemented read method %s", __METHOD__);
    }
    function writeLongLongInt($val) {
    }

    // type 'l'
    function readLongLongUInt() {
        $plb = substr($this->buff, $this->p, 4);
        $this->p += 4;
        $phb = substr($this->buff, $this->p, 4);
        $this->p += 4;
        $lb = (int) array_shift(unpack('N', $plb));
        $hb = (int) array_shift(unpack('N', $phb));
        return (int) $hb + (((int) $lb) << 16);
    }
    function writeLongLongUInt($val) {
    }

    // type 'f'
    function readFloat() {
        error("Unimplemented read method %s", __METHOD__);
    }
    function writeFloat($val) {
    }

    // type 'd'
    function readDouble() {
        error("Unimplemented read method %s", __METHOD__);
    }
    function writeDouble($val) {
    }

    // type 'D'
    function readDecimalValue() {
        error("Unimplemented read method %s", __METHOD__);
    }
    function writeDecimalValue($val) {
    }

    // type 's'
    function readShortString() {
        $l = array_pop(unpack('C', substr($this->buff, $this->p, 1)));
        $this->p++;
        $ret = substr($this->buff, $this->p, $l);
        //info("STRING: (len = %d, val = %s)", $l, $ret);
        $this->p += $l;
        return $ret;
    }
    function writeShortString($val) {
    }

    // type 'S'
    function readLongString() {
        $l = $this->readLongUInt();
        $ret = substr($this->buff, $this->p, $l);
        //info("LONGSTRING: (len = %d, val = %s)", $l, $ret);
        $this->p += $l;
        return $ret;
    }
    function writeLongString($val) {
    }

    // type 'A'
    function readFieldArray() {
        error("Unimplemented read method %s", __METHOD__);
    }
    function writeFieldArray($val) {
    }

    private function readFieldType($type) {
        if (isset(self::$FieldTypeMethodMap[$type])) {
            return $this->{self::$FieldTypeMethodMap[$type]}();
        } else {
            error("Unknown field type %s", $type);
            return null;
        }
    }
    function writeFieldType($type, $val) {
        error("TODO : Implement mapped read.");
    }

    // type 'T'
    function readTimestamp() {
        error("Unimplemented read method %s", __METHOD__);
    }
    function writeX($val) {
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
}



/**
 * Low level message wrapper
 */
class AmqpMessage extends AmqpCommunicator
{

    const PROTO_HEADER = "AMQP\x01\x01\x09\x01";
    const PROTO_FRME = "\xCE"; // Frame end marker


    //    protected $buff;  // Full Amqp frame

    protected $frameType;
    protected $frameChan;
    protected $frameLen;


    function __construct($buff) {
        $this->setBuffer($buff);
        $this->frameType = $this->readShortShortUInt();
        $this->frameChan = $this->readShortUInt();
        $this->frameLen = $this->readLongUInt();;
    }


    function getPointer() { return $this->p; }


    function getFrameType() { return $this->frameType; }
    function getFrameChannel() { return $this->frameChan; }
    function getFrameLen() { return $this->frameLen; }

    function dumpFrameData() {
        $args = array("Frame data:\nType: %d\nChan: %s\nLen:  %s", $this->frameType, $this->frameChan, $this->frameLen);
        call_user_func_array('info', $args);
    }




    // Recurse through $subject appending printf control string and params to by-ref buffers
    protected function recursiveShow($subject, &$str, &$vals, $depth = 0) {
        $preBuff = 10 + $depth;
        foreach ($subject as $k => $v) {
            if (is_array($v)) {
                $str .= "\n%{$preBuff}s:";
                $vals[] = $k;
                $this->recursiveShow($v, $str, $vals, $depth + 1);
            } else if (is_bool($v)) {
                $str .= "\n%{$preBuff}s = %s";
                $vals[] = $k;
                $vals[] = ($v) ? 'true' : 'false';
            } else if (is_int($v)) {
                $str .= "\n%{$preBuff}s = %d";
                $vals[] = $k;
                $vals[] = $v;
            } else if (is_string($v)) {
                $str .= "\n%{$preBuff}s = %s";
                $vals[] = $k;
                $vals[] = $v;
            } else if (is_float($v)) {
                $str .= "\n%{$preBuff}s = %f";
                $vals[] = $k;
                $vals[] = $v;
            } else {
                error("Unhandled type in reciursive show: %s (assume string)", gettype($v));
                $str .= "\n%{$preBuff}s = %s";
                $vals[] = $k;
                $vals[] = $v;
            }
        }
    }
}


abstract class AmqpMethod extends AmqpMessage
{
    private $inMessage;
    protected $methClassId = -1;
    protected $methMethodId = -1;
    protected $methClassName;
    protected $methMethodName;
    protected $methProperties;

    function getClassId() { return $this->classId; }
    function getMethodId() { return $this->methodId; }

    function __construct(AmqpMessage $in = null) {
        $this->inMessage = $in;
        if ($this->inMessage->frameType != 1) {
            throw new Exception(sprintf("Frame is not a method: (type, chan, len) = (%d, $d, %d)",
                                        $this->inMessage->frameType, $this->inMessage->frameChan, $this->inMessage->frameLen), 743);
        }
        $this->p = $this->inMessage->p;
        $this->setBuffer($this->inMessage->getBuffer());
        $this->readMethodArgs();
    }

    abstract function readMethodArgs();

    function dumpMethodData() {
        $msg = "Method frame details:\nClass:  %d (%s)\nMethod: %s (%s)\nProperties:";
        $vals = array($this->methClassId, $this->methClassName, $this->methMethodId, $this->methMethodName);
        $this->recursiveShow($this->methProperties, $msg, $vals);
        array_unshift($vals, $msg);
        call_user_func_array('info', $vals);
    }


    function writeResponse($data) {
    }

    static function GetMethodParts() {
    }
}

class Connection_Start extends AmqpMethod
{
    protected $methClassId = 10;
    protected $methMethodId = 10;
    protected $methClassName = 'connection';
    protected $methMethodName = 'start';

    function readMethodArgs() {
        $vMaj = $this->readShortShortUInt();
        $vMin = $this->readShortShortUInt();
        $this->methProperties = array(
                                      'version-major' => $vMaj,
                                      'version-minor' => $vMin,
                                      'server-properties' => $this->readFieldTable(),
                                      'mechanisms' => $this->readLongString(),
                                      'locales' => $this->readLongString()
                                      );
    }


}


function AmqpMethodFactory(AmqpMessage $msg) {
    $classId = $msg->readShortUInt();
    $methodId = $msg->readShortUInt();

    switch ($classId) {
    case 10:
        switch ($methodId) {
        case 10:
            return new Connection_Start($msg);
        }
        break;
    case 20:
        switch ($methodId) {
        }
        break;
    case 40:
        switch ($methodId) {
        }
        break;
    case 50:
        switch ($methodId) {
        }
        break;
    case 60:
        switch ($methodId) {
        }
        break;
    case 90:
        switch ($methodId) {
        }
        break;
    default:
        throw new Exception("Unknown class in method dispatch", 8954);
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
$s = new RabbitSockHandler('localhost', 5672);


info("Write protocol header");
$s->write(AmqpMessage::PROTO_HEADER);
info("Header written, now read");
$response = $s->read();
$amqp = new AmqpMessage($response);
$meth = AmqpMethodFactory($amqp);
info("Response received:\n%s", $amqp->hexdump($response));
$amqp->dumpFrameData();
$meth->dumpMethodData();
info("Msg pointer: %d, Meth pointer: %d", $amqp->getPointer(), $meth->getPointer());

info("Close socket");
$s->close();
info("Complete.");