<?php
      
namespace amqphp\protocol\v0_9_1\connection;
	
/** Ampq binding code, generated from doc version 0.9.1 */

class OpenVirtualHostField extends \amqphp\protocol\v0_9_1\PathDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'virtual-host'; }
    function getSpecFieldDomain() { return 'path'; }

}