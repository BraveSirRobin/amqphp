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
            $val = array();
        }
        $w = new Writer;
        foreach ($val as $fName => $field) {
            $w->writeShortString($fName);
            $w->writeShortShortUInt(ord($field->getType()));
            $w->write($field->getValue(), $field->getType(), true); // Could recurse
        }
        $this->bin .= pack('N', strlen($w->bin)) . $w->bin;
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