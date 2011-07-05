<?php
namespace amqphp\protocol\v0_9_1\channel;
/** Ampq binding code, generated from doc version 0.9.1 */
class OpenOkMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'channel';
    protected $name = 'open-ok';
    protected $index = 11;
    protected $synchronous = true;
    protected $responseMethods = array();
    protected $fields = array('reserved-1');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\channel\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\channel\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}