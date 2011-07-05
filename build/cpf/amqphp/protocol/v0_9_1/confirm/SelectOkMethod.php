<?php
namespace amqphp\protocol\v0_9_1\confirm;
/** Ampq binding code, generated from doc version 0.9.1 */
class SelectOkMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'confirm';
    protected $name = 'select-ok';
    protected $index = 11;
    protected $synchronous = true;
    protected $responseMethods = array();
    protected $fields = array();
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\confirm\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\confirm\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}