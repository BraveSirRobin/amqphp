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



/** Some hard-coded parameters. */
$conConfigs = array();
$conConfigs[] = array(
    'username' => 'testing',
    'userpass' => 'letmein',
    'vhost' => 'robin',
    'consumerName' => 'C1',
    'signalDispatch' => false,
    'socketImpl' => '\amqphp\StreamSocket',
    'socketParams' => array('url' => 'tcp://rabbit1:5672'),
    'socketFlags' => array('STREAM_CLIENT_PERSISTENT'));

$conConfigs[] = array(
    'username' => 'testing',
    'userpass' => 'letmein',
    'vhost' => 'robin',
    'consumerName' => 'C2',
    'signalDispatch' => false,
    'socketImpl' => '\amqphp\StreamSocket',
    'socketParams' => array('url' => 'tcp://rabbit2:5672'),
    'socketFlags' => array('STREAM_CLIENT_PERSISTENT'));


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
    public $messages = array('Hi!  I\'m a message');

    function render ($file='main-view.html') {
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
        error_log("Recv: $_tmp\n");
        return amqp\CONSUMER_ACK;
    }
}



/** A class to contain connections and run tests */
class Demo
{

    private $EX_NAME = 'most-basic';
    private $EX_TYPE = 'direct';
    private $Q = 'most-basic';


    private $pCons = array();

    private $conConfs = array();

    private $publishParams = array(
        'content-type' => 'text/plain',
        'content-encoding' => 'UTF-8',
        'routing-key' => '',
        'mandatory' => false,
        'immediate' => false,
        'exchange' => 'most-basic');

    private $v;


    function __construct (View $v) {
        $this->v = $v;
    }


    /**
     * Initialise the Q and binding
     */
    function initialiseDemo ($chan) {
        $excDecl = $chan->exchange('declare', array(
                                       'type' => $this->EX_TYPE,
                                       'durable' => true,
                                       'exchange' => $this->EX_NAME));
        $eDeclResponse = $chan->invoke($excDecl); // Declare the exchange

        $qDecl = $chan->queue('declare', array('queue' => $this->Q));

        $chan->invoke($qDecl);
        $qBind = $chan->queue('bind', array(
                                  'queue' => $this->Q,
                                  'routing-key' => '',
                                  'exchange' => $this->EX_NAME));
        $chan->invoke($qBind);// Bind Q to EX
        $this->v->messages[] = sprintf("Initialised Q and bindings for chan %s", spl_object_hash($chan));
    }




    function addConnectionDef ($cc) {
        $this->conConfs[] = $cc;
    }

    function startConnections () {
        foreach ($this->conConfs as $conf) {
            $conn = new pconn\PConnection($conf);
            //$conn->setPersistenceHelperImpl('\\amqphp\\persistent\\FilePersistenceHelper');
            $conn->setPersistenceHelperImpl('\\amqphp\\persistent\\APCPersistenceHelper');
            $conn->connect();

            /** 
             * Because this  connection  persists channels  we have to
             * check  that  the  channel  isn't  already  open  before
             * opening it
             */
            if ($conn->getPersistenceStatus() == pconn\PConnection::SOCK_NEW) {
                error_log("Open new channel");
                $chan = $conn->openChannel();
            } else {
                $chan = $conn->getChannel(1);
                error_log("Get existing channel (" . gettype($chan) . ')');
            }
            $basicP = $chan->basic('publish', $this->publishParams);
            $this->pCons[] = array($conn, $chan, $basicP);
            if ($conn->getPersistenceStatus() == pconn\PConnection::SOCK_NEW) {
                $this->initialiseDemo($chan);
            }
        }
    }

    function sleep () {
        foreach ($this->pCons as $stuff) {
            //$stuff[1]->shutdown(); // Shut down channel only.
            $stuff[0]->sleep();
        }
    }


    function shutdown () {
        foreach ($this->pCons as $stuff) {
            $stuff[1]->shutdown();
            $stuff[0]->shutdown();
        }
    }

    function getConnections () {
        return array_map(function ($mi) { return $mi[0]; }, $this->pCons);
    }

    /**
     * Sends  the given  message to  the given  connection, or  to all
     * connections if not specified
     */
    function sendMessage ($m, $cid=false) {
        if (false !== $cid && array_key_exists($cid, $this->pCons)) {
            $conns = array($this->pCons[$cid]);
        } else {
            $conns = $this->pCons;
        }
        $r = 0;
        if (! isset($this->v->sent)) {
            $this->v->sent = array();
        }
        foreach ($conns as $i => $c) {
            $c[2]->setContent($m);
            $c[1]->invoke($c[2]);
            $r++;
            $this->view->sent[] = sprintf("Sent message %s to %d", $m, $i);
            error_log("Message sent.");
        }
        return $r;
    }

    /**
     * Consumer messages from connections with given params
     */
    function consume ($mode, $params) {
        $el = new amqp\EventLoop;

        // Set the desired modes and add consumers.
        foreach ($this->pCons as $i => $c) {
            // TODO: Figure out why this is needed
            //$this->initialiseDemo($c[1]);
            $qosParams = array('prefetch-count' => 1,
                               'global' => false);
            $qOk = $c[1]->invoke($c[1]->basic('qos', $qosParams));


            $cons = new DemoConsumer("Consumer channel #{$i}", $this->v);
            if ($mode == 'relative') {
                $c[0]->setSelectMode(amqp\SELECT_TIMEOUT_REL, $params[0], $params[1]);
            } else {
                $c[0]->setSelectMode(amqp\SELECT_MAXLOOPS, $params[0]);
            }
            $c[1]->addConsumer($cons);
            $el->addConnection($c[0]);
        }
        $el->select();
        foreach ($this->pCons as $c) {
            $c[1]->removeAllConsumers();
        }
        // Here.  Need to add a disconnect helper to the Channel to be able to
        // stop consuming (re-instate removeConsumer() !!!)
        // Alternatively, don't call removeConsumer() and make the demo Consumer persistable
    }
}




/** Load some parameters from the request */
$message = array_key_exists('message', $_REQUEST)
    ? $_REQUEST['message']
    : 'Default message...';
$tasks = isset($_REQUEST['action']['tasks'])
    ? $_REQUEST['action']['tasks']
    : array();

$view = new View;

/** Set up the persistent connection demo helper */
$d = new Demo($view);
foreach ($conConfigs as $cc) {
    $d->addConnectionDef($cc);
}


/** Always connect */
$d->startConnections();

/** Check to see if we need to re-order tasks */
$iSend = array_search('send', $tasks);
$iRcv = array_search('receive', $tasks);
if ($iSend !== false && $iRcv !== false) {
    switch ($_REQUEST['action']['web-req-seq']) {
    case 'send_first':
        if ($iRcv < $iSend) {
            $tmp = $tasks[$iSend];
            $tasks[$iSend] = $tasks[$iRcv];
            $tasks[$iRcv] = $tmp;
        }
        break;
    case 'receive_first':
        if ($iSend < $iRcv) {
            $tmp = $tasks[$iRcv];
            $tasks[$iRcv] = $tasks[$iSend];
            $tasks[$iSend] = $tmp;
        }
        break;
    }
}

/** Perform the desired action. */
foreach ($tasks as $task) {
    switch ($task) {
    case 'disconnect':
        disconnectAction($view, $d);
        break;
    case 'send':
        sendAction($view, $d);
        break;
    case 'receive':
        receiveAction($view, $d);
        break;
    }
}

/** Render view */
$view->demo = $d;
$view->render();


/** Trailer tasks. */


$d->sleep();
/*
if (in_array('disconnect', $tasks)) {
    $d->shutdown();
    error_log("Demo disconnect");
} else {

    error_log("Demo sleep");
}
*/
die;
//
// Script ends
//

function disconnectAction (View $v, Demo $d) {
    $v->messages[] = "You're disconnectin!";
}

function sendAction (View $v, Demo $d) {
    $params = $_REQUEST['send'];

    if ($_REQUEST['send']['chans'] == 'cycle') {
        $v->messages[] = "Cycle mode send not yet implemented!";
    } else {
        $v->messages[] = "Send messages to all channels";
        foreach ($params['messages'] as $m) {
            $d->sendMessage($m);
        }
    }
}

function receiveAction (View $v, Demo $d) {
    if ($_REQUEST['read']['select-mode'] == 'relative') {
        $params = array();
        foreach (array('select-rel-secs', 'select-rel-usecs') as $param) {
            if (! isset($_REQUEST['read'][$param]) || ! is_numeric($_REQUEST['read'][$param])) {
                $v->messages[] = "Invalid or missing value for select mode parameter $param";
                return;
            }
            $params[] = (int) $_REQUEST['read'][$param];
        }
    } else {
        $params = array();
        if (! isset($_REQUEST['read']['select-maxloops-num']) || ! is_numeric($_REQUEST['read']['select-maxloops-num'])) {
            $v->messages[] = "Invalid or missing value for select mode parameter $param";
            return;
        }
        $params[] = (int) $_REQUEST['read']['select-maxloops-num'];
    }

    $d->consume($_REQUEST['read']['select-mode'], $params);
}

/**

Actions:
-------

Send message(s)

Receive Messages

Start channel

Close channel

Close connection


 */