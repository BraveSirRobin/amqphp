<?php
namespace amqphp\protocol\v0_9_1\queue;
abstract class MethodFactory extends \amqphp\protocol\abstrakt\MethodFactory
{
    protected static $Cache = array(array(10, 'declare', '\\amqphp\\protocol\\v0_9_1\\queue\\DeclareMethod'),array(11, 'declare-ok', '\\amqphp\\protocol\\v0_9_1\\queue\\DeclareOkMethod'),array(20, 'bind', '\\amqphp\\protocol\\v0_9_1\\queue\\BindMethod'),array(21, 'bind-ok', '\\amqphp\\protocol\\v0_9_1\\queue\\BindOkMethod'),array(50, 'unbind', '\\amqphp\\protocol\\v0_9_1\\queue\\UnbindMethod'),array(51, 'unbind-ok', '\\amqphp\\protocol\\v0_9_1\\queue\\UnbindOkMethod'),array(30, 'purge', '\\amqphp\\protocol\\v0_9_1\\queue\\PurgeMethod'),array(31, 'purge-ok', '\\amqphp\\protocol\\v0_9_1\\queue\\PurgeOkMethod'),array(40, 'delete', '\\amqphp\\protocol\\v0_9_1\\queue\\DeleteMethod'),array(41, 'delete-ok', '\\amqphp\\protocol\\v0_9_1\\queue\\DeleteOkMethod'));
}