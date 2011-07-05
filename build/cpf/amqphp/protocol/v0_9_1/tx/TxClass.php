<?php
namespace amqphp\protocol\v0_9_1\tx;
/** Ampq binding code, generated from doc version 0.9.1 */
class TxClass extends \amqphp\protocol\abstrakt\XmlSpecClass
{
    protected $name = 'tx';
    protected $index = 90;
    protected $fields = array();
    protected $methods = array(10 => 'select',11 => 'select-ok',20 => 'commit',21 => 'commit-ok',30 => 'rollback',31 => 'rollback-ok');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\tx\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\tx\\FieldFactory';
}