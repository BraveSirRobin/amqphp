<?php
      
namespace amqphp\protocol\v0_9_1\connection;
	
/** Ampq binding code, generated from doc version 0.9.1 */

class StartVersionMinorField extends \amqphp\protocol\v0_9_1\OctetDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'version-minor'; }
    function getSpecFieldDomain() { return 'octet'; }

}