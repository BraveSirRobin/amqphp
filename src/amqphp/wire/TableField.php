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




class TableField extends Protocol
{
    protected $val; // PHP native / Impl mixed
    protected $type;  // Amqp type

    /** Implement type guessing, i.e. PHP -> Amqp table mapping */
    function __construct($val, $type=false) {
        $this->val = $val;
        $this->type = ($type === false) ? $this->getTableTypeForValue($val) : $type;
    }
    function getValue() { return $this->val; }
    function setValue($val) { $this->val = $val; }
    function getType() { return $this->type; }
    function __toString() { return (string) $this->val; }
}
