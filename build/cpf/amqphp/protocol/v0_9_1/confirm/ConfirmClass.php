<?php
namespace amqphp\protocol\v0_9_1\confirm;
/** Ampq binding code, generated from doc version 0.9.1 */
class ConfirmClass extends \amqphp\protocol\abstrakt\XmlSpecClass
{
    protected $name = 'confirm';
    protected $index = 85;
    protected $fields = array();
    protected $methods = array(10 => 'select',11 => 'select-ok');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\confirm\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\confirm\\FieldFactory';
}