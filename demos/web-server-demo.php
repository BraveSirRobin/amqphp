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


    function getConsumeMethod (amqp\Channel $chan) {
        $r = $chan->basic('consume', $this->consumeParams);
        return $r;
    }

}




/**
 * Uses a global  tracker cache to track open  PConnections, each time
 * it's woken up it re-opens existing connections.
 */
class PConnHelper
{
    protected static $Set = false;

    protected $cache = array();

    protected $tempFileDir = '/tmp';

    protected $CK = '__pconn-helper-private';

    protected $pState;

    protected $EX_NAME = 'most-basic';
    protected $EX_TYPE = 'direct';
    protected $Q = 'most-basic';


    protected static $ConsMsg = array();
    static function ConsumerCallback ($msg) {
        self::$ConsMsg[] = $msg;
    }



    protected $publishParams = array(
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
        $this->CK = sprintf("%s_%s", $this->CK, getmypid());
        $this->wakeup();
    }

    /** Grab data from cache */
    protected function wakeup () {
        $file = $this->tempFileDir . DIRECTORY_SEPARATOR . $this->CK;
        if (is_file($file)) {
            $srz = file_get_contents($file);
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
        $file = $this->tempFileDir . DIRECTORY_SEPARATOR . $this->CK;
        return file_put_contents($file, serialize($this->cache));
    }



    /**
     * Open a connection  with the given params and  store a reference
     * to it with $key
     */
    function addConnection ($key, $params) {
        if (array_key_exists($key, $this->cache)) {
            throw new \Exception("Connection with key $key already exists", 2956);
        }

        $params['socketImpl'] = '\\amqphp\\StreamSocket';
        $params['signalDispatch'] = false;


        $conn = new pconn\PConnection($params);
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

    function getConnection ($k) {
        return array_key_exists($k, $this->cache)
            ? $this->cache[$k]
            : false;
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




/**
 * This demo over-rides the main demo and shows how to use "automatic"
 * persistent   connections.   We  cache   a  list   open  connections
 * separately, and allow the PConnection implementation to do it's own
 * thing.  Notice  how we re-create  the PConnection using  'new' each
 * time,  the  PConnection   implementation  handles  the  details  of
 * restoring the connection state.
 */
class PConnHelperAlt extends PConnHelper
{

    private $globalCacheFile = '/tmp/pconn-cache2';

    /**
     * Application  cache,  we  save  the  connection  params  of  the
     * PConnections so  that we can automatically  wake up connections
     * on subsequent requests.  Note that the actual PConnection cahce
     * is held elsewhere
     */
    private $altCache = array();

    function sleep () {
        $data = array();
        // Call sleep on all connections and collect a list of connection configs to put in *our* cache.
        foreach ($this->cache as $k => $conn) {
            $data[$k] = $this->altCache[$k];
            $conn->sleep();
        }

        // Load the full application cache, this might include connections for other processes
        if (is_file($this->globalCacheFile)) {
            $cache = unserialize(file_get_contents($this->globalCacheFile));
        } else {
            $cache = array();
        }

        // Update or add our cache
        $set = false;
        foreach ($cache as $k => $k2) {
            if ($k == getmypid()) {
                $cache[$k] = $data;
                $set = true;
                break;
            }
        }
        if (! $set) {
            $cache[getmypid()] = $data;
        }
        return file_put_contents($this->globalCacheFile, serialize($cache));
    }

    function wakeup () {
        if (is_file($this->globalCacheFile)) {
            $cache = unserialize(file_get_contents($this->globalCacheFile));
            foreach ($cache as $pid => $k) {
                if ($pid == getmypid()) {
                    // Found some connections for this process, wake em up!
                    foreach ($k as $key => $connParams) {
                        $this->cache[$key] = new pconn\PConnection($connParams);
                        $this->cache[$key]->setPersistenceHelperImpl('\\amqphp\\persistent\\FilePersistenceHelper');
                        $this->cache[$key]->connect();
                        $this->altCache[$key] = $connParams;
                    }
                }
            }
        }
    }


    /**
     * Override to save the connection params and set the automatic persistence handler
     */
    function addConnection ($key, $params) {
        parent::addConnection($key, $params);
        $this->cache[$key]->setPersistenceHelperImpl('\\amqphp\\persistent\\FilePersistenceHelper');
        $this->altCache[$key] = $params;
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
        unset($params['name']);

        try {
            $this->ch->addConnection($key, $params);
            $this->view->messages[] = "Connection added OK";
        } catch (\Exception $e) {
            error_log("Exception in newConnectionAction:\n {$e->getMessage()}");
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
            error_log("Exception in removeConnectionAction:\n {$e->getMessage()}");
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
            error_log("Exception in newChannelAction:\n {$e->getMessage()}");
            error_log(sprintf("newChannelAction: N undelivered = %d", count($this->ch->getConnection($ckey)->getUndeliveredMessages())));
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
            error_log("Exception in removeChannelAction:\n {$e->getMessage()}");
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
            error_log("Exception in newConsumerAction:\n {$e->getMessage()}");
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
            error_log("Exception in removeConsumerAction:\n {$e->getMessage()}");
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
            error_log("Exception in sendAction:\n {$e->getMessage()}");
            $this->view->messages[] = sprintf("Exception in %s [%d]: %s",
                                              __METHOD__, $e->getCode(), $e->getMessage());
        }
    }

    function receiveAction () {
        $conns = $_REQUEST['connection'];

        try {
            $this->view->received = $this->ch->consume($conns);
            $this->view->messages[] = sprintf("%d message(s) received OK", count($this->view->received));
        } catch (\Exception $e) {
            error_log("Exception in receiveAction:\n {$e->getMessage()}");
            $this->view->messages[] = sprintf("Exception in %s [%d]: %s",
                                              __METHOD__, $e->getCode(), $e->getMessage());
        }
    }
}








// MVC Runtime

$view = new View;
$actions = new Actions;
$chelper = new PConnHelper;
//$chelper = new PConnHelperAlt;

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
