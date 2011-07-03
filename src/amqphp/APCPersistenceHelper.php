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

namespace amqphp;

/**
 * Class  to save  Connection /  Channel metadata  in to  an  APC data
 * store.
 */
class APCPersistenceHelper implements PersistenceHelper
{
    private $pid;

    private $data = array();

    function setProcessId ($pid) {
        $this->pid = $pid;
    }

    function setDataItem ($key, $value) {
        $this->data[$key] = $value;
    }

    function getDataItem ($key) {
        return array_key_exists($key, $this->data)
            ? $this->key[$data]
            : null;
    }

    function hasDataItem ($key) {
        return array_key_exists($key, $this->data);
    }

    function save () {
        if (is_null($this->pid)) {
            throw new \Exception("Cannot save persistent connection metadata - processID is not set.", 8265);
        }
        return apc_store($this->pid, $this->data);
    }

    function load () {
        if (is_null($this->pid)) {
            throw new \Exception("Cannot save persistent connection metadata - processID is not set.", 8265);
        }
        $success = false;
        $this->data = apc_fetch($this->pid, $success);
        return $success;
    }
}