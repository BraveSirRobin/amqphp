<?php

use amqphp as amqp,
    amqphp\persistent as pconn,
    amqphp\protocol,
    amqphp\wire;

/**
 * Reads and  creates a set  of connection configurations from  an XML
 * document.
 */
class Setup
{


    /** Scalar fields that are turned in to Amqp parameters.  */
    // TODO: Add in cast parameters, i.e. (string), (int), etc.
    protected $rootFields = array('host' => 'string',
                                  'vhost' => 'string',
                                  'username' => 'string',
                                  'userpass' => 'string');
    protected $exFields = array('type' => 'string',
                                'durable' => 'boolean',
                                'exchange' => 'string');
    protected $qFields = array('queue' => 'string');
    protected $bindFields = array('queue' => 'string',
                                  'routing_key' => 'string',
                                  'exchange' => 'string');


    private function kast ($val, $cast) {
        switch ($cast) {
        case 'string':
            return (string) $val;
        case 'boolean':
            return (boolean) $val;
        case 'int':
            return (int) $val;
        default:
            trigger_error("Unknown Kast $cast", E_USER_WARNING);
            return (string) $val;
        }
    }

    private function xmlToArray (SimpleXmlElement $e) {
        $ret = array();
        foreach ($e as $c) {
            $ret[(string) $c->getName()] = (count($c) == 0)
                ? (string) $c
                : $this->xmlToArray($c);
        }
        return $ret;
    }


    private function params (SimpleXmlElement $e, $field) {
        $params = array();
        foreach ($this->$field as $f => $k) {
            $params[$f] = $this->kast($e->$f, $k);
        }
        return $params;
    }


    function getSetup ($xml) {
        if (! ($simp = simplexml_load_string($xml))) {
            throw new \Exception("Invalid setup format.", 92656);
        }

        $conns = array();
        $_chanid = 0; // deleteme

        foreach ($simp->connection as $conn) {
            $_chans = array();

            // Create connection and connect
            $impl = (string) $conn->impl;
            $_conn = new $impl($this->xmlToArray($conn->server->children()));
            $_conn->connect();




            // Create channels
            foreach ($conn->channel as $chan) {
                $_chan = $_conn->openChannel();
                foreach ($chan->consumer as $cons) {
                    // Add consumers
                    $impl = (string) $cons->impl;
                    $_chan->addConsumer(new $impl((string) $cons->queue));
                }
                $_chans[] = $_chan;
            }
            if (! $_chans) {
                throw new \Exception("You must define at least one channel");
            }
            $_chan = reset($_chans);


            // Create exchanges
            foreach ($conn->exchange as $ex) {
                $m = $_chan->exchange('declare', $this->params($ex, 'exFields'));
                printf("exchange.declare:\n%s", print_r($this->params($ex, 'exFields'), true));
                $_chan->invoke($m);
            }

            // Create Qs
            foreach ($conn->queue as $q) {
                $m = $_chan->queue('declare', $this->params($q, 'qFields'));
                printf("queue.declare:\n%s", print_r($this->params($ex, 'exFields'), true));
                $_chan->invoke($m);
            }

            // Create bindings
            foreach ($conn->binding as $q) {
                $m = $_chan->queue('bind', $this->params($q, 'bindFields'));
                printf("queue.bind:\n%s", print_r($this->params($ex, 'bindFields'), true));
                $_chan->invoke($m);
            }
            $conns[] = $_conn;

            // Execute whatever methods are supplied.
            foreach ($conn->method as $m) {
                printf("Test Method %s.%s:\n%s", $m->class, $m->method, print_r($this->xmlToArrayKast($m), true));
            }
        }
        return $conns;
    }



    private function xmlToArrayKast (SimpleXmlElement $e) {
        $ret = array();
        foreach ($e as $c) {
            printf("Xml Kast %s\n", (string) $c['k']);
            $ret[(string) $c->getName()] = (count($c) == 0)
                ? $this->kast($c, (string) $c['k'])
                : $this->xmlToArrayKast($c);
        }
        return $ret;
    }


}
