<?php
      
namespace amqphp\protocol\v0_9_1\connection;
	
/** Ampq binding code, generated from doc version 0.9.1 */

class TuneOkChannelMaxField extends \amqphp\protocol\v0_9_1\ShortDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'channel-max'; }
    function getSpecFieldDomain() { return 'short'; }

    function validate($subject) {
        return (parent::validate($subject) && ! is_null($subject) && true);
    }

}