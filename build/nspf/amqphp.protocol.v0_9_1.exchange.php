<?php
namespace amqphp\protocol\v0_9_1\exchange;

	
/** Ampq binding code, generated from doc version 0.9.1 */

class DeclareReserved3Field extends \amqphp\protocol\v0_9_1\BitDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'reserved-3'; }
    function getSpecFieldDomain() { return 'bit'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class UnbindNoWaitField extends \amqphp\protocol\v0_9_1\NoWaitDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'no-wait'; }
    function getSpecFieldDomain() { return 'no-wait'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class UnbindReserved1Field extends \amqphp\protocol\v0_9_1\ShortDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'reserved-1'; }
    function getSpecFieldDomain() { return 'short'; }

}

/** Ampq binding code, generated from doc version 0.9.1 */
class ExchangeClass extends \amqphp\protocol\abstrakt\XmlSpecClass
{
    protected $name = 'exchange';
    protected $index = 40;
    protected $fields = array();
    protected $methods = array(10 => 'declare',11 => 'declare-ok',20 => 'delete',21 => 'delete-ok',30 => 'bind',31 => 'bind-ok',40 => 'unbind',41 => 'unbind-ok');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\exchange\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\exchange\\FieldFactory';
}

/** Ampq binding code, generated from doc version 0.9.1 */
class BindMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'exchange';
    protected $name = 'bind';
    protected $index = 30;
    protected $synchronous = true;
    protected $responseMethods = array('bind-ok');
    protected $fields = array('reserved-1', 'destination', 'source', 'routing-key', 'no-wait', 'arguments');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\exchange\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\exchange\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = true;
}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class DeleteExchangeField extends \amqphp\protocol\v0_9_1\ExchangeNameDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'exchange'; }
    function getSpecFieldDomain() { return 'exchange-name'; }

    function validate($subject) {
        return (parent::validate($subject) && ! is_null($subject));
    }

}

/** Ampq binding code, generated from doc version 0.9.1 */
class BindOkMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'exchange';
    protected $name = 'bind-ok';
    protected $index = 31;
    protected $synchronous = true;
    protected $responseMethods = array();
    protected $fields = array();
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\exchange\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\exchange\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class BindArgumentsField extends \amqphp\protocol\v0_9_1\TableDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'arguments'; }
    function getSpecFieldDomain() { return 'table'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class DeclarePassiveField extends \amqphp\protocol\v0_9_1\BitDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'passive'; }
    function getSpecFieldDomain() { return 'bit'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class DeclareReserved1Field extends \amqphp\protocol\v0_9_1\ShortDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'reserved-1'; }
    function getSpecFieldDomain() { return 'short'; }

}

/** Ampq binding code, generated from doc version 0.9.1 */
class UnbindOkMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'exchange';
    protected $name = 'unbind-ok';
    protected $index = 41;
    protected $synchronous = true;
    protected $responseMethods = array();
    protected $fields = array();
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\exchange\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\exchange\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class DeclareArgumentsField extends \amqphp\protocol\v0_9_1\TableDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'arguments'; }
    function getSpecFieldDomain() { return 'table'; }

}

/** Ampq binding code, generated from doc version 0.9.1 */
class DeclareOkMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'exchange';
    protected $name = 'declare-ok';
    protected $index = 11;
    protected $synchronous = true;
    protected $responseMethods = array();
    protected $fields = array();
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\exchange\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\exchange\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}

/** Ampq binding code, generated from doc version 0.9.1 */
class DeleteMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'exchange';
    protected $name = 'delete';
    protected $index = 20;
    protected $synchronous = true;
    protected $responseMethods = array('delete-ok');
    protected $fields = array('reserved-1', 'exchange', 'if-unused', 'no-wait');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\exchange\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\exchange\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = true;
}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class DeleteReserved1Field extends \amqphp\protocol\v0_9_1\ShortDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'reserved-1'; }
    function getSpecFieldDomain() { return 'short'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class DeclareDurableField extends \amqphp\protocol\v0_9_1\BitDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'durable'; }
    function getSpecFieldDomain() { return 'bit'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class DeleteIfUnusedField extends \amqphp\protocol\v0_9_1\BitDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'if-unused'; }
    function getSpecFieldDomain() { return 'bit'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class DeclareTypeField extends \amqphp\protocol\v0_9_1\ShortstrDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'type'; }
    function getSpecFieldDomain() { return 'shortstr'; }

}

/** Ampq binding code, generated from doc version 0.9.1 */
class DeclareMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'exchange';
    protected $name = 'declare';
    protected $index = 10;
    protected $synchronous = true;
    protected $responseMethods = array('declare-ok');
    protected $fields = array('reserved-1', 'exchange', 'type', 'passive', 'durable', 'reserved-2', 'reserved-3', 'no-wait', 'arguments');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\exchange\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\exchange\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = true;
}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class BindNoWaitField extends \amqphp\protocol\v0_9_1\NoWaitDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'no-wait'; }
    function getSpecFieldDomain() { return 'no-wait'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class UnbindArgumentsField extends \amqphp\protocol\v0_9_1\TableDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'arguments'; }
    function getSpecFieldDomain() { return 'table'; }

}

/** Ampq binding code, generated from doc version 0.9.1 */
class UnbindMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'exchange';
    protected $name = 'unbind';
    protected $index = 40;
    protected $synchronous = true;
    protected $responseMethods = array('unbind-ok');
    protected $fields = array('reserved-1', 'destination', 'source', 'routing-key', 'no-wait', 'arguments');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\exchange\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\exchange\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = true;
}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class UnbindRoutingKeyField extends \amqphp\protocol\v0_9_1\ShortstrDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'routing-key'; }
    function getSpecFieldDomain() { return 'shortstr'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class DeclareNoWaitField extends \amqphp\protocol\v0_9_1\NoWaitDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'no-wait'; }
    function getSpecFieldDomain() { return 'no-wait'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class DeleteNoWaitField extends \amqphp\protocol\v0_9_1\NoWaitDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'no-wait'; }
    function getSpecFieldDomain() { return 'no-wait'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class UnbindSourceField extends \amqphp\protocol\v0_9_1\ExchangeNameDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'source'; }
    function getSpecFieldDomain() { return 'exchange-name'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class BindRoutingKeyField extends \amqphp\protocol\v0_9_1\ShortstrDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'routing-key'; }
    function getSpecFieldDomain() { return 'shortstr'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class DeclareReserved2Field extends \amqphp\protocol\v0_9_1\BitDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'reserved-2'; }
    function getSpecFieldDomain() { return 'bit'; }

}

/** Ampq binding code, generated from doc version 0.9.1 */
class DeleteOkMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'exchange';
    protected $name = 'delete-ok';
    protected $index = 21;
    protected $synchronous = true;
    protected $responseMethods = array();
    protected $fields = array();
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\exchange\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\exchange\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class BindSourceField extends \amqphp\protocol\v0_9_1\ExchangeNameDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'source'; }
    function getSpecFieldDomain() { return 'exchange-name'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class BindReserved1Field extends \amqphp\protocol\v0_9_1\ShortDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'reserved-1'; }
    function getSpecFieldDomain() { return 'short'; }

}

abstract class MethodFactory extends \amqphp\protocol\abstrakt\MethodFactory
{
    protected static $Cache = array(array(10, 'declare', '\\amqphp\\protocol\\v0_9_1\\exchange\\DeclareMethod'),array(11, 'declare-ok', '\\amqphp\\protocol\\v0_9_1\\exchange\\DeclareOkMethod'),array(20, 'delete', '\\amqphp\\protocol\\v0_9_1\\exchange\\DeleteMethod'),array(21, 'delete-ok', '\\amqphp\\protocol\\v0_9_1\\exchange\\DeleteOkMethod'),array(30, 'bind', '\\amqphp\\protocol\\v0_9_1\\exchange\\BindMethod'),array(31, 'bind-ok', '\\amqphp\\protocol\\v0_9_1\\exchange\\BindOkMethod'),array(40, 'unbind', '\\amqphp\\protocol\\v0_9_1\\exchange\\UnbindMethod'),array(41, 'unbind-ok', '\\amqphp\\protocol\\v0_9_1\\exchange\\UnbindOkMethod'));
}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class BindDestinationField extends \amqphp\protocol\v0_9_1\ExchangeNameDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'destination'; }
    function getSpecFieldDomain() { return 'exchange-name'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class DeclareExchangeField extends \amqphp\protocol\v0_9_1\ExchangeNameDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'exchange'; }
    function getSpecFieldDomain() { return 'exchange-name'; }

    function validate($subject) {
        return (parent::validate($subject) && ! is_null($subject));
    }

}

abstract class FieldFactory  extends \amqphp\protocol\abstrakt\FieldFactory
{
    protected static $Cache = array(array('reserved-1', 'declare', '\\amqphp\\protocol\\v0_9_1\\exchange\\DeclareReserved1Field'),array('exchange', 'declare', '\\amqphp\\protocol\\v0_9_1\\exchange\\DeclareExchangeField'),array('type', 'declare', '\\amqphp\\protocol\\v0_9_1\\exchange\\DeclareTypeField'),array('passive', 'declare', '\\amqphp\\protocol\\v0_9_1\\exchange\\DeclarePassiveField'),array('durable', 'declare', '\\amqphp\\protocol\\v0_9_1\\exchange\\DeclareDurableField'),array('reserved-2', 'declare', '\\amqphp\\protocol\\v0_9_1\\exchange\\DeclareReserved2Field'),array('reserved-3', 'declare', '\\amqphp\\protocol\\v0_9_1\\exchange\\DeclareReserved3Field'),array('no-wait', 'declare', '\\amqphp\\protocol\\v0_9_1\\exchange\\DeclareNoWaitField'),array('arguments', 'declare', '\\amqphp\\protocol\\v0_9_1\\exchange\\DeclareArgumentsField'),array('reserved-1', 'delete', '\\amqphp\\protocol\\v0_9_1\\exchange\\DeleteReserved1Field'),array('exchange', 'delete', '\\amqphp\\protocol\\v0_9_1\\exchange\\DeleteExchangeField'),array('if-unused', 'delete', '\\amqphp\\protocol\\v0_9_1\\exchange\\DeleteIfUnusedField'),array('no-wait', 'delete', '\\amqphp\\protocol\\v0_9_1\\exchange\\DeleteNoWaitField'),array('reserved-1', 'bind', '\\amqphp\\protocol\\v0_9_1\\exchange\\BindReserved1Field'),array('destination', 'bind', '\\amqphp\\protocol\\v0_9_1\\exchange\\BindDestinationField'),array('source', 'bind', '\\amqphp\\protocol\\v0_9_1\\exchange\\BindSourceField'),array('routing-key', 'bind', '\\amqphp\\protocol\\v0_9_1\\exchange\\BindRoutingKeyField'),array('no-wait', 'bind', '\\amqphp\\protocol\\v0_9_1\\exchange\\BindNoWaitField'),array('arguments', 'bind', '\\amqphp\\protocol\\v0_9_1\\exchange\\BindArgumentsField'),array('reserved-1', 'unbind', '\\amqphp\\protocol\\v0_9_1\\exchange\\UnbindReserved1Field'),array('destination', 'unbind', '\\amqphp\\protocol\\v0_9_1\\exchange\\UnbindDestinationField'),array('source', 'unbind', '\\amqphp\\protocol\\v0_9_1\\exchange\\UnbindSourceField'),array('routing-key', 'unbind', '\\amqphp\\protocol\\v0_9_1\\exchange\\UnbindRoutingKeyField'),array('no-wait', 'unbind', '\\amqphp\\protocol\\v0_9_1\\exchange\\UnbindNoWaitField'),array('arguments', 'unbind', '\\amqphp\\protocol\\v0_9_1\\exchange\\UnbindArgumentsField'));
}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class UnbindDestinationField extends \amqphp\protocol\v0_9_1\ExchangeNameDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'destination'; }
    function getSpecFieldDomain() { return 'exchange-name'; }

}