<?php
namespace amqphp\protocol\v0_9_1\connection;
/** Ampq binding code, generated from doc version 0.9.1 */
class ConnectionClass extends \amqphp\protocol\abstrakt\XmlSpecClass
{
    protected $name = 'connection';
    protected $index = 10;
    protected $fields = array();
    protected $methods = array(10 => 'start',11 => 'start-ok',20 => 'secure',21 => 'secure-ok',30 => 'tune',31 => 'tune-ok',40 => 'open',41 => 'open-ok',50 => 'close',51 => 'close-ok');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\connection\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\connection\\FieldFactory';
}