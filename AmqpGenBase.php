<?php

namespace bluelines\amqp\codegen_iface;

/**
   Refactoring:
     * ALL functionality will be implemented in final functions in superclasses,
       all child classes to be pure generated data
     * All "weak object references" non-factory generated classes must contain both the AMQP spec name & index, but also the FQname of the class which implements it.  All static:: references to be kept in the Factories,
     * +++ Investigate the "non-static" use of static - can this pull more code out of generated classes?

 */

// GLOBAL [one]
abstract class ClassFactory
{
    protected static $Cache; // Format: array(array(<class-idx>, <class-name>, <fully-qualified XmlSpecMethod impl. class name>))+

    final static function GetClassByIndex($index) {
        foreach (static::$Cache as $cNum => $c) {
            if ($c[0] === $index) {
                return is_string($c[2]) ?
                    (static::$Cache[$cNum][2] = new $c[2])
                    : $c[2];
            }
        }
    }
    final static function GetClassByName($name) {
        foreach (static::$Cache as $cNum => $c) {
            if ($c[1] === $name) {
                return is_string($c[2]) ?
                    (static::$Cache[$cNum][2] = new $c[2])
                    : $c[2];
            }
        }
    }
}



// Static factory
// GLOBAL [one]
abstract class DomainFactory
{
    protected static $Cache; // Map: array(<xml-domain-name> => <local XmlSpecDomain impl. class name>)

    final static function IsDomain($dName) {
        return isset(static::$Cache[$dName]);
    }
    final static function GetDomain($dName) {
        if (isset(static::$Cache[$dName])) {
            return (is_string(static::$Cache[$dName])) ? (static::$Cache[$dName] = new static::$Cache[$dName]) : static::$Cache[$dName];
        }
    }
    final static function Validate($val, $dName) {
        return static::GetDomain($dName)->validate($val);
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
    protected $methFact;
    protected $fieldFact;

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
    final function methods() {
        return call_user_func(array($this->methFact, 'GetMethods'), $this->methods);
    }
    final function fields() {
        return call_user_func(array($this->fieldFact, 'GetClassFields'), $this->name);
    }
}


// PER-NS [one]
abstract class MethodFactory
{
    protected static $Cache;// Map: array(<xml-method-name> => <fully-qualified XmlSpecMethod impl. class name>)

    final static function IsMethod($mName) {
        return isset(static::$Cache[$mName]);
    }
    final static function GetMethod($mName) {
        if (isset(static::$Cache[$mName])) {
            return (is_string(static::$Cache[$mName])) ?
                (static::$Cache[$mName] = new static::$Cache[$mName])
                : static::$Cache[$mName];
        } else {
            return null;
        }
    }
    final static function GetMethods(array $restrict = array()) {
        $m = array();
        foreach (static::$Cache as $mName => $clazz) {
            if (! $restrict || in_array($mName, $restrict)) {
                $m[] = static::GetMethod($mName);
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
    protected $methFact;
    protected $fieldFact;

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
    final function fields() {
        return call_user_func(array($this->fieldFact, 'GetFieldsForMethod'), $this->name);
    }
    final function responses() {
        return call_user_func(array($this->methFact, 'GetMethods'), $this->responseMethods);
    }
}

// PER-NS [one]
abstract class FieldFactory
{
    protected static $Cache; // Map: array(array(<fname>, <meth>, <Fully Qualified XmlSpecField impl. class name>)+)


    private static function Lookup($fName, $mName = '') {
        foreach (static::$Cache as $i => $f) {
            if ($f[0] === $fName && $f[1] === $mName) {
                return $i;
            }
        }
        return false;
    }
    final static function IsField($fName, $mName = '') {
        return (static::Lookup($fName, $mName) !== false);
    }
    final static function GetField($fName, $mName = '') {
        if (false !== ($f = static::Lookup($fName, $mName))) {
            //            echo "  [OK] : Return field ($fName, $mName) ($f)\n";
            return is_string(static::$Cache[$f][2]) ?
                (static::$Cache[$f][2] = new static::$Cache[$f][2])
                : static::$Cache[$f][2];
        }
    }
    final static function GetClassFields() {
        // Return all fields that are declared at the class level
        $r = array();
        foreach (static::$Cache as $f) {
            if ($f[1] === '') {
                $r[] = static::GetField($f[0]);
            }
        }
        return $r;
    }
    final static function GetFieldsForMethod($mName) {
        // Return all field for the given method, including common fields at the class level
        $r = array();
        foreach (static::$Cache as $f) {
            if ($f[1] === $mName) {
                $r[] = static::GetField($f[0], $mName);
            }
        }
        return array_merge(static::GetClassFields(), $r);
    }
    final static function Validate($val, $fName, $mName = '') {
        return static::GetField($fName, $mName)->validate($val);
    }

}

/** Use an underscore for a method field, i.e. <Method Name>_<Field Name> */
// PER-NS [many]
abstract class XmlSpecField
{
    protected $name;
    protected $domain;
    protected $fieldFact;

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
