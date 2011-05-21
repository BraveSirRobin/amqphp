<?php
/**
 * 
 * Copyright (C) 2010, 2011  Robin Harvey (harvey.robin@gmail.com)
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.

 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.

 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 */
namespace amqphp\wire;

use amqphp\protocol as proto; // Alias avoids name clash with class of same name
use amqphp\protocol\abstrakt;

const PROTOCOL_HEADER = "AMQP\x00\x00\x09\x01";

/**
 * Note the additional complexity WRT. Boolean types, from the 0.9.1 spec:
 *    When two or more bits are contiguous in a frame these will be packed
 *    into one or more octets, starting from the low bit in each octet.
 */

/**
 * TODO:
 * (1) Consider adding a new Protocol ImplType for table booleans - I don't think
 *     the these should share the plain Amqp packing behaviour.
 * (2) Implement unimplemented types
 */


// Protocol as in PDF / BNF type
abstract class Protocol
{
    private static $Versions = array('0.9.1');
    private static $ImplTypes = array('Table',
                                      'Boolean',
                                      'ShortShortInt',
                                      'ShortShortUInt',
                                      'ShortInt',
                                      'ShortUInt',
                                      'LongInt',
                                      'LongUInt',
                                      'LongLongInt',
                                      'LongLongUInt',
                                      'Float',
                                      'Double',
                                      'DecimalValue',
                                      'ShortString',
                                      'LongString',
                                      'FieldArray',
                                      'Timestamp');

    private static $XmlTypesMap = array('bit' => 'Boolean',
                                        'octet' => 'ShortShortUInt',
                                        'short' => 'ShortUInt',
                                        'long' => 'LongUInt',
                                        'longlong' => 'LongLongUInt',
                                        'shortstr' => 'ShortString',
                                        'longstr' => 'LongString',
                                        'timestamp' => 'LongLongUInt',
                                        'table' => 'Table');


    private static $AmqpTableMap = array('t' => 'ShortShortUInt',
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
                                         'T' => 'LongLongUInt', // timestamp
                                         'F' => 'Table');


    protected $bin;

    static function GetXmlTypes () { return self::$XmlTypesMap; }


    protected function getImplForXmlType($t) {
        return isset(self::$XmlTypesMap[$t]) ?
            self::$XmlTypesMap[$t]
            : null;
    }
    protected function getImplForTableType($t) {
        return isset(self::$AmqpTableMap[$t]) ?
            self::$AmqpTableMap[$t]
            : null;
    }

    /** CAUTION: Use with care - lots of types are 'unreachable', default behaviour is
        to use shortest storage type available */
    protected function getTableTypeForValue($val) {
        if (is_bool($val)) {
            return 't';
        } else if (is_int($val))  {
            // Prefer unsigned types
            if ($val > 0) {
                if ($val < 256) {
                    return 'B'; // short-short-uint
                } else if ($val < 65536) {
                    return 'u'; // short-uint
                } else if ($val < 4294967296) {
                    return 'i'; // long-uint
                } else {
                    return 'l'; // long-long-uint
                }
            } else if ($val < 0) {
                $val = abs($val);
                if ($val < 256) {
                    return 'b'; // short-short-int
                } else if ($val < 65536) {
                    return 'U'; // short-int
                } else if ($val < 4294967296) {
                    return 'I'; // long-int
                } else {
                    return 'L'; // long-long-int
                }
            } else {
                return 'B'; // short-short-uint
            }
        } else if (is_float($val)) {
            // Prefer Decimal?
            return 'f'; // float
        } else if (is_string($val)) {
            return (strlen($val) < 255) ?
                's' // short-string
                : 'S'; // long-string
        } else if (is_array($val)) {
            // If $val is integer keyed, assume an array type, otherwise a table
            $isArray = false;
            foreach (array_keys($val) as $k) {
                if (is_int($k)) {
                    $isArray = true;
                    break;
                }
            }
            return $isArray ? 'A' : 't';
        } else if ($val instanceof Decimal) {
            //'D' => 'DecimalValue',
            return 'D';
        }
        return null;
    }
    /** CAUTION: Use with care - some types are 'unreachable', default behaviour is
        to use shortest storage type available */
    protected function getXmlTypeForValue($val) {
        if (is_bool($val)) {
            return 'bit';
        } else if (is_int($val))  {
            $val = abs($val);
            if ($val < 256) {
                return 'octet'; // short-short-int
            } else if ($val < 65536) {
                return 'short'; // short-int
            } else if ($val < 4294967296) {
                return 'long'; // long-int
            } else {
                return 'longlong'; // long-long-int
            }
        } else if (is_string($val)) {
            return (strlen($val) < 255) ?
                'shortstr' // short-string
                : 'longstr'; // long-string
        } else if (is_array($val) || $val instanceof Table) {
            return 'table';
        }
        return null;
    }

    function getBuffer() { return $this->bin; }
}


class Reader extends Protocol
{
    private $p = 0;
    private $binPackOffset = 0;
    private $binBuffer;
    private $binLen = 0;

    function __construct ($bin) {
        $this->bin = $bin;
        $this->binLen = strlen($bin);
    }

    function isSpent ($n = false) {
        $n = ($n === false) ? 0 : $n - 1;
        return ($this->p + $n >= $this->binLen);
    }

    function getBin () { return $this->bin; }

    function bytesRemaining () { return $this->binLen - $this->p; }

    function getReadPointer () { return $this->p; }

    function getRemainingBuffer () {
        $r = substr($this->bin, $this->p);
        $this->p = $this->binLen - 1;
        return $r;
    }

    function append ($bin) {
        $this->bin .= $bin;
        $this->binLen += strlen($bin);
    }


    function rewind ($n) {
        $this->p -= $n;
    }


    /** Used to fetch a string of length $n from the buffer, updates the internal pointer  */
    function readN ($n) {
        $ret = substr($this->bin, $this->p, $n);
        $this->p += strlen($ret);
        return $ret;
    }

    /** Read the given type from the local buffer and return a PHP conversion */
    function read ($type, $tableField=false) {
        $implType = ($tableField) ?
            $this->getImplForTableType($type)
            : $this->getImplForXmlType($type);
        if (! $implType) {
            trigger_error("Warning: no type mapping found for input type or value - nothing read", E_USER_WARNING);
            return;
        }
        $r = $this->{"read$implType"}();
        if ($implType === 'Boolean') {
            if ($this->binPackOffset++ > 6) {
                $this->binPackOffset = 0;
            }
        } else {
            $this->binPackOffset = 0;
        }
        return $r;
    }


    private function readTable () {
        $tLen = $this->readLongUInt();
        $tEnd = $this->p + $tLen;
        $t = new Table;
        while ($this->p < $tEnd) {
            $fName = $this->readShortString();
            $fType = chr($this->readShortShortUInt());
            $t[$fName] = new TableField($this->read($fType, true), $fType);
        }
        return $t;
    }

    private function readBoolean () {
        // Buffer 8 bits at a time.
        if ($this->binPackOffset == 0) {
            $tmp = unpack('C', substr($this->bin, $this->p++, 1));
            $this->binBuffer = reset($tmp);
        }
        return ($this->binBuffer & (1 << $this->binPackOffset)) ? 1 : 0;
    }

    private function readShortShortInt () {
        $tmp = unpack('c', substr($this->bin, $this->p++, 1));
        $i = reset($tmp);
        return $i;
    }

    private function readShortShortUInt () {
        $tmp = unpack('C', substr($this->bin, $this->p++, 1));
        $i = reset($tmp);
        return $i;
    }

    private function readShortInt () {
        $tmp = unpack('s', substr($this->bin, $this->p, 2));
        $i = reset($tmp);
        $this->p += 2;
        return $i;
    }

    private function readShortUInt () {
        $tmp = unpack('n', substr($this->bin, $this->p, 2));
        $i = reset($tmp);
        $this->p += 2;
        return $i;
    }

    private function readLongInt () {
        $tmp = unpack('L', substr($this->bin, $this->p, 4));
        $i = reset($tmp);
        $this->p += 4;
        return $i;
    }

    private function readLongUInt () {
        $tmp = unpack('N', substr($this->bin, $this->p, 4));
        $i = reset($tmp);
        $this->p += 4;
        return $i;
    }

    private function readLongLongInt () {
        trigger_error("Unimplemented read method %s", __METHOD__);
    }

    private function readLongLongUInt () {
        $byte = substr($this->bin, $this->p++, 1);
        $ret = ord($byte);
        for ($i = 1; $i < 8; $i++) {
            $ret = ($ret << 8) + ord(substr($this->bin, $this->p++, 1));
        }
        return $ret;
    }

    private function readFloat () {
        $tmp = unpack('f', substr($this->bin, $this->p, 4));
        $i = reset($tmp);
        $this->p += 4;
        return $i;
    }

    private function readDouble () {
        $tmp = unpack('d', substr($this->bin, $this->p, 8));
        $i = reset($tmp);
        $this->p += 8;
        return $i;
    }

    private function readDecimalValue () {
        $scale = $this->readShortShortUInt();
        $unscaled = $this->readLongUInt();
        return new Decimal($unscaled, $scale);
    }

    private function readShortString () {
        $l = $this->readShortShortUInt();
        $s = substr($this->bin, $this->p, $l);
        $this->p += $l;
        return $s;
    }

    private function readLongString () {
        $l = $this->readLongUInt();
        $s = substr($this->bin, $this->p, $l);
        $this->p += $l;
        return $s;
    }

    private function readFieldArray () {
        $aLen = $this->readLongUInt();
        $aEnd = $this->p + $aLen;
        $a = array();
        while ($this->p < $aEnd) {
            $t = chr($this->readShortShortUInt());
            $a[] = $this->read($t, true);
        }
        return $a;
    }
}



class Writer extends Protocol
{
    private $binPackOffset = 0;

    /** Write the given PHP variable to the local buffer using the given
        Amqp type mapping. */
    function write ($value, $type, $tableField=false) {
        $implType = ($tableField) ?
            $this->getImplForTableType($type)
            : $this->getImplForXmlType($type);
        if (! $implType) {
            trigger_error(sprintf("Warning: Unknown Amqp type: %s", $type), E_USER_WARNING);
            $implType = ($tableField) ?
                $this->getTableTypeForValue($value)
                : $this->getXmlTypeForValue($value);
            if (! $implType) {
                trigger_error("Warning: no type mapping found for input type or value - nothing written", E_USER_WARNING);
                return;
            }
        }
        $r = $this->{"write$implType"}($value);
        if ($implType === 'Boolean') {
            $this->binPackOffset++;
        } else {
            $this->binPackOffset = 0;
        }
    }


    /** @arg  mixed   $val   Either a pre-built Table or an array - arrays are used
        to construct a table to write. */
    private function writeTable ($val) {
        if (is_array($val)) {
            $val = new Table($val);
        } else if (! ($val instanceof Table)) {
            // WARNING!  This code means that this functions will swallow scalars
            // and turn them in to nothing
            //trigger_error("Invalid table, cannot write", E_USER_WARNING);
            $val = array();
        }
        $p = strlen($this->bin); // Rewind to here and write in the table length
        foreach ($val as $fName => $field) {
            $this->writeShortString($fName);
            $this->writeShortShortUInt(ord($field->getType()));
            $this->write($field->getValue(), $field->getType(), true);
        }
        // Switcheroo the bin buffer so we cn re-use the native long u-int implementation
        $p2 = strlen($this->bin);
        $binSav = $this->bin;
        $this->bin = '';
        $this->writeLongUInt($p2 - $p);
        $binLen = $this->bin;
        $this->bin = substr($binSav, 0, $p) . $binLen . substr($binSav, $p);
    }


    private function writeFieldArray (array $arr) {
        $p = strlen($this->bin);
        foreach ($arr as $item) {
            if (! ($item instanceof TableField)) {
                $item = new TableField($item);
            }
            $this->writeShortShortUInt(ord($item->getType()));
            $this->write($item->getValue(), $item->getType(), true);
        }
        // Switcheroo the bin buffer so we cn re-use the native long u-int implementation
        $p2 = strlen($this->bin);
        $binSav = $this->bin;
        $this->bin = '';
        $this->writeLongUInt($p2 - $p);
        $binLen = $this->bin;
        $this->bin = substr($binSav, 0, $p) . $binLen . substr($binSav, $p);
    }


    private function writeBoolean ($val) {
        if ($this->binPackOffset == 0) {
            if ($val) {
                $this->bin .= pack('C', 1);
            } else {
                $this->bin .= pack('C', 0);
            }
        } else {
            $tmp = unpack('C', substr($this->bin, -1));
            $b = reset($tmp);
            if ($val) {
                $b += pow(2, $this->binPackOffset);
            }
            if ($this->binPackOffset > 6) {
                $this->binPackOffset = -1;
            }
            $this->bin = substr($this->bin, 0, -1) . pack('C', $b);
        }
    }

    private function writeShortShortInt ($val) {
        $this->bin .= pack('c', (int) $val);
    }

    private function writeShortShortUInt ($val) {
        $this->bin .= pack('C', (int) $val);
    }

    private function writeShortInt ($val) {
        $this->bin .= pack('s', (int) $val);
    }

    private function writeShortUInt ($val) {
        $this->bin .= pack('n', (int) $val);
    }

    private function writeLongInt ($val) {
        $this->bin .= pack('L', (int) $val);
    }

    private function writeLongUInt ($val) {
        $this->bin .= pack('N', (int) $val);
    }

    private function writeLongLongInt ($val) {
        error("Unimplemented *write* method %s", __METHOD__);
    }

    private function writeLongLongUInt ($val) {
        $tmp = array();
        for ($i = 0; $i < 8; $i++) {
            $tmp[] = $val & 255;
            $val = ($val >> 8);
        }
        foreach (array_reverse($tmp) as $octet) {
            $this->bin .= chr($octet);
        }
    }

    private function writeFloat ($val) {
        $this->bin .= pack('f', (float) $val);
    }

    private function writeDouble ($val) {
        $this->bin .= pack('d', (float) $val);
    }

    private function writeDecimalValue ($val) {
        if (! ($val instanceof Decimal)) {
            $val = new Decimal($val);
        }
        $this->writeShortShortUInt($val->getScale());
        $this->writeLongUInt($val->getUnscaled());
    }

    private function writeShortString ($val) {
        $this->writeShortShortUInt(strlen($val));
        $this->bin .= $val;
    }

    private function writeLongString ($val) {
        $this->writeLongUInt(strlen($val));
        $this->bin .= $val;
    }

}



// Prolly useful.
function getIntSize() {
    // Choose between 32 and 64 only
    return (gettype(pow(2, 31)) == 'integer') ? 64 : 32;
}



/*
function my_bytesplit($x, $bytes) {
    $ret = array();
    for ($i = 0; $i < $bytes; $i++) {
        $ret[] = $x & 255;
        $x = ($x >> 8);
    }
    return array_reverse($ret);
}
*/



class TableField extends Protocol
{
    protected $val; // PHP native / Impl mixed
    protected $type;  // Amqp type

    /** Implement type guessing, i.e. PHP -> Amqp table mapping */
    function __construct($val, $type=false) {
        $this->val = $val;
        $this->type = ($type === false) ? $this->getTableTypeForValue($val) : $type;
    }
    function getValue() { return $this->val; }
    function setValue($val) { $this->val = $val; }
    function getType() { return $this->type; }
    function __toString() { return (string) $this->val; }
}

/** V. Similar to php-amqplib's AMQPDecimal */
class Decimal
{
    const BC_SCALE_DEFAULT = 8;
    private $unscaled;
    private $scale;
    private $bcScale = self::BC_SCALE_DEFAULT;

    /** Support a mixed construction capability, can pass a float type
        or the unscaled and scale parts.  The latter is probably more reliable */
    function __construct($unscaled, $scale=false) {
        if ($scale !== false) {
            // Construct directly from unscaled and scale int args
            if ($scale < 0 || $scale > 255) {
                throw new \Exception("Scale out of range", 9876);
            }
            $this->unscaled = (string) $unscaled;
            $this->scale = (string) $scale;
        } else if (is_float($unscaled)) {
            // Interpret from PHP float, convert to scale, unscaled.
            list($whole, $frac) = explode('.', (string) $unscaled);
            $frac = rtrim($frac, '0');
            $this->unscaled = $whole . $frac;
            $this->scale = strlen($frac);
        } else if (is_int($unscaled)) {
            $this->unscaled = $unscaled;
            $this->scale = 0;
        } else {
            throw new \Exception("Unable to construct a decimal", 48943);
        }
        if ($this->scale > 255) {
            throw new \Exception("Decimal scale is out of range", 7843);
        }
    }
    function getUnscaled() { return $this->unscaled; }
    function getScale() { return $this->scale; }
    function setBcScale($i) {
        $this->bcScale = (int) $i;
    }

    function toBcString() {
        return bcdiv($this->unscaled, bcpow('10', $this->scale, $this->bcScale), $this->bcScale);
    }

    function toFloat() {
        return (float) $this->toBcString();
    }

    function __toString() { return $this->toBcString(); }
}


class Table implements \ArrayAccess, \Iterator
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
        return array_key_exists($k, $this->data);
    }

    function offsetGet($k) {
        if (! $this->offsetExists($k)) {
            trigger_error(sprintf("Offset not found [0]: %s", $k), E_USER_WARNING);
            return null;
        }
        return $this->data[$k];
    }

    function offsetSet($k, $v) {
        if ( ! self::IsValidKey($k)) {
            throw new \Exception("Invalid table key", 7255);
        } else if (! ($v instanceof TableField)) {
            $v = new TableField($v);
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
        $ac = array();
        foreach ($this->data as $k => $v) {
            $ac[$k] = $v->getValue();
        }
        return $ac;
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
 * Represents a single Amqp method, either incoming or outgoing.  Note that a
 * method may be composed of multiple Amqp frames, depending on it's type
 */
class Method
{
    /** Used to track progress of read construct */
    const ST_METH_READ = 1;
    const ST_CHEAD_READ = 2;
    const ST_BODY_READ = 4;

    /** Used as a signal when returned from readConstruct */
    const PARTIAL_FRAME = 7;

    private $rcState = 0; // Holds a bitmask of ST_* consts

    private $methProto; // XmlSpecMethod
    private $classProto; // XmlSpecClass
    private $mode; // {read,write}
    private $fields = array(); // Amqp method fields
    private $classFields = array(); // Amqp message class fields
    private $content; // Amqp message payload

    private $frameMax; // Max frame size in bytes

    private $wireChannel = null; // Read from Amqp frame
    private $isHb = false; // Heartbeat from flag
    private $wireMethodId; // Read from Amqp method frame
    private $wireClassId; // Read from Amqp method frame
    private $contentSize; // Read from Amqp content header frame


    /**
     * @param mixed   $src    String = A complete Amqp frame = read mode
     *                        XmlSpecMethod = The type of message this should be = write mode
     * @param int     $chan   Target channel - required for write mode only
     */
    function __construct (abstrakt\XmlSpecMethod $src = null, $chan = 0) {
        if ($src instanceof abstrakt\XmlSpecMethod) {
            $this->methProto = $src;
            $this->classProto = $this->methProto->getClass();
            $this->mode = 'write';
            $this->wireChannel = $chan;
        }
    }

    /** Check the given reader to see if it's on the same channel as this */
    function canReadFrom (Reader $src) {
        if (is_null($this->wireChannel)) {
            return true;
        }
        if (true === ($_fh = $this->extractFrameHeader($src))) {
            return false; // ??
        }
        list($wireType, $wireChannel, $wireSize) = $_fh;
        $ret = ($wireChannel == $this->wireChannel);
        $src->rewind(7);
        return $ret;
    }

    /**
     * Helper: parse the incoming message from $src.
     * @return   mixed     false => error, true => complete, PARTIAL_FRAME = split frame detected.
     */
    function readConstruct (Reader $src) {
        if ($this->mode == 'write') {
            trigger_error('Invalid read construct operation on a read mode method', E_USER_WARNING);
            return false;
        }
        $FRME = 206; // TODO!!  UN-HARD CODE!!
        $break = false;
        $ret = true;


        while (! $src->isSpent()) {
            if (true === ($_fh = $this->extractFrameHeader($src))) {
                // Must read again to get the whole frame header
                $ret = self::PARTIAL_FRAME; // return signal
                break;
            } else {
                list($wireType, $wireChannel, $wireSize) = $_fh;
            }
            if (! $this->wireChannel) {
                $this->wireChannel = $wireChannel;
            } else if ($this->wireChannel != $wireChannel) {
                $src->rewind(7);
                return true;
            }
            if ($src->isSpent($wireSize + 1)) {
                // make sure that the whole frame (including frame end) is available in the buffer, if
                // not, break out now so that the connection will read again.
                $src->rewind(7);
                $ret = self::PARTIAL_FRAME; // return signal
                break;
            }
            switch ($wireType) {
            case 1:
                // Load in method and method fields
                $this->readMethodContent($src, $wireSize);
                if (! $this->methProto->getSpecHasContent()) {
                    $break = true;
                }
                break;
            case 2:
                // Load in content header and property flags
                $this->readContentHeaderContent($src, $wireSize);
                break;
            case 3:
                $this->readBodyContent($src, $wireSize);
                if ($this->readConstructComplete()) {
                    $break = true;
                }
                break;
            case 8:
                $break = $ret = $this->isHb = true;
                break;
            default:
                throw new \Exception(sprintf("Unsupported frame type %d", $wireType), 8674);
            }

            if ($src->read('octet') != $FRME) {
                throw new \Exception(sprintf("Framing exception - missed frame end (%s.%s) - (%d,%d,%d,%d) [%d, %d]",
                                             $this->classProto->getSpecName(),
                                             $this->methProto->getSpecName(),
                                             $this->rcState,
                                             $break,
                                             $src->isSpent(),
                                             $this->readConstructComplete(),
                                             strlen($this->content),
                                             $this->contentSize
                                             ), 8763);
            }

            if ($break) {
                break;
            }
        }

        return $ret;
    }

    private function extractFrameHeader(Reader $src) {
        if ($src->isSpent(7)) {
            return true;
        }

        if (null === ($wireType = $src->read('octet'))) {
            throw new \Exception('Failed to read type from frame', 875);
        } else if (null === ($wireChannel = $src->read('short'))) {
            throw new \Exception('Failed to read channel from frame', 9874);
        } else if (null === ($wireSize = $src->read('long'))) {
            throw new \Exception('Failed to read size from frame', 8715);
        }
        return array($wireType, $wireChannel, $wireSize);
    }


    /** Read a full method frame from $src */
    private function readMethodContent (Reader $src, $wireSize) {
        $st = $src->getReadPointer();
        $this->wireClassId = $src->read('short');
        $this->wireMethodId = $src->read('short');

        if (! ($this->classProto = proto\ClassFactory::GetClassByIndex($this->wireClassId))) {
            throw new \Exception(sprintf("Failed to construct class prototype for class ID %s",
                                         $this->wireClassId), 9875);
        } else if (! ($this->methProto = $this->classProto->getMethodByIndex($this->wireMethodId))) {
            throw new \Exception("Failed to construct method prototype", 5645);
        }
        // Copy field data in to cache
        foreach ($this->methProto->getFields() as $f) {
            $this->fields[$f->getSpecFieldName()] = $src->read($f->getSpecDomainType());
        }
        $en = $src->getReadPointer();
        if ($wireSize != ($en - $st)) {
            throw new \Exception("Invalid method frame size", 9845);
        }
        $this->rcState = $this->rcState | self::ST_METH_READ;
    }


    /** Read a full content header frame from src */
    private function readContentHeaderContent (Reader $src, $wireSize) {
        $st = $src->getReadPointer();
        $wireClassId = $src->read('short');
        $src->read('short'); // pointless weight field
        $this->contentSize = $src->read('longlong');
        if ($wireClassId != $this->wireClassId) {
            throw new \Exception(sprintf("Unexpected class in content header (%d, %d) - read state %d",
                                         $wireClassId, $this->wireClassId, $this->rcState), 5434);
        }

        // Load the property flags
        $binFlags = '';
        while (true) {
            if (null === ($fBlock = $src->read('short'))) {
                throw new \Exception("Failed to read property flag block", 4548);
            }
            $binFlags .= str_pad(decbin($fBlock), 16, '0', STR_PAD_LEFT);
            if (0 !== (strlen($binFlags) % 16)) {
                throw new \Exception("Unexpected message property flags", 8740);
            }
            if (substr($binFlags, -1) == '1') {
                $binFlags = substr($binFlags, 0, -1);
            } else {
                break;
            }
        }

        foreach ($this->classProto->getFields() as $i => $f) {
            if ($f->getSpecFieldDomain() == 'bit') {
                $this->classFields[$f->getSpecFieldName()] = (boolean) substr($binFlags, $i, 1);
            } else if (substr($binFlags, $i, 1) == '1') {
                $this->classFields[$f->getSpecFieldName()] = $src->read($f->getSpecFieldDomain());
            } else {
                $this->classFields[$f->getSpecFieldName()] = null;
            }
        }
        $en = $src->getReadPointer();
        if ($wireSize != ($en - $st)) {
            throw new \Exception("Invalid content header frame size", 2546);
        }
        $this->rcState = $this->rcState | self::ST_CHEAD_READ;
    }



    private function readBodyContent (Reader $src, $wireSize) {
        $this->content .= $src->readN($wireSize);
        $this->rcState = $this->rcState | self::ST_BODY_READ;
    }

    /* This for content messages, has the full message been read from the wire yet?  */
    function readConstructComplete () {
        if ($this->isHb) {
            return true;
        } else if (! $this->methProto) {
            return false;
        } else if (! $this->methProto->getSpecHasContent()) {
            return (boolean) $this->rcState & self::ST_METH_READ;
        } else {
            return ($this->rcState & self::ST_CHEAD_READ) && (strlen($this->content) >= $this->contentSize);
        }
    }


    function setField ($name, $val) {
        if ($this->mode === 'read') {
            trigger_error('Setting field value for read constructed method', E_USER_WARNING);
        } else {
            $this->fields[$name] = $val;
        }
    }
    function getField ($name) {
        return isset($this->fields[$name]) ? $this->fields[$name] : null;
    }
    function getFields () { return $this->fields; }

    function setClassField ($name, $val) {
        if ($this->mode === 'read') {
            trigger_error('Setting class field value for read constructed method', E_USER_WARNING);
        } else if (! $this->methProto->getSpecHasContent()) {
            trigger_error('Setting class field value for a method which doesn\'t take content (' .
                          $this->classProto->getSpecName() . '.' . $this->methProto->getSpecName() . ')', E_USER_WARNING);
        } else {
            $this->classFields[$name] = $val;
        }
    }
    function getClassField ($name) {
        return isset($this->classFields[$name]) ? $this->classFields[$name] : null;
    }
    function getClassFields () { return $this->classFields; }

    function setContent ($content) {
        if (! $content) {
            return;
        } else if ($this->mode === 'read') {
            trigger_error('Setting content value for read constructed method', E_USER_WARNING);
        } else if (! $this->methProto->getSpecHasContent()) {
            trigger_error('Setting content value for a method which doesn\'t take content', E_USER_WARNING);
        } else {
            $this->content = $content;
        }
    }


    function getContent () {
        if (! $this->methProto->getSpecHasContent()) {
            trigger_error('Invalid serialize operation on a method which doesn\'t take content', E_USER_WARNING);
            return '';
        }
        return $this->content;
    }


    function getMethodProto () { return $this->methProto; }
    function getClassProto () { return $this->classProto; }
    function getWireChannel () { return $this->wireChannel; }
    function getWireSize () { return $this->wireSize; }

    function getWireClassId () { return $this->wireClassId; }
    function getWireMethodId () { return $this->wireMethodId; }
    function setMaxFrameSize ($max) { $this->frameSize = $max; }
    function setWireChannel ($chan) { $this->wireChannel = $chan; }
    function isHeartbeat () { return $this->isHb; }

    function toBin () {
        if ($this->mode == 'read') {
            trigger_error('Invalid serialize operation on a read mode method', E_USER_WARNING);
            return '';
        }
        // Create the method part
        $w = new Writer;
        $tmp = $this->getMethodBin();
        $w->write(1, 'octet');
        $w->write($this->wireChannel, 'short');
        $w->write(strlen($tmp), 'long');
        $buff = $w->getBuffer() . $tmp . proto\FRAME_END;
        $ret = array($buff);
        if ($this->methProto->getSpecHasContent()) {
            // Create content header and body parts
            $w = new Writer;
            $tmp = $this->getContentHeaderBin();
            $w->write(2, 'octet');
            $w->write($this->wireChannel, 'short');
            $w->write(strlen($tmp), 'long');
            $ret[] = $w->getBuffer() . $tmp . proto\FRAME_END;

            $tmp = (string) $this->content;
            $i = 0;
            $frameSize = $this->frameSize - 8;
            while (true) {
                $chunk = substr($tmp, ($i * $frameSize), $frameSize);
                if (strlen($chunk) == 0) {
                    break;
                }
                $w = new Writer;
                $w->write(3, 'octet');
                $w->write($this->wireChannel, 'short');
                $w->write(strlen($chunk), 'long');
                $ret[] = $w->getBuffer() . $chunk . proto\FRAME_END;
                $i++;
            }
        }
        return $ret;
    }

    private function getMethodBin () {
        if ($this->mode == 'read') {
            trigger_error('Invalid serialize operation on a read mode method', E_USER_WARNING);
            return '';
        }
        $src = new Writer;
        $src->write($this->classProto->getSpecIndex(), 'short');
        $src->write($this->methProto->getSpecIndex(), 'short');

        foreach ($this->methProto->getFields() as $f) {
            $name = $f->getSpecFieldName();
            $type = $f->getSpecDomainType();
            if (! isset($this->fields[$name])) {
                trigger_error("Missing field {$name} of method {$this->methProto->getSpecName()}", E_USER_WARNING);
                return '';
            } else if (! $f->validate($this->fields[$name])) {
                trigger_error("Field {$name} of method {$this->methProto->getSpecName()} is not valid", E_USER_WARNING);
                return '';
            }
            $src->write($this->fields[$name], $type);
        }
        return $src->getBuffer();
    }

    private function getContentHeaderBin () {
        if ($this->mode == 'read') {
            trigger_error('Invalid serialize operation on a read mode method', E_USER_WARNING);
            return '';
        } else if (! $this->methProto->getSpecHasContent()) {
            trigger_error('Invalid serialize operation on a method which doesn\'t take content', E_USER_WARNING);
            return '';
        }
        $src = new Writer;
        $src->write($this->classProto->getSpecIndex(), 'short');
        $src->write(0, 'short');
        $src->write(strlen($this->content), 'longlong');
        $pFlags = '';
        $pChunks = 0;
        $pList = '';
        $src2 = new Writer;
        foreach ($this->classProto->getFields() as $i => $f) {
            if (($i % 15) == 0) {
                if ($i > 0) {
                    /** The property flags can specify more than 16 properties. If the last bit (0) is set, this indicates that a
                        further property flags field follows. There are many property flags fields as needed. (4.2.6.1)*/
                    $pFlags .= '1';
                }
                $pChunks++;
            }
            $fName = $f->getSpecFieldName();
            $dName = $f->getSpecFieldDomain();
            if (isset($this->classFields[$fName]) && 
                ! ($dName == 'bit' && ! $this->classFields[$fName])) {
                $pFlags .= '1';
            } else {
                $pFlags .= '0';
            }
            if (isset($this->classFields[$fName]) && $dName != 'bit') {
                if (! $f->validate($this->classFields[$fName])) {
                    trigger_error("Field {$fName} of method {$this->methProto->getSpecName()} is not valid", E_USER_WARNING);
                    return '';
                }
                $src2->write($this->classFields[$fName], $f->getSpecDomainType());
            }
        }
        if ($pFlags && (strlen($pFlags) % 16) !== 0) {
            $pFlags .= str_repeat('0', 16 - (strlen($pFlags) % 16));
        }
        // Assemble the flag bytes
        $pBuff = '';
        for ($i = 0; $i < $pChunks; $i++) {
            $pBuff .= pack('n', bindec(substr($pFlags, $i*16, 16)));
        }
        return $src->getBuffer() . $pBuff . $src2->getBuffer();
    }



    /** Checks if $other is the right type to be a response to *this* method */
    function isResponse (Method $other) {
        if ($exp = $this->methProto->getSpecResponseMethods()) {
            if ($this->classProto->getSpecName() != $other->classProto->getSpecName() ||
                $this->wireChannel != $other->wireChannel) {
                return false;
            } else {
                return in_array($other->methProto->getSpecName(), $exp);
            }
        } else {
            trigger_error("Method does not expect a response", E_USER_WARNING);
            return false;
        }
    }
}






/**
 * A PHP replacement for hexdump -C
 */
function hexdump ($buff) {
    static $f, $f2;
    if ($buff === '') {
        return "00000000\n";
    }
    if (is_null($f)) {
        $f = function ($char) {
            return sprintf('%02s', dechex(ord($char)));
        };
        $f2 = function ($char) {
            $ord = ord($char);
            return ($ord > 31 && $ord < 127)
                ? chr($ord)
                : '.';
        };
    }
    $l = strlen($buff);
    $ret = '';

    for ($i = 0; $i < $l; $i += 16) {
        $line = substr($buff, $i, 8);
        $ll = $offLen = strlen($line);
        $rem = (8 - $ll) * 3;
        $hexes = vsprintf(str_repeat('%3s', $ll), array_map($f, str_split($line, 1)));
        $chars = '|' . vsprintf(str_repeat('%s', $ll), array_map($f2, str_split($line, 1)));
        $lBuff = sprintf("%08s %s", dechex($i), $hexes);

        if ($line = substr($buff, $i + 8, 8)) {
            $ll = strlen($line);
            $offLen += $ll;
            $rem = (8 - $ll) * 3 + 1;
            $hexes = vsprintf(str_repeat('%3s', $ll), array_map($f, str_split($line, 1)));
            $chars .= ' '.  vsprintf(str_repeat('%s', $ll), array_map($f2, str_split($line, 1)));
            $lBuff .= sprintf(" %s%{$rem}s %s|\n", $hexes, ' ', $chars);
        } else {
            $lBuff .= ' ' . str_repeat(" ", $rem + 26) . $chars . "|\n";
        }
        $ret .= $lBuff;
    }
    return sprintf("%s%08s\n", $ret, dechex($l));
}