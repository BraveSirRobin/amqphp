<?php
      
namespace amqphp\protocol\v0_9_1\basic;
	
/** Ampq binding code, generated from doc version 0.9.1 */

class PriorityField extends \amqphp\protocol\v0_9_1\OctetDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'priority'; }
    function getSpecFieldDomain() { return 'octet'; }

}