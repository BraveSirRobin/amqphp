<?php


class ConfigException extends \Exception {}

// i.e. existing class
class Factory
{
    const XML_FILE = 1;
    const XML_STRING = 2;
    const CONF_SETUP = 1;
    const CONF_METHODS = 2;
}

class XmlConfigParser
{
    private $file;
    private $agents;
    public $xmlType = Factory::XML_FILE;

    function setFile ($file) {
        $this->file = $file;
    }

    /* Depth  first  traversal  of  config  generates  a  sequence  of
       events */
    function run () {
        $simp = $this->getSimpleXml();

        switch ($simp->getName()) {
        case 'setup':
            return $this->runSetupSequence($simp);
        case 'methods':
            return $this->runMethodsSequence($simp->xpath('//method'));
        default:
            throw new ConfigException("Bad factory config root node", 2645);
        }
    }

    /** Helper method returns a simplexml */
    private function getSimpleXml () {
        $d = new \DOMDocument;
        switch ($this->xmlType) {
        case Factory::XML_FILE:
            if (! $d->load($this->file)) {
                throw new \Exception("Failed to load factory XML", 92656);
            }
            break;
        case Factory::XML_STRING:
            if (! $d->loadXML($this->file)) {
                throw new \Exception("Failed to load factory XML", 9262);
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
        } else if (! ($simp = simplexml_import_dom($d))) {
            throw new \Exception("Failed to load factory XML", 92658);
        }
        return $simp;
    }

    private function runMethodsSequence ($meths) {
        try {
            foreach ($meths as $m) {
                $raw = $this->xmlToArray($m);
                $c = array_key_exists('a_class', $raw)
                    ? $raw['a_class']
                    : null;
                $m = array_key_exists('a_method', $raw)
                    ? $raw['a_method']
                    : null;
                $a = array_key_exists('a_args', $raw)
                    ? $raw['a_args']
                    : null;
                array_map(function ($agent) use ($c, $m, $a) {
                        $agent->handleMethod($c, $m, $a);
                    }, $this->agents);
            }

        } catch (ConfigException $cfe) {
        } catch (\Exception $e) {
        }
    }

    private function runSetupSequence ($simp) {
        printf("**Runs setup seq**\n");
        try {
            foreach ($simp as $conn) {
                /*
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

                */
                // Start the connection
                $impl = (string) $conn->impl;
                $constArgs = $this->xmlToArray($conn->constr_args->children());
                array_map(function ($agent) use ($impl, $constArgs) {
                        $agent->startConnection($impl, $constArgs);
                    }, $this->agents);

                // Add connection exit strategies
                if (count($conn->exit_strats) > 0) {
                    foreach ($conn->exit_strats->strat as $strat) {
                        $strat = $this->xmlToArray($strat->children());
                        array_map(function ($agent) use ($strat) {
                                $agent->addExitStrategy($strat);
                            }, $this->agents);
                    }
                }

//                continue;

                // Create channels and channel event handlers.
                foreach ($conn->channel as $chan) {
                    /*
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
                    */
                    $impl = (string) $chan->impl;
                    if ($chan->constr_args) {
                        $constArgs = $this->xmlToArray($chan->constr_args->children());
                    } else {
                        $constArgs = array();
                    }
                    array_map(function ($agent) use ($impl, $constArgs) {
                            $agent->startChannel($impl, $constArgs);
                        }, $this->agents);


                    if (isset($chan->event_handler)) {
                        $impl = (string) $chan->event_handler->impl;
                        if ($chan->event_handler->constr_args) {
                            $constArgs = $this->xmlToArray($chan->event_handler->constr_args->children());
                        } else {
                            $constArgs = array();
                        }
                        array_map(function ($agent) use ($impl, $constArgs) {
                                $agent->addEventHandler($impl, $constArgs);
                            }, $this->agents);
                    }

                    array_map(function ($agent) {
                            $agent->endChannel();
                        }, $this->agents);

                }

                array_map(function ($agent) {
                        $agent->endConnection();
                    }, $this->agents);

                continue;

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



            



            
        } catch (ConfigException $cfe) {
        } catch (\Exception $e) {
        }
    }

    function addAgent (ConfigAgent $agt) {
        if (! $agt) {
            throw new \Exception("Cannot add empty agent", 2640);
        }
        $this->agents[] = $agt;
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

}

/* Defines an object which receives all possible config events */
interface ConfigAgent
{
    function startConnection ($impl, $constArgs);
    function endConnection ();
    function startChannel ($impl, $constArgs);
    function endChannel ();
    function addEventHandler ($impl, $constArgs);
    function addExitStrategy ($stratArgs);
    function startConsumer ($impl, $constArgs, $auStart);
    function endConsumer ();
    function setProperty ($pname, $pval);
    function handleMethod ($class, $meth, $args);
}

/**  */
class ConfigLogger implements ConfigAgent
{
    private $conns = array(); // Collect nested metadata
    private $meths = array(); // Collect methods for method-only scripts
    private $state;
    // Connection, Channel and Consumer counters
    private $i = 0, $j = 0, $k = 0;

    function getConfigType () {
        if ($this->conns || $this->meths) {
            return $this->conns
                ? Factory::CONF_SETUP
                : Factory::CONF_METHODS;
        }
    }

    function getConnctions () {
        return $this->conns;
    }

    function getMethods () {
        return $this->meths;
    }

    /** @implements ConfigAgent */
    function startConnection ($impl, $constArgs) {
        if ($this->state !== null || $this->meths) {
            throw new ConfigException("Unexpected connection");
        }
        $this->conns[] = array(
            'implementation' => $impl,
            'construction-args' => $constArgs,
            'channels' => array(),
            'properties' => array(),
            'exit-strats' => array());
        $this->j = 0;
        $this->state = 'connection';
    }

    function addExitStrategy ($stratArgs) {
        $this->conns[$this->i]['exit-strats'][] = $stratArgs;
    }

    /** @implements ConfigAgent */
    function endConnection () {
        $this->state = null;
        $this->i++;
    }

    /** @implements ConfigAgent */
    function startChannel ($impl, $constArgs) {
        if ($this->state !== 'connection') {
            echo "I NOT HAPPEH - {$this->state}!!!\n\n";
            throw new ConfigException("Unexpected Channel");
        }
        $this->conns[$this->i]['channels'][] = array(
            'implementation' => $impl,
            'construction-args' => $constArgs,
            'properties' => array(),
            'consumers' => array(),
            'methods' => array(),
            'event-handler' => null);
        $this->state = 'channel';
    }

    /** @implements ConfigAgent */
    function endChannel () {
        $this->k = 0;
        $this->j++;
        $this->state = 'connection';
    }

    /** @implements ConfigAgent */
    function addEventHandler ($impl, $constArgs) {
        if ($this->state !== 'channel' ||
            $this->conns[$this->i]['channels'][$this->j]['event-handler']) {
            throw new ConfigException("Unexpected Event handler");
        }
        $this->conns[$this->i]['channels'][$this->j]['event-handler'] = array(
            'implementation' => $impl,
            'construction-args' => $constArgs);
    }

    /** @implements ConfigAgent */
    function startConsumer ($impl, $constArgs, $auStart) {
        if ($this->state !== 'channel') {
            throw new ConfigException("Unexpected Channel");
        }
        $this->conns[$this->i]['channels'][$this->j]['consumers'][] = array(
            'impl' => $impl,
            'construction-args' => $constArgs,
            'autostart' => $auStart);
        $this->state = 'consumer';
    }

    function endConsumer () {
        $this->k++;
        $this->state = 'channel';
    }

    /** @implements ConfigAgent */
    function setProperty ($pname, $pval) {
        switch ($this->state) {
        case 'connection':
            $stack = &$this->conns[$this->i]['properties'];
            break;
        case 'channel':
            $stack = &$this->conns[$this->i]['channels'][$this->j]['properties'];
            break;
        case 'consumer':
            $stack = &$this->conns[$this->i]['channels'][$this->j]['properties'][$this->k]['properties'];
            break;
        default:
            throw new ConfigException("Unexpected Property");
        }
        $stack[] = array($pname, $pval);
    }

    /** @implements ConfigAgent */
    function handleMethod ($class, $meth, $args) {
        if ($this->state === null && ! $this->conns) {
            $stack = &$this->meths;
        } else if (! $this->meths && $this->state == 'channel') {
            $stack = &$this->conns[$this->i]['channels'][$this->j]['methods'];
        } else {
            throw new ConfigException("Unexpected method");
        }
        $stack[] = array($class, $meth, $args);
        //        var_dump($stack);
    }
}

// test code
$cfp = new XmlConfigParser;
$cfp->setFile(__DIR__ . '/demos/configs/web-multi.xml');
//$cfp->setFile(__DIR__ . '/demos/configs/broker-common-setup.xml');
$cfp->addAgent($log = new ConfigLogger);
$cfp->run();
//var_dump($log->getMethods());
var_dump($log->getConnctions());