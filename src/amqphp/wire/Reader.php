<?php
/**
 * 
 * Copyright (C) 2010, 2011, 2012  Robin Harvey (harvey.robin@gmail.com)
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




class Reader extends Protocol
{
    public $p = 0;
    public $binPackOffset = 0;
    public $binBuffer;
    public $binLen = 0;

    function __construct ($bin) {
        $this->bin = $bin;
        $this->binLen = strlen($bin);
    }

    function isSpent ($n = false) {
        $n = ($n === false) ? 0 : $n - 1;
        return ($this->p + $n >= $this->binLen);
    }


    function bytesRemaining () { return $this->binLen - $this->p; }


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
