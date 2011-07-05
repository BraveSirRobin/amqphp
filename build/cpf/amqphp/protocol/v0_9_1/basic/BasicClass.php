<?php
namespace amqphp\protocol\v0_9_1\basic;
/** Ampq binding code, generated from doc version 0.9.1 */
class BasicClass extends \amqphp\protocol\abstrakt\XmlSpecClass
{
    protected $name = 'basic';
    protected $index = 60;
    protected $fields = array('content-type','content-encoding','headers','delivery-mode','priority','correlation-id','reply-to','expiration','message-id','timestamp','type','user-id','app-id','cluster-id');
    protected $methods = array(10 => 'qos',11 => 'qos-ok',20 => 'consume',21 => 'consume-ok',30 => 'cancel',31 => 'cancel-ok',40 => 'publish',50 => 'return',60 => 'deliver',70 => 'get',71 => 'get-ok',72 => 'get-empty',80 => 'ack',90 => 'reject',100 => 'recover-async',110 => 'recover',111 => 'recover-ok',120 => 'nack');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\basic\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\basic\\FieldFactory';
}