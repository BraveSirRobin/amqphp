<?php

const PROTO_HEADER = "AMQP\x01\x01\x09\x01";
const PROTO_FRME = "\xCE"; // Frame end marker
const HEXDUMP_BIN = '/usr/bin/hexdump -C';


/**
 * Class to create connections to a single RabbitMQ endpoint.  If connections
 * to multiple servers are required, use multiple factories
 */
class RabbitConnectionFactory
{
    private $host = 'localhost'; // cannot vary after instantiation
    private $port = 5672; // cannot vary after instantiation
    private $username = 'guest';
    private $userpass = 'guest';
    private $vhost = '/';

    function __construct(array $params = array()) {
        foreach ($params as $pname => $pval) {
            switch ($pname) {
            case 'host':
            case 'port':
            case 'username':
            case 'userpass':
            case 'vhost':
                $this->{$pname} = $pval;
            default:
                throw new Exception("Invalid connection factory parameter", 8654);
            }
        }
    }

    function getConnection() {
        if (! ($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP))) {
            throw new Exception("Failed to create inet socket", 7895);
        } else if (! socket_connect($sock, $this->host, $this->port)) {
            throw new Exception("Failed to connect inet socket", 7564);
        }
        return new RabbitConnection($sock);
    }

    function writeToConnection() {
    }
}

class RabbitConnection
{
    private $sock; // TCP socket
    private $bw = 0;
    private $br = 0;

    private $chans = array(); // Format: array(<chan-id> => RabbitChannel)
    private $nextChan = 1;


    private $username;
    private $userpass;
    private $vhost;

    function __construct($sock, $username, $userpass, $vhost) {
        $this->sock = $sock;
        $this->username = $username;
        $this->userpass = $userpass;
        $this->vhost = $vhost;
    }

    private function initConnection() {
        // Perform initial Amqp connection negotiation
        $this->write(PROTO_HEADER);
        $resp = $this->read();
        // TODO: Handler errors!
        $msg = new AmqpMessageBuffer($resp);
        // Here.
    }

    function getChannel($num = false) {
        return ($num === false) ? $this->initNewChannel() : $this->chans[$num];
    }

    private function initNewChannel() {
        $newChan = $this->nextChan++;
        $this->chans[$newChan] = new RabbitChannel($this);
    }


    private function read() {
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

    private function write($buff) {
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


    function getBytesRead() {
        return $this->br;
    }


}

class RabbitChannel
{
    const FLOW_OPEN = 1;
    const FLOW_SHUT = 2;

    private $myConn;
    private $chanId;
    private $flow = self::FLOW_OPEN;

    function __construct(RabbitConnection $rConn, $chanId) {
        $this->myConn = $rConn;
        $this->chanId = $chanId;
    }

    function exchange() {
    }

    function basic() {
    }

    function queue() {
    }

    function tx() {
    }
}

// Could potentially be switched to write to a stream?
class AmqpMessageBuffer
{
    private $buff = '';
    private $p = 0;

    function __construct($buff = '') {
        $this->buff = $buff;
        $this->len = strlen($this->buff);
    }

    function read($n) {
        $ret = substr($this->buff, $this->p, $n);
        $this->p += $n;
        return $ret;
    }

    function write($buff) {
        $this->buff = substr($this->buff, 0, $this->p) . $buff . substr($this->buff, $this->p);
        $this->p += strlen($buff);
    }

    function getOffset() { return $this->p; }

    function setOffset($p) {
        $this->p = (int) $p;
    }

    function setOffsetEnd() {
        $this->p = strlen($this->buff);
    }

    function getBuffer() { return $this->buff; }
}

// Boxed type for Amqp values, subclasses provide a type token
// map to read and write value to messages
abstract class AmqpValue
{
    protected $val; // PHP native
    protected $type;  // Amqp type

    final function __construct($val, $type) {
        $this->name = $name;
        $this->type = $type;
    }
    final function getValue() { return $this->val; }
    final function setValue($val) { $this->val = $val; }
    final function getType() { return $this->type; }
    final function __toString() { return (string) $this->val; }


    final function readValue(AmqpMessageBuffer $c) {
        if (isset(self::$MethodMap[$this->type])) {
            $this->val = call_user_func(array('AmqpProtocol', 'read' . self::$MethodMap[$this->type]), $c);
        } else {
            throw new Exception(sprintf("Unknown parameter field type %s", $this->type), 986);
        }
    }

    final function writeValue(AmqpMessageBuffer $c) {
        if (isset(self::$MethodMap[$this->type])) {
            return call_user_func(array('AmqpProtocol', 'write' . self::$MethodMap[$this->type]),
                                  $c, $this->val);
        } else {
            throw new Exception(sprintf("Unknown parameter field type %s", $type), 7547);
        }

    }
}

class AmqpParameter extends AmqpValue
{
    protected static $MethodMap = array(
                                        'bit' => 'Boolean',
                                        'octet' => 'ShortShortUInt',
                                        'short' => 'ShortUInt',
                                        'long' => 'LongUInt',
                                        'longlong' => 'LongLongUInt',
                                        'shortstr' => 'ShortString',
                                        'longstr' => 'LongString',
                                        'timestamp' => 'Timestamp',
                                        'table' => 'Table'
                                        );
}


class AmqpTableField extends AmqpValue
{
    protected static $MethodMap = array(
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
                                        'F' => 'Table'
                                        );
}





class AmqpTable implements ArrayAccess, Iterator
{
    const ITER_MODE_SIMPLE = 1;
    const ITER_MODE_TYPED = 2;

    private $data = array();  // Holds field values
    private $iterMode = self::ITER_MODE_SIMPLE;
    private $iterP = 0;
    private $iterK;


    static function IsValidKey($k) {
        return is_string($k) && $k && (strlen($k) < 129) && preg_match("/[a-zA-Z\$\#][a-zA-Z-_\$\#]*/", $k);
    }


    // @arg  array    $data     List of Amqp values
    function __construct(array $data = array()) {
        foreach ($data as $name => $av) {
            $this->offsetSet($name, $av);
        }
    }

    /**
     * Native ArrayAccess implementation
     */
    function offsetExists($k) {
        return isset($this->keys[$k]);
    }

    function offsetGet($k) {
        if (! isset($this->keys[$k])) {
            trigger_error(sprintf("Offset not found [0]: %s", $k), E_USER_WARNING);
            return null;
        }
        return $this->data[$n];
    }

    function offsetSet($k, $v) {
        if (! ($v instanceof AmqpTableField)) {
            throw new Exception("Table data must already be boxed", 7355);
        } else if ( ! self::IsValidKey($k)) {
            throw new Exception("Invalid table key", 7255);
        }
        $this->data[$k] = $v;
    }

    function offsetUnset($k) {
        if (! isset($this->data[$k])) {
            trigger_error(sprintf("Offset not found [1]: %s", $k), E_USER_WARNING);
        } else {
            unset($this->data[$n]);
        }
    }

    function getArrayCopy() {
        return $this->data;
    }

    /**
     * Native Iterator Implementation
     */

    function rewind() {
        $this->iterP = 0;
        $this->iterK = array_keys($this->data);
    }

    function current() {
        return $this->data[$this->iterK[$this->iterP]];
    }

    function key() {
        return $this->iterK[$this->iterP];
    }

    function next() {
        $this->iterP++;
    }

    function valid() {
        return isset($this->iterK[$this->iterP]);
    }
}





/**
 * Stateless low level read / write routines - all content is written and
 * read using the AmqpMessageBuffer object.
 */
class AmqpProtocol
{
    // type 'F'
    static function readTable(AmqpMessageBuffer $msg) {
        $tableLen = self::readLongUInt($msg);
        $tableEnd = $msg->getOffset() + $tableLen;
        $table = new AmqpTable;
        while ($msg->getOffset() < $tableEnd) {
            $k = self::readShortString($msg);
            $t = chr(self::readShortShortUInt($msg));
            $v = new AmqpTableField;
            $v->readValue($msg, $t);
            $table[$k] = $v;
        }
        return $table;
    }

    static function writeTable(AmqpMessageBuffer $msg, AmqpTable $val) {
        $orig = $msg->getOffset();
        foreach ($val as $fName => $fVal) {
            self::writeShortString($msg, $fName);
            self::writeShortShortUInt($msg, ord($fVal->getType()));
            $fVal->writeValue($msg);
        }
        $new = $msg->getOffset();
        self::writeLongUInt($msg, ($new - $orig));
        $msg->setOffsetEnd();
    }




    // type 't'
    static function readBoolean(AmqpMessageBuffer $msg) {
        $i = array_pop(unpack('C', $msg->read(1)));
        return ($i !== 0);
    }
    static function writeBoolean(AmqpMessageBuffer $msg, $val) {
        if ($val) {
            $msg->write(pack('C', 1));
        } else {
            $msg->write(pack('C', 0));
        }
    }

    // type 'b'
    static function readShortShortInt(AmqpMessageBuffer $msg) {
        $i = array_pop(unpack('c', $msg->read(1)));
        return $i;
    }
    static function writeShortShortInt(AmqpMessageBuffer $msg, $val) {
        $msg->write(pack('c', (int) $val));
    }

    // type 'B'
    static function readShortShortUInt(AmqpMessageBuffer $msg) {
        $i = array_pop(unpack('C', substr($this->rBuff, $this->rp, 1)));
        $this->rp++;
        return $i;
    }
    static function writeShortShortUInt(AmqpMessageBuffer $msg, $val) {
        $this->wBuff .= pack('C', (int) $val);
    }

    // type 'U'
    static function readShortInt(AmqpMessageBuffer $msg) {
        $i = array_pop(unpack('s', $msg->read(2)));
        return $i;
    }
    static function writeShortInt(AmqpMessageBuffer $msg, $val) {
        $msg->write(pack('s', (int) $val));
    }


    // type 'u'
    static function readShortUInt(AmqpMessageBuffer $msg) {
        $i = array_pop(unpack('n', $msg->read(2)));
        return $i;
    }
    static function writeShortUInt(AmqpMessageBuffer $msg, $val) {
        $msg->write(pack('n', (int) $val));
    }

    // type 'I'
    static function readLongInt(AmqpMessageBuffer $msg) {
        $i = array_pop(unpack('L', $msg->read(4)));
        return $i;
    }
    static function writeLongInt(AmqpMessageBuffer $msg, $val) {
        $msg->write(pack('L', (int) $val));
    }

    // type 'i'
    static function readLongUInt(AmqpMessageBuffer $msg) {
        $i = array_pop(unpack('N', $msg->read(4)));
        return $i;
    }
    static function writeLongUInt(AmqpMessageBuffer $msg, $val) {
        $msg->write(pack('N', (int) $val));
    }

    // type 'L'
    static function readLongLongInt(AmqpMessageBuffer $msg) {
        error("Unimplemented read method %s", __METHOD__);
    }
    static function writeLongLongInt(AmqpMessageBuffer $msg, $val) {
        error("Unimplemented *write* method %s", __METHOD__);
    }

    // type 'l'
    static function readLongLongUInt(AmqpMessageBuffer $msg) {
        error("Unimplemented read method %s", __METHOD__);
    }
    static function writeLongLongUInt(AmqpMessageBuffer $msg, $val) {
        error("Unimplemented *write* method %s", __METHOD__);
    }

    // type 'f'
    static function readFloat(AmqpMessageBuffer $msg) {
        error("Unimplemented read method %s", __METHOD__);
    }
    static function writeFloat(AmqpMessageBuffer $msg, $val) {
        error("Unimplemented *write* method %s", __METHOD__);
    }

    // type 'd'
    static function readDouble(AmqpMessageBuffer $msg) {
        error("Unimplemented read method %s", __METHOD__);
    }
    static function writeDouble(AmqpMessageBuffer $msg, $val) {
        error("Unimplemented *write* method %s", __METHOD__);
    }

    // type 'D'
    static function readDecimalValue(AmqpMessageBuffer $msg) {
        error("Unimplemented read method %s", __METHOD__);
    }
    static function writeDecimalValue(AmqpMessageBuffer $msg, $val) {
        error("Unimplemented *write* method %s", __METHOD__);
    }

    // type 's'
    static function readShortString(AmqpMessageBuffer $msg) {
        $l = self::readShortShortUInt($msg);
        return $msg->read($l);
    }
    static function writeShortString(AmqpMessageBuffer $msg, $val) {
        self::writeShortShortUInt($msg, strlen($val));
        $msg->write($val);
    }

    // type 'S'
    static function readLongString(AmqpMessageBuffer $msg) {
        $l = self::readLongUInt($msg);
        return $msg->read($l);
    }
    static function writeLongString(AmqpMessageBuffer $msg, $val) {
        self::writeLongUInt($msg, strlen($val));
        $msg->write($val);
    }

    // type 'A'
    static function readFieldArray(AmqpMessageBuffer $msg) {
        error("Unimplemented read method %s", __METHOD__);
    }
    static function writeFieldArray(AmqpMessageBuffer $msg, $val) {
        error("Unimplemented *write* method %s", __METHOD__);
    }

    // type 'T'
    static function readTimestamp(AmqpMessageBuffer $msg) {
        error("Unimplemented read method %s", __METHOD__);
    }
    static function writeTimestamp(AmqpMessageBuffer $msg, $val) {
        error("Unimplemented *write* method %s", __METHOD__);
    }


    private static function packInt64($n) {
        static $lbMask = null;
        if (is_null($lbMask)) {
            $lbMask = (pow(2, 32) - 1);
        }
        $hb = $n >> 16;
        $lb = $n & $lbMask;
        return pack('N', $hb) . pack('N', $lb);
    }

    private static function unpackInt64($pInt) {
        $plb = substr($pInt, 0, 2);
        $phb = substr($pInt, 2, 2);
        $lb = (int) array_shift(unpack('N', $plb));
        $hb = (int) array_shift(unpack('N', $phb));
        return (int) $hb + (((int) $lb) << 16);
    }
}

abstract class AmqpProtocolMethod
{
    // Read message properties and return as array
    final function read(AmqpMessage $msg) {
    }

    final function setProperty($name, $value) {
    }

    final function write(AmqpMessage $msg) {
    }
}


class Connection_Start extends AmqpProtocolMethod
{
    protected $classId = 10;
    protected $methodId = 10;
    protected $className = 'connection';
    protected $methodName = 'start';

    // Generated
    protected $fieldMap = array(
                                'version-major' => 'octet',
                                'version-minor' => 'octet',
                                'server-properties' => 'table', // TODO: This is wrong!
                                'mechanisms' => 'longstr',
                                'locales' => 'longstr'
                                );


    function getResponseMethod() {
        // Return as string?
    }
}





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
