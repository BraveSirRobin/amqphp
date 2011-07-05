<?php
namespace amqphp\protocol\v0_9_1\tx;
abstract class MethodFactory extends \amqphp\protocol\abstrakt\MethodFactory
{
    protected static $Cache = array(array(10, 'select', '\\amqphp\\protocol\\v0_9_1\\tx\\SelectMethod'),array(11, 'select-ok', '\\amqphp\\protocol\\v0_9_1\\tx\\SelectOkMethod'),array(20, 'commit', '\\amqphp\\protocol\\v0_9_1\\tx\\CommitMethod'),array(21, 'commit-ok', '\\amqphp\\protocol\\v0_9_1\\tx\\CommitOkMethod'),array(30, 'rollback', '\\amqphp\\protocol\\v0_9_1\\tx\\RollbackMethod'),array(31, 'rollback-ok', '\\amqphp\\protocol\\v0_9_1\\tx\\RollbackOkMethod'));
}