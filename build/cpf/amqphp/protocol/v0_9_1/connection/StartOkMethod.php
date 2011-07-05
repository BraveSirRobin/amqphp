<?php
namespace amqphp\protocol\v0_9_1\connection;
/** Ampq binding code, generated from doc version 0.9.1 */
class StartOkMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'connection';
    protected $name = 'start-ok';
    protected $index = 11;
    protected $synchronous = true;
    protected $responseMethods = array();
    protected $fields = array('client-properties', 'mechanism', 'response', 'locale');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\connection\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\connection\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}