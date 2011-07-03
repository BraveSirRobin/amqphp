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
    private $data;

    function getData () {
        return $this->data;
    }

    function setData ($data) {
        $this->data = $data;
    }

    private function getKey () {
        return 'apc.amqphp.' . getmypid();
    }

    function save () {
        $k = $this->getKey();
        printf("<pre>APC save with key %s\n%s\n</pre>", $k, print_r($this->data, true));
        return apc_store($k, $this->data);
    }

    function load () {
        $success = false;
        $this->data = apc_fetch($this->getKey(), $success);
        return $success;
    }

    function destroy () {
        return apc_delete(getmypid());
    }
}