<?php
namespace amqphp\protocol\v0_9_1\tx;

/** Ampq binding code, generated from doc version 0.9.1 */
class RollbackOkMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'tx';
    protected $name = 'rollback-ok';
    protected $index = 31;
    protected $synchronous = true;
    protected $responseMethods = array();
    protected $fields = array();
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\tx\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\tx\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}

/** Ampq binding code, generated from doc version 0.9.1 */
class CommitMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'tx';
    protected $name = 'commit';
    protected $index = 20;
    protected $synchronous = true;
    protected $responseMethods = array('commit-ok');
    protected $fields = array();
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\tx\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\tx\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}

/** Ampq binding code, generated from doc version 0.9.1 */
class CommitOkMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'tx';
    protected $name = 'commit-ok';
    protected $index = 21;
    protected $synchronous = true;
    protected $responseMethods = array();
    protected $fields = array();
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\tx\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\tx\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}

/** Ampq binding code, generated from doc version 0.9.1 */
class RollbackMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'tx';
    protected $name = 'rollback';
    protected $index = 30;
    protected $synchronous = true;
    protected $responseMethods = array('rollback-ok');
    protected $fields = array();
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\tx\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\tx\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}

/** Ampq binding code, generated from doc version 0.9.1 */
class SelectMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'tx';
    protected $name = 'select';
    protected $index = 10;
    protected $synchronous = true;
    protected $responseMethods = array('select-ok');
    protected $fields = array();
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\tx\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\tx\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}

abstract class MethodFactory extends \amqphp\protocol\abstrakt\MethodFactory
{
    protected static $Cache = array(array(10, 'select', '\\amqphp\\protocol\\v0_9_1\\tx\\SelectMethod'),array(11, 'select-ok', '\\amqphp\\protocol\\v0_9_1\\tx\\SelectOkMethod'),array(20, 'commit', '\\amqphp\\protocol\\v0_9_1\\tx\\CommitMethod'),array(21, 'commit-ok', '\\amqphp\\protocol\\v0_9_1\\tx\\CommitOkMethod'),array(30, 'rollback', '\\amqphp\\protocol\\v0_9_1\\tx\\RollbackMethod'),array(31, 'rollback-ok', '\\amqphp\\protocol\\v0_9_1\\tx\\RollbackOkMethod'));
}

/** Ampq binding code, generated from doc version 0.9.1 */
class SelectOkMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'tx';
    protected $name = 'select-ok';
    protected $index = 11;
    protected $synchronous = true;
    protected $responseMethods = array();
    protected $fields = array();
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\tx\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\tx\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}

/** Ampq binding code, generated from doc version 0.9.1 */
class TxClass extends \amqphp\protocol\abstrakt\XmlSpecClass
{
    protected $name = 'tx';
    protected $index = 90;
    protected $fields = array();
    protected $methods = array(10 => 'select',11 => 'select-ok',20 => 'commit',21 => 'commit-ok',30 => 'rollback',31 => 'rollback-ok');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\tx\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\tx\\FieldFactory';
}

abstract class FieldFactory  extends \amqphp\protocol\abstrakt\FieldFactory
{
    protected static $Cache = array();
}