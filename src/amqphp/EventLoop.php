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

    private $forceExit = false;

    function addConnection (Connection $conn) {
        $this->cons[$conn->getSocketId()] = $conn;
    }

    function removeConnection (Connection $conn) {
        if (array_key_exists($conn->getSocketId(), $this->cons)) {
            unset($this->cons[$conn->getSocketId()]);
        }
    }

    /** Flips  an   internal  flag  that  forces  the   loop  to  exit
     * immediately the next time round. */
    function forceLoopExit () {
        $this->forceExit = true;
    }

    /**
     * Go in  to a listen  loop until no  more of the  currently added
     * connections is listening.
     */
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
        $started = false;
        while (true) {
            // Deliver all buffered messages and collect pre-select signals.
            $tv = array();
            foreach ($this->cons as $cid => $c) {
                $c->deliverAll();
                $tv[] = array($cid, $c->notifyPreSelect());
            }

            $psr = $this->processPreSelects($tv); // Connections could be removed here.

            if (is_array($psr)) {
                list($tvSecs, $tvUsecs) = $psr;
            } else if ($psr === true) {
                $tvSecs = null;
                $tvUsecs = 0;
            } else if (is_null($psr) && empty($this->cons)) {
                // All connections have finished listening.
                if (! $started) {
                    trigger_error("Select loop not entered - no connections are listening", E_USER_WARNING);
                }
                break;
            } else {
                throw new \Exception("Unexpected PSR response", 2758);
            }

            $this->signal();

            // If the force exit flag is set, exit now - place this after the call to signal
            if ($this->forceExit) {
                trigger_error("Select loop forced exit over-rides connection looping state", E_USER_WARNING);
                $this->forceExit = false;
                break;
            }

            $started = true;
            if (is_null($tvSecs)) {
                list($ret, $read, $ex) = call_user_func(array($sockImpl, 'Zelekt'),
                                                        array_keys($this->cons), null, 0);
            } else {
                list($ret, $read, $ex) = call_user_func(array($sockImpl, 'Zelekt'),
                                                        array_keys($this->cons), $tvSecs, $tvUsecs);
            }

            if ($ret === false) {
                $this->signal();
                $errNo = $errStr = array('(No specific socket exceptions found)');
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
                    try {
                        $c->doSelectRead();
                        $c->deliverAll();
                    } catch (\Exception $e) {
                        if ($sock->lastError()) {
                            trigger_error("Exception raised on socket {$sock->getId()} during " .
                                "event loop read: {$e->getMessage()}.  Socket indicates an error, " .
                                          "close the connection immediately", E_USER_WARNING);
                            try {
                                $c->shutdown();
                            } catch (\Exception $e) {
                                trigger_error("Nested exception swallowed during emergency socket " .
                                              "shutdown: {$e->getMessage()}", E_USER_WARNING);
                            }
                            $this->removeConnection($c);
                        } else {
                            trigger_error("Exception raised on socket {$sock->getId()} during " .
                                          "event loop read: {$e->getMessage()}. Socket does NOT " .
                                          "indicate an error, try again", E_USER_WARNING);

                        }
                    }
                }
            }
        } // End - the loop

        // Notify all existing connections that the loop has ended.
        foreach ($this->cons as $id => $conn) {
            $conn->notifyComplete();
            $conn->setBlocking(false);
            $this->removeConnection($conn);
        }
    }

    /**
     * Process  preSelect  responses,   remove  connections  that  are
     * complete  and  filter  out  the "soonest"  timeout.   Call  the
     * 'complete' callback for connections that get removed
     *
     * @return  mixed   True=Loop without timeout, False=exit loop, array(int, int)=specific timeout
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
            } else if ($tv === true && ! is_array($wins)) {
                $wins = true;
            } else if (is_array($tv)) {
                if ($wins === true) {
                    $wins = $tv;
                } else {
                    // Figure out which timeout is sooner and choose that one
                    switch (bccomp((string) $wins[0], (string) $tv[0])) {
                    case 0:
                        // Seconds are the same, compare millis
                        if (1 === bccomp((string) $wins[1], (string) $tv[1])) {
                            $wins = $tv;
                        }
                        break;
                    case 1;
                        // $wins second timeout is bigger
                        $wins = $tv;
                        break;
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

