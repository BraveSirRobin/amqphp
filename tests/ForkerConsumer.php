<?php

use amqp_091 as amqp;
use amqp_091\protocol;
use amqp_091\wire;


class ForkerConsumer extends Forker
{

    function start () {
        $sleep = rand(2,5);
        echo "ForkerConsumer {$this->n} starts with sleep $sleep\n";
        $this->initConnection();
        $this->prepareChannels();

        if (! pcntl_signal(SIGINT, array($this, 'sigHand'))) {
            echo "Failed to install SIGINT in consumer {$this->n}\n";
        }
        if (! pcntl_signal(SIGTERM, array($this, 'sigHand'))) {
            echo "Failed to install SIGTERM in consumer {$this->n}\n";
        }
        //sleep($sleep);
        $this->conn->startConsuming();
        $this->shutdownConnection();
        echo "ForkerConsumer {$this->n} exits\n";
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
            $chan->addConsumer(new TraceConsumer($cons, $i));
        }
    }
}



class TraceConsumer extends amqp\SimpleConsumer
{
    private $i;
    function __construct (wire\Method $consume = null, $i) {
        parent::__construct($consume);
        $this->i = $i;
    }

    function handleDelivery (wire\Method $meth, amqp\Channel $chan) {
        echo "[d{$this->i}]";
        return $this->ack($meth);
    }

}