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

    private $tempDir;

    function start () {
        printf("ForkerConsumer %d [PID=%d]\n", $this->n, posix_getpid());
        $this->initSaveDir();
        $this->initConnection();
        $this->prepareChannels();

        if (! pcntl_signal(SIGINT, array($this, 'sigHand'))) {
            echo "Failed to install SIGINT in consumer {$this->n}\n";
        }
        if (! pcntl_signal(SIGTERM, array($this, 'sigHand'))) {
            echo "Failed to install SIGTERM in consumer {$this->n}\n";
        }
        $this->conn->select();
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


    function initSaveDir () {
        if (RUN_TEMP_DIR) {
            $this->tempDir = tempnam(RUN_TEMP_DIR, 'consumer-' . posix_getpid());
            unlink($this->tempDir);
            if (! mkdir($this->tempDir)) {
                throw new \Exception("Failed to create tempdir for consumer", 8965);
            }
        }
    }

    private function prepareChannels () {
        for ($i = 0; $i < $this->fParams['consumerChannels']; $i++) {
            // QOS: set prefetch count to 5.
            $chan = $this->prepareChannel();
            $qOk = $chan->invoke($chan->basic('qos', array('prefetch-count' => (int) $this->fParams['consumerPrefetch'],
                                                           'global' => false)));

            $cons = array('queue' => $this->fParams['queueName'],
                          'no-local' => true,
                          'no-ack' => false,
                          'exclusive' => false,
                          'no-wait' => false);
            for ($j = 0; $j < $this->fParams['consumersPerChannel']; $j++) {
                $consumer = new TraceConsumer($cons, ($i * $this->fParams['consumersPerChannel']) + $j);
                $consumer->tempDir = $this->tempDir;
                $chan->addConsumer($consumer);
            }
        }
    }
}



class TraceConsumer extends amqp\SimpleConsumer
{
    private $i;
    private $n = 0;
    public $tempDir;
    function __construct (array $consume = null, $i) {
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
            printf("Payload is:\n%s\n", $pl);
            //throw new \Exception("Goto error handler ;-)");
        } else {//if (($this->n % 200) == 0) {
            echo '.';
            $this->n = 0;
        }
        if (RUN_TEMP_DIR) {
            $outFile = "{$this->tempDir}/${md5}.out";
            if (is_file($outFile)) {
                // Check if it's the same message
                $tmpFile = tempnam(RUN_TEMP_DIR,  'file-clash-check-');
                file_put_contents($tmpFile, $pl);
                if (exec("diff {$outFile} {$tmpFile}")) {
                    printf("Message delivered twice (content is different): md5: %s, clash file: %s\n",
                           $md5, $tmpFile);
                } else {
                    printf("Message delivered twice (content is identical): md5: %s\n", $md5);
                }
            } else {
                file_put_contents($outFile, $pl);
            }
        }
        return amqp\CONSUMER_ACK;
    }

}