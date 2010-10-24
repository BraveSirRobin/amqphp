<?php

namespace bluelines\amqp\codegen_iface;

/**

   Modelled Objects
   ----------------

   * constants - always top level in xml
   * domains - always top level in xml
   * classes - always top level in xml
   * fields - either class or method fields in xml
   * methods - always at class level in xml

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
   amqp_091\queue
   amqp_091\basic
   amqp_091\tx

 */

// GLOBAL [one]
abstract class Methods
{
    protected static $Meths; // Format: array(array(<class-idx>, <method-idx>, <fully-qualified XmlSpecMethod impl. class name>))+
    private $Imap = array();

    final static function IndexLookup($classId, $methodId) {
        if (isset(self::$Imap["$classId,$methodId"])) {
            return self::$Imap["$classId,$methodId"];
        }
        foreach (static::$Meths as $m) {
            if ($m[0] === $classId && $m[1] === $methodId) {
                return (self::$Imap["$classId,$methodId"] = new $m[2]);
            }
        }
    }
}



// Static factory
// GLOBAL [one]
abstract class DomainFactory
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
// GLOBAL [many]
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
    protected final function assert($bool) {
        if (! $bool) {
            throw new Exception("Domain vaidation assert failed for domain {$this->name}", 987543);
        }
    }
    /* Implementations always proxy to (unwritten!) protocol validation funcs,
       optionally contain additional xml-generated validation routines */
    abstract function validate($subject);
}


// PER-NS [one]
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
    /** Hard-coded generated method uses local MethodFactory to return a list of XmlSpecMethod objects.
        This is required, as the sub-namespace cannot be accessed from here */
    abstract function methods();
    // function methods () { return MethodFactory::GetMethods(); }
}


// PER-NS [one]
abstract class MethodFactory
{
    protected static $Mmap;// Map: array(<xml-method-name> => <local XmlSpecMethod impl. class name>)
    private static $Imap = array(); // Runtime validation class instance map
    final static function IsMethod($mName) {
        return isset(static::$Mmap[$mName]);
    }
    final static function GetMethod($mName) {
        return (isset(self::$Imap[$mName])) ? self::$Imap[$mName] : (self::$Imap[$mName] = new static::$Mmap[$mName]);
    }
    final static function GetMethods(array $restrict = array()) {
        $m = array();
        foreach (static::$Mmap as $mName => $clazz) {
            if ($restrict && in_array($mName, $restrict)) {
                $m[] = self::GetMethod($mName);
            }
        }
        return $m;
    }
}



// PER-NS [many]
abstract class XmlSpecMethod
{
    protected $name;
    protected $index;
    protected $synchronous;
    protected $responseMethods;
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
    final function getResponseMethods() {
        return $this->responseMethods;
    }
    final function getFields() {
        return $this->fields;
    }
    /** Hard-coded generated method uses local FieldFactory to return a list of XmlSpecField objects.
        this is required, as the sub-namespace cannot be accessed from here */
    abstract function fields();
    // function fields () { return FieldFactory::GetFields($this->getName()); }
    abstract function responses();
    // function responses() { return FieldFactory::GetFields($this->responseMethods); }
}

// PER-NS [one]
abstract class FieldFactory
{
    protected static $Fmap; // Map: array(array(<fname>, <meth>, <local XmlSpecField impl. class name>)+)
    private static $Imap = array(); // Runtime validation class instance map: array('<fname>:<meth>' => <obj>)
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
    final static function GetClassFields() {
        // Return all fields that are declared at the class level
        $r = array();
        foreach (static::$Fmap as $f) {
            if ($f[1] === '') {
                $r[] = self::GetField($f[0]);
            }
        }
        return $r;
    }
    final static function GetFields($mName) {
        // Return all field for the given method, including common fields at the class level
        $r = array();
        foreach (static::$Fmap as $f) {
            if ($f[1] === $mName) {
                $r[] = self::GetField($f[0]);
            }
        }
        return array_merge(self::GetClassFields(), $r);
    }
    final static function Validate($val, $fName, $mName = '') {
        return self::GetField($fName, $mName)->validate($val);
    }
    private static function Lookup($fName, $mName = '') {
        foreach (static::$Fmap as $i => $f) {
            if ($f[0] === $fName && $f[1] === $mName) {
                return $i;
            }
        }
        return false;
    }
}

/** Use an underscore for a method field, i.e. <Method Name>_<Field Name> */
// PER-NS [many]
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
    protected final function assert($bool) {
        if (! $bool) {
            throw new Exception("Method field vaidation assert failed for domain {$this->name}", 987543);
        }
    }
    // Some child classes will contain generated validation routines, these expected to call parent::validate()
    function validate($subject) {
        $this->assert(DomainFactory::Validate($this->domain, $subject));
    }
}
