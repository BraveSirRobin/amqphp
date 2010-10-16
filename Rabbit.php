<?php

/**
 * Wraps low-level socket operations, current impl uses blocking socket read / write
 */


const PROTO_HEADER = "AMQP\x01\x01\x09\x01";
const PROTO_FRME = "\xCE"; // Frame end marker
const HEXDUMP_BIN = '/usr/bin/hexdump -C';



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
            if (substr($tmp, -1) === PROTO_FRME) {
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

// Provides a simplified
class AmqpTable implements ArrayAccess, Iterator
{
    const ITER_MODE_SIMPLE = 1;
    const ITER_MODE_TYPED = 2;

    private $data = array();  // Holds field values
    private $keys = array();  // Holds field keys
    private $types = array(); // Holds field types
    private $iterMode = self::ITER_MODE_SIMPLE;
    private $iterP = 0;

    /**
     * Native ArrayAccess implementation
     */
    function offsetExists($k) {
        return in_array($this->keys[$k]);
    }

    function offsetGet($k) {
        if (false === ($n = array_search($k, $this->keys))) {
            error("Offset not found [0]: %s", $k);
            return null;
        }
        return $this->data[$n];
    }

    function offsetSet($k, $v) {
        $t = 'TODO: Guess type';
        $this->offsetSetWithType($k, $v, $t);
    }

    function offsetUnset($k) {
        if (false === ($n = array_search($k, $this->keys))) {
            error("Offset not found [1]: %s", $k);
        } else {
            unset($this->data[$n]);
            unset($this->keys[$n]);
            unset($this->types[$n]);
            $this->data = array_merge($this->data);
            $this->keys = array_merge($this->keys);
            $this->types = array_merge($this->types);
        }
    }

    function getArrayCopy() {
        return array_combine($this->keys, $this->data);
    }

    /**
     * Specialised access for Amqp specifics
     */
    function offsetSetWithType($k, $v, $t) {
        // Required for Amqp types which share underlying byte patterns, e.g. timestamp
        if (false === ($n = array_search($k, $this->keys))) {
            $this->data[] = $v;
            $this->keys[] = $k;
            $this->types[] = $t;
        } else {
            $this->data[$n] = $v;
            $this->keys[$n] = $k;
            $this->types[$n] = $t;
        }
    }

    function offsetGetType($k) {
        if (false === ($n = array_search($k, $this->keys))) {
            error("Offset not found [2]: %s", $k);
        } else {
            return $this->types[$k];
        }
    }

    /**
     * Native Iterator Implementation
     */

    function rewind() {
        $this->iterP = 0;
    }

    function current() {
        return $this->data[$this->iterP];
    }

    function key() {
        return $this->keys[$this->iterP];
    }

    function next() {
        $this->iterP++;
    }

    function valid() {
        return isset($this->data[$this->iterP]);
    }
}



/**
 * Low level Amqp protocol parsing operations
 */
abstract class AmqpCommunicator
{

    private $rBuff;
    protected $rp = 0; // Position pointer within $rBuff

    private $wBuff = '';
    protected $wp = 0;


    // Mapping for property tables field types -> data extraction routines.
    protected static $FieldTypeMethodMap = array(
                                                 't' => 'Boolean',
                                                 'b' => 'ShortShortInt',
                                                 'B' => 'ShortShortUInt',
                                                 'U' => 'ShortInt',
                                                 'u' => 'ShortUInt',
                                                 'I' => 'LongInt',
                                                 'i' => 'LongUInt',
                                                 'L' => 'LongLongInt',
                                                 'l' => 'LongLongUInt',
                                                 'f' => 'Float',
                                                 'd' => 'Double',
                                                 'D' => 'DecimalValue',
                                                 's' => 'ShortString',
                                                 'S' => 'LongString',
                                                 'A' => 'FieldArray',
                                                 'T' => 'Timestamp',
                                                 'F' => 'FieldTable'
                                                 );


    protected function setReadBuffer($buff) {
        $this->rBuff = $buff;
    }

    protected function setWriteBuffer($buff) {
    }

    protected function getReadBuffer() {
        return $this->rBuff;
    }

    function getReadPointer() { return $this->rp; }


    // type 'F'
    function readFieldTable() {
        //info("Table start");
        $tableLen = $this->readLongUInt();
        $tableEnd = $this->rp + $tableLen;
        //info("Table len: %d, table end %d", $tableLen, $tableEnd);
        $table = new AmqpTable;
        while ($this->rp < $tableEnd) {
            $k = $this->readShortString();
            $t = chr($this->readShortShortUInt());
            $v = $this->readFieldType($t);
            //            info(" (name, type, value) = (%s, %s, %s)", $k, $t, $v);
            $table->offsetSetWithType($k, $v, $t);
        }
        //info("Table Ends at %d", $this->rp);
        return $table;
    }
    function writeFieldTable($val) {
        $tmpBuff = '';
    }




    // type 't'
    function readBoolean() {
        $i = array_pop(unpack('C', substr($this->rBuff, $this->rp, 1)));
        $this->rp++;
        return ($i !== 0);
    }
    function writeBoolean($val) {
        $this->wBuff .= ($val) ?
            pack('C', 1) :
            pack('C', 0);
    }

    // type 'b'
    function readShortShortInt() {
        $i = array_pop(unpack('c', substr($this->rBuff, $this->rp, 1)));
        $this->rp++;
        return $i;
    }
    function writeShortShortInt($val) {
        $this->wBuff .= pack('c', (int) $val);
    }

    // type 'B'
    function readShortShortUInt() {
        $i = array_pop(unpack('C', substr($this->rBuff, $this->rp, 1)));
        $this->rp++;
        return $i;
    }
    function writeShortShortUInt($val) {
        $this->wBuff .= pack('C', (int) $val);
    }

    // type 'U'
    function readShortInt() {
        $i = array_pop(unpack('s', substr($this->rBuff, $this->rp, 2)));
        $this->rp += 2;
        return $i;
    }
    function writeShortInt($val) {
        $this->wBuff .= pack('s', (int) $val);
    }


    // type 'u'
    function readShortUInt() {
        $i = array_pop(unpack('n', substr($this->rBuff, $this->rp, 2)));
        $this->rp += 2;
        return $i;
    }
    function writeShortUInt($val) {
        $this->wBuff .= pack('n', (int) $val);
    }

    // type 'I'
    function readLongInt() {
        $i = array_pop(unpack('L', substr($this->rBuff, $this->rp, 4)));
        $this->rp += 4;
        return $i;
    }
    function writeLongInt($val) {
        $this->wBuff .= pack('L', (int) $val);
    }

    // type 'i'
    function readLongUInt() {
        $i = array_pop(unpack('N', substr($this->rBuff, $this->rp, 4)));
        $this->rp += 4;
        return $i;
    }
    function writeLongUInt($val) {
        $this->wBuff .= pack('N', (int) $val);
    }

    // type 'L'
    function readLongLongInt() {
        error("Unimplemented read method %s", __METHOD__);
    }
    function writeLongLongInt($val) {
        error("Unimplemented *write* method %s", __METHOD__);
    }

    // type 'l'
    function readLongLongUInt() {
        error("Unimplemented read method %s", __METHOD__);
    }
    function writeLongLongUInt($val) {
        error("Unimplemented *write* method %s", __METHOD__);
    }

    // type 'f'
    function readFloat() {
        error("Unimplemented read method %s", __METHOD__);
    }
    function writeFloat($val) {
        error("Unimplemented *write* method %s", __METHOD__);
    }

    // type 'd'
    function readDouble() {
        error("Unimplemented read method %s", __METHOD__);
    }
    function writeDouble($val) {
        error("Unimplemented *write* method %s", __METHOD__);
    }

    // type 'D'
    function readDecimalValue() {
        error("Unimplemented read method %s", __METHOD__);
    }
    function writeDecimalValue($val) {
        error("Unimplemented *write* method %s", __METHOD__);
    }

    // type 's'
    function readShortString() {
        $l = $this->readShortShortUInt();
        $ret = substr($this->rBuff, $this->rp, $l);
        $this->rp += $l;
        return $ret;
    }
    function writeShortString($val) {
        $this->writeShortShortUInt(strlen($val));
        $this->wp .= $val;
    }

    // type 'S'
    function readLongString() {
        $l = $this->readLongUInt();
        $ret = substr($this->rBuff, $this->rp, $l);
        //info("LONGSTRING: (len = %d, val = %s)", $l, $ret);
        $this->rp += $l;
        return $ret;
    }
    function writeLongString($val) {
        $this->writeLongUInt(strlen($val));
        $this->wp .= $val;
    }

    // type 'A'
    function readFieldArray() {
        error("Unimplemented read method %s", __METHOD__);
    }
    function writeFieldArray($val) {
        error("Unimplemented *write* method %s", __METHOD__);
    }

    // Used to read table fields based on type
    private function readFieldType($type) {
        if (isset(self::$FieldTypeMethodMap[$type])) {
            return $this->{'read' .self::$FieldTypeMethodMap[$type]}();
        } else {
            error("Unknown field type %s", $type);
            return null;
        }
    }

    // Used to convert input var in to a table field, writes the
    // type indicator and value
    function writeFieldType($val) {
        $type = 's';
        if (is_int($val)) {
        } else if (is_float($val)) {
        } else if (is_string($val)) {
        }
        if (is_array($val)) {
        }
        if (isset(self::$FieldTypeMethodMap[$type])) {
            $this->{'write' . self::$FieldTypeMethodMap[$type]}($val);
        } else {
            error("Unknown field type %s", $type);
        }

    }

    // type 'T'
    function readTimestamp() {
        error("Unimplemented read method %s", __METHOD__);
    }
    function writeTimestamp($val) {
        error("Unimplemented *write* method %s", __METHOD__);
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
}





/**
 * Low level message wrapper
 */
class AmqpMessage extends AmqpCommunicator
{

    //    protected $buff;  // Full Amqp frame

    protected $frameType;
    protected $frameChan;
    protected $frameLen;


    function __construct($buff) {
        $this->setReadBuffer($buff);
        $this->frameType = $this->readShortShortUInt();
        $this->frameChan = $this->readShortUInt();
        $this->frameLen = $this->readLongUInt();;
    }


    function getFrameType() { return $this->frameType; }
    function getFrameChannel() { return $this->frameChan; }
    function getFrameLen() { return $this->frameLen; }

    function dumpFrameData() {
        $args = array("Frame data:\nType: %d\nChan: %s\nLen:  %s", $this->frameType, $this->frameChan, $this->frameLen);
        call_user_func_array('info', $args);
    }
}




abstract class AmqpMethod extends AmqpMessage
{
    private $inMessage;
    protected $classId = -1;
    protected $methodId = -1;
    protected $className;
    protected $methodName;
    protected $properties;

    function getClassId() { return $this->classId; }
    function getMethodId() { return $this->methodId; }

    function __construct(AmqpMessage $in = null) {
        if ($in) {
            $this->inMessage = $in;
            if ($this->inMessage->frameType != 1) {
                throw new Exception(sprintf("Frame is not a method: (type, chan, len) = (%d, $d, %d)",
                                            $this->inMessage->frameType,
                                            $this->inMessage->frameChan,
                                            $this->inMessage->frameLen), 743);
            }
            $this->rp = $this->inMessage->rp;
            $this->setReadBuffer($this->inMessage->getReadBuffer());
            $this->readMethodArgs();
        }
    }

    abstract function readMethodArgs();

    function dumpMethodData() {
        $msg = "Method frame details:\nClass:  %d (%s)\nMethod: %s (%s)\nProperties:";
        $vals = array($this->classId, $this->className, $this->methodId, $this->methodName);
        recursiveShow($this->properties, $msg, $vals);
        array_unshift($vals, $msg);
        call_user_func_array('info', $vals);
    }


}

class Connection_Start extends AmqpMethod
{
    protected $classId = 10;
    protected $methodId = 10;
    protected $className = 'connection';
    protected $methodName = 'start';

    function readMethodArgs() {
        $this->properties = array(
                                  'version-major' => $this->readShortShortUInt(),
                                  'version-minor' => $this->readShortShortUInt(),
                                  'server-properties' => $this->readFieldTable(),
                                  'mechanisms' => $this->readLongString(),
                                  'locales' => $this->readLongString()
                                  );
    }

    function getResponse() {
        return new Connection_Ok;
    }

    function getResponseArgDefaults() {
        // TODO: Clever?
    }
}


class Connection_StartOk extends AmqpMethod
{
    protected $classId = 10;
    protected $methodId = 11;
    protected $className = 'connection';
    protected $methodName = 'start-ok';

    function readMethodArgs() {
        $this->properties = array(
                                  'client-properties' => $this->readFieldTable(),
                                  'mechanism' => $this->readShortString(),
                                  'response' => $this->readLongString(),
                                  'locale' => $this->readShortString()
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
        case 11:
            return new Connection_StartOk($msg);
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





//
// Debug / Dev functions
//



/**
 * Hexdump provided by system hexdump command via. proc_open
 */
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
    if (($proc = proc_open(HEXDUMP_BIN, $pDesc, $pipes, null, null, $pOpts)) === false) {
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


/**
 * Debug helper: recurse through $subject appending printf control string and params to by-ref buffers
 */
function recursiveShow($subject, &$str, &$vals, $depth = 0) {
    $preBuff = 10 + $depth;
    foreach ($subject as $k => $v) {
        if (is_object($v) && ($v instanceof AmqpTable)) {
            $str .= "\n%{$preBuff}s:";
            $vals[] = $k;
            recursiveShow($v->getArrayCopy(), $str, $vals, $depth + 1);
        } else if (is_array($v)) {
            $str .= "\n%{$preBuff}s:";
            $vals[] = $k;
            recursiveShow($v, $str, $vals, $depth + 1);
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
$s->write(PROTO_HEADER);
info("Header written, now read");
$response = $s->read();
$amqp = new AmqpMessage($response);
$meth = AmqpMethodFactory($amqp);
info("Response received:\n%s", hexdump($response));
$amqp->dumpFrameData();
$meth->dumpMethodData();
info("Msg pointer: %d, Meth pointer: %d", $amqp->getReadPointer(), $meth->getReadPointer());

info("Close socket");
$s->close();
info("Complete.");