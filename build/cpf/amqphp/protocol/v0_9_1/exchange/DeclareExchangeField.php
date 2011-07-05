<?php
      
namespace amqphp\protocol\v0_9_1\exchange;
	
/** Ampq binding code, generated from doc version 0.9.1 */

class DeclareExchangeField extends \amqphp\protocol\v0_9_1\ExchangeNameDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'exchange'; }
    function getSpecFieldDomain() { return 'exchange-name'; }

    function validate($subject) {
        return (parent::validate($subject) && ! is_null($subject));
    }

}