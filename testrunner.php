<?php

//require_once 'amqp.php';
//require_once 'rabbit.php';
use amqp_091 as amqp;
use amqp_091\protocol;
use amqp_091\wire;

require('amqp.wire.php');
require('amqp.protocol.abstrakt.php');
require('gencode/amqp.0_9_1.php');




test4();

//
// NEW TESTS
//

function test4() {
    // Method.
    $m = new wire\Method(new wire\Writer, protocol\ClassFactory::GetClassByName('connection')->getMethodByName('start-ok'));
    //    var_Dump($m);
    $props = new wire\Table;
    $props['library'] = 'Refactored sex shiznit';
    $props['version'] = '0.1';
    $m->setField($props, 'client-properties');
    $m->setField('mech here', 'mechanism');
    $m->setField('response here', 'response');
    $m->setField('en_US', 'locale');
    echo $m->toBin();
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