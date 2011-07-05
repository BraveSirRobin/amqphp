<?php
namespace amqphp\protocol\v0_9_1\connection;
/** Ampq binding code, generated from doc version 0.9.1 */
class OpenMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'connection';
    protected $name = 'open';
    protected $index = 40;
    protected $synchronous = true;
    protected $responseMethods = array('open-ok');
    protected $fields = array('virtual-host', 'reserved-1', 'reserved-2');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\connection\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\connection\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}