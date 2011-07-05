<?php
      
namespace amqphp\protocol\v0_9_1\queue;
	
/** Ampq binding code, generated from doc version 0.9.1 */

class DeclareOkMessageCountField extends \amqphp\protocol\v0_9_1\MessageCountDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'message-count'; }
    function getSpecFieldDomain() { return 'message-count'; }

}