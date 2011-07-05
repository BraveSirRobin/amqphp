<?php
namespace amqphp\protocol\v0_9_1\channel;
abstract class FieldFactory  extends \amqphp\protocol\abstrakt\FieldFactory
{
    protected static $Cache = array(array('reserved-1', 'open', '\\amqphp\\protocol\\v0_9_1\\channel\\OpenReserved1Field'),array('reserved-1', 'open-ok', '\\amqphp\\protocol\\v0_9_1\\channel\\OpenOkReserved1Field'),array('active', 'flow', '\\amqphp\\protocol\\v0_9_1\\channel\\FlowActiveField'),array('active', 'flow-ok', '\\amqphp\\protocol\\v0_9_1\\channel\\FlowOkActiveField'),array('reply-code', 'close', '\\amqphp\\protocol\\v0_9_1\\channel\\CloseReplyCodeField'),array('reply-text', 'close', '\\amqphp\\protocol\\v0_9_1\\channel\\CloseReplyTextField'),array('class-id', 'close', '\\amqphp\\protocol\\v0_9_1\\channel\\CloseClassIdField'),array('method-id', 'close', '\\amqphp\\protocol\\v0_9_1\\channel\\CloseMethodIdField'));
}