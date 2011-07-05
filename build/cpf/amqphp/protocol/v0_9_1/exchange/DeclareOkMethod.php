<?php
namespace amqphp\protocol\v0_9_1\exchange;
/** Ampq binding code, generated from doc version 0.9.1 */
class DeclareOkMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'exchange';
    protected $name = 'declare-ok';
    protected $index = 11;
    protected $synchronous = true;
    protected $responseMethods = array();
    protected $fields = array();
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\exchange\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\exchange\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}