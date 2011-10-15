<?php
/**
 * This  demo  is  intended  to  test real-life  usage  of  persistent
 * connections.   A  single Connection  /  Broker  config  is used  to
 * establish the same Connection / Channel setup on all worker threads
 * of a PHP server, the user can then send and receive messages.
 */
use amqphp as amqp,
    amqphp\persistent as pconn,
    amqphp\protocol,
    amqphp\wire;


// Must match webdepl.libdir property
const LIBDIR = 'amqphp-libs';

// Must match webdepl.viewdir property
const VIEWDIR = 'amqphp-view';

// Must match webdepl.confdir property
const CONFDIR = 'amqphp-config';

if (0) {
    // Use the NSPF distribution
    define('DEMO_LOAD_NSPF', true);
    define('DEMO_LOADER_DIR', sprintf("%s%s%s%s%s%s",
                                      __DIR__,
                                      DIRECTORY_SEPARATOR,
                                      LIBDIR,
                                      DIRECTORY_SEPARATOR,
                                      'nspf',
                                      DIRECTORY_SEPARATOR));
} else {
    // Use the CPF distribution and a class loader
    define('DEMO_LOADER_DIR', sprintf("%s%s%s%s%s%s",
                                      __DIR__,
                                      DIRECTORY_SEPARATOR,
                                      LIBDIR,
                                      DIRECTORY_SEPARATOR,
                                      'cpf',
                                      DIRECTORY_SEPARATOR));
}
require LIBDIR . DIRECTORY_SEPARATOR . 'demo-loader.php';
require LIBDIR . DIRECTORY_SEPARATOR . 'web-common.php';



/**
 * Uses automatic Amqphp persistence and keeps a central store of open
 * connections so  that the interface  can display information  on all
 * open connections.
 */
class MultiProcessPCTest
{

    // WARNING - If you restart your web server you must delete this file!!
    // Persistence format: array(<procid> => <created timestamp>);
    public $connStore = '/tmp/MultiProcessPCTest-runtime.txt';
    public $connections = array();
    public $allProcs = array();
    public $connConfig;
    public $view;

    function start ($config) {
        $fact = new amqp\Factory($config);
        $this->connections = $fact->getConnections();

        // Read in the server process tracker data and make sure this process is in it.
        if ($buff = @file_get_contents($this->connStore)) {
            $this->allProcs = unserialize($buff);
        }
        $p = getmypid();
        if (! array_key_exists($p, $this->allProcs)) {
            $this->allProcs[$p] = time();
        }

        // Parse the connections config so thisis available in the template
        $d = new DOMDocument;
        $d->load($config);
        $d->xinclude();
        if (! ($this->connConfig = simplexml_import_dom($d))) {
            throw new \Exception("Invalid setup format.", 92656);
        }
    }

    /**
     * Sleep  the local connections  (automatic persistence)  and save
     * this processes connection meta data to the central store
     */
    function sleep () {
        foreach ($this->connections as $i => $c) {
            $c->sleep();
        }

        file_put_contents($this->connStore, serialize($this->allProcs));
    }


    function runCommands ($c) {
        try {
            foreach ($c as $cmd) {
                switch ($cmd['cmd_type']) {
                case 'consume':
                    MultiProcessPCTest::$CC = 0;
                    $this->doConsume($cmd);
                    break;
                case 'publish':
                    $this->doPublish($cmd);
                    break;
                default:
                    $this->view->messages[] = sprintf('Invalid command type: %s', $cmd['cmd_type']);
                }
            }
        } catch (Exception $e) {
            $this->view->messages[] = sprintf("Exception in command loop:<br/>%s", $e->getMessage());
        }
    }

    public static $CC = 0;

    private function doConsume ($params) {
        // Locate target connection
        $conn = false;
        foreach ($this->connections as $i => $c) {
            if ($i == $params['connection']) {
                $conn = $c;
                break;
            }
        }
        if (! $conn) {
            $this->view->messages[] = sprintf("Cannot consume on connection %d - no such connection", $params['connection']);
            return;
        }

        // Attach an output alert function to any connected consumers
        $nf = function (wire\Method $meth, amqp\Channel $chan, $cons) {
            error_log(sprintf("[multi-recv:%s]\n%s\n", $cons->name, substr($meth->getContent(), 0, 10)));
            MultiProcessPCTest::$CC++;
        };
        foreach ($conn->getChannels() as $chan) {
            foreach ($chan->getConsumerTags() as $t) {
                error_log(sprintf("Add callback to consumer %d-%d-%s", $params['connection'], $chan->getChanId(), $t));
                $chan->getConsumerByTag($t)->nf = $nf;
            }
        }

        // Invoke the consume
        switch ($params['type']) {
        case 'maxloops':
            $loops = ($params['param1'] && is_numeric($params['param1'])) ?
                (int) $params['param1']
                : 1;
            $conn->pushExitStrategy(amqp\STRAT_MAXLOOPS, $loops);
            $conn->select();
            $this->view->messages[] = sprintf("Consumed %d messages on connection %d via. maxloops with trigger %d",
                                              MultiProcessPCTest::$CC, $params['connection'], $loops);
            break;
        case 'timeout':
            // Sanitize the input.
            $secs = ($params['param1'] && is_numeric($params['param1'])) ?
                (int) $params['param1']
                : 1;
            $usecs = ($params['param2'] && is_numeric($params['param2'])) ?
                (int) $params['param2']
                : 0;
            $conn->pushExitStrategy(amqp\STAT_TIMEOUT_REL, (string) $secs, (string) $usecs);
            $conn->select();
            $this->view->messages[] = sprintf("Consumed %d messages on connection %d via. timeout with trigger (%s, %s)",
                                              MultiProcessPCTest::$CC, $params['connection'], (string) $secs, (string) $usecs);
            break;
        default:
            $this->view->messages[] = sprintf("Unknown / not implemented consume type: %s", $params['type']);
            return;
        }

    }

    /**
     * Publishes messages as specified in $params
     */
    private function doPublish ($params) {
        // Collect the channels that will be published on
        $chans = array();
        foreach (array_keys($params['chans']) as $chanDef) {
            list($connId, $chanId) = explode('.', $chanDef);
            foreach ($this->connections as $i => $conn) {
                if ($i == $connId) {
                    foreach ($conn->getChannels() as $j => $chan) {
                        if ($j == $chanId) {
                            $chans[] = $chan;
                            break;
                        }
                    }
                    break;
                }
            }
        }
        if (! $chans) {
            $this->view->messages[] = "Error: no channels were found!";
        }
        $pubParams = array();
        foreach ($params['params'] as $p => $v) {
            if ($p == 'mandatory' || $p == 'immediate') {
                $pubParams[$p] = true;
            } else {
                $pubParams[$p] = $v;
            }
        }

        $np = 0;
        foreach ($chans as $chan) {
            $bp = $chan->basic('publish', $pubParams, $params['content']);
            for ($j = 0; $j < (int) $params['repeat']; $j++) {
                $chan->invoke($bp);
                $np++;
            }
        }
        $this->view->messages[] = sprintf("Published %d messages to %d channels", $np, count($chans));
    }
}


function dump () {
    ob_start();
    foreach (func_get_args() as $arg) {
        var_dump($arg);
    }
    return ob_get_clean();
}




class Actions extends Router
{

    function sendMessages () {
        $this->ch == 'MultiProcessPCTest';
    }

}





//
// MVC Runtime.
//

$view = new View;
$actions = new Actions;
$chelper = new MultiProcessPCTest;


$chelper->start(CONFDIR . DIRECTORY_SEPARATOR . 'web-multi.xml');
$chelper->view = $view;

if (array_key_exists('cmds', $_POST)) {
    $chelper->runCommands($_POST['cmds']);
}



$view->helper = $chelper;
$view->render(VIEWDIR . DIRECTORY_SEPARATOR . 'web-controls-multi.phtml');



$chelper->sleep();

