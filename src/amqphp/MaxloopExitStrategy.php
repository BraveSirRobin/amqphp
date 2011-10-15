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

use amqphp\protocol;
use amqphp\wire;




class MaxloopExitStrategy implements ExitStrategy
{
    /** Config param - max loops value */
    private $maxLoops;

    /** Runtime param */
    private $nLoops;

    function configure ($sMode, $ml=null) {
        if (! is_int($ml) || $ml == 0) {
            trigger_error("Select mode - invalid maxloops params", E_USER_WARNING);
            return false;
        } else {
            $this->maxLoops = $ml;
            return true;
        }
    }

    function init (Connection $conn) {
        $this->nLoops = 0;
    }

    function preSelect ($prev=null) {
        if ($prev === false) {
            return $prev;
        }
        if (++$this->nLoops > $this->maxLoops) {
            return false;
        } else {
            return $prev;
        }
    }

    function complete () {}
}
