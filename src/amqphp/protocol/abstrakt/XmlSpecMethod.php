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
