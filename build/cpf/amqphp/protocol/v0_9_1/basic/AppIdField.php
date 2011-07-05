<?php
      
namespace amqphp\protocol\v0_9_1\basic;
	
/** Ampq binding code, generated from doc version 0.9.1 */

class AppIdField extends \amqphp\protocol\v0_9_1\ShortstrDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'app-id'; }
    function getSpecFieldDomain() { return 'shortstr'; }

}