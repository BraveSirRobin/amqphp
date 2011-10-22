<?php
/**
 * Equivalent to Vidal Alvaro Videla's RpcClient demo
 */

use amqphp as amqp,
    amqphp\wire;

require_once __DIR__ . '/../demo-loader.php';


class RpcClient implements amqp\Consumer, amqp\ChannelEventHandler
{

    public $queueName;
    public $requests = 0;
    public $replies = array();

    private $connection;
    private $channel;


    /** Create connection and config from xml config */
    public function __construct ($config) {
        $f = new amqp\Factory($config);
        $built = $f->getConnections();
        $this->connection = reset($built);
        $chans = $this->connection->getChannels();
        $this->channel = reset($chans);
        $this->channel->setEventHandler($this);
    }

    /** Create broker-named response queue */
    public function initClient() {
        $qd = $this->channel->queue('declare', array('auto-delete' => true,
                                                     'exclusive' => true));
        $qdr = $this->channel->invoke($qd);
        $this->queueName = $qdr->getField('queue');

        // Manually start the consume session for the response queue.
        $this->channel->addConsumer($this);
        $this->channel->startAllConsumers();
    }

    /** Send RPC message and return immediately */
    public function addRequest($msgBody, $server, $requestId = null, $routingKey = '') {
        if(empty($requestId)) {
            throw new InvalidArgumentException('You must provide a requestId!', 2561);
        }

        $params = array('content-type' => 'text/plain',
                        'reply-to' => $this->queueName,
                        'correlation-id' => $requestId,
                        'exchange' => $server . '-exchange',
                        'routing-key' => $routingKey,
                        'mandatory' => true,
                        'immediate' => true);
        $bp = $this->channel->basic('publish', $params, $msgBody);
        $tmpRet = $this->channel->invoke($bp);
        $this->requests++;
    }

    /** Add the current object as  a consumer and enter a consume loop
     * to wait for RPC replies. */
    public function getReplies() {
        $evh = new amqp\EventLoop;
        $this->connection->pushExitStrategy(amqp\STRAT_CALLBACK, array($this, 'loopCallbackHandler'));
        $evh->addConnection($this->connection);
        $evh->select();
        $this->channel->removeAllConsumers();
    }


    /** @override \amqphp\Consumer */
    function handleCancelOk (wire\Method $m, amqp\Channel $chan) { }

    /** @override \amqphp\Consumer */
    function handleConsumeOk (wire\Method $m, amqp\Channel $chan) { }

    /** @override \amqphp\Consumer */
    function handleDelivery (wire\Method $m, amqp\Channel $chan) {
        printf(" (Message received)\n");
        $this->replies[$m->getField('correlation-id')] = $m->getContent();
        $this->requests--;
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
        return ($this->requests > 0);
    }

    /** @override \amqphp\ChannelEventHandler */
    public function publishConfirm (wire\Method $m) {
    }

    /** @override \amqphp\ChannelEventHandler */
    public function publishReturn (wire\Method $m) {
        printf("Your message was rejected: %s [%d]\n", $m->getField('reply-text'), $m->getField('reply-code'));
        $this->requests--;
    }

    /** @override \amqphp\ChannelEventHandler */
    public function publishNack (wire\Method $m) {
    }
}