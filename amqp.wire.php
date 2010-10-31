<?php
namespace amqp_091\wire;
use amqp_091\protocol as protocol;

const HELLO = "AMQP\x00\x00\x09\x01"; // Hello text to spit down a freshly opened socket
const FRME = "\xCE"; // Frame end marker


// Could potentially be switched to write to a stream?
class AmqpMessageBuffer
{
    private $buff = '';
    private $p = 0;

    function __construct($buff = '') {
        $this->buff = $buff;
        $this->len = strlen($this->buff);
    }
    /** Read from current position and return up to $n bytes, advances the internal pointer by N
        bytes wjere N is strlen(<return value>) */
    function read($n) {
        $ret = substr($this->buff, $this->p, $n);
        $this->p += $n;
        return $ret;
    }
    /** Insert the given text in to buffer at current position, advances the internal pointer by
        N bytes where N = strlen(<input data>) */
    function write($buff) {
        $this->buff = substr($this->buff, 0, $this->p) . $buff . substr($this->buff, $this->p);
        $l = strlen($buff);
        $this->p += $l;
        return $l;
    }

    function getOffset() { return $this->p; }

    function setOffset($p) {
        $this->p = (int) $p;
    }

    function setOffsetEnd() {
        $this->p = strlen($this->buff);
    }

    function getBuffer() { return $this->buff; }

    function getLength() { return strlen($this->buff); }
}



class AmqpTableField
{
    private static $MethodMap = array(
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
    protected $val; // PHP native
    protected $type;  // Amqp type

    function __construct($val, $type) {
        $this->val = $val;
        $this->type = $type;
    }
    function getValue() { return $this->val; }
    function setValue($val) { $this->val = $val; }
    function getType() { return $this->type; }
    function __toString() { return (string) $this->val; }

    final function readValue(AmqpMessageBuffer $c) {
        if (isset(self::$MethodMap[$this->type])) {
            $this->val = call_user_func(__NAMESPACE__ . '\\read' . self::$MethodMap[$this->type], $c);
        } else {
            throw new \Exception(sprintf("Unknown parameter field type %s", $this->type), 986);
        }
    }

    final function writeValue(AmqpMessageBuffer $c) {
        if (isset(self::$MethodMap[$this->type])) {
            return call_user_func(__NAMESPACE__ . '\\write' . self::$MethodMap[$this->type], $c, $this->val);
        } else {
            throw new \Exception(sprintf("Unknown parameter field type %s", $type), 7547);
        }

    }
}


class AmqpTable implements \ArrayAccess, \Iterator
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
            throw new \Exception("Table data must already be boxed", 7355);
        } else if ( ! self::IsValidKey($k)) {
            throw new \Exception("Invalid table key", 7255);
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
// type 'F'
function readTable(AmqpMessageBuffer $msg) {
    $tableLen = readLongUInt($msg);
    $tableEnd = $msg->getOffset() + $tableLen;
    $table = new AmqpTable;
    while ($msg->getOffset() < $tableEnd) {
        $k = readShortString($msg);
        $t = chr(readShortShortUInt($msg));
        $v = new AmqpTableField(null, $t);
        $v->readValue($msg);
        $table[$k] = $v;
    }
    return $table;
}

function writeTable(AmqpMessageBuffer $msg, AmqpTable $val) {
    $orig = $msg->getOffset();
    foreach ($val as $fName => $fVal) {
        writeShortString($msg, $fName);
        writeShortShortUInt($msg, ord($fVal->getType()));
        $fVal->writeValue($msg);
    }
    $new = $msg->getOffset();
    $msg->setOffset($orig);
    writeLongUInt($msg, ($new - $orig));
    $msg->setOffsetEnd();
}




// type 't'
function readBoolean(AmqpMessageBuffer $msg) {
    $i = array_pop(unpack('C', $msg->read(1)));
    return ($i !== 0);
}
function writeBoolean(AmqpMessageBuffer $msg, $val) {
    if ($val) {
        $msg->write(pack('C', 1));
    } else {
        $msg->write(pack('C', 0));
    }
}

// type 'b'
function readShortShortInt(AmqpMessageBuffer $msg) {
    $i = array_pop(unpack('c', $msg->read(1)));
    return $i;
}
function writeShortShortInt(AmqpMessageBuffer $msg, $val) {
    $msg->write(pack('c', (int) $val));
}

// type 'B'
function readShortShortUInt(AmqpMessageBuffer $msg) {
    $i = array_pop(unpack('C', $msg->read(1)));
    return $i;
}
function writeShortShortUInt(AmqpMessageBuffer $msg, $val) {
    $msg->write(pack('C', (int) $val));
}

// type 'U'
function readShortInt(AmqpMessageBuffer $msg) {
    $i = array_pop(unpack('s', $msg->read(2)));
    return $i;
}
function writeShortInt(AmqpMessageBuffer $msg, $val) {
    $msg->write(pack('s', (int) $val));
}


// type 'u'
function readShortUInt(AmqpMessageBuffer $msg) {
    $i = array_pop(unpack('n', $msg->read(2)));
    return $i;
}
function writeShortUInt(AmqpMessageBuffer $msg, $val) {
    $msg->write(pack('n', (int) $val));
}

// type 'I'
function readLongInt(AmqpMessageBuffer $msg) {
    $i = array_pop(unpack('L', $msg->read(4)));
    return $i;
}
function writeLongInt(AmqpMessageBuffer $msg, $val) {
    $msg->write(pack('L', (int) $val));
}

// type 'i'
function readLongUInt(AmqpMessageBuffer $msg) {
    $i = array_pop(unpack('N', $msg->read(4)));
    return $i;
}
function writeLongUInt(AmqpMessageBuffer $msg, $val) {
    $msg->write(pack('N', (int) $val));
}

// type 'L'
function readLongLongInt(AmqpMessageBuffer $msg) {
    error("Unimplemented read method %s", __METHOD__);
}
function writeLongLongInt(AmqpMessageBuffer $msg, $val) {
    error("Unimplemented *write* method %s", __METHOD__);
}

// type 'l'
function readLongLongUInt(AmqpMessageBuffer $msg) {
    error("Unimplemented read method %s", __METHOD__);
}
function writeLongLongUInt(AmqpMessageBuffer $msg, $val) {
    error("Unimplemented *write* method %s", __METHOD__);
}

// type 'f'
function readFloat(AmqpMessageBuffer $msg) {
    error("Unimplemented read method %s", __METHOD__);
}
function writeFloat(AmqpMessageBuffer $msg, $val) {
    error("Unimplemented *write* method %s", __METHOD__);
}

// type 'd'
function readDouble(AmqpMessageBuffer $msg) {
    error("Unimplemented read method %s", __METHOD__);
}
function writeDouble(AmqpMessageBuffer $msg, $val) {
    error("Unimplemented *write* method %s", __METHOD__);
}

// type 'D'
function readDecimalValue(AmqpMessageBuffer $msg) {
    error("Unimplemented read method %s", __METHOD__);
}
function writeDecimalValue(AmqpMessageBuffer $msg, $val) {
    error("Unimplemented *write* method %s", __METHOD__);
}

// type 's'
function readShortString(AmqpMessageBuffer $msg) {
    $l = readShortShortUInt($msg);
    return $msg->read($l);
}
function writeShortString(AmqpMessageBuffer $msg, $val) {
    writeShortShortUInt($msg, strlen($val));
    $msg->write($val);
}

// type 'S'
function readLongString(AmqpMessageBuffer $msg) {
    $l = readLongUInt($msg);
    return $msg->read($l);
}
function writeLongString(AmqpMessageBuffer $msg, $val) {
    writeLongUInt($msg, strlen($val));
    $msg->write($val);
}

// type 'A'
function readFieldArray(AmqpMessageBuffer $msg) {
    error("Unimplemented read method %s", __METHOD__);
}
function writeFieldArray(AmqpMessageBuffer $msg, $val) {
    error("Unimplemented *write* method %s", __METHOD__);
}

// type 'T'
function readTimestamp(AmqpMessageBuffer $msg) {
    error("Unimplemented read method %s", __METHOD__);
}
function writeTimestamp(AmqpMessageBuffer $msg, $val) {
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