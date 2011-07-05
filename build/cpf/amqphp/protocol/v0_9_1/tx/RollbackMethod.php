<?php
namespace amqphp\protocol\v0_9_1\tx;
/** Ampq binding code, generated from doc version 0.9.1 */
class RollbackMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'tx';
    protected $name = 'rollback';
    protected $index = 30;
    protected $synchronous = true;
    protected $responseMethods = array('rollback-ok');
    protected $fields = array();
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\tx\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\tx\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}