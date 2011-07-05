<?php
      
namespace amqphp\protocol\v0_9_1\connection;
	
/** Ampq binding code, generated from doc version 0.9.1 */

class StartOkResponseField extends \amqphp\protocol\v0_9_1\LongstrDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'response'; }
    function getSpecFieldDomain() { return 'longstr'; }

    function validate($subject) {
        return (parent::validate($subject) && ! is_null($subject));
    }

}