<?php
/**
 * This one forks worker threads based on a config file
 */
use amqp_091 as amqp;
use amqp_091\protocol;
use amqp_091\wire;

require __DIR__ . '/../amqp.php';


$CONFIG = @parse_ini_file(__DIR__ . '/forker.ini') OR die("Fault: missing config file!\n");
//var_dump($CONFIG);

include "{$CONFIG['consumerClass']}.php";
include "{$CONFIG['producerClass']}.php";

if (! class_exists($CONFIG['consumerClass']) || ! class_exists($CONFIG['producerClass'])) {
    die("Consumer or Producer class is missing\n");
}

if (! ($N_PROCS = $CONFIG['numConsumers'] + $CONFIG['numProducers'])) {
    die("Must have some producers or consumers\n");
} else if (! ($SL_MILIS = (int) $CONFIG['parentSleepMillis'])) {
    die("No parent process sleep timeout set\n");
}

$PIDS = array();
printf("Test begins, %d children (%d producers, %d consumers)\n", $N_PROCS, $CONFIG['numProducers'], $CONFIG['numConsumers']);
for ($i = 0; $i < $N_PROCS; $i++) {
    $clazz = ($i < $CONFIG['numConsumers']) ? $CONFIG['consumerClass'] : $CONFIG['producerClass'];

    switch ($pid = pcntl_fork()) {
    case -1:
        die("Fork $i failed\n");
        break;
    case 0:
        // child
        try {
            $pHandler = new $clazz($i, $CONFIG);
            $pHandler->_start();
        } catch (Exception $e) {
            printf("[O] Child {$i} failed:\n%s\n", $e->getMessage());
        }
        die;
        break;
    default:
        // parent
        $PIDS[] = $pid;
        usleep($SL_MILIS);
        break;
    }
}

printf("Done.  PIDs are %s\n", implode(', ', $PIDS));



pcntl_signal(SIGINT, 'sigHand'); 
pcntl_signal(SIGTERM, 'sigHand');

while ($PIDS) {
    switch ($pid = pcntl_wait($status, WNOHANG|WUNTRACED)) {
    case -1:
        echo "Parent status returned error\n";
        die;
        break;
    case 0:
        usleep($SL_MILIS);
        pcntl_signal_dispatch();
        //echo "+";
        break;
    default:
        if (false !== ($k = array_search($pid, $PIDS))) {
            unset($PIDS[$k]);
            echo "Child $pid exited\n";
        } else {
            printf("Unknown PID returned: %d\n", $pid);
            die;
        }
    }
}



//
// ! Script ends !
//

// Main signal handler
function sigHand () {
    global $PIDS;
    echo "Global signal handler, signal children..\n";
    foreach ($PIDS as $pid) {
        if (! posix_kill($pid, SIGINT)) {
            echo " (failed to signal $pid)\n";
        } else {
            $wSt = pcntl_waitpid($pid, $status, WUNTRACED);
            $statStr = sprintf("%d%d%d%d%d%d", pcntl_wifexited($status), pcntl_wifstopped($status),
                               pcntl_wifsignaled($status), pcntl_wexitstatus($status),
                               pcntl_wtermsig($status), pcntl_wstopsig($status));
            if ($wSt == -1) {
                echo " E-[$statStr]";
            } else {
                echo " K-[$statStr]";
            }
        }
    }
    //sleep(1);
    echo " done\n";
    die;
}

class Forker
{
    protected $n;
    protected $fParams;

    protected $exchange;
    protected $queueName;
    protected $exchangeType;

    protected $conn;
    protected $chans = array();

    function __construct ($n, $fParams) {
        $this->n = $n;
        $this->fParams = $fParams;
    }

    final function _start () {
        try {
            $this->start();
        } catch (Exception $e) {
            printf("[I] Child {$this->n} failed:\n%s\n", $e->getMessage());
        }
        
    }

    function start () { echo "Forker $n starts\n"; }

    function initConnection () {
        $connFact = new amqp\ConnectionFactory($this->fParams);
        $this->conn = $connFact->newConnection();
    }

    function prepareChannel () {
        $chan = $this->conn->getChannel();
        if (! $this->chans) {
            /** NOTE: This is only here because the API forces you to create a channel before
                you can have access to Method construction functions.  Could either provide
                static accessors, or move some Amqp classes to the Connection ? */
            // Declare the exchange
            $excDecl = $chan->exchange('declare', array('type' => $this->fParams['exchangeType'],
                                                        'durable' => true,
                                                        'exchange' => $this->fParams['exchange']));
            $chan->invoke($excDecl);

            // Declare the queue
            $qDecl = $chan->queue('declare', array('queue' => $this->fParams['queueName']));
            $chan->invoke($qDecl);

            // Bind Q to EX
            $qBind = $chan->queue('bind', array('queue' => $this->fParams['queueName'],
                                                'routing-key' => '',
                                                'exchange' => $this->fParams['exchange']));
            $chan->invoke($qBind);
        }
        $this->chans[] = $chan;
        return $chan;
    }

    function shutdownConnection () {
        $this->conn->shutdown();
    }
}