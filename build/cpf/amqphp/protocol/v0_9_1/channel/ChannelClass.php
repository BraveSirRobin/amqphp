<?php
namespace amqphp\protocol\v0_9_1\channel;
/** Ampq binding code, generated from doc version 0.9.1 */
class ChannelClass extends \amqphp\protocol\abstrakt\XmlSpecClass
{
    protected $name = 'channel';
    protected $index = 20;
    protected $fields = array();
    protected $methods = array(10 => 'open',11 => 'open-ok',20 => 'flow',21 => 'flow-ok',40 => 'close',41 => 'close-ok');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\channel\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\channel\\FieldFactory';
}