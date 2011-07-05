<?php
namespace amqphp\protocol\v0_9_1\queue;
/** Ampq binding code, generated from doc version 0.9.1 */
class PurgeOkMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'queue';
    protected $name = 'purge-ok';
    protected $index = 31;
    protected $synchronous = true;
    protected $responseMethods = array();
    protected $fields = array('message-count');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\queue\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\queue\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}