<?php
namespace amqphp\protocol\v0_9_1;
class PathDomain extends ShortstrDomain
{
    protected $name = 'path';
    protected $protocolType = 'shortstr';
    
    function validate($subject) {
        return (parent::validate($subject) && ! is_null($subject) && strlen($subject) < 127);
    }
    
}
