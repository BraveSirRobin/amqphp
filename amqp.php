<?php

namespace amqp_091;

require 'amqp.wire.php';
require 'gencode/amqp.0_9_1.php';

use amqp_091\wire;
use amqp_091\protocol;


const HEXDUMP_BIN = '/usr/bin/hexdump -C';


const PROTO_HEADER = wire\HELLO;
const PROTO_FRME = wire\FRME;




abstract class AmqpMessage
{

    const TYPE_METHOD = protocol\FRAME_METHOD;
    const TYPE_HEADER = protocol\FRAME_HEADER;
    const TYPE_BODY = protocol\FRAME_BODY;
    const TYPE_HEARTBEAT = protocol\FRAME_HEARTBEAT;
    /**
     * Factory methods
     */
    private function __construct () {}
    static function FromMessage ($binStr) {
        $buff = new wire\AmqpMessageBuffer($binStr);
        $type = wire\readShortShortUInt($buff);
        $chan = wire\readShortUInt($buff);
        $len = wire\readLongUInt($buff);
        $m = self::_New($type);
        $m->chan = $chan;
        $m->len = $len;
        $m->buff = $buff;
        return $m;
    }
    static function NewMessage ($type, $chan) {
        $m = self::_New($type);
        $m->type = $type;
        $m->chan = $chan;
        $m->buff = new wire\AmqpMessageBuffer('');
        return $m;
    }
    private static function _New ($type) {
        switch ($type) {
        case self::TYPE_METHOD:
            $ret = new AmqpMethod;
            break;
        case self::TYPE_HEADER:
            $ret = new AmqpHeader;
            break;
        case self::TYPE_BODY:
            $ret = new AmqpBody;
            break;
        case self::TYPE_HEARTBEAT:
            $ret = new AmqpHeartbeat;
            break;
        default:
            throw new \Exception("Bad message type", 9864);
        }
        $ret->type = $type;
        return $ret;
    }
    /**
     * Message content handling
     */
    private $buff;
    private $type;
    private $len;
    private $chan;

    function getBuffer () { return $this->buff; }
    function getType () { return $this->type; }
    function getLength () { return $this->len; }
    function getChannel () { return $this->chan; }
    function setChannel ($chan) { $this->chan = $chan; }


    function flush() {
        $this->buff = new wire\AmqpMessageBuffer('');
        static::flushMessage(); // Copy message contents from child class
        $len = $this->buff->getLength();
        wire\writeShortShortUInt($this->buff, 206);
        $this->buff->setOffset(0);
        echo "  [AmqpMessage->flush] len = $len, type = {$this->type}, channel = {$this->chan}\n";
        wire\writeShortShortUInt($this->buff, $this->type);
        wire\writeShortUInt($this->buff, $this->chan);
        wire\writeLongUInt($this->buff, $len);

        $b = $this->buff->getBuffer();
        //        echo hexdump($b);
        return $b;
    }

    // Subclasses can implement so that their content can be added to the message
    function flushMessage() {}
}


class AmqpMethod extends AmqpMessage implements \ArrayAccess {
    private $cache; // PHP version of underlying method fields
    private $classId;
    private $methodId;
    private $className;
    private $methodName;

    function setClassId ($id) { $this->classId = $id; }
    function setClassName ($name) { $this->className = $name; }
    function getClassId () { return $this->classId; }
    function getClassName () { return $this->className; }

    function setMethodId ($id) { $this->methodId = $id; }
    function setMethodName ($name) { $this->methodName = $name; }
    function getMethodId () { return $this->methodId; }
    function getMethodName () { return $this->methodName; }


    /** Copies data from underlying message in to PHP data cache  */
    function parseMessage () {
        if ($this->cache) {
            return $this->cache;
        }
        $buff = $this->getBuffer();
        $this->classId = wire\readShortUInt($buff);
        $this->methodId = wire\readShortUInt($buff);
        // Look up the method prototype object
        list($classProto, $methProto) = $this->getPrototypes();
        // Copy field data in to cache
        foreach ($methProto->getFields() as $f) {
            $this->cache[$f->getSpecFieldName()] = $f->read($buff);
            // $this->cache[$f->getSpecFieldName()] = $buff->read($f);
        }
    }

    /** Copies data from cache to the underlying message, returns number of bytes copied
        NOTE: this does not copy the message level parameters (type, channel, length) */
    function flushMessage () {
        if (! $this->cache) {
            return 0;
        }
        $buff = $this->getBuffer();
        // Look up the method prototype object
        list($classProto, $methProto) = $this->getPrototypes();
        $ret = 0;
        // Write the class and method numbers in to the buffer
        wire\writeShortUInt($buff, $classProto->getSpecIndex());
        wire\writeShortUInt($buff, $methProto->getSpecIndex());
        foreach ($methProto->getFields() as $f) {
            //echo "  Process field {$f->getSpecFieldName()}: " .
            //"({$this->cache[$f->getSpecFieldName()]})-[" . get_class($f) . "]\n";
            if (! isset($this->cache[$f->getSpecFieldName()])) {
                throw new \Exception("Missing field {$f->getSpecFieldName()} of method {$methProto->getSpecName()}", 98765);
            }
            $f->write($buff, $this->cache[$f->getSpecFieldName()]);
            // $buff->write($f, $this->cache[$f->getSpecFieldName()]);
        }
    }
    /** Lookup method allows mixed usage of method / class names / numbers.  Numbers are preferred */
    private function getPrototypes () {
        if (! is_null($this->classId)) {
            $classProto = protocol\ClassFactory::GetClassByIndex($this->classId);
        } else if (! is_null($this->className)) {
            $classProto = protocol\ClassFactory::GetClassByName($this->className);
        } else {
            throw new \Exception("Unknown class index", 98532);
        }

        if (! is_null($this->methodId)) {
            $methProto = $classProto->getMethodByIndex($this->methodId);
        } else if (! is_null($this->methodName)) {
            $methProto = $classProto->getMethodByName($this->methodName);
        } else {
            throw new \Exception("Unknown method index", 8529);
        }
        return array($classProto, $methProto);
    }


    function offsetExists ($offset) { return isset($this->cache[$offset]); }
    function offsetGet ($offset) { return $this->cache[$offset]; }
    function offsetSet ($offset, $value) { $this->cache[$offset] = $value; }
    function offsetUnset ($offset) { unset($this->cache[$offset]); }

    function getMethodData() {
        return $this->cache;
    }
}
class AmqpHeader extends AmqpMessage {}
class AmqpBody extends AmqpMessage {}
class AmqpHeartbeat extends AmqpMessage {}




function hexdump($subject) {
    if ($subject === '') {
        return "00000000\n";
    }
    $pDesc = array(
                   array('pipe', 'r'),
                   array('pipe', 'w'),
                   array('pipe', 'r')
                   );
    $pOpts = array('binary_pipes' => true);
    if (($proc = proc_open(HEXDUMP_BIN, $pDesc, $pipes, null, null, $pOpts)) === false) {
        throw new \Exception("Failed to open hexdump proc!", 675);
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
