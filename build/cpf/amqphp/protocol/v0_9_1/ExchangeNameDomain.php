<?php
namespace amqphp\protocol\v0_9_1;
class ExchangeNameDomain extends ShortstrDomain
{
    protected $name = 'exchange-name';
    protected $protocolType = 'shortstr';
    
    function validate($subject) {
        return (parent::validate($subject) && strlen($subject) < 127 && preg_match("/^[a-zA-Z0-9-_.:]*$/", $subject));
    }
    
}
