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

abstract class DomainFactory
{
    protected static $Cache; // Map: array(<xml-domain-name> => <local XmlSpecDomain impl. class name>)

    final static function IsDomain ($dName) {
        return isset(static::$Cache[$dName]);
    }
    final static function GetDomain ($dName) {
        if (isset(static::$Cache[$dName])) {
            return (is_string(static::$Cache[$dName])) ? (static::$Cache[$dName] = new static::$Cache[$dName]) : static::$Cache[$dName];
        }
    }
    final static function Validate ($val, $dName) {
        return static::GetDomain($dName)->validate($val);
    }
}
