<?php
      
namespace amqphp\protocol\v0_9_1\basic;
	
/** Ampq binding code, generated from doc version 0.9.1 */

class DeliverRedeliveredField extends \amqphp\protocol\v0_9_1\RedeliveredDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'redelivered'; }
    function getSpecFieldDomain() { return 'redelivered'; }

}