<?php
      
namespace amqphp\protocol\v0_9_1\basic;
	
/** Ampq binding code, generated from doc version 0.9.1 */

class QosPrefetchSizeField extends \amqphp\protocol\v0_9_1\LongDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'prefetch-size'; }
    function getSpecFieldDomain() { return 'long'; }

}