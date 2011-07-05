<?php
      
namespace amqphp\protocol\v0_9_1\queue;
	
/** Ampq binding code, generated from doc version 0.9.1 */

class UnbindExchangeField extends \amqphp\protocol\v0_9_1\ExchangeNameDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'exchange'; }
    function getSpecFieldDomain() { return 'exchange-name'; }

}