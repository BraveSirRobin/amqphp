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
use amqp_091 as amqp;
use amqp_091\protocol;
use amqp_091\wire;


class ForkerConsumer extends Forker
{

    function start () {
        printf("ForkerConsumer %d [PID=%d]\n", $this->n, posix_getpid());
        $this->initConnection();
        $this->prepareChannels();

        if (! pcntl_signal(SIGINT, array($this, 'sigHand'))) {
            echo "Failed to install SIGINT in consumer {$this->n}\n";
        }
        if (! pcntl_signal(SIGTERM, array($this, 'sigHand'))) {
            echo "Failed to install SIGTERM in consumer {$this->n}\n";
        }
        $this->conn->startConsuming();
        if (! $this->sigHandled) {
            $this->shutdownConnection();
        }
        printf("ForkerConsumer %d [PID=%d] exits\n", $this->n, posix_getpid());
    }

    private $sigHandled = false;
    function sigHand () {
        if (! $this->sigHandled) {
            echo " --Signal handler for consumer {$this->n}\n";
            $this->sigHandled = true;
            $this->shutdownConnection();
        }
    }

    private function prepareChannels () {
        for ($i = 0; $i < $this->fParams['consumerChannels']; $i++) {
            // QOS: set prefetch count to 5.
            $chan = $this->prepareChannel();
            $qOk = $chan->invoke($chan->basic('qos', array('prefetch-count' => (int) $this->fParams['consumerPrefetch'],
                                                           'global' => false)));

            $cons = $chan->basic('consume', array('queue' => $this->fParams['queueName'],
                                                  'no-local' => true,
                                                  'no-ack' => false,
                                                  'exclusive' => false,
                                                  'no-wait' => false));
            for ($j = 0; $j < $this->fParams['consumersPerChannel']; $j++) {
                $chan->addConsumer(new TraceConsumer($cons, ($i * $this->fParams['consumersPerChannel']) + $j));
            }
        }
    }
}



class TraceConsumer extends amqp\SimpleConsumer
{
    private $i;
    private $n = 0;
    function __construct (wire\Method $consume = null, $i) {
        parent::__construct($consume);
        $this->i = $i;
    }

    function handleDelivery (wire\Method $meth, amqp\Channel $chan) {
        $pl = $meth->getContent();
        if (false !== ($p = strpos($pl, ' '))) {
            $md5 = substr($pl, 0, $p);
            $e = (md5(substr($pl, $p+1)) == $md5) ? 'K' : 'F';
        } else {
            $e = '#';
        }

        $this->n++;
        if ($e == 'F') {
            printf("\nChecksum failed (%s):\n\$this->i: %d\nOrig MD5: %s\nActual MD5: %s\nContent-Length: %s\n",
                   $e, $this->i, $md5, md5(substr($pl, $p+1)), strlen($pl));
            $meth->debugDumpReadingMethod();
            throw new \Exception("Goto error handler ;-)");
        } else if (($this->n % 10) == 0) {
            echo '.';
        }
        return $this->ack($meth);
    }

}