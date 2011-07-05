<?php
namespace amqphp\protocol\v0_9_1\basic;
/** Ampq binding code, generated from doc version 0.9.1 */
class ConsumeMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'basic';
    protected $name = 'consume';
    protected $index = 20;
    protected $synchronous = true;
    protected $responseMethods = array('consume-ok');
    protected $fields = array('reserved-1', 'queue', 'consumer-tag', 'no-local', 'no-ack', 'exclusive', 'no-wait', 'arguments');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\basic\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\basic\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = true;
}