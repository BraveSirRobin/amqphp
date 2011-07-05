<?php
namespace amqphp\protocol\v0_9_1;
class MessageCountDomain extends LongDomain
{
    protected $name = 'message-count';
    protected $protocolType = 'long';
    
}
