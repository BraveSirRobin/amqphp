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
 * This strategy can be used to set a timeout for the event loop.
 */
class TimeoutExitStrategy implements ExitStrategy
{
    /** Config param, one of STRAT_TIMEOUT_ABS or STRAT_TIMEOUT_REL */
    private $toStyle;

    /** Config param */
    private $secs;

    /** Config / Runtime param */
    private $usecs;

    /** Runtime param */
    private $epoch;

    /**
     * @param   integer     $sMode      The select mode const that was passed to pushExitStrategy
     * @param   string      $secs       The configured seconds timeout value
     * @param   string      $usecs      the configured millisecond timeout value (1 millionth of a second)
     */
    function configure ($sMode, $secs=null, $usecs=null) {
        $this->toStyle = $sMode;
        $this->secs = (string) $secs;
        $this->usecs = (string) $usecs;
        return true;
    }

    function init (Connection $conn) {
        if ($this->toStyle == STRAT_TIMEOUT_REL) {
            list($uSecs, $epoch) = explode(' ', microtime());
            $uSecs = bcmul($uSecs, '1000000');
            $this->usecs = bcadd($this->usecs, $uSecs);
            $this->epoch = bcadd($this->secs, $epoch);
            if (! (bccomp($this->usecs, '1000000') < 0)) {
                $this->epoch = bcadd('1', $this->epoch);
                $this->usecs = bcsub($this->usecs, '1000000');
            }
        } else {
            $this->epoch = $this->secs;
        }
    }

    /** Return a timeout spec */
    function preSelect ($prev=null) {
        if ($prev === false) {
            // Don't override previous handlers if they want to exit.
            return false;
        }

        list($uSecs, $epoch) = explode(' ', microtime());
        $epDiff = bccomp($epoch, $this->epoch);
        if ($epDiff == 1) {
            //$epoch is bigger
            return false;
        }
        $uSecs = bcmul($uSecs, '1000000');
        if ($epDiff == 0 && bccomp($uSecs, $this->usecs) >= 0) {
            // $usecs is bigger
            return false;
        }

        // Calculate select blockout values that expire at the same as the target exit time
        $udiff = bcsub($this->usecs, $uSecs);
        if (substr($udiff, 0, 1) == '-') {
            $blockTmSecs = (int) bcsub($this->epoch, $epoch) - 1;
            $udiff = bcadd($udiff, '1000000');
        } else {
            $blockTmSecs = (int) bcsub($this->epoch, $epoch);
        }

        // Return the nearest timeout.
        if (is_array($prev) && ($prev[0] < $blockTmSecs || ($prev[0] == $blockTmSecs && $prev[1] < $udiff))) {
            return $prev;
        } else {
            return array($blockTmSecs, $udiff);
        }
    }

    function complete () {}
}
