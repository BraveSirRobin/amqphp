<?php
namespace amqphp\protocol\v0_9_1\channel;

/** Ampq binding code, generated from doc version 0.9.1 */
class FlowOkMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'channel';
    protected $name = 'flow-ok';
    protected $index = 21;
    protected $synchronous = false;
    protected $responseMethods = array();
    protected $fields = array('active');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\channel\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\channel\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}

/** Ampq binding code, generated from doc version 0.9.1 */
class CloseMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'channel';
    protected $name = 'close';
    protected $index = 40;
    protected $synchronous = true;
    protected $responseMethods = array('close-ok');
    protected $fields = array('reply-code', 'reply-text', 'class-id', 'method-id');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\channel\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\channel\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}

/** Ampq binding code, generated from doc version 0.9.1 */
class OpenOkMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'channel';
    protected $name = 'open-ok';
    protected $index = 11;
    protected $synchronous = true;
    protected $responseMethods = array();
    protected $fields = array('reserved-1');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\channel\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\channel\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class CloseClassIdField extends \amqphp\protocol\v0_9_1\ClassIdDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'class-id'; }
    function getSpecFieldDomain() { return 'class-id'; }

}

/** Ampq binding code, generated from doc version 0.9.1 */
class OpenMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'channel';
    protected $name = 'open';
    protected $index = 10;
    protected $synchronous = true;
    protected $responseMethods = array('open-ok');
    protected $fields = array('reserved-1');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\channel\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\channel\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class FlowOkActiveField extends \amqphp\protocol\v0_9_1\BitDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'active'; }
    function getSpecFieldDomain() { return 'bit'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class FlowActiveField extends \amqphp\protocol\v0_9_1\BitDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'active'; }
    function getSpecFieldDomain() { return 'bit'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class CloseMethodIdField extends \amqphp\protocol\v0_9_1\MethodIdDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'method-id'; }
    function getSpecFieldDomain() { return 'method-id'; }

}

/** Ampq binding code, generated from doc version 0.9.1 */
class CloseOkMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'channel';
    protected $name = 'close-ok';
    protected $index = 41;
    protected $synchronous = true;
    protected $responseMethods = array();
    protected $fields = array();
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\channel\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\channel\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}

/** Ampq binding code, generated from doc version 0.9.1 */
class ChannelClass extends \amqphp\protocol\abstrakt\XmlSpecClass
{
    protected $name = 'channel';
    protected $index = 20;
    protected $fields = array();
    protected $methods = array(10 => 'open',11 => 'open-ok',20 => 'flow',21 => 'flow-ok',40 => 'close',41 => 'close-ok');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\channel\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\channel\\FieldFactory';
}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class CloseReplyCodeField extends \amqphp\protocol\v0_9_1\ReplyCodeDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'reply-code'; }
    function getSpecFieldDomain() { return 'reply-code'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class OpenReserved1Field extends \amqphp\protocol\v0_9_1\ShortstrDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'reserved-1'; }
    function getSpecFieldDomain() { return 'shortstr'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class OpenOkReserved1Field extends \amqphp\protocol\v0_9_1\LongstrDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'reserved-1'; }
    function getSpecFieldDomain() { return 'longstr'; }

}

abstract class MethodFactory extends \amqphp\protocol\abstrakt\MethodFactory
{
    protected static $Cache = array(array(10, 'open', '\\amqphp\\protocol\\v0_9_1\\channel\\OpenMethod'),array(11, 'open-ok', '\\amqphp\\protocol\\v0_9_1\\channel\\OpenOkMethod'),array(20, 'flow', '\\amqphp\\protocol\\v0_9_1\\channel\\FlowMethod'),array(21, 'flow-ok', '\\amqphp\\protocol\\v0_9_1\\channel\\FlowOkMethod'),array(40, 'close', '\\amqphp\\protocol\\v0_9_1\\channel\\CloseMethod'),array(41, 'close-ok', '\\amqphp\\protocol\\v0_9_1\\channel\\CloseOkMethod'));
}

/** Ampq binding code, generated from doc version 0.9.1 */
class FlowMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'channel';
    protected $name = 'flow';
    protected $index = 20;
    protected $synchronous = true;
    protected $responseMethods = array('flow-ok');
    protected $fields = array('active');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\channel\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\channel\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class CloseReplyTextField extends \amqphp\protocol\v0_9_1\ReplyTextDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'reply-text'; }
    function getSpecFieldDomain() { return 'reply-text'; }

}

abstract class FieldFactory  extends \amqphp\protocol\abstrakt\FieldFactory
{
    protected static $Cache = array(array('reserved-1', 'open', '\\amqphp\\protocol\\v0_9_1\\channel\\OpenReserved1Field'),array('reserved-1', 'open-ok', '\\amqphp\\protocol\\v0_9_1\\channel\\OpenOkReserved1Field'),array('active', 'flow', '\\amqphp\\protocol\\v0_9_1\\channel\\FlowActiveField'),array('active', 'flow-ok', '\\amqphp\\protocol\\v0_9_1\\channel\\FlowOkActiveField'),array('reply-code', 'close', '\\amqphp\\protocol\\v0_9_1\\channel\\CloseReplyCodeField'),array('reply-text', 'close', '\\amqphp\\protocol\\v0_9_1\\channel\\CloseReplyTextField'),array('class-id', 'close', '\\amqphp\\protocol\\v0_9_1\\channel\\CloseClassIdField'),array('method-id', 'close', '\\amqphp\\protocol\\v0_9_1\\channel\\CloseMethodIdField'));
}