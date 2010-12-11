<?php

//require_once 'amqp.php';
//require_once 'rabbit.php';
use amqp_091 as amqp;
use amqp_091\protocol;
use amqp_091\wire;

require('amqp.php');




class DebugConsumer implements amqp\Consumer
{
    private $n = 0;
    function onMessageReceive (wire\Method $meth) {
        printf("Received Message:\n%s\n", $meth->getContent());
        if ($this->n++ > 10000) {
            return amqp\CONSUME_BREAK;
        } else {
            // TODO: Build and return an ack
        }
    }

    // Callback invoked by the select loop, called regardless of whether
    // and messages were delivered.  Should only be called when select
    // is called with a timeout
    function onSelectLoop () {
        printf("   select loop called {$this->n}\n");
        if ($this->n++ > 10000) {
            return amqp\CONSUME_BREAK;
        }
    }
}


// Script starts



if (isset($argv[1]) && strtolower($argv[1]) == 'consume') {
    test9();
} else {
    test5();
}

//
// NEW TESTS
//

function test4() {
    // Method.
    $m = new wire\Method(new wire\Writer, protocol\ClassFactory::GetClassByName('connection')->getMethodByName('start-ok'));
    //    var_Dump($m);
    /*$props = new wire\Table;
    $props['library'] = 'Refactored sex shiznit';
    $props['version'] = '0.1';*/
    $props = array('library' => 'Refactored sex shiznit', 'version' => '0.1');
    $m->setField($props, 'client-properties');
    $m->setField('mech here', 'mechanism');
    $m->setField('response here', 'response');
    $m->setField('en_US', 'locale');
    echo $m->toBin();
}


function test5() {
    $VH = 'robin';
    $EX = 'router';
    $Q = 'msgQ2';
    $USER = 'testing';
    $PASS = 'letmein';


    // Produce
    $sParams = array('host' => 'localhost',
                     'port' => 5672,
                     'username' => $USER,
                     'userpass' => $PASS,
                     'vhost' => $VH);
    $connFact = new amqp\ConnectionFactory($sParams);
    $conn = $connFact->newConnection();
    $chan = $conn->getChannel();


    // Declare the exchange
    $excDecl = $chan->exchange('declare', array('reserved-1' => $chan->getTicket(),
                                                'type' => 'direct',
                                                'durable' => true,
                                                'exchange' => $EX));
    $chan->invoke($excDecl);

    // Declare the queue
    $qDecl = $chan->queue('declare', array('reserved-1' => $chan->getTicket(),
                                           'queue' => $Q));
    $chan->invoke($qDecl);

    // Bind Q to EX
    $qBind = $chan->queue('bind', array('reserved-1' => $chan->getTicket(),
                                        'queue' => $Q,
                                        'exchange' => $EX));
    $chan->invoke($qBind);


    // Start a transaction
    //$chan->invoke($chan->tx('select'));


    // Pushes content to a queue
    $basicP = $chan->basic('publish', array('content-type' => 'text/plain',
                                            'content-encoding' => 'UTF-8',
                                            'reserved-1' => $chan->getTicket(),
                                            'mandatory' => false,
                                            'immediate' => false,
                                            'exchange' => $EX), 'You should help out the aged beatnik (mebbeh)');

    for ($i = 0; $i < 100; $i++) {
        $chan->invoke($basicP);
    }

    echo "Written $i messages to the broker\n";
    return; // ***************


    // Pull a single message from the queue
    $basicGet = $chan->basic('get', array('reserved-1' => $chan->getTicket(), 'queue' => $Q));
    // Suck all content from that Q.
    $contents = array();
    $i = $delTag = 0;
    while (true) {
        $getOk = $chan->invoke($basicGet);
        //printf(" [%d bytes read]\n", $getOk->getBytesRead());
        if ($i == 1) {
            printf("GetOK Class fields:\n");
            foreach ($getOk->getClassFields() as $k => $v) {
                if (is_bool($v)) {
                    $v = $v ? 'true' : 'false';
                }
                printf(" %s => %s\n", $k, $v);
            }
            printf("GetOK Method fields:\n");
            foreach ($getOk->getFields() as $k => $v) {
                if (is_bool($v)) {
                    $v = $v ? 'true' : 'false';
                }
                printf(" %s => %s\n", $k, $v);
            }
        }
        if ($getOk->getMethodProto()->getSpecHasContent()) {
            $c = $getOk->getContent();
            if (! in_array($c, $contents)) {
                $contents[] = $c;
            }
            $delTag = $getOk->getField('delivery-tag');
        } else {
            break;
        }
        if (($i++ % 100) == 0) {
            printf("...Consumed %d messages\n", $i);
        }
    }

    printf("Read %d messages, distinct versions are:\n%s", $i, implode("\n", $contents));
    //printf("Get result method %s, content:\n%s\n", $getOk->getClassProto()->getSpecName(), $getOk->getContent());

    // Send an ack to clear all msgs
    $basicAck = $chan->basic('ack', array('delivery-tag' => $delTag, 'multiple' => true));
    $chan->invoke($basicAck);

    //$chan->invoke($chan->tx('commit'));


    $conn->shutdown();

    /*$excDel = $chan->exchange('delete', array('reserved-1' => $chan->getTicket(),
                                              'exchange' => $EX));
                                              $chan->invoke($excDel);*/

}



function test9() {
    // Consume
    $VH = 'robin';
    $EX = 'router';
    $Q = 'msgQ2';
    $USER = 'testing';
    $PASS = 'letmein';

    // Produce
    $sParams = array('host' => 'localhost',
                     'port' => 5672,
                     'username' => $USER,
                     'userpass' => $PASS,
                     'vhost' => $VH);
    $connFact = new amqp\ConnectionFactory($sParams);
    $conn = $connFact->newConnection();
    $chan = $conn->getChannel();


    // Declare the exchange
    $excDecl = $chan->exchange('declare', array('reserved-1' => $chan->getTicket(),
                                                'type' => 'direct',
                                                'durable' => true,
                                                'exchange' => $EX));
    $chan->invoke($excDecl);

    // Declare the queue
    $qDecl = $chan->queue('declare', array('reserved-1' => $chan->getTicket(),
                                           'queue' => $Q));
    $chan->invoke($qDecl);

    // Bind Q to EX
    $qBind = $chan->queue('bind', array('reserved-1' => $chan->getTicket(),
                                        'queue' => $Q,
                                        'exchange' => $EX));
    $chan->invoke($qBind);

    register_shutdown_function(function () use ($conn) { echo "Do channel shutdown\n"; $conn->shutdown(); });

    // Consume messages forever
    $chan->consume(new DebugConsumer, array('reserved-1' => $chan->getTicket(),
                                            'queue' => $Q,
                                            'no-local' => true,
                                            'no-ack' => true,
                                            'exclusive' => false,
                                            'no-wait' => false)); // Blocks indefinitely
}




function test6() {
    // What gets returned for bad reads?
    $r = new wire\Reader('');
    $r->read('octet');
}

function test7() {
    // Class Fields
    $basicP = new wire\Method(protocol\ClassFactory::GetClassByName('basic')->getMethodByName('publish'));
    var_Dump($basicP->getClassProto()->getSpecFields());
    var_Dump($basicP->getClassProto()->getFields());
}

function test8() {
    // Class Fields
    $basicP = new wire\Method(protocol\ClassFactory::GetClassByName('basic')->getMethodByName('publish'));
    $cFields = array ('content-type' => 'text/plain',
                      'content-encoding' => 'UTF-8',
                      'headers',
                      'delivery-mode',
                      'priority',
                      'correlation-id',
                      'reply-to',
                      'expiration',
                      'message-id',
                      'timestamp',
                      'type',
                      'user-id',
                      'app-id',
                      'reserved');
    foreach ($cFields as $i => $cf) {
        if (! is_int($i)) {
            $basicP->setClassField($i, $cf);
        }
    }
    $mFields = array('reserved-1' => 2,
                     'exchange' => 'exch',
                     'routing-key' => '<route-key>',
                     'mandatory' => true,
                     'immediate' => true);
    foreach ($mFields as $i => $mf) {
        $basicP->setField($i, $mf);
    }
    $basicP->setContent('So, yeah, Jazz!');
    echo $basicP->toBin();
}



//
// OLD TESTS
//

function test1() {
    // Test creating a simple message.
    $m = amqp\AmqpMessage::NewMessage(amqp\AmqpMessage::TYPE_METHOD, 0);
    $m->setClassName('connection');
    $m->setMethodName('tune');
    $m['channel-max'] = 33;
    $m['frame-max'] = 1024 * 1024;
    $m['heartbeat'] = 0;
    $m->flushMessage();
    printf("Message after the flush:\n%s\n", amqp\hexdump($m->getBuffer()->getBuffer()));
}


function test2() {
    // Test creating a message with a table
    $m = amqp\AmqpMessage::NewMessage(amqp\AmqpMessage::TYPE_METHOD, 0);
    $m->setClassName('connection');
    $m->setMethodName('start-ok');
    $m['client-properties'] = test2BuildAmqpTable();
    $m['mechanism'] = 'Mechanism';
    $m['response'] = 'Response';
    $m['locale'] = 'en_UK';
    $m->flushMessage();
    printf("Message after the flush:\n%s\n", amqp\hexdump($m->getBuffer()->getBuffer()));
}
function test2BuildAmqpTable() {
    $t = new \amqp_091\wire\AmqpTable;
    $t['product'] = new \amqp_091\wire\AmqpTableField('Product table field', 'S');
    $t['version'] = new \amqp_091\wire\AmqpTableField('Version table field', 'S');
    $t['platform'] = new \amqp_091\wire\AmqpTableField('Platform table field', 'S');
    $t['copyright'] = new \amqp_091\wire\AmqpTableField('Copyright table field', 'S');
    $t['information'] = new \amqp_091\wire\AmqpTableField('Information table field', 'S');
    return $t;
}


function test3() {
    // Talk to the local rabbit :-)
    $connFact = new rmq\RabbitConnectionFactory;
    $conn = $connFact->newConnection();
    $chan = $conn->getChannel();

    echo "\n\n~~Test3 Complete~~\n\n";
}



/**

   Older test functions

use amqp_091\protocol;
use amqp_091\wire;



//test2();


function test3() {
    // do not run - pseduo code for connection setup
    $msgBuff = readMessageFromSocketSomehow();
    $msg = new AmqpMessage($msgBuff);
    $msg->setOffset(0);
}

function test2() {
    $meth = protocol\ClassFactory::GetClassByIndex(10)->getMethodByIndex(10);
    printf("Test 2, \$meth has name %s, methods(%s)\n", $meth->getSpecName(), implode(", ", $meth->getSpecFields()));
    $fieldI = $meth->getField('version-major');
    $fieldS = $meth->getField('locales');
    var_dump($fieldS);
    $mb = new wire\AmqpMessageBuffer;
    $fieldI->write($mb, 1);
    $fieldS->write($mb, ' more ');
    $fieldI->write($mb, 4);
    $fieldS->write($mb, ' for me ');
    printf("Data written to buffer by field:\n%s\n", wire\hexdump($mb->getBuffer()));
    $mb->setOffset(0);
    $v1 = $fieldI->read($mb);
    $v2 = $fieldS->read($mb);
    $v3 = $fieldI->read($mb);
    $v4 = $fieldS->read($mb);
    printf("Data, unpacked: %s, %s, %s, %s\n", $v1, $v2, $v3, $v4);
}

function test1() {
    $c = protocol\ClassFactory::GetClassByIndex(60);

    echo "Methods:\n";
    $methods = $c->methods();
    var_dump($methods[0]);


    echo "\n\nFields:\n";
    $fields = $methods[0]->fields();
    var_dump($fields);

    echo "\n\nResponses:\n";
    $resp = $methods[0]->responses();
    var_dump($resp);

    echo "Class Field:\n";
    $ct = $c->field('content-type');
    $ct = $c->field('content-type');
    $ct = $c->field('content-type');
    var_dump($ct);
}

 **/




/*

// Test Code - originally from amqp.wire.php

t1();

function t1() {
    $aTable = array("Foo" => "Bar");
    $table = new Table($aTable);
    $table['bigfoo'] = 'B' . str_repeat('b', 256) . 'ar!';
    $table['num'] = 2;
    $table['bignum'] = 259;
    $table['negnum'] = -2;
    $table['bignegnum'] = -259;
    $table['array1'] = array('String element', 1, -2, array('sub1', 'sub2'));
    $table['littlestring'] = 'Eeek';
    $table['Decimal'] = new Decimal(1234567, 3);
    $table['longlong'] = new TableField(100000034000001, 'l');
    $table['float'] = new TableField(1.23, 'f');
    $table['double'] = new TableField(453245476568.2342, 'd');
    $table['timestamp'] = new TableField(14, 'T');
    //    var_dump($table);

    $w = new Writer;
    $w->write('a table:', 'shortstr');
    $w->write($table, 'table');
    $w->write('phew!', 'shortstr');
    $w->write(pow(2, 62), 'longlong');
    //    echo $w->getBuffer();
    //    die;
    echo "\n-Regurgitate-\n";

    $r = new Reader($w->getBuffer());
    echo $r->read('shortstr') . "\n";
    echo "Table:\n";
    foreach ($r->read('table') as $fName => $tField) {
        $value = $tField->getValue();
        if (is_array($value)) {
            printf(" [name=%s,type=%s] %s\n", $fName, $tField->getType(), implode(', ', $value));
        } else {
            printf(" [name=%s,type=%s] %s\n", $fName, $tField->getType(), $value);
        }
    }
    echo $r->read('shortstr') . "\n" . $r->read('longlong') . "\n";
}


// Write stuff then read it back again
function t2() {
    $w = new Writer;
    $w->write('I ATE ', 'shortstr');
    $w->write(3, 'octet');
    $w->write(' GOATS', 'shortstr');
    $w->write(0, 'bit');//1
    $w->write(1, 'bit');//2
    $w->write(0, 'bit');//3
    $w->write(1, 'bit');//4
    $w->write(0, 'bit');//5
    $w->write(1, 'bit');//6
    $w->write(0, 'bit');//7
    $w->write(0, 'bit');//8
    $w->write(0, 'bit');//9
    echo $w->getBuffer();

    echo "\n-Regurgitate-\n";
    $r = new Reader($w->getBuffer());
    echo $r->read('shortstr') . $r->read('octet') . $r->read('shortstr') .
        ' ' . $r->read('bit') . ' ' . $r->read('bit') . ' ' . $r->read('bit') .
        ' ' . $r->read('bit') . ' ' . $r->read('bit') . ' ' . $r->read('bit') .
        ' ' . $r->read('bit') . ' ' . $r->read('bit') . ' ' . $r->read('bit');
}



// Bools
function t3() {
    $w = new Writer;
    $w->write('2 bools next:', 'shortstr');
    $w->write(0, 'bit');//1
    $w->write(0, 'bit');//2
    $w->write(0, 'bit');//3
    $w->write(0, 'bit');//4
    $w->write(0, 'bit');//5
    $w->write(0, 'bit');//6
    $w->write(0, 'bit');//7
    $w->write(1, 'bit');//8
    $w->write(1, 'bit');//9
    echo $w->getBuffer();
}

function t4() {
    $d1 = new Decimal(100, 4);
    $d2 = new Decimal(100, 10);
    $d3 = new Decimal(69, 2);
    var_dump($d2);
    printf("Convert to string BC: (\$d1, \$d2, \$d3) = (%s, %s, %s)\n", $d1->toBcString(), $d2->toBcString(), $d3->toBcString());
}
*/