<?php
/**
 * 
 * Copyright (C) 2010, 2011  Robin Harvey (harvey.robin@gmail.com)
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.

 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.

 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 */
/**
 * This one forks worker threads based on a config file
 */
use amqp_091 as amqp;
use amqp_091\protocol;
use amqp_091\wire;

require __DIR__ . '/../amqp.php';




$CONFIG = XmlToArray(simplexml_load_file(__DIR__ . '/forker.xml')) OR die("Fault: failed to read configs!\n");
if ($CONFIG['saveMessagesDir']) {
    if (is_dir($CONFIG['saveMessagesDir'])) {
        define('RUN_TEMP_DIR', tempnam($CONFIG['saveMessagesDir'], 'forker-run-'));
        unlink(RUN_TEMP_DIR);
        if (! mkdir(RUN_TEMP_DIR)) {
            echo "Failed to create global temp dir " . RUN_TEMP_DIR . "\n";
            die;
        }
        printf("Messages will be saved to run dir %s\n", RUN_TEMP_DIR);
    } else {
        printf(" [ERROR] - message save dir does not exist: [%s]\n", $CONFIG['saveMessagesDir']);
    }
} else {
    define('RUN_TEMP_DIR', false);
    printf("Message saving is switched off\n");
}

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
printf("Test begins [PID=%d], %d children (%d producers, %d consumers)\n", posix_getpid(), $N_PROCS, $CONFIG['numProducers'], $CONFIG['numConsumers']);
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
            printf("[O] Child %d (%d) failed:\n%s\n", posix_getpid(), $i, $e->getMessage());
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

/** Utility class for process handlers - write subclasses to run a process
    and override the start() method. */
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
        //$connFact = new amqp\ConnectionFactory($this->fParams);
        //$this->conn = $connFact->newConnection();
        $this->conn = new amqp\Connection($this->fParams);
        $this->conn->connect();
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

// Pretty print the given backtrace, from debug_backtrace, return as string
function printBacktrace ($bt) {
    $r = '';
    foreach ($bt as $t) {
        if (isset($t['type']) && ($t['type'] == '->' || $t['type'] == '::')) {
            $r .= sprintf("Class call %s%s%s at %s [%s]\n", $t['class'], $t['type'], $t['function'], basename($t['file']), $t['line']);
        } else if (isset($t['function'])) {
            $r .= sprintf("Function call %s at %s [%s]\n", $t['function'], basename($t['file']), $t['line']);
        } else {
            $r .= sprintf("File %s [%s]\n", basename($t['file']), $t['line']);
        }
    }
    return $r;
}

/** Used to convert configuration XML in to array.  No attributes please!  */
function XmlToArray (SimpleXmlElement $simp) {
    $ret = array();
    foreach ($simp->children() as $v) {
        if ($v->count()) {
            $ret[$v->getName()] = XmlToArray($v);
        } else if (preg_match('/^\d+$/', (string) $v)) {
            $ret[$v->getName()] = (int) $v;
        } else {
            $ret[$v->getName()] = (string) $v;
        }
    }
    return $ret;
}


/** Return an XML serialized version of meth  */
function methodToXml (wire\Method $meth) {
    $w = new XmlWriter;
    $w->openMemory();
    $w->setIndent(true);
    $w->setIndentString('  ');
    $w->startElement('msg');
    $w->writeAttribute('class', $meth->getClassProto()->getSpecName());
    $w->writeAttribute('method', $meth->getMethodProto()->getSpecName());
    $w->writeAttribute('channel', $meth->getWireChannel());
    $w->startElement('class-fields');
    if ($meth->getClassFields()) {
        foreach ($meth->getClassFields() as $fn => $fv) {
            $w->startElement('field');
            $w->writeAttribute('name', $fn);
            if (is_bool($fv)) {
                $w->text('(false)');
            } else {
                $w->text($fv);
            }
            $w->endElement(); // field
        }
    }
    $w->endElement(); // class-fields


    $w->startElement('method-fields');
    if ($meth->getFields()) {
        foreach ($meth->getFields() as $fn => $fv) {
            $w->startElement('field');
            $w->writeAttribute('name', $fn);
            if (is_bool($fv)) {
                $w->text('(false)');
            } else {
                $w->text($fv);
            }
            $w->endElement(); // field
        }
    }
    $w->endElement(); // method-fields
    $w->startElement('content');
    $w->text($meth->getContent());
    $w->endElement(); // content
    $w->endElement(); // msg
    return $w->flush();
}
