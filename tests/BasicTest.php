<?php


use amqp_091 as amqp;
use amqp_091\protocol;
use amqp_091\wire;

require __DIR__ . '/../amqp.php';


class XmlBindingTest extends PHPUnit_Framework_TestCase
{
    /** Instance of amqp\Connection  */
    private $conn;
    /** Instance of amqp\Channel */
    private $chan;
    /** Name of test exchange */
    private $exchange;
    /** Type of test exchange */
    private $exchangeType;
    /** Name of test Q */
    private $queueName;

    /**
     * Use test setup procedure to create queues and bindings, store
     * the connection and channel locally
     */
    function setUp () {
        $sParams = parse_ini_file(__DIR__ . '/BasicTest.ini');
        if (! $sParams) {
            throw new Exception("Failed to find test settings", 9854);
        }
        $this->exchange = $sParams['exchange'];
        $this->queueName = $sParams['queueName'];
        $this->exchangeType = $sParams['exchangeType'];
        $this->queueName = $sParams['queueName'];

        $connFact = new amqp\ConnectionFactory($sParams);
        $this->conn = $connFact->newConnection();
        $this->chan = $this->conn->getChannel();

        // Declare the exchange
        $excDecl = $this->chan->exchange('declare', array('type' => $this->exchangeType,
                                                          'durable' => true,
                                                          'exchange' => $this->exchange));
        $this->chan->invoke($excDecl);

        // Declare the queue
        $qDecl = $this->chan->queue('declare', array('queue' => $this->queueName));
        $this->chan->invoke($qDecl);

        // Bind Q to EX
        $qBind = $this->chan->queue('bind', array('queue' => $this->queueName,
                                                  'routing-key' => '',
                                                  'exchange' => $this->exchange));
        $this->chan->invoke($qBind);
    }

    /** Do a gracefull connection close */
    function tearDown () {
        if ($this->conn) {
            $this->conn->shutdown();
        }
    }

    /** Use queue.declare to find out how many messages are on the test queue */
    function getQueueLength () {
        $qDecl = $this->chan->queue('declare', array('queue' => $this->queueName));
        $qOk = $this->chan->invoke($qDecl);
        return $qOk->getField('message-count');
    }



    /**
     * Publish a couple of messages, then check that they've arrived on the server
     */
    function testBasicPublish () {
        $basicP = $this->chan->basic('publish', array('content-type' => 'text/plain',
                                                      'content-encoding' => 'UTF-8',
                                                      'routing-key' => '',
                                                      'mandatory' => false,
                                                      'immediate' => false,
                                                      'exchange' => $this->exchange));

        $before = $this->getQueueLength();
        for ($i = 0; $i < 5; $i++) {
            $basicP->setContent(sprintf("Test content from %s->%s, number %d", __CLASS__, __METHOD__, $i + 1));
            $this->chan->invoke($basicP);
        }
        $after = $this->getQueueLength();
        $this->assertEquals($after, $before + 5);
    }


    /**
     * Use basic.get to read back the messages published in testBasicPublish()
     */
    function testBasicGet () {
        $basicGet = $this->chan->basic('get', array('queue' => $this->queueName));
        for ($i = 0; $i < 5; $i++) {
            $getOk = $this->chan->invoke($basicGet);
            $this->assertNotEmpty($getOk->getContent());
            $delTag = $getOk->getField('delivery-tag');
            $this->assertNotEmpty($delTag);
            $ack = $this->chan->basic('ack', array('delivery-tag' => $delTag, 'multiple' => false));
            $this->assertNotEmpty($ack);
            $this->chan->invoke($ack);
        }
    }


}