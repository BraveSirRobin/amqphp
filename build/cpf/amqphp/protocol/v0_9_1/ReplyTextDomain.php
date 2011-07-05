<?php
namespace amqphp\protocol\v0_9_1;
class ReplyTextDomain extends ShortstrDomain
{
    protected $name = 'reply-text';
    protected $protocolType = 'shortstr';
    
    function validate($subject) {
        return (parent::validate($subject) && ! is_null($subject));
    }
    
}
