<?php
/**
 * Equivalent to Vidal Alvaro Videla's RpcClient demo
 */

use amqphp as amqp,
    amqphp\wire;

require_once __DIR__ . '/../demo-loader.php';

class RpcServer implements amqp\Consumer, amqp\ChannelEventHandler
{

    public $token;

    public $callback;

    private $connection;

    private $channel;

    /** Creates connection, channel, configures broker  */
    public function __construct ($config) {
        $f = new amqp\Factory($config);
        $built = $f->getConnections();
        $this->connection = reset($built);
        $chans = $this->connection->getChannels();
        $this->channel = reset($chans);
        $this->channel->setEventHandler($this);

        if (defined('BROKER_CONFIG')) {
            $f = new amqp\Factory(BROKER_CONFIG);
            $meths = $f->run($this->channel);
            printf("Ran %d brokers methods\n", count($meths));
        }
    }

    /** Sets the queue to listen on */
    public function initServer($token) {
        $this->token = $token;
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
                     'routing-key' => $m->getField('reply-to'),
                     'mandatory' => true,
                     'immediate' => true,
                     'correlation-id' => $this->token);
        $bp = $chan->basic('publish', $bpa, $result);
        printf("(RpcServer) : Send response messages:\n%s\n", $result);
        var_dump(array_filter($bp->getFields()));
        $chan->invoke($bp);
    }

    /** @override \amqphp\Consumer */
    function handleRecoveryOk (amqp\wire\Method $m, amqp\Channel $chan) { }

    /** @override \amqphp\Consumer */
    function getConsumeMethod (amqp\Channel $chan) {
        $queue = $this->token . '-queue';
        $cps = array('queue' => $queue,
                     'consumer-tag' => 'PHPPROCESS_' . getmypid());
        printf("(RpcServer): returns consume method for %s\n", $queue);
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