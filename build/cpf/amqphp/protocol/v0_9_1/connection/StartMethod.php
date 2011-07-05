<?php
namespace amqphp\protocol\v0_9_1\connection;
/** Ampq binding code, generated from doc version 0.9.1 */
class StartMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'connection';
    protected $name = 'start';
    protected $index = 10;
    protected $synchronous = true;
    protected $responseMethods = array('start-ok');
    protected $fields = array('version-major', 'version-minor', 'server-properties', 'mechanisms', 'locales');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\connection\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\connection\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}