<?php
      
namespace amqphp\protocol\v0_9_1\exchange;
	
/** Ampq binding code, generated from doc version 0.9.1 */

class UnbindDestinationField extends \amqphp\protocol\v0_9_1\ExchangeNameDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'destination'; }
    function getSpecFieldDomain() { return 'exchange-name'; }

}