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
 * This exit strategy counts the  number of times the underlying event
 * loop breaks and forces an exit  after a specified number of breaks.
 * NOTE: this  does _NOT_ mean the  same as a "max  number of messages
 * received"  - a  single  break of  an event  loop  can deliver  many
 * messages, or indeed deliver no message, for example if a very large
 * message is being received in many parts.
 */
class MaxloopExitStrategy implements ExitStrategy
{
    /** Config param - max loops value */
    private $maxLoops;

    /** Runtime param */
    private $nLoops;

    function configure ($sMode, $ml=null) {
        if (! (is_int($ml) || is_numeric($ml)) || $ml == 0) {
            trigger_error("Select mode - invalid maxloops params : '$ml'", E_USER_WARNING);
            return false;
        } else {
            $this->maxLoops = (int) $ml;
            return true;
        }
    }

    function init (Connection $conn) {
        $this->nLoops = 0;
    }

    function preSelect ($prev=null) {
        if ($prev === false) {
            return false;
        }
        if (++$this->nLoops > $this->maxLoops) {
            return false;
        } else {
            return $prev;
        }
    }

    function complete () {}
}
