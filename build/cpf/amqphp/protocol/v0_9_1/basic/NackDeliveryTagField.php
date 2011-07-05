<?php
      
namespace amqphp\protocol\v0_9_1\basic;
	
/** Ampq binding code, generated from doc version 0.9.1 */

class NackDeliveryTagField extends \amqphp\protocol\v0_9_1\DeliveryTagDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'delivery-tag'; }
    function getSpecFieldDomain() { return 'delivery-tag'; }

}