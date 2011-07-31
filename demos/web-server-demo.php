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

    function __construct ($name) {
        parent::__construct(array('queue' => 'most-basic'));
        $this->name = $name;
    }


    function handleDelivery (wire\Method $meth, amqp\Channel $chan) {
        PConnHelper::ConsumerCallback(sprintf("[recv:%s]\n%s\n", $this->name, substr($meth->getContent(), 0, 10)));
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

    private $EX_NAME = 'most-basic';
    private $EX_TYPE = 'direct';
    private $Q = 'most-basic';


    private static $ConsMsg = array();
    static function ConsumerCallback ($msg) {
        self::$ConsMsg[] = $msg;
    }



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
        $params['signalDispatch'] = false;

        if ($persistent) {
            $conn = new pconn\PConnection($params);
//            $conn->setPersistenceHelperImpl('\\amqphp\\persistent\\APCPersistenceHelper');
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
            $this->cache[$key]->shutdown();
            unset($this->cache[$key]);
        }
    }


    /**
     * Opens a channel on the given connection with the given params.
     */
    function openChannel ($ckey, $chanParams) {
        if (array_key_exists($ckey, $this->cache)) {
            $chan = $this->cache[$ckey]->openChannel();

            $excDecl = $chan->exchange('declare', array(
                                           'type' => $this->EX_TYPE,
                                           'durable' => true,
                                           'exchange' => $this->EX_NAME));
            $eDeclResponse = $chan->invoke($excDecl); // Declare the queue


            if (false) {
                /**
                 * This code demonstrates  how to set a TTL  value on a queue.
                 * You have to  manually specify the table field  type using a
                 * TableField  object  because  RabbitMQ expects  the  integer
                 * x-message-ttl value to be  a long-long int (that's what the
                 * second parameter, 'l', in  the constructore means).  If you
                 * don't use a TableField,  Amqphp guesses the integer type by
                 * choosing the smallest possible storage type.
                 */
                $args = new \amqphp\wire\Table;
                $args['x-message-ttl'] = new \amqphp\wire\TableField(5000, 'l');

                $qDecl = $chan->queue('declare', array(
                                          'queue' => $this->Q,
                                          'arguments' => $args)); // Declare the Queue
            } else {
                $qDecl = $chan->queue('declare', array('queue' => $this->Q));
            }

            $chan->invoke($qDecl);

            $qBind = $chan->queue('bind', array(
                                      'queue' => $this->Q,
                                      'routing-key' => '',
                                      'exchange' => $this->EX_NAME));
            $chan->invoke($qBind);// Bind Q to EX


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
/*
                    if (! $chan->startConsumer($cons)) {
                        throw new \Exception("Failed to start consumer on {$ckey}.{$chanId}", 1778);
                    }
                    error_log("Force consume:");
                    $r = $this->consume(array($ckey));
                    error_log("...force complete.");
                    return $r;
*/
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
        if (array_key_exists($ckey, $this->cache)) {
            $chans = $this->cache[$ckey]->getChannels();
            foreach ($chans as $chan) {
                if ($chan->getChanId() == $chanId && $cons = $chan->getConsumerByTag($ctag)) {
                    return $chan->removeConsumer($cons);
                }
            }
        }
        return false;
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
    function consume ($cons) {
        $wd = false;
        $evl = new amqp\EventLoop;

        foreach ($cons as $k) {
            if (array_key_exists($k, $this->cache)) {
                $this->cache[$k]->setSelectMode(amqp\SELECT_TIMEOUT_REL, 1, 500000);
                $evl->addConnection($this->cache[$k]);
                $wd = true;
            }
        }
        if (! $wd) {
            throw new \Exception("No valid channels specified", 4940);
        } else {
            self::$ConsMsg = array();
            $evl->select();
            return self::$ConsMsg;
        }
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
            $this->view->messages[] = "Connection added OK";
        } catch (\Exception $e) {
            $this->view->messages[] = sprintf("Exception in %s [%d]: %s",
                                              __METHOD__, $e->getCode(), $e->getMessage());
        }
    }
    function removeConnectionAction () {
        $key = $_REQUEST['name'];

        try {
            $this->ch->removeConnection($key);
            $this->view->messages[] = "Connection removed OK";
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
            $this->view->messages[] = "New Channel added OK";
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
            $this->view->messages[] = "Channel Removed OK";
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
            $this->view->messages[] = "Consumer added OK";
        } catch (\Exception $e) {
            $this->view->messages[] = sprintf("Exception in %s [%d]: %s",
                                              __METHOD__, $e->getCode(), $e->getMessage());
            return;
        }
        $_REQUEST['connection'] = array($_REQUEST['connection']);
        $this->receiveAction();
    }


    function removeConsumerAction () {
        $ckey = $_REQUEST['connection'];
        $chanId = $_REQUEST['channel'];
        $ctag = $_REQUEST['consumer-tag'];

        try {
            $this->ch->removeConsumer($ckey, $chanId, $ctag);
            $this->view->messages[] = "Consumer removed OK";
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
            $m = array();
            foreach ($targets as $ckey => $chans) {
                foreach ($chans as $chanId) {
                    $m[] = array($ckey, $chanId, $msg);
                    $this->view->messages[] = $this->ch->sendMessage($ckey, $chanId, $msg)
                        ? "Message sent to $ckey.$chanId OK"
                        : "Message send to $ckey.$chanId failed";
                }
            }
            $this->view->sent = $m;
            $this->view->messages[] = "Message(s) sent OK";
        } catch (\Exception $e) {
            $this->view->messages[] = sprintf("Exception in %s [%d]: %s",
                                              __METHOD__, $e->getCode(), $e->getMessage());
        }
    }

    function receiveAction () {
        $conns = $_REQUEST['connection'];

        try {
            $this->view->received = $this->ch->consume($conns);
            $this->view->messages[] = "Message(s) received OK";
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
