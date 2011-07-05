<?php
namespace amqphp\protocol\v0_9_1\queue;
/** Ampq binding code, generated from doc version 0.9.1 */
class UnbindMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'queue';
    protected $name = 'unbind';
    protected $index = 50;
    protected $synchronous = true;
    protected $responseMethods = array('unbind-ok');
    protected $fields = array('reserved-1', 'queue', 'exchange', 'routing-key', 'arguments');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\queue\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\queue\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}