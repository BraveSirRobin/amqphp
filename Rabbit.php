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
            if (substr($tmp, -1) === AmqpMessageHandler::PROTO_FRME) {
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
 * Decodes / Encodes from / to AMQP wire format
 */
class AmqpMessageHandler
{

    const PROTO_HEADER = "AMQP\x01\x01\x09\x01";
    const PROTO_FRME = "\xCE"; // Frame end marker

    // Mapping for property tables field types -> data extraction routines.
    private static $FieldTypeMethodMap = array(
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

    private $buff;  // Full Amqp frame
    private $p = 0; // Position pointer within $buff

    function __construct($buff) {
        $this->buff = $buff;
        $this->extractFrame();
    }


    private $frameType;
    private $frameChan;
    private $frameLen;

    function extractFrame() {
        $this->frameType = array_pop(unpack('c', substr($this->buff, 0, 1)));
        $this->frameChan = array_pop(unpack('n', substr($this->buff, 1, 2)));
        $this->frameLen = array_pop(unpack('N', substr($this->buff, 3, 4)));
        $this->p += 7;
    }

    function dumpFrameData() {
        $args = array("Frame data:\nType: %d\nChan: %s\nLen:  %s", $this->frameType, $this->frameChan, $this->frameLen);
        call_user_func_array('info', $args);
    }


    private $methClassId;
    private $methMethodId;
    function extractMethod() {
        if ($this->frameType != 1) {
            error("Frame is not a method: (type, chan, len) = (%d, $d, %d)", $this->frameType, $this->frameChan, $this->frameLen);
            return false;
        }
        $bits = unpack('n2', substr($this->buff, $this->p, 4));
        $this->p += 4;
        $this->methClassId = $bits[1];
        $this->methMethodId = $bits[2];
        $this->readMethodArgs();
    }


    /**
     * Given the Amqp method frame, class and method, extract and return
     * the class and method name and the method args
     * @param  integer     $classId       Class number
     * @param  integer     $methodId      Method number
     * @param  bstring     $buff          Method frame contents *after* method and class id are removed.
     * @return array                      Format: array(<cls-name>, <meth-name>, <vers-maj>, <vers-min>, <TODO>)
     */
    private $methClassName;
    private $methMethodName;
    private $methProperties;
    private function readMethodArgs() {
        if ($this->methClassId == 10 && $this->methMethodId == 10) {
            $this->methClassName = 'connection';
            $this->methMethodName = 'start';
            // (10, 10) = connection.start
            // field [name = "version-major" domain = "octet" label = "protocol major version"]
            // field [name = "version-minor" domain = "octet" label = "protocol minor version"]
            // field [name = "server-properties" domain = "peer-properties" label = "server properties"]
            // field [name = "mechanisms" domain = "longstr" label = "available security mechanisms"]
            // field [name = "locales" domain = "longstr" label = "available message locales"]
            $proto = unpack('C2', substr($this->buff, $this->p, 2));
            $this->p += 2;
            $vMaj = $proto[1];
            $vMin = $proto[2];
            $this->methProperties = array(
                                          'version-major' => $vMaj,
                                          'version-minor' => $vMin,
                                          'server-properties' => $this->readFieldTable(),
                                          'mechanisms' => $this->readLongString(),
                                          'locales' => $this->readLongString()
                                          );
        }
    }

    function dumpMethodData() {
        $msg = "Method frame details:\nClass:  %d (%s)\nMethod: %s (%s)\nProperties:";
        $vals = array($this->methClassId, $this->methClassName, $this->methMethodId, $this->methMethodName);
        $this->recursiveShow($this->methProperties, $msg, $vals);
        array_unshift($vals, $msg);
        call_user_func_array('info', $vals);
    }


    // Recurse through $subject appending printf control string and params to by-ref buffers
    private function recursiveShow($subject, &$str, &$vals, $depth = 0) {
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

    // type 'b'
    function readShortShortInt() {
        $i = array_pop(unpack('c', substr($this->buff, $this->p, 1)));
        $this->p++;
        return $i;
    }

    // type 'B'
    function readShortShortUInt() {
        $i = array_pop(unpack('C', substr($this->buff, $this->p, 1)));
        $this->p++;
        return $i;
    }

    // type 'U'
    function readShortInt() {
        $i = array_pop(unpack('s', substr($this->buff, $this->p, 2)));
        $this->p += 2;
        return $i;
    }


    // type 'u'
    function readShortUInt() {
        $i = array_pop(unpack('n', substr($this->buff, $this->p, 2)));
        $this->p += 2;
        return $i;
    }

    // type 'I'
    function readLongInt() {
        $i = array_pop(unpack('L', substr($this->buff, $this->p, 4)));
        $this->p += 4;
        return $i;
    }

    // type 'i'
    function readLongUInt() {
        $i = array_pop(unpack('N', substr($this->buff, $this->p, 4)));
        $this->p += 4;
        return $i;
    }

    // type 'L'
    function readLongLongInt() {
        error("Unimplemented read method %s", __METHOD__);
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

    // type 'f'
    function readFloat() {
        error("Unimplemented read method %s", __METHOD__);
    }

    // type 'd'
    function readDouble() {
        error("Unimplemented read method %s", __METHOD__);
    }

    // type 'D'
    function readDecimalValue() {
        error("Unimplemented read method %s", __METHOD__);
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

    // type 'S'
    function readLongString() {
        $l = $this->readLongUInt();
        $ret = substr($this->buff, $this->p, $l);
        //info("LONGSTRING: (len = %d, val = %s)", $l, $ret);
        $this->p += $l;
        return $ret;
    }

    // type 'A'
    function readFieldArray() {
        error("Unimplemented read method %s", __METHOD__);
    }

    private function readFieldType($type) {
        if (isset(self::$FieldTypeMethodMap[$type])) {
            return $this->{self::$FieldTypeMethodMap[$type]}();
        } else {
            error("Unknown field type %s", $type);
            return null;
        }
    }

    // type 'T'
    function readTimestamp() {
        error("Unimplemented read method %s", __METHOD__);
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
$s->write(AmqpMessageHandler::PROTO_HEADER);
info("Header written, now read");
$response = $s->read();
$amqp = new AmqpMessageHandler($response);
info("Response received:\n%s", $amqp->hexdump($response));

$amqp->dumpFrameData();

$amqp->extractMethod();

$amqp->dumpMethodData();


info("Close socket");
$s->close();
info("Complete.");