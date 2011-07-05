<?php
namespace amqphp\protocol\v0_9_1\confirm;

/** Ampq binding code, generated from doc version 0.9.1 */
class ConfirmClass extends \amqphp\protocol\abstrakt\XmlSpecClass
{
    protected $name = 'confirm';
    protected $index = 85;
    protected $fields = array();
    protected $methods = array(10 => 'select',11 => 'select-ok');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\confirm\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\confirm\\FieldFactory';
}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class SelectNowaitField extends \amqphp\protocol\v0_9_1\BitDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'nowait'; }
    function getSpecFieldDomain() { return 'bit'; }

}

/** Ampq binding code, generated from doc version 0.9.1 */
class SelectMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'confirm';
    protected $name = 'select';
    protected $index = 10;
    protected $synchronous = true;
    protected $responseMethods = array('select-ok');
    protected $fields = array('nowait');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\confirm\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\confirm\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}

abstract class MethodFactory extends \amqphp\protocol\abstrakt\MethodFactory
{
    protected static $Cache = array(array(10, 'select', '\\amqphp\\protocol\\v0_9_1\\confirm\\SelectMethod'),array(11, 'select-ok', '\\amqphp\\protocol\\v0_9_1\\confirm\\SelectOkMethod'));
}

/** Ampq binding code, generated from doc version 0.9.1 */
class SelectOkMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'confirm';
    protected $name = 'select-ok';
    protected $index = 11;
    protected $synchronous = true;
    protected $responseMethods = array();
    protected $fields = array();
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\confirm\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\confirm\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}

abstract class FieldFactory  extends \amqphp\protocol\abstrakt\FieldFactory
{
    protected static $Cache = array(array('nowait', 'select', '\\amqphp\\protocol\\v0_9_1\\confirm\\SelectNowaitField'));
}