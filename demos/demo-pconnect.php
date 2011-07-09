<?php
/**
 * This  demo  is intended  to  run inside  a  webserver  to show  how
 * persistent connections  work.  The CPF distribution needs  to be in
 * 'pwd' in order for the classloader to work.
 **/

use amqphp as amqp;
use amqphp\protocol;
use amqphp\wire;



/** Some hard-coded parameters. */
$conConfigs = array();
$conConfigs[] = array(
    'username' => 'testing',
    'userpass' => 'letmein',
    'vhost' => 'robin',
    'consumerName' => 'C1',
    'socketImpl' => '\amqphp\StreamSocket',
    'socketParams' => array('url' => 'tcp://rabbit1:5672'),
    'socketFlags' => array('STREAM_CLIENT_PERSISTENT'));

$conConfigs[] = array(
    'username' => 'testing',
    'userpass' => 'letmein',
    'vhost' => 'robin',
    'consumerName' => 'C2',
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

/** Very simple view, just provides a context for a phtml include */
class View
{
    public $messages = array('Hi!  I\'m a message');

    function render ($file='main-view.html') {
        include $file;
    }
}


class Demo
{
    private $pCons = array();

    private $conConfs = array();

    private $publishParams = array(
        'content-type' => 'text/plain',
        'content-encoding' => 'UTF-8',
        'routing-key' => '',
        'mandatory' => false,
        'immediate' => false,
        'exchange' => 'most-basic');


    function addConnectionDef ($cc) {
        $this->conConfs[] = $cc;
    }

    function startConnections () {
        foreach ($this->conConfs as $conf) {
            $conn = new amqp\PConnection($conf);
            $conn->setPersistenceHelperImpl('\\amqphp\\FilePersistenceHelper');
            $conn->connect();
            $chan = $conn->getChannel();
            $basicP = $chan->basic('publish', $this->publishParams);
            $this->pCons[] = array($conn, $chan, $basicP);
        }
    }

    function sleep () {
        foreach ($this->pCons as $stuff) {
            $stuff[1]->shutdown(); // Shut down channel only.
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
}

/** Register the classloader */
if (false === spl_autoload_register(array(new DefaultLoader(), 'load'), false)) {
    throw new Exception("Failed to register loader", 8754);
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
$d = new Demo();
foreach ($conConfigs as $cc) {
    $d->addConnectionDef($cc);
}


/** Always connect */
$d->startConnections();

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
    $view->messages[] = sprintf("Completed action %s", $task);
}

/** Render view */
$view->demo = $d;
$view->render();


/** Trailer tasks. */
if (in_array('disconnect', $tasks)) {
    $d->shutdown();
    error_log("Demo disconnect");
} else {
    $d->sleep();
    error_log("Demo sleep");
}

die;
//
// Script ends
//

function disconnectAction (View $v, Demo $d) {
    $v->messages[] = "You're disconnectin!";
}

function sendAction (View $v, Demo $d) {

    $v->messages[] = "You're sendin!";

}

function receiveAction (View $v, Demo $d) {

    $v->messages[] = "You're receivein!";

}