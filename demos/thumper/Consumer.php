<?php

use amqphp as amqp,
    amqphp\wire;

require_once __DIR__ . '/../demo-loader.php';

class Consumer implements amqp\Consumer
{
    public $queueName;
    public $n = 0;
    public $cb;

    private $connection;
    private $channel;


    /** Create connection and config from xml config */
    function __construct ($config) {
        $f = new amqp\Factory($config);
        $built = $f->getConnections();
        $this->connection = reset($built);
        $chans = $this->connection->getChannels();
        $this->channel = reset($chans);
        $this->channel->addConsumer($this);

        if (defined('BROKER_CONFIG')) {
            $f = new amqp\Factory(BROKER_CONFIG);
            $meths = $f->run($this->channel);
            foreach ($meths as $m) {
                if ($m instanceof wire\Method && $m->amqpClass == 'queue.declare-ok') {
                    $this->queueName = $m->getField('queue');
                }
            }
            printf("Ran %d brokers methods, got queue %s\n", count($meths), $this->queueName);
        }
    }


    function setCallback ($cb) {
        $this->cb = $cb;
    }


    function consume ($n) {
        $this->n = $n;

        $this->connection->setSelectMode(amqp\SELECT_CALLBACK, array($this, 'loopCallbackHandler'));
        $this->connection->select();
    }


    /** @override \amqphp\Consumer */
    function handleCancelOk (wire\Method $m, amqp\Channel $chan) { }

    /** @override \amqphp\Consumer */
    function handleConsumeOk (wire\Method $m, amqp\Channel $chan) { }

    /** @override \amqphp\Consumer */
    function handleDelivery (wire\Method $m, amqp\Channel $chan) {
        $this->n--;
        try {
            call_user_func($this->cb, $m->getContent());
        } catch (\Exception $e) {
            printf("Your handler failed:\n%s\n", $e->getMessage());
        }
    }

    /** @override \amqphp\Consumer */
    function handleRecoveryOk (wire\Method $m, amqp\Channel $chan) { }

    /** @override \amqphp\Consumer */
    function getConsumeMethod (amqp\Channel $chan) {
        $cps = array('queue' => $this->queueName,
                     'consumer-tag' => $this->queueName,
                     'no-local' => false,
                     'no-ack' => true,
                     'exclusive' => false,
                     'no-wait' => false);
        return $chan->basic('consume', $cps);
    }

    /** Event loop callback, used to trigger event loop exit */
    public function loopCallbackHandler () {
        return ($this->n > 0);
    }
}