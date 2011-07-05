<?php
namespace amqphp\protocol\v0_9_1\basic;

	
/** Ampq binding code, generated from doc version 0.9.1 */

class ExpirationField extends \amqphp\protocol\v0_9_1\ShortstrDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'expiration'; }
    function getSpecFieldDomain() { return 'shortstr'; }

}

/** Ampq binding code, generated from doc version 0.9.1 */
class RecoverOkMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'basic';
    protected $name = 'recover-ok';
    protected $index = 111;
    protected $synchronous = true;
    protected $responseMethods = array();
    protected $fields = array();
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\basic\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\basic\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class AppIdField extends \amqphp\protocol\v0_9_1\ShortstrDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'app-id'; }
    function getSpecFieldDomain() { return 'shortstr'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class GetEmptyReserved1Field extends \amqphp\protocol\v0_9_1\ShortstrDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'reserved-1'; }
    function getSpecFieldDomain() { return 'shortstr'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class GetOkDeliveryTagField extends \amqphp\protocol\v0_9_1\DeliveryTagDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'delivery-tag'; }
    function getSpecFieldDomain() { return 'delivery-tag'; }

}

/** Ampq binding code, generated from doc version 0.9.1 */
class GetOkMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'basic';
    protected $name = 'get-ok';
    protected $index = 71;
    protected $synchronous = true;
    protected $responseMethods = array();
    protected $fields = array('delivery-tag', 'redelivered', 'exchange', 'routing-key', 'message-count');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\basic\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\basic\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = true;
    protected $hasNoWait = false;
}

/** Ampq binding code, generated from doc version 0.9.1 */
class RejectMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'basic';
    protected $name = 'reject';
    protected $index = 90;
    protected $synchronous = false;
    protected $responseMethods = array();
    protected $fields = array('delivery-tag', 'requeue');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\basic\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\basic\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class PublishRoutingKeyField extends \amqphp\protocol\v0_9_1\ShortstrDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'routing-key'; }
    function getSpecFieldDomain() { return 'shortstr'; }

}

/** Ampq binding code, generated from doc version 0.9.1 */
class RecoverAsyncMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'basic';
    protected $name = 'recover-async';
    protected $index = 100;
    protected $synchronous = false;
    protected $responseMethods = array();
    protected $fields = array('requeue');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\basic\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\basic\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}

/** Ampq binding code, generated from doc version 0.9.1 */
class DeliverMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'basic';
    protected $name = 'deliver';
    protected $index = 60;
    protected $synchronous = false;
    protected $responseMethods = array();
    protected $fields = array('consumer-tag', 'delivery-tag', 'redelivered', 'exchange', 'routing-key');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\basic\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\basic\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = true;
    protected $hasNoWait = false;
}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class QosPrefetchCountField extends \amqphp\protocol\v0_9_1\ShortDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'prefetch-count'; }
    function getSpecFieldDomain() { return 'short'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class ConsumeQueueField extends \amqphp\protocol\v0_9_1\QueueNameDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'queue'; }
    function getSpecFieldDomain() { return 'queue-name'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class QosGlobalField extends \amqphp\protocol\v0_9_1\BitDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'global'; }
    function getSpecFieldDomain() { return 'bit'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class NackDeliveryTagField extends \amqphp\protocol\v0_9_1\DeliveryTagDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'delivery-tag'; }
    function getSpecFieldDomain() { return 'delivery-tag'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class DeliverRoutingKeyField extends \amqphp\protocol\v0_9_1\ShortstrDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'routing-key'; }
    function getSpecFieldDomain() { return 'shortstr'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class AckDeliveryTagField extends \amqphp\protocol\v0_9_1\DeliveryTagDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'delivery-tag'; }
    function getSpecFieldDomain() { return 'delivery-tag'; }

}

/** Ampq binding code, generated from doc version 0.9.1 */
class PublishMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'basic';
    protected $name = 'publish';
    protected $index = 40;
    protected $synchronous = false;
    protected $responseMethods = array();
    protected $fields = array('reserved-1', 'exchange', 'routing-key', 'mandatory', 'immediate');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\basic\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\basic\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = true;
    protected $hasNoWait = false;
}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class DeliverDeliveryTagField extends \amqphp\protocol\v0_9_1\DeliveryTagDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'delivery-tag'; }
    function getSpecFieldDomain() { return 'delivery-tag'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class CancelOkConsumerTagField extends \amqphp\protocol\v0_9_1\ConsumerTagDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'consumer-tag'; }
    function getSpecFieldDomain() { return 'consumer-tag'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class CorrelationIdField extends \amqphp\protocol\v0_9_1\ShortstrDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'correlation-id'; }
    function getSpecFieldDomain() { return 'shortstr'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class ConsumeNoLocalField extends \amqphp\protocol\v0_9_1\NoLocalDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'no-local'; }
    function getSpecFieldDomain() { return 'no-local'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class HeadersField extends \amqphp\protocol\v0_9_1\TableDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'headers'; }
    function getSpecFieldDomain() { return 'table'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class NackMultipleField extends \amqphp\protocol\v0_9_1\BitDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'multiple'; }
    function getSpecFieldDomain() { return 'bit'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class DeliveryModeField extends \amqphp\protocol\v0_9_1\OctetDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'delivery-mode'; }
    function getSpecFieldDomain() { return 'octet'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class RejectDeliveryTagField extends \amqphp\protocol\v0_9_1\DeliveryTagDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'delivery-tag'; }
    function getSpecFieldDomain() { return 'delivery-tag'; }

}

/** Ampq binding code, generated from doc version 0.9.1 */
class GetEmptyMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'basic';
    protected $name = 'get-empty';
    protected $index = 72;
    protected $synchronous = true;
    protected $responseMethods = array();
    protected $fields = array('reserved-1');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\basic\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\basic\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class NackRequeueField extends \amqphp\protocol\v0_9_1\BitDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'requeue'; }
    function getSpecFieldDomain() { return 'bit'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class CancelConsumerTagField extends \amqphp\protocol\v0_9_1\ConsumerTagDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'consumer-tag'; }
    function getSpecFieldDomain() { return 'consumer-tag'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class MessageIdField extends \amqphp\protocol\v0_9_1\ShortstrDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'message-id'; }
    function getSpecFieldDomain() { return 'shortstr'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class ConsumeArgumentsField extends \amqphp\protocol\v0_9_1\TableDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'arguments'; }
    function getSpecFieldDomain() { return 'table'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class ConsumeReserved1Field extends \amqphp\protocol\v0_9_1\ShortDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'reserved-1'; }
    function getSpecFieldDomain() { return 'short'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class ContentEncodingField extends \amqphp\protocol\v0_9_1\ShortstrDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'content-encoding'; }
    function getSpecFieldDomain() { return 'shortstr'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class GetOkRedeliveredField extends \amqphp\protocol\v0_9_1\RedeliveredDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'redelivered'; }
    function getSpecFieldDomain() { return 'redelivered'; }

}

/** Ampq binding code, generated from doc version 0.9.1 */
class QosMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'basic';
    protected $name = 'qos';
    protected $index = 10;
    protected $synchronous = true;
    protected $responseMethods = array('qos-ok');
    protected $fields = array('prefetch-size', 'prefetch-count', 'global');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\basic\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\basic\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}

/** Ampq binding code, generated from doc version 0.9.1 */
class AckMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'basic';
    protected $name = 'ack';
    protected $index = 80;
    protected $synchronous = false;
    protected $responseMethods = array();
    protected $fields = array('delivery-tag', 'multiple');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\basic\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\basic\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class CancelNoWaitField extends \amqphp\protocol\v0_9_1\NoWaitDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'no-wait'; }
    function getSpecFieldDomain() { return 'no-wait'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class GetQueueField extends \amqphp\protocol\v0_9_1\QueueNameDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'queue'; }
    function getSpecFieldDomain() { return 'queue-name'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class QosPrefetchSizeField extends \amqphp\protocol\v0_9_1\LongDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'prefetch-size'; }
    function getSpecFieldDomain() { return 'long'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class PublishImmediateField extends \amqphp\protocol\v0_9_1\BitDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'immediate'; }
    function getSpecFieldDomain() { return 'bit'; }

}

/** Ampq binding code, generated from doc version 0.9.1 */
class CancelMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'basic';
    protected $name = 'cancel';
    protected $index = 30;
    protected $synchronous = true;
    protected $responseMethods = array('cancel-ok');
    protected $fields = array('consumer-tag', 'no-wait');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\basic\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\basic\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = true;
}

/** Ampq binding code, generated from doc version 0.9.1 */
class BasicClass extends \amqphp\protocol\abstrakt\XmlSpecClass
{
    protected $name = 'basic';
    protected $index = 60;
    protected $fields = array('content-type','content-encoding','headers','delivery-mode','priority','correlation-id','reply-to','expiration','message-id','timestamp','type','user-id','app-id','cluster-id');
    protected $methods = array(10 => 'qos',11 => 'qos-ok',20 => 'consume',21 => 'consume-ok',30 => 'cancel',31 => 'cancel-ok',40 => 'publish',50 => 'return',60 => 'deliver',70 => 'get',71 => 'get-ok',72 => 'get-empty',80 => 'ack',90 => 'reject',100 => 'recover-async',110 => 'recover',111 => 'recover-ok',120 => 'nack');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\basic\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\basic\\FieldFactory';
}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class PublishReserved1Field extends \amqphp\protocol\v0_9_1\ShortDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'reserved-1'; }
    function getSpecFieldDomain() { return 'short'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class DeliverRedeliveredField extends \amqphp\protocol\v0_9_1\RedeliveredDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'redelivered'; }
    function getSpecFieldDomain() { return 'redelivered'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class PublishMandatoryField extends \amqphp\protocol\v0_9_1\BitDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'mandatory'; }
    function getSpecFieldDomain() { return 'bit'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class ConsumeOkConsumerTagField extends \amqphp\protocol\v0_9_1\ConsumerTagDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'consumer-tag'; }
    function getSpecFieldDomain() { return 'consumer-tag'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class DeliverExchangeField extends \amqphp\protocol\v0_9_1\ExchangeNameDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'exchange'; }
    function getSpecFieldDomain() { return 'exchange-name'; }

}

/** Ampq binding code, generated from doc version 0.9.1 */
class QosOkMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'basic';
    protected $name = 'qos-ok';
    protected $index = 11;
    protected $synchronous = true;
    protected $responseMethods = array();
    protected $fields = array();
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\basic\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\basic\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class ConsumeConsumerTagField extends \amqphp\protocol\v0_9_1\ConsumerTagDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'consumer-tag'; }
    function getSpecFieldDomain() { return 'consumer-tag'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class TimestampField extends \amqphp\protocol\v0_9_1\TimestampDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'timestamp'; }
    function getSpecFieldDomain() { return 'timestamp'; }

}

/** Ampq binding code, generated from doc version 0.9.1 */
class ConsumeMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'basic';
    protected $name = 'consume';
    protected $index = 20;
    protected $synchronous = true;
    protected $responseMethods = array('consume-ok');
    protected $fields = array('reserved-1', 'queue', 'consumer-tag', 'no-local', 'no-ack', 'exclusive', 'no-wait', 'arguments');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\basic\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\basic\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = true;
}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class ClusterIdField extends \amqphp\protocol\v0_9_1\ShortstrDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'cluster-id'; }
    function getSpecFieldDomain() { return 'shortstr'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class DeliverConsumerTagField extends \amqphp\protocol\v0_9_1\ConsumerTagDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'consumer-tag'; }
    function getSpecFieldDomain() { return 'consumer-tag'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class RecoverRequeueField extends \amqphp\protocol\v0_9_1\BitDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'requeue'; }
    function getSpecFieldDomain() { return 'bit'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class PublishExchangeField extends \amqphp\protocol\v0_9_1\ExchangeNameDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'exchange'; }
    function getSpecFieldDomain() { return 'exchange-name'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class ReturnReplyCodeField extends \amqphp\protocol\v0_9_1\ReplyCodeDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'reply-code'; }
    function getSpecFieldDomain() { return 'reply-code'; }

}

/** Ampq binding code, generated from doc version 0.9.1 */
class NackMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'basic';
    protected $name = 'nack';
    protected $index = 120;
    protected $synchronous = false;
    protected $responseMethods = array();
    protected $fields = array('delivery-tag', 'multiple', 'requeue');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\basic\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\basic\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class RejectRequeueField extends \amqphp\protocol\v0_9_1\BitDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'requeue'; }
    function getSpecFieldDomain() { return 'bit'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class ReturnReplyTextField extends \amqphp\protocol\v0_9_1\ReplyTextDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'reply-text'; }
    function getSpecFieldDomain() { return 'reply-text'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class ReturnRoutingKeyField extends \amqphp\protocol\v0_9_1\ShortstrDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'routing-key'; }
    function getSpecFieldDomain() { return 'shortstr'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class UserIdField extends \amqphp\protocol\v0_9_1\ShortstrDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'user-id'; }
    function getSpecFieldDomain() { return 'shortstr'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class GetReserved1Field extends \amqphp\protocol\v0_9_1\ShortDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'reserved-1'; }
    function getSpecFieldDomain() { return 'short'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class GetOkMessageCountField extends \amqphp\protocol\v0_9_1\MessageCountDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'message-count'; }
    function getSpecFieldDomain() { return 'message-count'; }

}

abstract class MethodFactory extends \amqphp\protocol\abstrakt\MethodFactory
{
    protected static $Cache = array(array(10, 'qos', '\\amqphp\\protocol\\v0_9_1\\basic\\QosMethod'),array(11, 'qos-ok', '\\amqphp\\protocol\\v0_9_1\\basic\\QosOkMethod'),array(20, 'consume', '\\amqphp\\protocol\\v0_9_1\\basic\\ConsumeMethod'),array(21, 'consume-ok', '\\amqphp\\protocol\\v0_9_1\\basic\\ConsumeOkMethod'),array(30, 'cancel', '\\amqphp\\protocol\\v0_9_1\\basic\\CancelMethod'),array(31, 'cancel-ok', '\\amqphp\\protocol\\v0_9_1\\basic\\CancelOkMethod'),array(40, 'publish', '\\amqphp\\protocol\\v0_9_1\\basic\\PublishMethod'),array(50, 'return', '\\amqphp\\protocol\\v0_9_1\\basic\\ReturnMethod'),array(60, 'deliver', '\\amqphp\\protocol\\v0_9_1\\basic\\DeliverMethod'),array(70, 'get', '\\amqphp\\protocol\\v0_9_1\\basic\\GetMethod'),array(71, 'get-ok', '\\amqphp\\protocol\\v0_9_1\\basic\\GetOkMethod'),array(72, 'get-empty', '\\amqphp\\protocol\\v0_9_1\\basic\\GetEmptyMethod'),array(80, 'ack', '\\amqphp\\protocol\\v0_9_1\\basic\\AckMethod'),array(90, 'reject', '\\amqphp\\protocol\\v0_9_1\\basic\\RejectMethod'),array(100, 'recover-async', '\\amqphp\\protocol\\v0_9_1\\basic\\RecoverAsyncMethod'),array(110, 'recover', '\\amqphp\\protocol\\v0_9_1\\basic\\RecoverMethod'),array(111, 'recover-ok', '\\amqphp\\protocol\\v0_9_1\\basic\\RecoverOkMethod'),array(120, 'nack', '\\amqphp\\protocol\\v0_9_1\\basic\\NackMethod'));
}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class ReplyToField extends \amqphp\protocol\v0_9_1\ShortstrDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'reply-to'; }
    function getSpecFieldDomain() { return 'shortstr'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class ConsumeExclusiveField extends \amqphp\protocol\v0_9_1\BitDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'exclusive'; }
    function getSpecFieldDomain() { return 'bit'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class ConsumeNoAckField extends \amqphp\protocol\v0_9_1\NoAckDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'no-ack'; }
    function getSpecFieldDomain() { return 'no-ack'; }

}

/** Ampq binding code, generated from doc version 0.9.1 */
class RecoverMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'basic';
    protected $name = 'recover';
    protected $index = 110;
    protected $synchronous = true;
    protected $responseMethods = array('recover-ok');
    protected $fields = array('requeue');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\basic\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\basic\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class ReturnExchangeField extends \amqphp\protocol\v0_9_1\ExchangeNameDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'exchange'; }
    function getSpecFieldDomain() { return 'exchange-name'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class AckMultipleField extends \amqphp\protocol\v0_9_1\BitDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'multiple'; }
    function getSpecFieldDomain() { return 'bit'; }

}

/** Ampq binding code, generated from doc version 0.9.1 */
class CancelOkMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'basic';
    protected $name = 'cancel-ok';
    protected $index = 31;
    protected $synchronous = true;
    protected $responseMethods = array();
    protected $fields = array('consumer-tag');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\basic\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\basic\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}

/** Ampq binding code, generated from doc version 0.9.1 */
class GetMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'basic';
    protected $name = 'get';
    protected $index = 70;
    protected $synchronous = true;
    protected $responseMethods = array('get-ok', 'get-empty');
    protected $fields = array('reserved-1', 'queue', 'no-ack');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\basic\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\basic\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class PriorityField extends \amqphp\protocol\v0_9_1\OctetDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'priority'; }
    function getSpecFieldDomain() { return 'octet'; }

}

/** Ampq binding code, generated from doc version 0.9.1 */
class ReturnMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'basic';
    protected $name = 'return';
    protected $index = 50;
    protected $synchronous = false;
    protected $responseMethods = array();
    protected $fields = array('reply-code', 'reply-text', 'exchange', 'routing-key');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\basic\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\basic\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = true;
    protected $hasNoWait = false;
}

/** Ampq binding code, generated from doc version 0.9.1 */
class ConsumeOkMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'basic';
    protected $name = 'consume-ok';
    protected $index = 21;
    protected $synchronous = true;
    protected $responseMethods = array();
    protected $fields = array('consumer-tag');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\basic\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\basic\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class ContentTypeField extends \amqphp\protocol\v0_9_1\ShortstrDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'content-type'; }
    function getSpecFieldDomain() { return 'shortstr'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class GetNoAckField extends \amqphp\protocol\v0_9_1\NoAckDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'no-ack'; }
    function getSpecFieldDomain() { return 'no-ack'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class GetOkExchangeField extends \amqphp\protocol\v0_9_1\ExchangeNameDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'exchange'; }
    function getSpecFieldDomain() { return 'exchange-name'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class ConsumeNoWaitField extends \amqphp\protocol\v0_9_1\NoWaitDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'no-wait'; }
    function getSpecFieldDomain() { return 'no-wait'; }

}

abstract class FieldFactory  extends \amqphp\protocol\abstrakt\FieldFactory
{
    protected static $Cache = array(array('content-type', '', '\\amqphp\\protocol\\v0_9_1\\basic\\ContentTypeField'),array('content-encoding', '', '\\amqphp\\protocol\\v0_9_1\\basic\\ContentEncodingField'),array('headers', '', '\\amqphp\\protocol\\v0_9_1\\basic\\HeadersField'),array('delivery-mode', '', '\\amqphp\\protocol\\v0_9_1\\basic\\DeliveryModeField'),array('priority', '', '\\amqphp\\protocol\\v0_9_1\\basic\\PriorityField'),array('correlation-id', '', '\\amqphp\\protocol\\v0_9_1\\basic\\CorrelationIdField'),array('reply-to', '', '\\amqphp\\protocol\\v0_9_1\\basic\\ReplyToField'),array('expiration', '', '\\amqphp\\protocol\\v0_9_1\\basic\\ExpirationField'),array('message-id', '', '\\amqphp\\protocol\\v0_9_1\\basic\\MessageIdField'),array('timestamp', '', '\\amqphp\\protocol\\v0_9_1\\basic\\TimestampField'),array('type', '', '\\amqphp\\protocol\\v0_9_1\\basic\\TypeField'),array('user-id', '', '\\amqphp\\protocol\\v0_9_1\\basic\\UserIdField'),array('app-id', '', '\\amqphp\\protocol\\v0_9_1\\basic\\AppIdField'),array('cluster-id', '', '\\amqphp\\protocol\\v0_9_1\\basic\\ClusterIdField'),array('prefetch-size', 'qos', '\\amqphp\\protocol\\v0_9_1\\basic\\QosPrefetchSizeField'),array('prefetch-count', 'qos', '\\amqphp\\protocol\\v0_9_1\\basic\\QosPrefetchCountField'),array('global', 'qos', '\\amqphp\\protocol\\v0_9_1\\basic\\QosGlobalField'),array('reserved-1', 'consume', '\\amqphp\\protocol\\v0_9_1\\basic\\ConsumeReserved1Field'),array('queue', 'consume', '\\amqphp\\protocol\\v0_9_1\\basic\\ConsumeQueueField'),array('consumer-tag', 'consume', '\\amqphp\\protocol\\v0_9_1\\basic\\ConsumeConsumerTagField'),array('no-local', 'consume', '\\amqphp\\protocol\\v0_9_1\\basic\\ConsumeNoLocalField'),array('no-ack', 'consume', '\\amqphp\\protocol\\v0_9_1\\basic\\ConsumeNoAckField'),array('exclusive', 'consume', '\\amqphp\\protocol\\v0_9_1\\basic\\ConsumeExclusiveField'),array('no-wait', 'consume', '\\amqphp\\protocol\\v0_9_1\\basic\\ConsumeNoWaitField'),array('arguments', 'consume', '\\amqphp\\protocol\\v0_9_1\\basic\\ConsumeArgumentsField'),array('consumer-tag', 'consume-ok', '\\amqphp\\protocol\\v0_9_1\\basic\\ConsumeOkConsumerTagField'),array('consumer-tag', 'cancel', '\\amqphp\\protocol\\v0_9_1\\basic\\CancelConsumerTagField'),array('no-wait', 'cancel', '\\amqphp\\protocol\\v0_9_1\\basic\\CancelNoWaitField'),array('consumer-tag', 'cancel-ok', '\\amqphp\\protocol\\v0_9_1\\basic\\CancelOkConsumerTagField'),array('reserved-1', 'publish', '\\amqphp\\protocol\\v0_9_1\\basic\\PublishReserved1Field'),array('exchange', 'publish', '\\amqphp\\protocol\\v0_9_1\\basic\\PublishExchangeField'),array('routing-key', 'publish', '\\amqphp\\protocol\\v0_9_1\\basic\\PublishRoutingKeyField'),array('mandatory', 'publish', '\\amqphp\\protocol\\v0_9_1\\basic\\PublishMandatoryField'),array('immediate', 'publish', '\\amqphp\\protocol\\v0_9_1\\basic\\PublishImmediateField'),array('reply-code', 'return', '\\amqphp\\protocol\\v0_9_1\\basic\\ReturnReplyCodeField'),array('reply-text', 'return', '\\amqphp\\protocol\\v0_9_1\\basic\\ReturnReplyTextField'),array('exchange', 'return', '\\amqphp\\protocol\\v0_9_1\\basic\\ReturnExchangeField'),array('routing-key', 'return', '\\amqphp\\protocol\\v0_9_1\\basic\\ReturnRoutingKeyField'),array('consumer-tag', 'deliver', '\\amqphp\\protocol\\v0_9_1\\basic\\DeliverConsumerTagField'),array('delivery-tag', 'deliver', '\\amqphp\\protocol\\v0_9_1\\basic\\DeliverDeliveryTagField'),array('redelivered', 'deliver', '\\amqphp\\protocol\\v0_9_1\\basic\\DeliverRedeliveredField'),array('exchange', 'deliver', '\\amqphp\\protocol\\v0_9_1\\basic\\DeliverExchangeField'),array('routing-key', 'deliver', '\\amqphp\\protocol\\v0_9_1\\basic\\DeliverRoutingKeyField'),array('reserved-1', 'get', '\\amqphp\\protocol\\v0_9_1\\basic\\GetReserved1Field'),array('queue', 'get', '\\amqphp\\protocol\\v0_9_1\\basic\\GetQueueField'),array('no-ack', 'get', '\\amqphp\\protocol\\v0_9_1\\basic\\GetNoAckField'),array('delivery-tag', 'get-ok', '\\amqphp\\protocol\\v0_9_1\\basic\\GetOkDeliveryTagField'),array('redelivered', 'get-ok', '\\amqphp\\protocol\\v0_9_1\\basic\\GetOkRedeliveredField'),array('exchange', 'get-ok', '\\amqphp\\protocol\\v0_9_1\\basic\\GetOkExchangeField'),array('routing-key', 'get-ok', '\\amqphp\\protocol\\v0_9_1\\basic\\GetOkRoutingKeyField'),array('message-count', 'get-ok', '\\amqphp\\protocol\\v0_9_1\\basic\\GetOkMessageCountField'),array('reserved-1', 'get-empty', '\\amqphp\\protocol\\v0_9_1\\basic\\GetEmptyReserved1Field'),array('delivery-tag', 'ack', '\\amqphp\\protocol\\v0_9_1\\basic\\AckDeliveryTagField'),array('multiple', 'ack', '\\amqphp\\protocol\\v0_9_1\\basic\\AckMultipleField'),array('delivery-tag', 'reject', '\\amqphp\\protocol\\v0_9_1\\basic\\RejectDeliveryTagField'),array('requeue', 'reject', '\\amqphp\\protocol\\v0_9_1\\basic\\RejectRequeueField'),array('requeue', 'recover-async', '\\amqphp\\protocol\\v0_9_1\\basic\\RecoverAsyncRequeueField'),array('requeue', 'recover', '\\amqphp\\protocol\\v0_9_1\\basic\\RecoverRequeueField'),array('delivery-tag', 'nack', '\\amqphp\\protocol\\v0_9_1\\basic\\NackDeliveryTagField'),array('multiple', 'nack', '\\amqphp\\protocol\\v0_9_1\\basic\\NackMultipleField'),array('requeue', 'nack', '\\amqphp\\protocol\\v0_9_1\\basic\\NackRequeueField'));
}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class RecoverAsyncRequeueField extends \amqphp\protocol\v0_9_1\BitDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'requeue'; }
    function getSpecFieldDomain() { return 'bit'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class GetOkRoutingKeyField extends \amqphp\protocol\v0_9_1\ShortstrDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'routing-key'; }
    function getSpecFieldDomain() { return 'shortstr'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class TypeField extends \amqphp\protocol\v0_9_1\ShortstrDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'type'; }
    function getSpecFieldDomain() { return 'shortstr'; }

}