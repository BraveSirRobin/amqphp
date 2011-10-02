<?php
/**
 * Equivalent to Vidal Alvaro Videla's RpcClient demo
 */

use amqphp as amqp,
    amqphp\wire;

require_once __DIR__ . '/../demo-loader.php';

class RpcServer implements amqp\Consumer, amqp\ChannelEventHandler
{

    private $setupQs = array();

    public $queue;

    public $callback;

    private $connection;

    private $channel;

    /** Creates connection, channel, configures broker  */
    public function __construct ($config) {
        /* Call  run()  on  the  factory,  this  will  pass  back  all
           responses from setup methods as well as the connection */
        $f = new amqp\Factory($config);
        foreach ($f->run() as $resp) {
            if ($resp instanceof amqp\Connection) {
                $this->connection = $resp;
                $chans = $this->connection->getChannels();
                $this->channel = reset($chans);
            } else if (is_array($resp)) {
                foreach ($resp as $rmeth) {
                    if ($rmeth instanceof wire\Method &&
                        $rmeth->getClassProto()->getSpecName() == 'queue' &&
                        $rmeth->getMethodProto()->getSpecName() == 'declare-ok') {
                        $this->setupQs[] = $rmeth->getField('queue');
                    }
                }
            }
        }
        printf(" (RpcServer Setup): (conn, chan, queue(,s)) = (%d, %d, %s)\n",
               is_null($this->connection), is_null($this->channel), implode(', ', $this->setupQs));
    }

    /** Sets the queue to listen on */
    public function initServer($queue) {
        if (! in_array($queue, $this->setupQs)) {
            trigger_error(" (RpcServer) - listen Q $queue was not created by config!", E_USER_WARNING);
        }
        $this->queue = $queue;
    }


    public function setCallback ($cb) {
        $this->callback = $cb;
    }

    public function start () {
        $this->channel->addConsumer($this);
        $this->connection->select();
    }


    /** @override \amqphp\Consumer */
    function handleCancelOk (amqp\wire\Method $m, amqp\Channel $chan) { }

    /** @override \amqphp\Consumer */
    function handleConsumeOk (amqp\wire\Method $m, amqp\Channel $chan) { }

    /** @override \amqphp\Consumer */
    function handleDelivery (amqp\wire\Method $m, amqp\Channel $chan) {
        printf(" (RpcServer: Message received)\n");
        // Over-ride the default behaviour by manually sending the ack.
        $ack = $chan->basic('ack', array('delivery-tag' => $m->getField('delivery-tag')));
        $chan->invoke($ack);

        // Invoke the callback
        $result = call_user_func($this->callback, $m->getContent());

        // Publish the response message
        $bpa = array('content-type' => 'text/plain',
                     'correlation-id' => $m->getField('correlation-id'),
                     'routing-key' => $m->getClassField('reply-to'),
                     'mandatory' => true,
                     'immediate' => true);
        $bp = $chan->basic('publish', $bpa, $result);
        printf("(RpcServer) : Send response messages:\n%s\n", $result);
        var_dump(array_merge($bp->getFields(), $bp->getClassFields()));
        $chan->invoke($bp);
    }

    /** @override \amqphp\Consumer */
    function handleRecoveryOk (amqp\wire\Method $m, amqp\Channel $chan) { }

    /** @override \amqphp\Consumer */
    function getConsumeMethod (amqp\Channel $chan) {
        $cps = array('queue' => $this->queue,
                     'consumer-tag' => 'PHPPROCESS_' . getmypid());
        printf("(RpcServer): returns consume method for %s\n", $this->queue);
        return $chan->basic('consume', $cps);
    }


    /** @override \amqphp\ChannelEventHandler */
    public function publishConfirm (wire\Method $m) {
    }

    /** @override \amqphp\ChannelEventHandler */
    public function publishReturn (wire\Method $m) {
        printf("(RpcServer) - Your message was rejected!:\n");
        var_dump($m->getFields());
    }

    /** @override \amqphp\ChannelEventHandler */
    public function publishNack (wire\Method $m) {
    }
}