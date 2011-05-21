<?php
/**
 * 
 * Copyright (C) 2010, 2011  Robin Harvey (harvey.robin@gmail.com)
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



// Static factory
// GLOBAL [one]
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

// GLOBAL [many]
abstract class XmlSpecDomain
{
    protected $name;
    protected $protocolType;

    final function getSpecDomainName () {
        return $this->name;
    }
    final function getSpecDomainType () {
        return $this->protocolType;
    }
    function validate($subject) { return true; }
}

// PER-NS [many]
interface XmlSpecField
{
    function getSpecFieldName ();
    function getSpecFieldDomain ();
}



// PER-NS [one]
abstract class XmlSpecClass
{
    protected $name;
    protected $index;
    protected $fields; // Format: array(<amqp field name>)
    protected $methods; // Format: array(<amqp method name>)
    protected $methFact;
    protected $fieldFact;

    final function getSpecName () {
        return $this->name;
    }
    final function getSpecIndex () {
        return $this->index;
    }
    final function getSpecFields () {
        return $this->fields;
    }
    final function getSpecMethods () {
        return $this->methods;
    }
    final function getMethods () {
        return call_user_func(array($this->methFact, 'GetMethodsByName'), $this->methods);
    }
    final function getMethodByName ($mName) {
        if (in_array($mName, $this->methods)) {
            return call_user_func(array($this->methFact, 'GetMethodByName'), $mName);
        }
    }
    final function getMethodByIndex ($idx) {
        if (in_array($idx, array_keys($this->methods))) {
            return call_user_func(array($this->methFact, 'GetMethodByIndex'), $idx);
        }
    }
    final function getFields () {
        return call_user_func(array($this->fieldFact, 'GetClassFields'), $this->name);
    }
    final function getFieldByName ($fName) {
        if (in_array($fName, $this->fields)) {
            return call_user_func(array($this->fieldFact, 'GetField'), $fName);
        }
    }
}


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



// PER-NS [many]
abstract class XmlSpecMethod
{
    protected $class;
    protected $name;
    protected $index;
    protected $synchronous;
    protected $responseMethods;
    protected $fields;
    protected $methFact;
    protected $fieldFact;
    protected $classFact;
    protected $content;

    final function getSpecClass () {
        return $this->class;
    }
    final function getSpecName () {
        return $this->name;
    }
    final function getSpecIndex () {
        return $this->index;
    }
    final function getSpecIsSynchronous () {
        return $this->synchronous;
    }
    final function getSpecResponseMethods () {
        return $this->responseMethods;
    }
    final function getSpecFields () {
        return $this->fields;
    }
    final function getSpecHasContent () {
        return $this->content;
    }
    final function getFields () {
        return call_user_func(array($this->fieldFact, 'GetFieldsForMethod'), $this->name);
    }
    final function getField ($fName) {
        if (in_array($fName, $this->fields)) {
            return call_user_func(array($this->fieldFact, 'GetField'), $fName, $this->name);
        }
    }
    final function getResponses () {
        return call_user_func(array($this->methFact, 'GetMethodsByName'), $this->responseMethods);
    }
    final function getClass () {
        return call_user_func(array($this->classFact, 'GetClassByName'), $this->class);
    }
    // Cheat to help out with the pesky no-wait domain
    final function hasNoWaitField () {
        return $this->hasNoWait;
    }
}

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

