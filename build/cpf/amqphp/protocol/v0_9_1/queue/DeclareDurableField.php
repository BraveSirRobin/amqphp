<?php
      
namespace amqphp\protocol\v0_9_1\queue;
	
/** Ampq binding code, generated from doc version 0.9.1 */

class DeclareDurableField extends \amqphp\protocol\v0_9_1\BitDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'durable'; }
    function getSpecFieldDomain() { return 'bit'; }

}