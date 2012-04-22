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
