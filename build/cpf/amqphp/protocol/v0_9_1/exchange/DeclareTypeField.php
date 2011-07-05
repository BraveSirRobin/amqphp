<?php
      
namespace amqphp\protocol\v0_9_1\exchange;
	
/** Ampq binding code, generated from doc version 0.9.1 */

class DeclareTypeField extends \amqphp\protocol\v0_9_1\ShortstrDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'type'; }
    function getSpecFieldDomain() { return 'shortstr'; }

}