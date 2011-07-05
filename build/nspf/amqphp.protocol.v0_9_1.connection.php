<?php
namespace amqphp\protocol\v0_9_1\connection;

	
/** Ampq binding code, generated from doc version 0.9.1 */

class TuneOkHeartbeatField extends \amqphp\protocol\v0_9_1\ShortDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'heartbeat'; }
    function getSpecFieldDomain() { return 'short'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class TuneFrameMaxField extends \amqphp\protocol\v0_9_1\LongDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'frame-max'; }
    function getSpecFieldDomain() { return 'long'; }

}

/** Ampq binding code, generated from doc version 0.9.1 */
class CloseMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'connection';
    protected $name = 'close';
    protected $index = 50;
    protected $synchronous = true;
    protected $responseMethods = array('close-ok');
    protected $fields = array('reply-code', 'reply-text', 'class-id', 'method-id');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\connection\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\connection\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}

/** Ampq binding code, generated from doc version 0.9.1 */
class SecureMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'connection';
    protected $name = 'secure';
    protected $index = 20;
    protected $synchronous = true;
    protected $responseMethods = array('secure-ok');
    protected $fields = array('challenge');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\connection\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\connection\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class TuneHeartbeatField extends \amqphp\protocol\v0_9_1\ShortDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'heartbeat'; }
    function getSpecFieldDomain() { return 'short'; }

}

/** Ampq binding code, generated from doc version 0.9.1 */
class OpenOkMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'connection';
    protected $name = 'open-ok';
    protected $index = 41;
    protected $synchronous = true;
    protected $responseMethods = array();
    protected $fields = array('reserved-1');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\connection\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\connection\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}

/** Ampq binding code, generated from doc version 0.9.1 */
class TuneOkMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'connection';
    protected $name = 'tune-ok';
    protected $index = 31;
    protected $synchronous = true;
    protected $responseMethods = array();
    protected $fields = array('channel-max', 'frame-max', 'heartbeat');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\connection\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\connection\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class SecureOkResponseField extends \amqphp\protocol\v0_9_1\LongstrDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'response'; }
    function getSpecFieldDomain() { return 'longstr'; }

    function validate($subject) {
        return (parent::validate($subject) && ! is_null($subject));
    }

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
    protected $class = 'connection';
    protected $name = 'open';
    protected $index = 40;
    protected $synchronous = true;
    protected $responseMethods = array('open-ok');
    protected $fields = array('virtual-host', 'reserved-1', 'reserved-2');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\connection\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\connection\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}

/** Ampq binding code, generated from doc version 0.9.1 */
class ConnectionClass extends \amqphp\protocol\abstrakt\XmlSpecClass
{
    protected $name = 'connection';
    protected $index = 10;
    protected $fields = array();
    protected $methods = array(10 => 'start',11 => 'start-ok',20 => 'secure',21 => 'secure-ok',30 => 'tune',31 => 'tune-ok',40 => 'open',41 => 'open-ok',50 => 'close',51 => 'close-ok');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\connection\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\connection\\FieldFactory';
}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class StartMechanismsField extends \amqphp\protocol\v0_9_1\LongstrDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'mechanisms'; }
    function getSpecFieldDomain() { return 'longstr'; }

    function validate($subject) {
        return (parent::validate($subject) && ! is_null($subject));
    }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class StartOkMechanismField extends \amqphp\protocol\v0_9_1\ShortstrDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'mechanism'; }
    function getSpecFieldDomain() { return 'shortstr'; }

    function validate($subject) {
        return (parent::validate($subject) && ! is_null($subject));
    }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class OpenVirtualHostField extends \amqphp\protocol\v0_9_1\PathDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'virtual-host'; }
    function getSpecFieldDomain() { return 'path'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class CloseMethodIdField extends \amqphp\protocol\v0_9_1\MethodIdDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'method-id'; }
    function getSpecFieldDomain() { return 'method-id'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class StartOkResponseField extends \amqphp\protocol\v0_9_1\LongstrDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'response'; }
    function getSpecFieldDomain() { return 'longstr'; }

    function validate($subject) {
        return (parent::validate($subject) && ! is_null($subject));
    }

}

/** Ampq binding code, generated from doc version 0.9.1 */
class CloseOkMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'connection';
    protected $name = 'close-ok';
    protected $index = 51;
    protected $synchronous = true;
    protected $responseMethods = array();
    protected $fields = array();
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\connection\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\connection\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class TuneChannelMaxField extends \amqphp\protocol\v0_9_1\ShortDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'channel-max'; }
    function getSpecFieldDomain() { return 'short'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class SecureChallengeField extends \amqphp\protocol\v0_9_1\LongstrDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'challenge'; }
    function getSpecFieldDomain() { return 'longstr'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class StartVersionMinorField extends \amqphp\protocol\v0_9_1\OctetDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'version-minor'; }
    function getSpecFieldDomain() { return 'octet'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class StartVersionMajorField extends \amqphp\protocol\v0_9_1\OctetDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'version-major'; }
    function getSpecFieldDomain() { return 'octet'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class TuneOkFrameMaxField extends \amqphp\protocol\v0_9_1\LongDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'frame-max'; }
    function getSpecFieldDomain() { return 'long'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class TuneOkChannelMaxField extends \amqphp\protocol\v0_9_1\ShortDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'channel-max'; }
    function getSpecFieldDomain() { return 'short'; }

    function validate($subject) {
        return (parent::validate($subject) && ! is_null($subject) && true);
    }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class StartOkClientPropertiesField extends \amqphp\protocol\v0_9_1\PeerPropertiesDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'client-properties'; }
    function getSpecFieldDomain() { return 'peer-properties'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class CloseReplyCodeField extends \amqphp\protocol\v0_9_1\ReplyCodeDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'reply-code'; }
    function getSpecFieldDomain() { return 'reply-code'; }

}

/** Ampq binding code, generated from doc version 0.9.1 */
class TuneMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'connection';
    protected $name = 'tune';
    protected $index = 30;
    protected $synchronous = true;
    protected $responseMethods = array('tune-ok');
    protected $fields = array('channel-max', 'frame-max', 'heartbeat');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\connection\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\connection\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class StartLocalesField extends \amqphp\protocol\v0_9_1\LongstrDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'locales'; }
    function getSpecFieldDomain() { return 'longstr'; }

    function validate($subject) {
        return (parent::validate($subject) && ! is_null($subject));
    }

}

/** Ampq binding code, generated from doc version 0.9.1 */
class SecureOkMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'connection';
    protected $name = 'secure-ok';
    protected $index = 21;
    protected $synchronous = true;
    protected $responseMethods = array();
    protected $fields = array('response');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\connection\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\connection\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class OpenReserved1Field extends \amqphp\protocol\v0_9_1\ShortstrDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'reserved-1'; }
    function getSpecFieldDomain() { return 'shortstr'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class OpenOkReserved1Field extends \amqphp\protocol\v0_9_1\ShortstrDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'reserved-1'; }
    function getSpecFieldDomain() { return 'shortstr'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class StartServerPropertiesField extends \amqphp\protocol\v0_9_1\PeerPropertiesDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'server-properties'; }
    function getSpecFieldDomain() { return 'peer-properties'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class OpenReserved2Field extends \amqphp\protocol\v0_9_1\BitDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'reserved-2'; }
    function getSpecFieldDomain() { return 'bit'; }

}

abstract class MethodFactory extends \amqphp\protocol\abstrakt\MethodFactory
{
    protected static $Cache = array(array(10, 'start', '\\amqphp\\protocol\\v0_9_1\\connection\\StartMethod'),array(11, 'start-ok', '\\amqphp\\protocol\\v0_9_1\\connection\\StartOkMethod'),array(20, 'secure', '\\amqphp\\protocol\\v0_9_1\\connection\\SecureMethod'),array(21, 'secure-ok', '\\amqphp\\protocol\\v0_9_1\\connection\\SecureOkMethod'),array(30, 'tune', '\\amqphp\\protocol\\v0_9_1\\connection\\TuneMethod'),array(31, 'tune-ok', '\\amqphp\\protocol\\v0_9_1\\connection\\TuneOkMethod'),array(40, 'open', '\\amqphp\\protocol\\v0_9_1\\connection\\OpenMethod'),array(41, 'open-ok', '\\amqphp\\protocol\\v0_9_1\\connection\\OpenOkMethod'),array(50, 'close', '\\amqphp\\protocol\\v0_9_1\\connection\\CloseMethod'),array(51, 'close-ok', '\\amqphp\\protocol\\v0_9_1\\connection\\CloseOkMethod'));
}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class CloseReplyTextField extends \amqphp\protocol\v0_9_1\ReplyTextDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'reply-text'; }
    function getSpecFieldDomain() { return 'reply-text'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class StartOkLocaleField extends \amqphp\protocol\v0_9_1\ShortstrDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'locale'; }
    function getSpecFieldDomain() { return 'shortstr'; }

    function validate($subject) {
        return (parent::validate($subject) && ! is_null($subject));
    }

}

/** Ampq binding code, generated from doc version 0.9.1 */
class StartOkMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'connection';
    protected $name = 'start-ok';
    protected $index = 11;
    protected $synchronous = true;
    protected $responseMethods = array();
    protected $fields = array('client-properties', 'mechanism', 'response', 'locale');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\connection\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\connection\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}

abstract class FieldFactory  extends \amqphp\protocol\abstrakt\FieldFactory
{
    protected static $Cache = array(array('version-major', 'start', '\\amqphp\\protocol\\v0_9_1\\connection\\StartVersionMajorField'),array('version-minor', 'start', '\\amqphp\\protocol\\v0_9_1\\connection\\StartVersionMinorField'),array('server-properties', 'start', '\\amqphp\\protocol\\v0_9_1\\connection\\StartServerPropertiesField'),array('mechanisms', 'start', '\\amqphp\\protocol\\v0_9_1\\connection\\StartMechanismsField'),array('locales', 'start', '\\amqphp\\protocol\\v0_9_1\\connection\\StartLocalesField'),array('client-properties', 'start-ok', '\\amqphp\\protocol\\v0_9_1\\connection\\StartOkClientPropertiesField'),array('mechanism', 'start-ok', '\\amqphp\\protocol\\v0_9_1\\connection\\StartOkMechanismField'),array('response', 'start-ok', '\\amqphp\\protocol\\v0_9_1\\connection\\StartOkResponseField'),array('locale', 'start-ok', '\\amqphp\\protocol\\v0_9_1\\connection\\StartOkLocaleField'),array('challenge', 'secure', '\\amqphp\\protocol\\v0_9_1\\connection\\SecureChallengeField'),array('response', 'secure-ok', '\\amqphp\\protocol\\v0_9_1\\connection\\SecureOkResponseField'),array('channel-max', 'tune', '\\amqphp\\protocol\\v0_9_1\\connection\\TuneChannelMaxField'),array('frame-max', 'tune', '\\amqphp\\protocol\\v0_9_1\\connection\\TuneFrameMaxField'),array('heartbeat', 'tune', '\\amqphp\\protocol\\v0_9_1\\connection\\TuneHeartbeatField'),array('channel-max', 'tune-ok', '\\amqphp\\protocol\\v0_9_1\\connection\\TuneOkChannelMaxField'),array('frame-max', 'tune-ok', '\\amqphp\\protocol\\v0_9_1\\connection\\TuneOkFrameMaxField'),array('heartbeat', 'tune-ok', '\\amqphp\\protocol\\v0_9_1\\connection\\TuneOkHeartbeatField'),array('virtual-host', 'open', '\\amqphp\\protocol\\v0_9_1\\connection\\OpenVirtualHostField'),array('reserved-1', 'open', '\\amqphp\\protocol\\v0_9_1\\connection\\OpenReserved1Field'),array('reserved-2', 'open', '\\amqphp\\protocol\\v0_9_1\\connection\\OpenReserved2Field'),array('reserved-1', 'open-ok', '\\amqphp\\protocol\\v0_9_1\\connection\\OpenOkReserved1Field'),array('reply-code', 'close', '\\amqphp\\protocol\\v0_9_1\\connection\\CloseReplyCodeField'),array('reply-text', 'close', '\\amqphp\\protocol\\v0_9_1\\connection\\CloseReplyTextField'),array('class-id', 'close', '\\amqphp\\protocol\\v0_9_1\\connection\\CloseClassIdField'),array('method-id', 'close', '\\amqphp\\protocol\\v0_9_1\\connection\\CloseMethodIdField'));
}

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