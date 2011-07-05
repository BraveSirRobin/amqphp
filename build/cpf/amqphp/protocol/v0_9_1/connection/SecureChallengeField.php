<?php
      
namespace amqphp\protocol\v0_9_1\connection;
	
/** Ampq binding code, generated from doc version 0.9.1 */

class SecureChallengeField extends \amqphp\protocol\v0_9_1\LongstrDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'challenge'; }
    function getSpecFieldDomain() { return 'longstr'; }

}