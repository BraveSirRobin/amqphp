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
abstract class FieldFactory
{
    protected static $Cache; // Map: array(array(<fname>, <meth>, <Fully Qualified XmlSpecField impl. class name>)+)


    private static function Lookup ($fName, $mName = '') {
        foreach (static::$Cache as $i => $f) {
            if ($f[0] === $fName && $f[1] === $mName) {
                return $i;
            }
        }
        return false;
    }
    final static function IsField ($fName, $mName = '') {
        return (static::Lookup($fName, $mName) !== false);
    }
    final static function GetField ($fName, $mName = '') {
        if (false !== ($f = static::Lookup($fName, $mName))) {
            return is_string(static::$Cache[$f][2]) ?
                (static::$Cache[$f][2] = new static::$Cache[$f][2])
                : static::$Cache[$f][2];
        }
    }
    final static function GetClassFields () {
        // Return all fields that are declared at the class level
        $r = array();
        foreach (static::$Cache as $f) {
            if ($f[1] === '') {
                $r[] = static::GetField($f[0]);
            }
        }
        return $r;
    }
    final static function GetFieldsForMethod ($mName) {
        $r = array();
        foreach (static::$Cache as $f) {
            if ($f[1] === $mName) {
                $r[] = static::GetField($f[0], $mName);
            }
        }
        return $r;
        //return array_merge(static::GetClassFields(), $r);
    }
    final static function Validate ($val, $fName, $mName = '') {
        return static::GetField($fName, $mName)->validate($val);
    }

}
