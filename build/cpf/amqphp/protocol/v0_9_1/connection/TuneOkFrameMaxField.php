<?php
      
namespace amqphp\protocol\v0_9_1\connection;
	
/** Ampq binding code, generated from doc version 0.9.1 */

class TuneOkFrameMaxField extends \amqphp\protocol\v0_9_1\LongDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'frame-max'; }
    function getSpecFieldDomain() { return 'long'; }

}