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


class ForkerProducer extends Forker
{

    const WAFFLE_SIZE = 4096;

    protected $basicPub;
    protected $chan;
    protected $sigHandled = false;

    protected $smallMsgMin;
    protected $smallMsgMax;
    protected $largeMsgMin;
    protected $largeMsgMax;

    protected $waffle;

    protected $prodNumLoops;
    protected $prodSleepMillis;

    function start () {
        printf("ForkerProducer %d [PID=%d]\n", $this->n, posix_getpid());
        if (! pcntl_signal(SIGINT, array($this, 'sigHand'))) {
            echo "Failed to install SIGINT in producer {$this->n}\n";
        }
        if (! pcntl_signal(SIGTERM, array($this, 'sigHand'))) {
            echo "Failed to install SIGTERM in producer {$this->n}\n";
        }
        $this->initConnection();
        $this->prepareAndRun();
        if ( ! $this->sigHandled) {
            $this->shutdownConnection();
        }
        printf("ForkerConsumer %d [PID=%d] exits\n", $this->n, posix_getpid());
    }

    /**
     * Main entry point.
     */
    function prepareAndRun () {
        // Prepare the large and small messages
        $this->chan = $this->prepareChannel();
        $this->basicPub = $this->chan->basic('publish', array('content-type' => 'text/plain',
                                                              'content-encoding' => 'UTF-8',
                                                              'routing-key' => '',
                                                              'mandatory' => false,
                                                              'immediate' => false,
                                                              'exchange' => $this->fParams['exchange']));
        $this->smallMsgMin = $this->fParams['smallMsgMin'];
        $this->smallMsgMax = $this->fParams['smallMsgMax'];
        $this->largeMsgMin = $this->fParams['largeMsgMin'];
        $this->largeMsgMax = $this->fParams['largeMsgMax'];
        $this->prodNumLoops = $this->fParams['prodNumLoops'];
        $this->prodSleepMillis = $this->fParams['prodSleepMillis'];

        $perc = $this->fParams['prodSmallMsgPercent'];
        $i = 0;
        while (true) {
            if ($this->prodNumLoops && ($i++ > $this->prodNumLoops)) {
                break;
            }
            if (rand(0, 99) > $perc) {
                $this->sendLargeMessage();
            } else {
                $this->sendSmallMessage();
            }
            // Always poll after sending in case there's an exception message waiting to be process
            $this->pollQueue();
            if ($this->prodSleepMillis) {
                usleep($this->prodSleepMillis);
            }
            pcntl_signal_dispatch();
            if ($this->sigHandled) {
                return;
            }
        }
        printf("Test producer {$this->n} completed producing:\n");
    }



    function sigHand () {
        if (! $this->sigHandled) {
            echo " --Signal handler for producer {$this->n}\n";
            $this->sigHandled = true;
            $this->shutdownConnection();
        }
    }


    function returnCrashPayload () {
        return file_get_contents('/tmp/amqp-meth-debug.ir3rMv');
    }



    function sendLargeMessage () {
        $buff = $this->getNBytesOfWaffle(rand($this->largeMsgMin, $this->largeMsgMax));
        $buff = md5($buff) . ' ' . $buff;
        //echo "\{LGE: $buff\}";
        $this->basicPub->setContent($buff);
        //$this->basicPub->setContent($this->returnCrashPayload());
        $this->chan->invoke($this->basicPub);
    }

    function sendSmallMessage() {
        $buff = $this->getNBytesOfWaffle(rand($this->smallMsgMin, $this->smallMsgMax));
        $buff = md5($buff) . ' ' . $buff;
        //echo "\{SML: $buff\}";
        $this->basicPub->setContent($buff);
        //$this->basicPub->setContent($this->returnCrashPayload());
        $this->chan->invoke($this->basicPub);
    }

    function pollQueue () {
        $qDecl = $this->chan->queue('declare', array('queue' => $this->fParams['queueName']));
        $declOk = $this->chan->invoke($qDecl);
        return $declOk->getField('message-count');
    }

    /** Ronseal! */
    function getNBytesOfWaffle ($n) {
        $this->acquireWaffle();
        if ($n <= self::WAFFLE_SIZE) {
            $s = rand(0, self::WAFFLE_SIZE - $n);
            return substr($this->waffle, $s, $n);
        } else {
            // Build the response out of 2K chunks
            $rem = $n % 2048;
            $nCalls = ($n - $rem) / 2048;
            $buff = '';
            for ($i = 0; $i < $nCalls; $i++) {
                $buff .= $this->getNBytesOfWaffle(2048);
            }
            return $buff . $this->getNBytesOfWaffle($rem);
        }
    }

    protected function acquireWaffle () {
        if (! $this->waffle) {
            if (! ($fp = fopen(__DIR__ . '/data/large-file.txt', 'r'))) {
                throw new Exception("Failed to open large file to acquire waffle", 9654);
            }
            $p = rand(0, filesize(__DIR__ . '/data/large-file.txt') - self::WAFFLE_SIZE);
            if (0 === fseek($fp, $p, SEEK_CUR)) {
                $this->waffle = fread($fp, self::WAFFLE_SIZE);
                if (strlen($this->waffle) != self::WAFFLE_SIZE) {
                    throw new Exception("Failed to acquire sufficient waffle - only " . strlen($this->waffle) . " bytes found", 9875);
                }
            } else {
                var_dump($tmp);
                throw new Exception("Failed to acquire sufficient waffle (3)", 9876);
            }
            fclose($fp);
        }
    }
}