<?php
namespace amqphp\protocol\v0_9_1\channel;
/** Ampq binding code, generated from doc version 0.9.1 */
class OpenMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'channel';
    protected $name = 'open';
    protected $index = 10;
    protected $synchronous = true;
    protected $responseMethods = array('open-ok');
    protected $fields = array('reserved-1');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\channel\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\channel\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}