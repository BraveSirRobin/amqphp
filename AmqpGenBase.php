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

    final function getSpecDomainName() {
        return $this->name;
    }
    final function getSpecDomainType() {
        return $this->domainType;
    }
    /* Implementations always proxy to (unwritten!) protocol validation funcs,
       optionally contain additional xml-generated validation routines */
    abstract function validate($subject);
}

// PER-NS [many]
interface XmlSpecField
{
    function getSpecFieldName();
    function getSpecFieldDomain();
}



// PER-NS [one]
abstract class XmlSpecClass
{
    protected $name;
    protected $index;
    protected $fields; // Format: array(<amqp field name>)
    protected $methods; // Format: array(<amqp method name>)
    protected $methFact;
    protected $fieldFact;

    final function getSpecName() {
        return $this->name;
    }
    final function getSpecIndex() {
        return $this->index;
    }
    final function getSpecFields() {
        return $this->fields;
    }
    final function getSpecMethods() {
        return $this->methods;
    }
    final function getMethods() {
        return call_user_func(array($this->methFact, 'GetMethodsByName'), $this->methods);
    }
    final function getMethodByName($mName) {
        if (in_array($mName, $this->methods)) {
            return call_user_func(array($this->methFact, 'GetMethodByName'), $mName);
        }
    }
    final function getMethodByIndex($idx) {
        if (in_array($idx, array_keys($this->methods))) {
            return call_user_func(array($this->methFact, 'GetMethodByIndex'), $idx);
        }
    }
    final function getFields() {
        return call_user_func(array($this->fieldFact, 'GetClassFields'), $this->name);
    }
    final function getFieldByName($fName) {
        if (in_array($fName, $this->fields)) {
            return call_user_func(array($this->fieldFact, 'GetField'), $fName);
        }
    }
}


// PER-NS [one]
abstract class MethodFactory
{
    protected static $Cache;// Map: array(array(<xml-method-index>, <xml-method-name>, <fully-qualified XmlSpecMethod impl. class name>)+)

    private static function Lookup($mName, $asName = true) {
        $j = ($asName) ? 1 : 0;
        foreach (static::$Cache as $i => $f) {
            if ($f[$j] === $mName) {
                return $i;
            }
        }
        return false;
    }

    final static function GetMethodByName($mName) {
        if (false !== ($i = static::Lookup($mName))) {
            return (is_string(static::$Cache[$i][2])) ?
                (static::$Cache[$i][2] = new static::$Cache[$i][2])
                : static::$Cache[$i][2];
        }
    }
    final static function GetMethodsByName(array $restrict = array()) {
        $m = array();
        foreach (static::$Cache as $c) {
            if (! $restrict || in_array($c[1], $restrict)) {
                $m[] = static::GetMethodByName($c[1]);
            }
        }
        return $m;
    }

    final static function GetMethodByIndex($idx) {
        if (false !== ($i = static::Lookup($idx, false))) {
            return (is_string(static::$Cache[$i][2])) ?
                (static::$Cache[$i][2] = new static::$Cache[$i][2])
                : static::$Cache[$i][2];
        }
    }
    final static function GetMethodsByIndex(array $restrict = array()) {
        $m = array();
        foreach (static::$Cache as $c) {
            if (! $restrict || in_array($c[0], $restrict)) {
                $m[] = static::GetMethodByIndex($c[0]);
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

    final function getSpecName() {
        return $this->name;
    }
    final function getSpecIndex() {
        return $this->index;
    }
    final function getSpecIsSynchronous() {
        return $this->synchronous;
    }
    final function getSpecResponseMethods() {
        return $this->responseMethods;
    }
    final function getSpecFields() {
        return $this->fields;
    }
    final function getFields() {
        return call_user_func(array($this->fieldFact, 'GetFieldsForMethod'), $this->name);
    }
    final function getResponses() {
        return call_user_func(array($this->methFact, 'GetMethodsByName'), $this->responseMethods);
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

