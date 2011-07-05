<?php
namespace amqphp\protocol\v0_9_1\exchange;
/** Ampq binding code, generated from doc version 0.9.1 */
class ExchangeClass extends \amqphp\protocol\abstrakt\XmlSpecClass
{
    protected $name = 'exchange';
    protected $index = 40;
    protected $fields = array();
    protected $methods = array(10 => 'declare',11 => 'declare-ok',20 => 'delete',21 => 'delete-ok',30 => 'bind',31 => 'bind-ok',40 => 'unbind',41 => 'unbind-ok');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\exchange\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\exchange\\FieldFactory';
}