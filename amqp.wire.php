<?php
namespace amqp_091\wire;

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

const HELLO = "AMQP\x00\x00\x09\x01"; // Hello text to spit down a freshly opened socket
const FRME = "\xCE"; // Frame end marker


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
                                        'timestamp' => 'Timestamp',
                                        'table' => 'Table');


    private static $AmqpTableMap = array('t' => 'Boolean',
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
                                         'F' => 'Table');

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
}


class Reader extends Protocol
{
    private $bin;
    private $p = 0;
    private $binPackOffset = 0;
    private $binBuffer;

    function __construct ($bin) { $this->bin = $bin; }

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
            $this->binBuffer = array_pop(unpack('C', substr($this->bin, $this->p++, 1)));
        }
        return ($this->binBuffer & (1 << $this->binPackOffset)) ? 1 : 0;
    }

    private function readShortShortInt () {
        $i = array_pop(unpack('c', substr($this->bin, $this->p++, 1)));
        return $i;
    }

    private function readShortShortUInt () {
        $i = array_pop(unpack('C', substr($this->bin, $this->p++, 1)));
        return $i;
    }

    private function readShortInt () {
        $i = array_pop(unpack('s', substr($this->bin, $this->p, 2)));
        $this->p += 2;
        return $i;
    }

    private function readShortUInt () {
        $i = array_pop(unpack('n', substr($this->bin, $this->p, 2)));
        $this->p += 2;
        return $i;
    }

    private function readLongInt () {
        $i = array_pop(unpack('L', substr($this->bin, $this->p, 4)));
        $this->p += 4;
        return $i;
    }

    private function readLongUInt () {
        $i = array_pop(unpack('N', substr($this->bin, $this->p, 4)));
        $this->p += 4;
        return $i;
    }

    private function readLongLongInt () {
        error("Unimplemented read method %s", __METHOD__);
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
        error("Unimplemented read method %s", __METHOD__);
    }

    private function readDouble () {
        error("Unimplemented read method %s", __METHOD__);
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
        //        echo "Built array?!\n";
        //        var_dump($a);
        return $a;
    }

    private function readTimestamp () {
        error("Unimplemented read method %s", __METHOD__);
    }


    private function packInt64 ($n) {
        static $lbMask = null;
        if (is_null($lbMask)) {
            $lbMask = (pow(2, 32) - 1);
        }
        $hb = $n >> 16;
        $lb = $n & $lbMask;
        return pack('N', $hb) . pack('N', $lb);
    }

    private function unpackInt64 ($pInt) {
        $plb = substr($pInt, 0, 2);
        $phb = substr($pInt, 2, 2);
        $lb = (int) array_shift(unpack('N', $plb));
        $hb = (int) array_shift(unpack('N', $phb));
        return (int) $hb + (((int) $lb) << 16);
    }
}



class Writer extends Protocol
{
    private $bin;
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

    function getBuffer() { return $this->bin; }

    /** @arg  mixed   $val   Either a pre-built Table or an array - arrays are used
        to construct a table to write. */
    private function writeTable ($val) {
        if (is_array($val)) {
            $val = new Table($val);
        } else if (! ($val instanceof Table)) {
            trigger_error("Invalid table, cannot write", E_USER_WARNING);
            return;
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
            $b = array_pop(unpack('C', substr($this->bin, -1)));
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
        error("Unimplemented *write* method %s", __METHOD__);
    }

    private function writeDouble ($val) {
        error("Unimplemented *write* method %s", __METHOD__);
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

    private function writeTimestamp ($val) {
        error("Unimplemented *write* method %s", __METHOD__);
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

// Test Code.

t1();

function t1() {
    $aTable = array("Foo" => "Bar");
    $table = new Table($aTable);
    $table['bigfoo'] = 'B' . str_repeat('b', 256) . 'ar!';
    $table['num'] = 2;
    $table['bignum'] = 259;
    $table['negnum'] = -2;
    $table['bignegnum'] = -259;
    $table['array1'] = array('String element', 1, -2, array('sub1', 'sub2'));
    $table['littlestring'] = 'Eeek';
    $table['Decimal'] = new Decimal(1234567, 3);
    $table['longlong'] = new TableField(100000034000001, 'l');
    //    var_dump($table);

    $w = new Writer;
    $w->write('a table:', 'shortstr');
    $w->write($table, 'table');
    $w->write('phew!', 'shortstr');
    echo $w->getBuffer();
    die;
    echo "\n-Regurgitate-\n";

    $r = new Reader($w->getBuffer());
    echo $r->read('shortstr') . "\n";
    echo "Table:\n";
    foreach ($r->read('table') as $fName => $tField) {
        $value = $tField->getValue();
        if (is_array($value)) {
            printf(" [name=%s,type=%s] %s\n", $fName, $tField->getType(), implode(', ', $value));
        } else {
            printf(" [name=%s,type=%s] %s\n", $fName, $tField->getType(), $value);
        }
    }
    echo $r->read('shortstr') . "\n";
}


// Write stuff then read it back again
function t2() {
    $w = new Writer;
    $w->write('I ATE ', 'shortstr');
    $w->write(3, 'octet');
    $w->write(' GOATS', 'shortstr');
    $w->write(0, 'bit');//1
    $w->write(1, 'bit');//2
    $w->write(0, 'bit');//3
    $w->write(1, 'bit');//4
    $w->write(0, 'bit');//5
    $w->write(1, 'bit');//6
    $w->write(0, 'bit');//7
    $w->write(0, 'bit');//8
    $w->write(0, 'bit');//9
    echo $w->getBuffer();

    echo "\n-Regurgitate-\n";
    $r = new Reader($w->getBuffer());
    echo $r->read('shortstr') . $r->read('octet') . $r->read('shortstr') .
        ' ' . $r->read('bit') . ' ' . $r->read('bit') . ' ' . $r->read('bit') .
        ' ' . $r->read('bit') . ' ' . $r->read('bit') . ' ' . $r->read('bit') .
        ' ' . $r->read('bit') . ' ' . $r->read('bit') . ' ' . $r->read('bit');
}



// Bools
function t3() {
    $w = new Writer;
    $w->write('2 bools next:', 'shortstr');
    $w->write(0, 'bit');//1
    $w->write(0, 'bit');//2
    $w->write(0, 'bit');//3
    $w->write(0, 'bit');//4
    $w->write(0, 'bit');//5
    $w->write(0, 'bit');//6
    $w->write(0, 'bit');//7
    $w->write(1, 'bit');//8
    $w->write(1, 'bit');//9
    echo $w->getBuffer();
}

function t4() {
    $d1 = new Decimal(100, 4);
    $d2 = new Decimal(100, 10);
    $d3 = new Decimal(69, 2);
    var_dump($d2);
    printf("Convert to string BC: (\$d1, \$d2, \$d3) = (%s, %s, %s)\n", $d1->toBcString(), $d2->toBcString(), $d3->toBcString());
}