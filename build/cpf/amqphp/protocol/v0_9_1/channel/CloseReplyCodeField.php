<?php
      
namespace amqphp\protocol\v0_9_1\channel;
	
/** Ampq binding code, generated from doc version 0.9.1 */

class CloseReplyCodeField extends \amqphp\protocol\v0_9_1\ReplyCodeDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'reply-code'; }
    function getSpecFieldDomain() { return 'reply-code'; }

}