<?php
/**
 * Shared lib bits for the web demos.
 */
use amqphp as amqp,
    amqphp\persistent as pconn,
    amqphp\protocol,
    amqphp\wire;


/** Very simple view, just provides a context for a phtml include */
class View
{
    public $messages = array();

    function render ($file) {
        include $file;
    }
}



abstract class Router
{
    protected $view;
    protected $ch;

    /**
     * Routes   actions  to   handler  methods   as  per   foo-bar  to
     * fooBarAction
     */
    function _route (PConnHelper $ch, View $v, $action) {
        $this->view = $v;
        $this->ch = $ch;

        $meth = preg_replace('/(-(.{1}))/e', 'strtoupper(\'$2\')', $action) . 'Action';
        if (! method_exists($this, $meth)) {
            throw new \Exception("Failed to route action $action", 964);
        }
        return $this->$meth();
    }
}



/** Trivial consumer implementation */
class DemoConsumer extends amqp\SimpleConsumer
{
    public $name;

    function __construct ($cp) {
        parent::__construct($cp);
        $this->name = 'test-consumer-' . rand(0, 1000);
    }
}


class DemoPConsumer extends DemoConsumer implements \Serializable
{
    // Set the notification function here.
    public $nf;

    function serialize () {
        return serialize($this->name);
    }

    function unserialize ($serialised) {
        $this->name = unserialize($serialised);
    }


    function getConsumeMethod (amqp\Channel $chan) {
        error_log("DemoPConsumer ..consumer start..");
        $r = $chan->basic('consume', $this->consumeParams);
        return $r;
    }


    function handleDelivery (wire\Method $meth, amqp\Channel $chan) {
        if ($this->nf) {
            call_user_func($this->nf, $meth, $chan, $this);
        }
        return amqp\CONSUMER_ACK;
    }
}

class LoggingCEH implements amqp\ChannelEventHandler
{

    function publishConfirm (wire\Method $meth) {
        error_log("LoggingCEH: Receive pubish confirm");
    }

    function publishReturn (wire\Method $meth) {
        error_log("LoggingCEH: Receive publish return");
    }

    function publishNack(wire\Method $meth) {
        error_log("LoggingCEH: Receive publish Nack");
    }
}