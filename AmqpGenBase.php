<?php

namespace bluelines\amqp\codegen_iface;

/**

   Modelled Objects
   ----------------

   * constants - always top level
   * domains - always top level
   * classes - always top level
   * fields - either class or method fields
   * methods - always at class level

   Additional Objects
   ------------------

   * rules - apply to domains and fields

   Tasks the code should acomplish
   -------------------------------

   * extract messages from binary wire format, including method mapping
   * encode messages in binary format from PHP variables
   * domain validation for all messages
   * make all xml data available in the API


   Generated files / namespaces
   ----------------------------

   * One file, namespace for global amqp element, this uses *this* ns [1]
     + contains all XmlSpecDomain classes
     + contains factory functions for classes and methods
   * One file, namespace per class, these all use (*1) namespace
     + uses the global generated namespace (above)


   amqp_091
   amqp_091\connection
   amqp_091\channel
   amqp_091\exchange
   amqp_091\tx

 */


abstract class XmlSpecClass
{
    protected $name;
    protected $index;
    protected $fields;
    protected $methods;

    final function getName() {
        return $this->name;
    }
    final function getIndex() {
        return $this->index;
    }
    final function getFields() {
        return $this->fields;
    }
    final function getMethods() {
        return $this->methods;
    }
}


abstract class XmlSpecMethod
{
    protected $name;
    protected $index;
    protected $synchronous;
    protected $responseMethod;
    protected $fields;

    final function getName() {
        return $this->name;
    }
    final function getIndex() {
        return $this->index;
    }
    final function isSynchronous() {
        return $this->synchronous;
    }
    final function getResponseMethod() {
        return $this->responseMethod;
    }
    final function getFields() {
        return $this->fields;
    }
}

// Static factory - global - in the top level generated NS
abstract class DomainMap
{
    protected static $Dmap; // Map: array(<xml-domain-name> => <local XmlSpecDomain impl. class name>)
    private static $Imap = array(); // Runtime validation class instance map
    final static function IsDomain($dName) {
        return isset(self::$Dmap[$dName]);
    }
    final static function GetDomain($dName) {
        return (isset(self::$Imap[$dName])) ? self::$Imap[$dName] : (self::$Imap[$dName] = new self::$Dmap[$dName]);
    }
    final static function Validate($val, $dName) {
        return self::GetDomain($dName)->validate($val);
    }
}
/** Generate one per domain, input elementary domain list with external file and use xslt:document()
    Input document will list mappings from elementary domain names to (unwritten) protocol validation functions */
abstract class XmlSpecDomain
{
    protected $name;
    protected $protocolType;

    final function getName() {
        return $this->name;
    }
    final function getDomainType() {
        return $this->domainType;
    }
    /* Implementations always proxy to (unwritten!) protocol validation funcs,
       optionally contain additional xml-generated validation routines */
    abstract function validate($subject);
}
// One per class NS
abstract class FieldMap
{
    protected static $Fmap; // Map: array(array(<fname>, <meth>, <local XmlSpecField impl. class name>)+)
    protected static $Imap = array(); // Runtime validation class instance map: array('<fname>:<meth>' => <obj>)
    final static function IsField($fName, $mName = '') {
        return (self::Lookup($fName, $mName) !== false);
    }
    final static function GetField($fName, $mName = '') {
        if (isset(self::$Imap["{$fName}:{$mName}"])){
            return self::$Imap["{$fName}:{$mName}"];
        } else {
            return self::$Imap["{$fName}:{$mName}"] = new self::$Fmap[self::Lookup($fName, $mName)][2];
        }
    }
    final static function Validate($val, $fName, $mName = '') {
        return self::GetField($fName, $mName)->validate($val);
    }
    private static function Lookup($fName, $mName = '') {
        foreach (self::$Fmap as $i => $f) {
            if ($f[0] === $fName && $f[1] === $mName) {
                return $i;
            }
        }
        return false;
    }
}

abstract class XmlSpecField
{
    protected $name;
    protected $domain;

    final function getName() {
        return $this->name;
    }
    final function getDomain() {
        return $this->domain;
    }
    // Some child classes will contain generated validation routines, these expected to call parent::validate()
    function validate($subject) { return DomainMap::Validate($this->domain, $subject); }
}
