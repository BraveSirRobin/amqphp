<?php
/**
 *
 * Copyright (C) 2010, 2011, 2012  Robin Harvey (harvey.robin@gmail.com)
 *
 * This  library is  free  software; you  can  redistribute it  and/or
 * modify it under the terms  of the GNU Lesser General Public License
 * as published by the Free Software Foundation; either version 2.1 of
 * the License, or (at your option) any later version.

 * This library is distributed in the hope that it will be useful, but
 * WITHOUT  ANY  WARRANTY;  without   even  the  implied  warranty  of
 * MERCHANTABILITY or  FITNESS FOR A PARTICULAR PURPOSE.   See the GNU
 * Lesser General Public License for more details.

 * You should  have received a copy  of the GNU  Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation,  Inc.,  51 Franklin  Street,  Fifth  Floor, Boston,  MA
 * 02110-1301 USA
 */

namespace amqphp;

use amqphp\protocol;
use amqphp\wire;
use \amqphp\persistent as pers;


/**
 * This  is a  helper  class  which can  create  Amqp connections  and
 * channels  (persistent and  non-persistent), add  consumers, channel
 * event  handlers, and  call amqp  methods.   All of  the broker  and
 * client  configuration can  be  stored  in an  XML  file, and  their
 * corresponding setup be performed by a single method call.
 *
 * Note that the use of this  component is not required, you can still
 * set up  your connections / channels and  broker configuration calls
 * manually, if desired.
 */
class Factory
{
    /* Constructor flag - load XML from the given file */
    const XML_FILE = 1;

    /* Constructor flag - load XML from the given string */
    const XML_STRING = 2;

    /** Cache ReflectionClass instances, key is class name */
    private static $RC_CACHE = array();


    /* SimpleXML object, with content x-included */
    private $simp;

    /* Name of the configuration root element */
    private $rootEl;

    function __construct ($xml, $documentURI=false, $flag=self::XML_FILE) {
        $d = new \DOMDocument;

        switch ($flag) {
        case self::XML_FILE:
            if (! $d->load($xml)) {
                throw new \Exception("Failed to load factory XML", 92656);
            }
            break;
        case self::XML_STRING:
            if (! $d->loadXML($xml)) {
                throw new \Exception("Failed to load factory XML", 92656);
            }
            if ($documentURI) {
                $d->documentURI = $documentURI;
            }
            break;
        default:
            throw new \Exception("Invalid construct flag", 95637);
        }

        if (-1 === $d->xinclude()) {
            throw new \Exception("Failed to load factory XML", 92657);
        } else if (! ($this->simp = simplexml_import_dom($d))) {
            throw new \Exception("Failed to load factory XML", 92658);
        }

        switch ($tmp = strtolower((string) $this->simp->getName())) {
        case 'setup':
        case 'methods':
            $this->rootEl = $tmp;
            break;
        default:
            throw new \Exception("Unexpected Factory configuration data root element", 17893);
        }
    }


    /**
     * Run the XML instructions and return the corresponding objects /
     * responses.
     */
    function run (Channel $chan=null) {
        switch ($this->rootEl) {
        case 'setup':
            return $this->runSetupSequence();
        case 'methods':
            if (is_null($chan)) {
                throw new \Exception("Invalid factory configuration - expected a target channel", 15758);
            }
            return $this->runMethodSequence($chan, $this->simp->xpath('/methods/method'));
        }
    }


    /**
     * Helper  method -  run the  config and  return  only connections
     * (throw away method responses)
     */
    function getConnections () {
        $r = array();
        foreach ($this->run() as $res) {
            if ($res instanceof Connection) {
                $r[] = $res;
            }
        }
        return $r;
    }


    /**
     * Helper: loop the children of a set_properties element of $conf,
     * an set these as scalar key value pairs as properties of $subj
     */
    private function callProperties ($subj, $conf) {
        foreach ($conf->xpath('./set_properties/*') as $prop) {
            $pname = (string) $prop->getName();
            $pval = $this->kast($prop, $prop['k']);
            $subj->$pname = $pval;
        }
    }


    /**
     * Run the connection setup sequence
     */
    private function runSetupSequence () {
        $ret = array();

        foreach ($this->simp->connection as $conn) {
            $_chans = array();

            // Create connection and connect
            $refl = $this->getRc((string) $conn->impl);
            $_conn = $refl->newInstanceArgs($this->xmlToArray($conn->constr_args->children()));
            $this->callProperties($_conn, $conn);
            $_conn->connect();
            $ret[] = $_conn;

            // Add exit strategies, if required.
            if (count($conn->exit_strats) > 0) {
                foreach ($conn->exit_strats->strat as $strat) {
                    call_user_func_array(array($_conn, 'pushExitStrategy'), $this->xmlToArray($strat->children()));
                }
            }

            if ($_conn instanceof pers\PConnection && $_conn->getPersistenceStatus() == pers\PConnection::SOCK_REUSED) {
                // Assume that the setup is complete for existing PConnection
                // ??TODO??  Run method sequence here too?
                continue;
            }



            // Create channels and channel event handlers.
            foreach ($conn->channel as $chan) {
                $_chan = $_conn->openChannel();
                $this->callProperties($_chan, $chan);
                if (isset($chan->event_handler)) {
                    $impl = (string) $chan->event_handler->impl;
                    if (count($chan->event_handler->constr_args)) {
                        $refl = $this->getRc($impl);
                        $_evh = $refl->newInstanceArgs($this->xmlToArray($chan->event_handler->constr_args->children()));
                    } else {
                        $_evh = new $impl;
                    }
                    $_chan->setEventHandler($_evh);
                }
                $_chans[] = $_chan;
                $rMeths = $chan->xpath('.//method');

                if (count($rMeths) > 0) {
                    $ret[] = $this->runMethodSequence($_chan, $rMeths);
                }
                if (count($chan->confirm_mode) > 0 && $this->kast($chan->confirm_mode, 'boolean')) {
                    $_chan->setConfirmMode();
                }
            }


            /* Finally, set  up consumers.  This is done  last in case
               queues /  exchanges etc. need  to be set up  before the
               consumers. */
            $i = 0;
            foreach ($conn->channel as $chan) {
                $_chan = $_chans[$i++];
                foreach ($chan->consumer as $cons) {
                    $impl = (string) $cons->impl;
                    if (count($cons->constr_args)) {
                        $refl = $this->getRc($impl);
                        $_cons = $refl->newInstanceArgs($this->xmlToArray($cons->constr_args->children()));
                    } else {
                        $_cons = new $impl;
                    }
                    $this->callProperties($_cons, $cons);
                    $_chan->addConsumer($_cons);
                    if (isset($cons->autostart) && $this->kast($cons->autostart, 'boolean')) {
                        $_chan->startConsumer($_cons);
                    }
                }
            }
        }
        return $ret;
    }



    /**
     * Execute the  methods defined  in $meths against  channel $chan,
     * return the results.
     */
    private function runMethodSequence (Channel $chan, array $meths) {
        $r = array();
        // Execute whatever methods are supplied.
        foreach ($meths as $iMeth) {
            $a = $this->xmlToArray($iMeth);
            $c = $a['a_class'];
            $r[] = $chan->invoke($chan->$c($a['a_method'], $a['a_args']));
        }
        return $r;
    }



    /**
     * Perform the given cast on the given value, defaults to a string
     * cast.
     */
    private function kast ($val, $cast) {
        switch ($cast) {
        case 'string':
            return (string) $val;
        case 'bool':
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
        case 'integer':
            return (int) $val;
        case 'const':
            return constant((string) $val);
        case 'eval':
            return eval((string) $val);
        default:
            trigger_error("Unknown Kast $cast", E_USER_WARNING);
            return (string) $val;
        }
    }


    /**
     * Recursively convert  an XML  structure to nested  assoc arrays.
     * For each "leaf", use the "cast" given in the @k attribute.
     */
    private function xmlToArray (\SimpleXmlElement $e) {
        $ret = array();
        foreach ($e as $c) {
            $ret[(string) $c->getName()] = (count($c) == 0)
                ? $this->kast($c, (string) $c['k'])
                : $this->xmlToArray($c);
        }
        return $ret;
    }


    /** Accessor for the local ReflectionClass cache */
    private function getRc ($class) {
        return array_key_exists($class, self::$RC_CACHE)
            ? self::$RC_CACHE[$class]
            : (self::$RC_CACHE[$class] = new \ReflectionClass($class));
    }
}