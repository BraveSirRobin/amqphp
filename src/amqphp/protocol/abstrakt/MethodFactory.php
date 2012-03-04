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
/**
 * TODO: Consider switching implementation to cache protocol
 * objects in to groups, like XmlSpecClass caching it's.
 * XmlSpecMethods  Current impl probably isn't very efficient
 */
namespace amqphp\protocol\abstrakt;

// PER-NS [one]
abstract class MethodFactory
{
    protected static $Cache;// Map: array(array(<xml-method-index>, <xml-method-name>, <fully-qualified XmlSpecMethod impl. class name>)+)

    private static function Lookup ($mName, $asName = true) {
        $j = ($asName) ? 1 : 0;
        foreach (static::$Cache as $i => $f) {
            if ($f[$j] === $mName) {
                return $i;
            }
        }
        return false;
    }

    final static function GetMethodByName ($mName) {
        if (false !== ($i = static::Lookup($mName))) {
            return (is_string(static::$Cache[$i][2])) ?
                (static::$Cache[$i][2] = new static::$Cache[$i][2])
                : static::$Cache[$i][2];
        }
    }
    final static function GetMethodsByName (array $restrict = array()) {
        $m = array();
        foreach (static::$Cache as $c) {
            if (! $restrict || in_array($c[1], $restrict)) {
                $m[] = static::GetMethodByName($c[1]);
            }
        }
        return $m;
    }

    final static function GetMethodByIndex ($idx) {
        if (false !== ($i = static::Lookup($idx, false))) {
            return (is_string(static::$Cache[$i][2])) ?
                (static::$Cache[$i][2] = new static::$Cache[$i][2])
                : static::$Cache[$i][2];
        }
    }
    final static function GetMethodsByIndex (array $restrict = array()) {
        $m = array();
        foreach (static::$Cache as $c) {
            if (! $restrict || in_array($c[0], $restrict)) {
                $m[] = static::GetMethodByIndex($c[0]);
            }
        }
        return $m;
    }
}
