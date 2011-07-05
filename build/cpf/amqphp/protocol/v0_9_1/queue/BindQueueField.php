<?php
      
namespace amqphp\protocol\v0_9_1\queue;
	
/** Ampq binding code, generated from doc version 0.9.1 */

class BindQueueField extends \amqphp\protocol\v0_9_1\QueueNameDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'queue'; }
    function getSpecFieldDomain() { return 'queue-name'; }

}