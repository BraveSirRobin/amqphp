<?php
/**
 * This  demo  is intended  to  run inside  a  webserver  to show  how
 * persistent connections  work.  The CPF distribution needs  to be in
 * 'pwd' in order for the classloader to work.
 **/

use amqphp as amqp,
    amqphp\persistent as pconn,
    amqphp\protocol,
    amqphp\wire;




/** A simple classloader for the Amqphp CPF distribution */
class DefaultLoader
{
    function load ($class) {
        $target = implode(DIRECTORY_SEPARATOR, explode('\\', $class)) . '.php';
        include $target;
        if (! (class_exists($class, false) || interface_exists($class, false))) {
            throw new Exception("Failed to load {$class} (2)", 6473);
        }
    }
}


/** Register the classloader */
if (false === spl_autoload_register(array(new DefaultLoader(), 'load'), false)) {
    throw new Exception("Failed to register loader", 8754);
}


/** Very simple view, just provides a context for a phtml include */
class View
{
    public $messages = array();

    function render ($file='web-controls.phtml') {
        include $file;
    }
}


/** Trivial consumer implementation */
class DemoConsumer extends amqp\SimpleConsumer
{
    private $name;
    private $view;

    function __construct ($name, $view) {
        $this->name = $name;
        $this->view = $view;
        if (! isset($this->view->received)) {
            $this->view->received = array();
        }
    }


    function handleDelivery (wire\Method $meth, amqp\Channel $chan) {
        $this->view->received[] = $_tmp = sprintf("[recv:%s]\n%s\n", $this->name, substr($meth->getContent(), 0, 10));
        return amqp\CONSUMER_ACK;
    }
}




/**
 * Uses a global  tracker cache to track open  PConnections, each time
 * it's woken up it re-opens existing connections.
 */
class PConnHelper
{
    private static $Set = false;

    private $cache = array();

    private $CK = '__pconn-helper-private';

    private $pState;

    /**
     *
     */
    function __construct () {
        if (self::$Set) {
            throw new \Exception("PConnHelper is a singleton", 8539);
        }
        $this->wakeup();
    }

    /** Grab data from cache */
    protected function wakeup () {
        $srz = apc_fetch($this->CK, $flg);
        if ($flg) {
            $this->cache = unserialize($srz);
            foreach ($this->cache as $conn) {
                $conn->connect();
            }
        }
    }

    /**
     * Runs  shutdown sequence  and puts  connection refs  data  in to
     * cache
     */
    function sleep () {
        foreach ($this->cache as $conn) {
            $this->shutdownConnection($conn);
        }
        return apc_store($this->CK, serialize($this->cache));
    }


    private function shutdownConnection ($conn) {
        if ($conn instanceof pconn\PConnection) {
            $conn->sleep();
        } else {
            $conn->shutdown();
        }
    }




    /**
     * Open a connection  with the given params and  store a reference
     * to it with $key
     */
    function addConnection ($key, $params, $persistent) {
        if (array_key_exists($key, $this->cache)) {
            throw new \Exception("Connection with key $key already exists", 2956);
        }

        $params['socketImpl'] = '\\amqphp\\StreamSocket';
        if ($persistent) {
            $conn = new pconn\PConnection($params);
            //$conn->setPersistenceHelperImpl('\\amqphp\\persistent\\FilePersistenceHelper');
            $conn->setPersistenceHelperImpl('\\amqphp\\persistent\\APCPersistenceHelper');
        } else {
            $conn = new amqp\Connection($params);
        }
        $conn->connect();

        $this->cache[$key] = $conn;
    }

    /**
     * Close the given connection  and remove the reference from local
     * storage.
     */
    function removeConnection ($key) {
        if (array_key_exists($key, $this->cache)) {
            $this->shutdownConnection($this->cache[$k]);
            unset($this->cache[$k]);
        }
    }


    /**
     * Opens a channel on the given connection with the given params.
     */
    function openChannel ($ckey, $chanParams) {
        if (! array_key_exists($ckey, $this->cache)) {
            $this->cache[$ckey]->openChannel();
        }
    }

    /**
     * Closes the given channel on the given connection
     */
    function closeChannel ($ckey, $chanId) {
        if (array_key_exists($ckey, $this->cache) && 
            ($chan = $this->cache[$key]->getChannel($chanId))) {
            // Here : Close / Cancel consumers?
        }
    }

    /**
     * Adds a consumer to the given
     */
    function addConsumer ($ckey, $chanId, $impl) {
    }

    /**
     * Remove the given consumer from the given connection/channel
     */
    function removeConsumer ($ckey, $chanId, $ctag) {
    }


    /**
     * Sends a single message to the given connection, channel.
     */
    function sendMessage ($ckey, $chanId, $msg) {
    }

    /**
     * Puts the given  connections in to an event  loop with the given
     * parameters.
     */
    function consume ($cons, $params) {
    }


    /**
     * Sends an ad-hock amqp method
     */
    function sendMethod ($ckey, $chanId, $clazz, $meth, $params) {
    }


    function getConnections () {
        return $this->cache;
    }
}


class Actions
{
    private $view;
    private $ch;

    /**
     * Routes   actions  to   handler  methods   as  per   foo-bar  to
     * fooBarAction
     */
    function _route (PConnHelper $ch, View $v, $action) {
        $this->view = $v;
        $this->ch = $ch;

        $meth = preg_replace('/(-(.{1}))/e', 'strtoupper(\'$2\')', $action) . 'Action';
        $this->view->messages[] = sprintf("Route %s to %s", $action, $meth);
        $this->view->messages[] = sprintf("Request: <pre>%s</pre>", print_r($_REQUEST, true));
        if (! method_exists($this, $meth)) {
            throw new \Exception("Failed to route action $action", 964);
        }
        return $this->$meth();
    }


    function newConnectionAction () {
        $key = $_REQUEST['name'];
        $params = $_REQUEST;
        $pers = array_key_exists('persistent', $_REQUEST);
        unset($params['name']);
        unset($params['persistent']);

        try {
            $this->ch->addConnection($key, $params, $pers);
        } catch (\Exception $e) {
            $this->view->messages[] = sprintf("Exception in %s [%d]: %s",
                                              __METHOD__, $e->getCode(), $e->getMessage());
        }
    }
    function removeConnectionAction () {
        $key = $_REQUEST['name'];

        try {
            $this->ch->removeConnection($key);
        } catch (\Exception $e) {
            $this->view->messages[] = sprintf("Exception in %s [%d]: %s",
                                              __METHOD__, $e->getCode(), $e->getMessage());
        }
    }

    function newChannelAction () {
        $ckey = $_REQUEST['connection'];
        $params = $_REQUEST;
        unset($params['connection']);

        try {
            $this->ch->openChannel($ckey, $params);
        } catch (\Exception $e) {
            $this->view->messages[] = sprintf("Exception in %s [%d]: %s",
                                              __METHOD__, $e->getCode(), $e->getMessage());
        }
    }
    function removeChannelAction () {
        $ckey = $_REQUEST['connection'];
        $chanId = $_REQUEST['channel'];

        try {
            $this->ch->closeChannel($ckey, $chanId);
        } catch (\Exception $e) {
            $this->view->messages[] = sprintf("Exception in %s [%d]: %s",
                                              __METHOD__, $e->getCode(), $e->getMessage());
        }
    }

    function newConsumerAction () {
        $ckey = $_REQUEST['connection'];
        $chanId = $_REQUEST['channel'];
        $impl = $_REQUEST['impl'];

        try {
            $this->ch->addconsumer($ckey, $chanId, $impl);
        } catch (\Exception $e) {
            $this->view->messages[] = sprintf("Exception in %s [%d]: %s",
                                              __METHOD__, $e->getCode(), $e->getMessage());
        }
    }


    function removeConsumerAction () {
        $ckey = $_REQUEST['connection'];
        $chanId = $_REQUEST['channel'];
        $ctag = $_REQUEST['consumer-tag'];

        try {
            $this->ch->removeConsumer($ckey, $chanId, $ctag);
        } catch (\Exception $e) {
            $this->view->messages[] = sprintf("Exception in %s [%d]: %s",
                                              __METHOD__, $e->getCode(), $e->getMessage());
        }
    }


    function invokeMethodAction () {}

    function sendAction () {
        $targets = $_REQUEST['target'];
        $msg = $_REQUEST['message'];

        try {
            foreach ($targets as $ckey => $chans) {
                foreach ($chans as $chanId) {
                    $this->ch->sendMessage($ckey, $chanId, $msg);
                }
            }
        } catch (\Exception $e) {
            $this->view->messages[] = sprintf("Exception in %s [%d]: %s",
                                              __METHOD__, $e->getCode(), $e->getMessage());
        }
    }

    function receiveAction () {
        $conns = $_REQUEST['connections'];

        try {
            $this->ch->consume($conns);
        } catch (\Exception $e) {
            $this->view->messages[] = sprintf("Exception in %s [%d]: %s",
                                              __METHOD__, $e->getCode(), $e->getMessage());
        }
    }


}








// MVC Runtime

$view = new View;
$actions = new Actions;
$chelper = new PConnHelper;

if (array_key_exists('action', $_REQUEST)) {
    $action = $_REQUEST['action'];
    if ($action[0] == '_') {
        throw new \Exception("Illegal action token $action", 10965);
    }
    $actions->_route($chelper, $view, $action);
}


$view->conns = $chelper;
$view->render();



$chelper->sleep();
