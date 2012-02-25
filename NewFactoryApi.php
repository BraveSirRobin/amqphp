<?php


class ConfigException extends \Exception {}

/**
 * Base class for config parsers.
 */
abstract class ConfigParser
{
    /** Available configuration sources */
    const RESOURCE_TYPE_FILE = 1;
    const RESOURCE_TYPE_STRING = 2;
    const RESOURCE_TYPE_NETWORK = 3;

    /** Configuration types */
    const CONF_SETUP = 1;
    const CONF_METHODS = 2;

    /** Agents receive configuration discovery events */
    protected $agents = array();

    /** Storage for the raw resource, either a string, file name or url */
    protected $resource;

    /** Storage for resource type */
    protected $retType;

    /** Method to instruct parser on how it will receive it's input */
    final function setConfigResource ($resource, $resType=self::RESOURCE_TYPE_FILE) {
        $this->resource = $resource;
        $this->resType = self::RESOURCE_TYPE_FILE;
    }

    /** Method to trigger the parser to run */
    abstract function run ();

    /** Setter for adding agents to the stack */
    final function addAgent (ConfigAgent $agt) {
        if (! $agt) {
            throw new \Exception("Cannot add empty agent", 2640);
        }
        $this->agents[] = $agt;
    }
}


/**
 * Xml based configuration parser
 */
class XmlConfigParser extends ConfigParser
{

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
        switch ($this->resType) {
        case self::RESOURCE_TYPE_FILE:
        case self::RESOURCE_TYPE_NETWORK:
            if (! $d->load($this->resource)) {
                throw new \Exception("Failed to load factory XML", 92656);
            }
            break;
        case self::RESOURCE_TYPE_STRING:
            if (! $d->loadXML($this->resource)) {
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
            array_walk($this->agents, function ($agent) use ($c, $m, $a) {
                    $agent->handleMethod($c, $m, $a);
                });
        }
    }

    private function runPropertiesSequence ($conf) {
        foreach ($conf->xpath('./set_properties/*') as $prop) {
            $pname = (string) $prop->getName();
            $pval = $this->kast($prop, $prop['k']);
            array_walk($this->agents, function ($agent) use ($pname, $pval) {
                    $agent->setProperty($pname, $pval); 
                });
        }
    }

    private function runSetupSequence ($simp) {
        foreach ($simp as $conn) {
            // Start the connection
            $impl = (string) $conn->impl;
            $constArgs = $this->xmlToArray($conn->constr_args->children());
            array_walk($this->agents, function ($agent) use ($impl, $constArgs) {
                    $agent->startConnection($impl, $constArgs);
                });

            // Set all properties on the connection
            $this->runPropertiesSequence($conn);

            // Add connection exit strategies
            if (count($conn->exit_strats) > 0) {
                foreach ($conn->exit_strats->strat as $strat) {
                    $strat = $this->xmlToArray($strat->children());
                    array_walk($this->agents, function ($agent) use ($strat) {
                            $agent->addExitStrategy($strat);
                        });
                }
            }

            foreach ($conn->channel as $chan) {
                // Start the Channel
                array_walk($this->agents, function ($agent) use ($impl, $constArgs) {
                        $agent->startChannel();
                    });

                // Set all properties on the connection
                $this->runPropertiesSequence($chan);

                // Add the event handler
                if (isset($chan->event_handler)) {
                    $impl = (string) $chan->event_handler->impl;
                    $constArgs = $chan->event_handler->constr_args
                        ? $this->xmlToArray($chan->event_handler->constr_args->children())
                        : array();

                    array_walk($this->agents, function ($agent) use ($impl, $constArgs) {
                            $agent->addEventHandler($impl, $constArgs);
                        });
                }

                // Add consumers to the channel
                foreach ($chan->consumer as $cons) {
                    $impl = (string) $cons->impl;
                    $constArgs = count($cons->constr_args)
                        ? $this->xmlToArray($cons->constr_args->children())
                        : $constArgs = array();

                    $auStart = isset($cons->autostart) && $this->kast($cons->autostart, 'boolean');
                    array_walk($this->agents, function ($agent) use ($impl, $constArgs, $auStart) {
                            $agent->startConsumer($impl, $constArgs, $auStart);
                        });
                    $this->runPropertiesSequence($cons);

                }

                // End the channel
                array_walk($this->agents, function ($agent) {
                        $agent->endChannel();
                    });
            }

            // End the connection
            array_walk($this->agents, function ($agent) {
                    $agent->endConnection();
                });
        }
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
    function startChannel ();
    function endChannel ();
    function addEventHandler ($impl, $constArgs);
    function addExitStrategy ($stratArgs);
    function startConsumer ($impl, $constArgs, $auStart);
    function endConsumer ();
    function setProperty ($pname, $pval);
    function handleMethod ($class, $meth, $args);
}

/** An   agent  which   simply   collects  all   data  from   received
 * configuration events  and makes the data available  as nested array
 * structures. */
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
    function startChannel () {
        if ($this->state !== 'connection') {
            throw new ConfigException("Unexpected Channel");
        }
        $this->conns[$this->i]['channels'][] = array(
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
        $stack[$pname] = $pval;
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
    }
}

// test code
$cfp = new XmlConfigParser;
$cfp->setConfigResource(__DIR__ . '/demos/configs/web-multi.xml');
//$cfp->setConfigResource(__DIR__ . '/demos/configs/broker-common-setup.xml');
$cfp->addAgent($log = new ConfigLogger);
$cfp->addAgent($log2 = new ConfigLogger);
$cfp->run();
//var_dump($log->getMethods());
var_dump($log->getConnctions());
