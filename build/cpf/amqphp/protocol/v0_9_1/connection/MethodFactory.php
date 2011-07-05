<?php
namespace amqphp\protocol\v0_9_1\connection;
abstract class MethodFactory extends \amqphp\protocol\abstrakt\MethodFactory
{
    protected static $Cache = array(array(10, 'start', '\\amqphp\\protocol\\v0_9_1\\connection\\StartMethod'),array(11, 'start-ok', '\\amqphp\\protocol\\v0_9_1\\connection\\StartOkMethod'),array(20, 'secure', '\\amqphp\\protocol\\v0_9_1\\connection\\SecureMethod'),array(21, 'secure-ok', '\\amqphp\\protocol\\v0_9_1\\connection\\SecureOkMethod'),array(30, 'tune', '\\amqphp\\protocol\\v0_9_1\\connection\\TuneMethod'),array(31, 'tune-ok', '\\amqphp\\protocol\\v0_9_1\\connection\\TuneOkMethod'),array(40, 'open', '\\amqphp\\protocol\\v0_9_1\\connection\\OpenMethod'),array(41, 'open-ok', '\\amqphp\\protocol\\v0_9_1\\connection\\OpenOkMethod'),array(50, 'close', '\\amqphp\\protocol\\v0_9_1\\connection\\CloseMethod'),array(51, 'close-ok', '\\amqphp\\protocol\\v0_9_1\\connection\\CloseOkMethod'));
}