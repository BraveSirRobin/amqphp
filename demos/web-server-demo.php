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
    protected $name;
    protected $view;

    function __construct ($name) {
        $this->name = $name;
    }


    function handleDelivery (wire\Method $meth, amqp\Channel $chan) {
        error_log(sprintf("[recv:%s]\n%s\n", $this->name, substr($meth->getContent(), 0, 10)));
        return amqp\CONSUMER_ACK;
    }
}


class DemoPConsumer extends DemoConsumer implements \Serializable
{
    function serialize () {
        return serialize($this->name);
    }

    function unserialize ($serialised) {
        $this->name = unserialize($serialised);
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



    private $publishParams = array(
        'content-type' => 'text/plain',
        'content-encoding' => 'UTF-8',
        'routing-key' => '',
        'mandatory' => false,
        'immediate' => false,
        'exchange' => 'most-basic');



    /**
     *
     */
    function __construct () {
        if (self::$Set) {
            throw new \Exception("PConnHelper is a singleton", 8539);
        }
        $this->CK = sprintf("%s:%s", $this->CK, getmypid());
        $this->wakeup();
    }

    /** Grab data from cache */
    protected function wakeup () {
        $srz = apc_fetch($this->CK, $flg);
        if ($flg) {
            // Connections should all wake up here.
            error_log(sprintf("Wakup cache %s", $this->CK));
            $this->cache = unserialize($srz);
        }
    }

    /**
     * Runs  shutdown sequence  and puts  connection refs  data  in to
     * cache
     */
    function sleep () {
        foreach ($this->cache as $conn) {
            if ($conn instanceof pconn\PConnection) {
                // TODO: Configurable sleep sequence
            } else if (false !== ($k = array_search($conn, $this->cache, true))) {
                $conn->shutdown();
                unset($this->cache[$k]);
            } else {
                throw new \Exception("Bad connection during shutdown", 2789);
            }
        }
        error_log(sprintf("Sleep cache %s", $this->CK));
        return apc_store($this->CK, serialize($this->cache));
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
            error_log(sprintf("Start pconnection with %s", print_r($params, true)));
            $conn = new pconn\PConnection($params);
            //$conn->setPersistenceHelperImpl('\\amqphp\\persistent\\FilePersistenceHelper');
            $conn->setPersistenceHelperImpl('\\amqphp\\persistent\\APCPersistenceHelper');
        } else {
            $conn = new amqp\Connection($params);
        }
        error_log("Connect....");
        $conn->connect();
        error_log("Done.");

        $this->cache[$key] = $conn;
    }

    /**
     * Close the given connection  and remove the reference from local
     * storage.
     */
    function removeConnection ($key) {
        if (array_key_exists($key, $this->cache)) {
            $this->cache[$key]->shutdown();
            unset($this->cache[$key]);
            error_log("Removed connection $key");
        }
    }


    /**
     * Opens a channel on the given connection with the given params.
     */
    function openChannel ($ckey, $chanParams) {
        if (array_key_exists($ckey, $this->cache)) {
            $this->cache[$ckey]->openChannel();
            error_log("Opened channel on connection $ckey OK");
        } else {
            error_log("No such channel: $ckey (" . implode(',', array_keys($this->cache)) . ')');
        }
    }

    /**
     * Closes the given channel on the given connection
     */
    function closeChannel ($ckey, $chanId) {
        if (array_key_exists($ckey, $this->cache) && 
            ($chan = $this->cache[$ckey]->getChannel($chanId))) {
            $chan->shutdown();
        }
    }

    /**
     * Adds a consumer to the given
     */
    function addConsumer ($ckey, $chanId, $impl) {
        if (array_key_exists($ckey, $this->cache)) {
            foreach ($this->cache[$ckey]->getChannels() as $chan) {
                if ($chan->getChanId() == $chanId) {
                    $cons = new $impl($tmp = rand());
                    $chan->addConsumer($cons);
                    error_log("Added a consumer of type $impl with name $tmp");
                    $this->cache[$ckey]->setSelectMode(amqp\SELECT_TIMEOUT_REL, 1, 500000);
                    $this->cache[$ckey]->select();
                    error_log("Select is finished");
                    return true;
                }
            }
        }
        return true;
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
        if (array_key_exists($ckey, $this->cache)) {
            $chans = $this->cache[$ckey]->getChannels();
            foreach ($chans as $chan) {
                if ($chan->getChanId() == $chanId) {
                    $bp = $chan->basic('publish', $this->publishParams, $msg);
                    $chan->invoke($bp);
                    return $this->cache[$ckey];
                }
            }
        }
        return false;
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

    function hasChannels () {
        foreach ($this->cache as $conn) {
            if ($conn->getChannels()) {
                return true;
            }
        }
        return false;
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
            $this->ch->addConsumer($ckey, $chanId, $impl);
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
                    $this->view->messages[] = $this->ch->sendMessage($ckey, $chanId, $msg)
                        ? "Message sent to $ckey.$chanId OK"
                        : "Message send to $ckey.$chanId failed";
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
