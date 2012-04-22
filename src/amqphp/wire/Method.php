<?php
/**
 * 
 * Copyright (C) 2010, 2011, 2012  Robin Harvey (harvey.robin@gmail.com)
 *
 * This  library is  free  software; you  can  redistribute it  and/or
 * modify it under the terms  of the GNU Lesser General Public License
 * as published by the Free Software Foundation; either version 2.1 of
 * the License, or (at your option) any later version.

 * This library is distributed in the hope that it will be useful, but
 * WITHOUT  ANY  WARRANTY;  without   even  the  implied  warranty  of
 * MERCHANTABILITY or  FITNESS FOR A PARTICULAR PURPOSE.   See the GNU
 * Lesser General Public License for more details.

 * You should  have received a copy  of the GNU  Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation,  Inc.,  51 Franklin  Street,  Fifth  Floor, Boston,  MA
 * 02110-1301 USA
 */
namespace amqphp\wire;

use amqphp\protocol as proto; // Alias avoids name clash with class of same name
use amqphp\protocol\abstrakt;


/**
 * Represents a single Amqp method, either incoming or outgoing.  Note
 * that a method may be composed of multiple Amqp frames, depending on
 * it's  type.  TODO:  Review wire  level implementation  and optimise
 * this implementation.
 */
class Method implements \Serializable
{
    /** Used to track progress of read construct */
    const ST_METH_READ = 1;
    const ST_CHEAD_READ = 2;
    const ST_BODY_READ = 4;

    /** Used as a signal when returned from readConstruct */
    const PARTIAL_FRAME = 7;


    /** List of fields that will be persisted as-is */
    private static $PlainPFields = 
        array('rcState', 'mode', 'fields', 'classFields', 'content',
              'frameSize', 'wireChannel', 'isHb', 'wireMethodId', 'wireClassId',
              'contentSize');

    private $rcState = 0; // Holds a bitmask of ST_* consts

    private $methProto; // XmlSpecMethod
    private $classProto; // XmlSpecClass
    private $mode; // {read,write}
    private $fields = array(); // Amqp method fields
    private $classFields = array(); // Amqp message class fields
    private $content; // Amqp message payload

    private $frameSize; // Max frame size in bytes, set by channel

    private $wireChannel = null; // Read from Amqp frame
    private $wireMethodId; // Read from Amqp method frame
    private $wireClassId; // Read from Amqp method frame
    private $contentSize; // Read from Amqp content header frame
    private $isHb = false; // Heartbeat from flag

    private $protoLoader; // Closure passed in to readConstruct from Connection

    /** Set to class.method as soon as the type of the underlying Amqp
     * method is known */
    public $amqpClass;

    function serialize () {
        if ($this->mode != 'read') {
            trigger_error("Only read mode methods should be serialised", E_USER_WARNING);
            return null;
        }
        $ret = array();
        $ret['plainFields'] = array();
        foreach (self::$PlainPFields as $k) {
            $ret['plainFields'][$k] = $this->$k;
        }
        if ($this->methProto && $this->classProto) {
            $ret['protos'] = array(get_class($this->methProto), get_class($this->classProto));
        }

        return serialize($ret);
    }


    function unserialize ($s) {
        $state = unserialize($s);
        foreach (self::$PlainPFields as $k) {
            $this->$k = $state['plainFields'][$k];
        }
        if (array_key_exists('protos', $state)) {
            list($mc, $cc) = $state['protos'];
            /* Note:   breaks   convention;   in   all   other   cases
             * XmlSpecClass /  XmlSpecProto instances are  loaded (and
             * re-used) from their factories. */
            $this->methProto = new $mc;
            $this->classProto = new $cc;
            $this->amqpClass = sprintf('%s.%s', $this->classProto->getSpecName(), $this->methProto->getSpecName());
        }
    }

    /**
     * Required  only  for  unserialisation,  seems hacky.   Might  be
     * better  to  refactor and  get  rid  of  the loader  Closure  in
     * Connection  and replace  with a  class /  factory which  can be
     * referenced statically
     */
    function setProtocolLoader ($l) {
        $this->protoLoader = $l;
    }

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
            $this->amqpClass = sprintf('%s.%s', $this->classProto->getSpecName(), $this->methProto->getSpecName());
        } else {
            $this->mode = 'read';
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
    function readConstruct (Reader $src, \Closure $protoLoader) {
        if ($this->mode == 'write') {
            trigger_error('Invalid read construct operation on a read mode method', E_USER_WARNING);
            return false;
        }
        $FRME = 206; // TODO!!  UN-HARD CODE!!
        $break = false;
        $ret = true;
        $this->protoLoader = $protoLoader;


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
                throw new \Exception(sprintf("Framing exception - missed frame end (%s) - (%d,%d,%d,%d) [%d, %d]",
                                             $this->amqpClass,
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
        $st = $src->p;
        $this->wireClassId = $src->read('short');
        $this->wireMethodId = $src->read('short');

        $protoLoader = $this->protoLoader;

        if (! ($this->classProto = $protoLoader('ClassFactory', 'GetClassByIndex', array($this->wireClassId)))) {
            throw new \Exception(sprintf("Failed to construct class prototype for class ID %s",
                                         $this->wireClassId), 9875);
        } else if (! ($this->methProto = $this->classProto->getMethodByIndex($this->wireMethodId))) {
            throw new \Exception("Failed to construct method prototype", 5645);
        }
        $this->amqpClass = sprintf('%s.%s', $this->classProto->getSpecName(), $this->methProto->getSpecName());
        // Copy field data in to cache
        foreach ($this->methProto->getFields() as $f) {
            $this->fields[$f->getSpecFieldName()] = $src->read($f->getSpecDomainType());
        }
        $en = $src->p;
        if ($wireSize != ($en - $st)) {
            throw new \Exception("Invalid method frame size", 9845);
        }
        $this->rcState = $this->rcState | self::ST_METH_READ;
    }


    /** Read a full content header frame from src */
    private function readContentHeaderContent (Reader $src, $wireSize) {
        $st = $src->p;
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
        $en = $src->p;
        if ($wireSize != ($en - $st)) {
            throw new \Exception("Invalid content header frame size", 2546);
        }
        $this->rcState = $this->rcState | self::ST_CHEAD_READ;
    }



    private function readBodyContent (Reader $src, $wireSize) {
        $this->content .= $src->readN($wireSize);
        $this->rcState = $this->rcState | self::ST_BODY_READ;
    }

    /* This for content messages, has  the full message been read from
     * the wire yet?  */
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

    /* Sets  a  message  field,  this  could  be  a  method  or  class
     * property. */
    function setField ($name, $val) {
        if ($this->mode == 'read') {
            trigger_error('Setting field value for read constructed method', E_USER_WARNING);
        } else if (in_array($name, $this->methProto->getSpecFields())) {
            $this->fields[$name] = $val;
        } else if (in_array($name, $this->classProto->getSpecFields())) {
            $this->classFields[$name] = $val;
        } else {
            $warns = sprintf("Field %s is invalid for Amqp message type %s", $name, $this->amqpClass);
            trigger_error($warns, E_USER_WARNING);
        }
    }

    /* Return the given field value */
    function getField ($name) {
        if (array_key_exists($name, $this->fields)) {
            return $this->fields[$name];
        } else if (array_key_exists($name, $this->classFields)) {
            return $this->classFields[$name];
        } else if (! in_array($name, array_merge($this->classProto->getSpecFields(), $this->methProto->getSpecFields()))) {
            $warns = sprintf("Field %s is invalid for Amqp message type %s", $name, $this->amqpClass);
            trigger_error($warns, E_USER_WARNING);
        }
    }

    function getFields () { return array_merge($this->classFields, $this->fields); }


    function setContent ($content) {
        if ($this->mode == 'read') {
            trigger_error('Setting content value for read constructed method', E_USER_WARNING);
        } else if (strlen($content)) {
            if (! $this->methProto->getSpecHasContent()) {
                trigger_error('Setting content value for a method which doesn\'t take content', E_USER_WARNING);
            }
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

    function toBin (\Closure $protoLoader) {
        if ($this->mode == 'read') {
            trigger_error('Invalid serialize operation on a read mode method', E_USER_WARNING);
            return '';
        }
        // Create the method part
        $frme = $protoLoader('ProtoConsts', 'GetConstant', array('FRAME_END'));
        $w = new Writer;
        $tmp = $this->getMethodBin();
        $w->write(1, 'octet');
        $w->write($this->wireChannel, 'short');
        $w->write(strlen($tmp), 'long');
        $buff = $w->getBuffer() . $tmp . $frme;
        $ret = array($buff);
        if ($this->methProto->getSpecHasContent()) {
            // Create content header and body parts
            $w = new Writer;
            $tmp = $this->getContentHeaderBin();
            $w->write(2, 'octet');
            $w->write($this->wireChannel, 'short');
            $w->write(strlen($tmp), 'long');
            $ret[] = $w->getBuffer() . $tmp . $frme;

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
                $ret[] = $w->getBuffer() . $chunk . $frme;
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
            $val = '';
            if (array_key_exists($name, $this->fields)) {
                $val = $this->fields[$name];

                if (! $f->validate($val)) {
                    $warns = sprintf("Field %s of method %s failed validation by protocol binding class %s",
                                     $name, $this->amqpClass, get_class($f));
                    trigger_error($warns, E_USER_WARNING);
                }
            }
            $src->write($val, $type);
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
            if (array_key_exists($fName, $this->classFields) &&
                ! ($dName == 'bit' && ! $this->classFields[$fName])) {
                $pFlags .= '1';
            } else {
                $pFlags .= '0';
            }
            if (array_key_exists($fName, $this->classFields) && $dName != 'bit') {
                if (! $f->validate($this->classFields[$fName])) {
                    trigger_error("Field {$fName} of method {$this->amqpClass} is not valid", E_USER_WARNING);
//                    return '';
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