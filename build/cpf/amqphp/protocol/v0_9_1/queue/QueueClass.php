<?php
namespace amqphp\protocol\v0_9_1\queue;
/** Ampq binding code, generated from doc version 0.9.1 */
class QueueClass extends \amqphp\protocol\abstrakt\XmlSpecClass
{
    protected $name = 'queue';
    protected $index = 50;
    protected $fields = array();
    protected $methods = array(10 => 'declare',11 => 'declare-ok',20 => 'bind',21 => 'bind-ok',50 => 'unbind',51 => 'unbind-ok',30 => 'purge',31 => 'purge-ok',40 => 'delete',41 => 'delete-ok');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\queue\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\queue\\FieldFactory';
}