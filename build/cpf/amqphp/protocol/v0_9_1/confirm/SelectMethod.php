<?php
namespace amqphp\protocol\v0_9_1\confirm;
/** Ampq binding code, generated from doc version 0.9.1 */
class SelectMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'confirm';
    protected $name = 'select';
    protected $index = 10;
    protected $synchronous = true;
    protected $responseMethods = array('select-ok');
    protected $fields = array('nowait');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\confirm\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\confirm\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}