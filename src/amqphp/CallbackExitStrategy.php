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

namespace amqphp;

use amqphp\protocol;
use amqphp\wire;





class CallbackExitStrategy implements ExitStrategy
{
    private $cb;
    private $args;

    function configure ($sMode, $cb=null, $args=null) {
        if (! is_callable($cb)) {
            trigger_error("Select mode - invalid callback params", E_USER_WARNING);
            return false;
        } else {
            $this->cb = $cb;
            $this->args = $args;
            return true;
        }
    }

    function init (Connection $conn) {}

    function preSelect ($prev=null) {
        if ($prev === false) {
            return false;
        }
        if (true !== call_user_func_array($this->cb, $this->args)) {
            return false;
        } else {
            return $prev;
        }
    }

    function complete () {}
}
