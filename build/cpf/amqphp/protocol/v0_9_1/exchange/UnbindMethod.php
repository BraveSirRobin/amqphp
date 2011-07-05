<?php
namespace amqphp\protocol\v0_9_1\exchange;
/** Ampq binding code, generated from doc version 0.9.1 */
class UnbindMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'exchange';
    protected $name = 'unbind';
    protected $index = 40;
    protected $synchronous = true;
    protected $responseMethods = array('unbind-ok');
    protected $fields = array('reserved-1', 'destination', 'source', 'routing-key', 'no-wait', 'arguments');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\exchange\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\exchange\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = true;
}