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

/**
 * A PHP replacement for hexdump -C.
 */

final class Hexdump
{
    final static function hexdump ($buff) {
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
}