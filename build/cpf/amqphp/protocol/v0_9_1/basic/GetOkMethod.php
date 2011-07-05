<?php
namespace amqphp\protocol\v0_9_1\basic;
/** Ampq binding code, generated from doc version 0.9.1 */
class GetOkMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'basic';
    protected $name = 'get-ok';
    protected $index = 71;
    protected $synchronous = true;
    protected $responseMethods = array();
    protected $fields = array('delivery-tag', 'redelivered', 'exchange', 'routing-key', 'message-count');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\basic\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\basic\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = true;
    protected $hasNoWait = false;
}