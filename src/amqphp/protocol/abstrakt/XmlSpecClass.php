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
