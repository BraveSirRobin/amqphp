<?php
namespace amqphp\protocol\v0_9_1;
class ReplyCodeDomain extends ShortDomain
{
    protected $name = 'reply-code';
    protected $protocolType = 'short';
    
    function validate($subject) {
        return (parent::validate($subject) && ! is_null($subject));
    }
    
}
