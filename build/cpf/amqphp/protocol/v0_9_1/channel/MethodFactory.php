<?php
namespace amqphp\protocol\v0_9_1\channel;
abstract class MethodFactory extends \amqphp\protocol\abstrakt\MethodFactory
{
    protected static $Cache = array(array(10, 'open', '\\amqphp\\protocol\\v0_9_1\\channel\\OpenMethod'),array(11, 'open-ok', '\\amqphp\\protocol\\v0_9_1\\channel\\OpenOkMethod'),array(20, 'flow', '\\amqphp\\protocol\\v0_9_1\\channel\\FlowMethod'),array(21, 'flow-ok', '\\amqphp\\protocol\\v0_9_1\\channel\\FlowOkMethod'),array(40, 'close', '\\amqphp\\protocol\\v0_9_1\\channel\\CloseMethod'),array(41, 'close-ok', '\\amqphp\\protocol\\v0_9_1\\channel\\CloseOkMethod'));
}