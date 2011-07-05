<?php
namespace amqphp\protocol\v0_9_1\confirm;
abstract class MethodFactory extends \amqphp\protocol\abstrakt\MethodFactory
{
    protected static $Cache = array(array(10, 'select', '\\amqphp\\protocol\\v0_9_1\\confirm\\SelectMethod'),array(11, 'select-ok', '\\amqphp\\protocol\\v0_9_1\\confirm\\SelectOkMethod'));
}