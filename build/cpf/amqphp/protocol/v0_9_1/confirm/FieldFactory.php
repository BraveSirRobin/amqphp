<?php
namespace amqphp\protocol\v0_9_1\confirm;
abstract class FieldFactory  extends \amqphp\protocol\abstrakt\FieldFactory
{
    protected static $Cache = array(array('nowait', 'select', '\\amqphp\\protocol\\v0_9_1\\confirm\\SelectNowaitField'));
}