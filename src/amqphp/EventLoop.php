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


/**
 * Use the  low level Zelect method  to allow consumers  to connect to
 * more than one exchange.
 */
class EventLoop
{
    private $cons = array();
    private static $In = false;

    function addConnection (Connection $conn) {
        $this->cons[$conn->getSocketId()] = $conn;
    }

    function removeConnection (Connection $conn) {
        if (array_key_exists($conn->getSocketId(), $this->cons)) {
            unset($this->cons[$conn->getSocketId()]);
        }
    }

    function select () {
        $sockImpl = false;
        foreach ($this->cons as $c) {
            if ($c->isBlocking()) {
                throw new \Exception("Event loop cannot start - connection is already blocking", 3267);
            }
            if ($sockImpl === false) {
                $sockImpl = $c->getSocketImplClass();
            } else if ($sockImpl != $c->getSocketImplClass()) {
                throw new \Exception("Event loop doesn't support mixed socket implementations", 2678);
            }
            if (! $c->isConnected()) {
                throw new \Exception("Connection is not connected", 2174);
            }
        }

        // Notify that the loop begins
        foreach ($this->cons as $c) {
            $c->setBlocking(true);
            $c->notifySelectInit();
        }

        // The loop
        while (true) {
            $tv = array();
            foreach ($this->cons as $cid => $c) {
                $c->deliverAll();
                $tv[] = array($cid, $c->notifyPreSelect());
            }
            $psr = $this->processPreSelects($tv); // Connections could be removed here.
            if (is_array($psr)) {
                list($tvSecs, $tvUsecs) = $psr;
            } else if (is_null($psr) && empty($this->cons)) {
                // All connections have finished litening.
                return;
            } else {
                throw new \Exception("Unexpected PSR response", 2758);
            }

            $this->signal();

            if (is_null($tvSecs)) {
                list($ret, $read, $ex) = call_user_func(array($sockImpl, 'Zelekt'),
                                                        array_keys($this->cons), null, 0);
            } else {
                list($ret, $read, $ex) = call_user_func(array($sockImpl, 'Zelekt'),
                                                        array_keys($this->cons), $tvSecs, $tvUsecs);
            }
            if ($ret === false) {
                $this->signal();
                $errNo = $errStr = array('??');
                if ($ex) {
                    $errNo = $errStr = array();
                    foreach ($ex as $sock) {
                        $errNo[] = $sock->lastError();
                        $errStr[] = $sock->strError();
                    }
                }
                $eMsg = sprintf("[2] Read block select produced an error: [%s] (%s)",
                                implode(",", $errNo), implode("),(", $errStr));
                throw new \Exception ($eMsg, 9963);

            } else if ($ret > 0) {
                foreach ($read as $sock) {
                    $c = $this->cons[$sock->getId()];
                    $c->doSelectRead();
                    $c->deliverAll();
                }
                foreach ($ex as $sock) {
                    printf("--(Socket Exception (?))--\n");
                }
            }
        }
    }

    /**
     * Process  preSelect  responses,   remove  connections  that  are
     * complete  and  filter  out  the "soonest"  timeout.   Call  the
     * 'complete' callback for connections that get removed
     *
     * @return  mixed   Array = (tvSecs, tvUsecs), False = loop complete
     *                  (no more listening connections)
     */
    private function processPreSelects (array $tvs) {
        $wins = null;
        foreach ($tvs as $tv) {
            $sid = $tv[0]; // Socket id
            $tv = $tv[1]; // Return value from preSelect()
            if ($tv === false) {
                $this->cons[$sid]->notifyComplete();
                $this->cons[$sid]->setBlocking(false);
                $this->removeConnection($this->cons[$sid]);
            } else if (is_null($wins)) {
                $wins = $tv;
                $winSum = is_null($tv[0]) ? 0 : bcadd($tv[0], $tv[1], 5);
            } else if (! is_null($tv[0])) {
                // A Specific timeout
                if (is_null($wins[0])) {
                    $wins = $tv;
                } else {
                    // TODO: compact this logic - too many continues!
                    $diff = bccomp($wins[0], $tv[0]);
                    if ($diff == -1) {
                        // $wins second timeout is smaller
                        continue;
                    } else if ($diff == 0) {
                        // seconds are the same, compare millis
                        $diff = bccomp($wins[1], $tv[1]);
                        if ($diff == -1) {
                            continue;
                        } else if ($diff == 0) {
                            continue;
                        } else {
                            $wins = $tv;
                        }
                    } else {
                        // $wins second timeout is bigger
                        $wins = $tv;
                    }
                }
            }
        }
        return $wins;
    }

    private function signal () {
        foreach ($this->cons as $c) {
            if ($c->getSignalDispatch()) {
                pcntl_signal_dispatch();
                return;
            }
        }
    }
}

