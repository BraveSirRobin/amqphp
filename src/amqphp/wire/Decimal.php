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
