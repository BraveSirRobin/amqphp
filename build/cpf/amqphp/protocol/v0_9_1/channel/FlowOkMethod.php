<?php
namespace amqphp\protocol\v0_9_1\channel;
/** Ampq binding code, generated from doc version 0.9.1 */
class FlowOkMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'channel';
    protected $name = 'flow-ok';
    protected $index = 21;
    protected $synchronous = false;
    protected $responseMethods = array();
    protected $fields = array('active');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\channel\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\channel\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}