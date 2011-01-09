<?php


use amqp_091 as amqp;
use amqp_091\protocol;
use amqp_091\wire;

require __DIR__ . '/../amqp.php';


class XmlBindingTest extends PHPUnit_Framework_TestCase
{
    /** Instance of amqp\Connection  */
    private static $conn;
    /** Instance of amqp\Channel */
    private static $chan;
    /** Name of test exchange */
    private static $exchange;
    /** Type of test exchange */
    private static $exchangeType;
    /** Name of test Q */
    private static $queueName;




    /**
     * Use test setup procedure to create queues and bindings, store
     * the connection and channel locally
     */
    static function setUpBeforeClass () {
        $sParams = parse_ini_file(__DIR__ . '/BasicTest.ini');
        if (! $sParams) {
            throw new Exception("Failed to find test settings", 9854);
        }
        self::$exchange = $sParams['exchange'];
        self::$queueName = $sParams['queueName'];
        self::$exchangeType = $sParams['exchangeType'];
        self::$queueName = $sParams['queueName'];

        //$connFact = new amqp\ConnectionFactory($sParams);
        //self::$conn = $connFact->newConnection();
        self::$conn = new amqp\Connection($sParams);
        self::$chan = self::$conn->getChannel();

        // Declare the exchange
        $excDecl = self::$chan->exchange('declare', array('type' => self::$exchangeType,
                                                          'durable' => true,
                                                          'exchange' => self::$exchange));
        self::$chan->invoke($excDecl);

        // Declare the queue
        $qDecl = self::$chan->queue('declare', array('queue' => self::$queueName));
        self::$chan->invoke($qDecl);

        // Bind Q to EX
        $qBind = self::$chan->queue('bind', array('queue' => self::$queueName,
                                                  'routing-key' => '',
                                                  'exchange' => self::$exchange));
        self::$chan->invoke($qBind);
    }

    /** Do a gracefull connection close */
    static function tearDownAfterClass () {
        if (self::$conn) {
            self::$conn->shutdown();
        }
    }





    /** Use queue.declare to find out how many messages are on the test queue */
    function getQueueLength () {
        $qDecl = self::$chan->queue('declare', array('queue' => self::$queueName));
        $qOk = self::$chan->invoke($qDecl);
        return $qOk->getField('message-count');
    }






    /**
     * Publish a couple of messages, then check that they've arrived on the server
     */
    function testBasicPublish () {
        $basicP = self::$chan->basic('publish', array('content-type' => 'text/plain',
                                                      'content-encoding' => 'UTF-8',
                                                      'routing-key' => '',
                                                      'mandatory' => false,
                                                      'immediate' => false,
                                                      'exchange' => self::$exchange));

        $before = $this->getQueueLength();
        for ($i = 0; $i < 5; $i++) {
            $basicP->setContent(sprintf("Test content from %s->%s, number %d", __CLASS__, __METHOD__, $i + 1));
            self::$chan->invoke($basicP);
        }
        $after = $this->getQueueLength();
        $this->assertEquals($after, $before + 5);
    }


    /**
     * Use basic.get to read back the messages published in testBasicPublish()
     */
    function testBasicGet () {
        $basicGet = self::$chan->basic('get', array('queue' => self::$queueName));
        $this->assertEquals($basicGet->getClassProto()->getSpecName(), 'basic');
        $this->assertEquals($basicGet->getMethodProto()->getSpecName(), 'get');
        for ($i = 0; $i < 5; $i++) {
            $getOk = self::$chan->invoke($basicGet);
            $this->assertEquals($getOk->getClassProto()->getSpecName(), 'basic');
            $this->assertEquals($getOk->getMethodProto()->getSpecName(), 'get-ok');
            $this->assertNotEmpty($getOk->getContent());
            $delTag = $getOk->getField('delivery-tag');
            $this->assertNotEmpty($delTag);
            $ack = self::$chan->basic('ack', array('delivery-tag' => $delTag, 'multiple' => false));
            $this->assertNotEmpty($ack);
            self::$chan->invoke($ack);
        }
    }


}