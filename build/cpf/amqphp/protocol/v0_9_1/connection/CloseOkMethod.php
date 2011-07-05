<?php
namespace amqphp\protocol\v0_9_1\connection;
/** Ampq binding code, generated from doc version 0.9.1 */
class CloseOkMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'connection';
    protected $name = 'close-ok';
    protected $index = 51;
    protected $synchronous = true;
    protected $responseMethods = array();
    protected $fields = array();
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\connection\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\connection\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}