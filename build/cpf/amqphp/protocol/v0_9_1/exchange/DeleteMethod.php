<?php
namespace amqphp\protocol\v0_9_1\exchange;
/** Ampq binding code, generated from doc version 0.9.1 */
class DeleteMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'exchange';
    protected $name = 'delete';
    protected $index = 20;
    protected $synchronous = true;
    protected $responseMethods = array('delete-ok');
    protected $fields = array('reserved-1', 'exchange', 'if-unused', 'no-wait');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\exchange\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\exchange\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = true;
}