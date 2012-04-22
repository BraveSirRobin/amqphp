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

// GLOBAL [one]
abstract class ClassFactory
{
    protected static $Cache; // Format: array(array(<class-idx>, <class-name>, <fully-qualified XmlSpecMethod impl. class name>))+

    final static function GetClassByIndex ($index) {
        foreach (static::$Cache as $cNum => $c) {
            if ($c[0] === $index) {
                return is_string($c[2]) ?
                    (static::$Cache[$cNum][2] = new $c[2])
                    : $c[2];
            }
        }
    }
    final static function GetClassByName ($name) {
        foreach (static::$Cache as $cNum => $c) {
            if ($c[1] === $name) {
                return is_string($c[2]) ?
                    (static::$Cache[$cNum][2] = new $c[2])
                    : $c[2];
            }
        }
    }
    final static function GetMethod ($c, $m) {
        $c = is_int($c) || is_numeric($c) ? self::GetClassByIndex($c) : self::GetClassByName($c);
        if ($c) {
            $m = is_int($m) || is_numeric($m) ? $c->getMethodByIndex($m) : $c->getMethodByName($m);
            if ($m) {
                return $m;
            }
        }
    }
}
