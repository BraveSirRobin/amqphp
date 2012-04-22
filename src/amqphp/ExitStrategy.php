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


/**
 * @internal
 */
interface ExitStrategy
{
    /**
     * Called once when the  select mode is first configured, possibly
     * with other parameters
     */
    function configure ($sMode);

    /**
     * Called once  per select loop run, calculates  initial values of
     * select loop timeouts.
     */
    function init (Connection $conn);

    /**
     * Forms a "chain of responsibility" - each
     *
     * @return   mixed    True=Loop without timeout, False=exit loop, array(int, int)=specific timeout
     */
    function preSelect ($prev=null);

    /**
     * Notification that the loop has exited
     */
    function complete ();
}
