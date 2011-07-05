<?php
namespace amqphp\protocol\v0_9_1\basic;
/** Ampq binding code, generated from doc version 0.9.1 */
class DeliverMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'basic';
    protected $name = 'deliver';
    protected $index = 60;
    protected $synchronous = false;
    protected $responseMethods = array();
    protected $fields = array('consumer-tag', 'delivery-tag', 'redelivered', 'exchange', 'routing-key');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\basic\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\basic\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = true;
    protected $hasNoWait = false;
}