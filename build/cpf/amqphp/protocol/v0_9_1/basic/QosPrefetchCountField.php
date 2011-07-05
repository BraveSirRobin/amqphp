<?php
      
namespace amqphp\protocol\v0_9_1\basic;
	
/** Ampq binding code, generated from doc version 0.9.1 */

class QosPrefetchCountField extends \amqphp\protocol\v0_9_1\ShortDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'prefetch-count'; }
    function getSpecFieldDomain() { return 'short'; }

}