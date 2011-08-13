<?php

use amqphp as amqp,
    amqphp\persistent as pconn,
    amqphp\protocol,
    amqphp\wire;

/**
 * Reads and  creates a set  of connection configurations from  an XML
 * document.   The  XML document  can  specify connections,  channels,
 * consumers   and  Amqp   methods,   for  example   exchange.declare,
 * queue.declare, etc.
 */
class Setup
{


    /**
     * Factory  method  -  create  and  return a  set  of  Connections
     * corresponding  to the  given XML.   The given  XML  can contain
     * xincludes,  these  are  processed.   If  a  re-used  persistent
     * connection is encountered, channel and other methods invocation
     * is skipped.
     *
     * @arg  string    $xml             Either a string containing XML or the name of an XML file.
     * @arg  mixed     $documentURI     If specified, $xml is 
     */
    function getSetup ($xml) {
        $d = new DOMDocument;
        $d->load($xml);
        $d->xinclude();
        if (! ($simp = simplexml_import_dom($d))) {
            throw new \Exception("Invalid setup format.", 92656);
        }

        $conns = array();
        $_chanid = 0; // deleteme

        foreach ($simp->connection as $conn) {
            $_chans = array();

            // Create connection and connect
            $impl = (string) $conn->impl;
            $_conn = new $impl($this->xmlToArray($conn->server->children()));
            if (isset($conn->persistence)) {
                $_conn->setPersistenceHelperImpl((string) $conn->persistence);
            }
            $_conn->connect();

            if ($_conn instanceof pconn\PConnection && $_conn->getPersistenceStatus() == pconn\PConnection::SOCK_REUSED) {
                // Assume that the setup is complete for existing PConnection
                $conns[] = $_conn;
                continue;
            }


            // Create channels and channel event handlers.
            foreach ($conn->channel as $chan) {
                $_chan = $_conn->openChannel();
                if (isset($chan->event_handler)) {
                    $impl = (string) $chan->event_handler->impl;
                    $_chan->setEventHandler(new $impl);
                }
                $_chans[] = $_chan;
            }
            if (! $_chans) {
                throw new \Exception("You must define at least one channel");
            }
            $_chan = reset($_chans);


            // Execute whatever methods are supplied.
            foreach ($conn->methods->method as $iMeth) {
                $a = $this->xmlToArray($iMeth);
                $c = $a['class'];
                $m = $a['method'];
                $meth = $_chan->$c($m, $a['args']);
                $_chan->invoke($meth);
            }


            // Finally, set up consumers.  This is done last in case queues / exchanges etc. need to be set up before the consumers.
            $i = 0;
            foreach ($conn->channel as $chan) {
                $_chan = $_chans[$i++];
                foreach ($chan->consumer as $cons) {
                    $impl = (string) $cons->impl;
                    $_chan->addConsumer($_cons = new $impl($this->xmlToArray($cons->args->children())));
                    if (isset($cons->autostart) && $this->kast($cons->autostart, 'boolean')) {
                        $_chan->startConsumer($_cons);
                    }
                }
            }


            $conns[] = $_conn;
        }
        return $conns;
    }


    /**
     * Perform the given cast on the given value, defaults to a string
     * cast.
     */
    private function kast ($val, $cast) {
        switch ($cast) {
        case 'string':
            return (string) $val;
        case 'boolean':
            $val = trim((string) $val);
            if ($val === '0' || strtolower($val) === 'false') {
                return false;
            } else if ($val === '1' || strtolower($val) === 'true') {
                return true;
            } else {
                trigger_error("Bad boolean cast $val - use 0/1 true/false", E_USER_WARNING);
                return true;
            }
        case 'int':
            return (int) $val;
        default:
            trigger_error("Unknown Kast $cast", E_USER_WARNING);
            return (string) $val;
        }
    }


    /**
     * Recursively convert  an XML  structure to nested  assoc arrays.
     * For each "leaf", use the "cast" given in the @k attribute.
     */
    private function xmlToArray (SimpleXmlElement $e) {
        $ret = array();
        foreach ($e as $c) {
            $ret[(string) $c->getName()] = (count($c) == 0)
                ? $this->kast($c, (string) $c['k'])
                : $this->xmlToArray($c);
        }
        return $ret;
    }


}
