<?xml version="1.0"?>

<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
		xmlns:func="http://exslt.org/functions"
		xmlns:str="http://exslt.org/strings"
		xmlns:exsl="http://exslt.org/common"
		xmlns:bl="http://www.bluelines.org/"
		version="1.0"
		extension-element-prefixes="func str exsl">

  <xsl:variable name="VERSION_TOKEN" select="concat(string(/amqp/@major), '_', string(/amqp/@minor), '_', string(/amqp/@revision))"/>
  <xsl:variable name="VERSION_STRING" select="concat(string(/amqp/@major), '.', string(/amqp/@minor), '.', string(/amqp/@revision))"/>

  <xsl:variable name="OUTPUT_DIR" select="'gencode/'"/>

  <xsl:output method="text"/>



  <!-- Embedded elementary domain looup map, hacky? -->
  <bl:elk>
    <bl:map version="0.9.1">
      <bl:domain name="bit" type="Boolean" />
      <bl:domain name="octet" type="ShortShortUInt" />
      <bl:domain name="short" type="ShortUInt" />
      <bl:domain name="long" type="LongUInt" />
      <bl:domain name="longlong" type="LongLongUInt" />
      <bl:domain name="shortstr" type="ShortString"/>
      <bl:domain name="longstr" type="LongString" />
      <bl:domain name="timestamp" type="Timestamp" />
      <bl:domain name="table" type="Table" />
    </bl:map>
  </bl:elk>



  <xsl:template match="/">
    <xsl:call-template name="output-global-code"/>
    <xsl:apply-templates select="/amqp/class" mode="output-class-classes"/>
  </xsl:template>


  <!-- Output the top level generated code file -->
  <xsl:template name="output-global-code">
    <xsl:variable name="file-name" select="bl:getFilePath()"/>
    <exsl:document href="{$file-name}" method="text" omit-xml-declaration="yes">&lt;?php
namespace amqp_<xsl:value-of select="$VERSION_TOKEN"/>;
/** Ampq binding code, generated from doc version <xsl:value-of select="$VERSION_STRING"/> */
use bluelines\amqp\codegen_iface;
require 'AmqpGenBase.php';

<xsl:for-each select="/amqp/class">
require '<xsl:value-of select="bl:getFileName(@name)"/>';
</xsl:for-each>

<!-- Output constants -->
<xsl:for-each select="/amqp/constant"> <!-- TODO: Convert to hex consts -->
const <xsl:value-of select="bl:convertToConst(@name)"/> = <xsl:value-of select="@value"/>;</xsl:for-each>

<!-- Output method lookup factory -->
class ClassFactory extends codegen_iface\ClassFactory
{
    <!-- Format: array(array(<class-idx>, <class-name>, <fully-qualified XmlSpecMethod impl. class name>))+ -->
    protected static $Cache = array(<xsl:for-each select="//class">array(<xsl:value-of select="@index"/>, '<xsl:value-of select="@name"/>', '<xsl:value-of select="bl:getFQClassName(@name, @name, 'Class')"/>')<xsl:if test="position() != last()">,</xsl:if></xsl:for-each>);

}

<!-- Ouptput global static domain loader map -->
class DomainFactory extends codegen_iface\DomainFactory
{
    <!-- Map: array(<xml-domain-name> => <local XmlSpecDomain impl. class name>) -->
    protected static $Dmap = array(<xsl:for-each select="/amqp/domain">'<xsl:value-of select="@name"/>' => '<xsl:value-of select="bl:getGenClassName(@name, 'Domain')"/>'<xsl:if test="position() != last()">, </xsl:if></xsl:for-each>);
}

<!-- Output the fundamental domain objects -->
// Fundamental domains
<xsl:apply-templates select="/amqp/domain[@name = @type]" mode="output-fundamental-domain-class"/>
<!-- Output the global domain objects -->
// Global domains
<xsl:apply-templates select="/amqp/domain[@name != @type]" mode="output-domain-class"/>
    </exsl:document>
  </xsl:template>


  <!-- Output the domain class implementation for a single domain -->
  <xsl:template match="domain" mode="output-domain-class">
class <xsl:value-of select="bl:getGenClassName(@name, 'Domain')"/> extends codegen_iface\XmlSpecDomain
{
    protected $name = '<xsl:value-of select="@name"/>';
    protected $protocolType = '<xsl:value-of select="@type"/>';
    function validate($subject) {
        if (DomainFactory::Validate($subject, $this->name)) {
        <xsl:for-each select="./assert">
          $this->assert(<xsl:value-of select="bl:getCodeForAssert(@check, @value, '$subject')"/>);</xsl:for-each>
          return true;
        }
        return false;
    }
}
  </xsl:template>


  <!-- Output classes for fundamental domains, these use the internal protocol mappinging functions -->
  <xsl:template match="domain" mode="output-fundamental-domain-class">
    <xsl:variable name="proto" select="bl:getElementaryDomainType(@type)"/>
    <xsl:choose>
      <xsl:when test="$proto = ''"><xsl:message terminate="yes">Unmapped fundamental domain: '<xsl:value-of select="@type"/>'</xsl:message></xsl:when>
      <xsl:otherwise>
class <xsl:value-of select="bl:getGenClassName(@name, 'Domain')"/> extends codegen_iface\XmlSpecDomain
{
    protected $name = '<xsl:value-of select="@name"/>';
    protected $protocolType = '<xsl:value-of select="@type"/>';
    function validate($subject) { return validate<xsl:value-of select="$proto"/>($subject); }
}
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>





  <!-- Output lower level class file/namespaces -->
  <xsl:template match="class" mode="output-class-classes">
    <xsl:variable name="file-name" select="bl:getFilePath(@name)"/>
    <exsl:document href="{$file-name}" method="text" omit-xml-declaration="yes">&lt;?php
namespace <xsl:value-of select="bl:getPackageName(@name)"/>;
use bluelines\amqp\codegen_iface;
/** Ampq binding code, generated from doc version <xsl:value-of select="$VERSION_STRING"/> */
class <xsl:value-of select="bl:getGenClassName(@name, 'Class')"/> extends codegen_iface\XmlSpecClass
{
    protected $name = '<xsl:value-of select="@name"/>';
    protected $index = <xsl:value-of select="@index"/>;
    protected $fields = array(<xsl:for-each select="./field">'<xsl:value-of select="@name"/>'<xsl:if test="position() != last()">,</xsl:if></xsl:for-each>);
    protected $methods = array(<xsl:for-each select="./method">'<xsl:value-of select="@name"/>'<xsl:if test="position() != last()">,</xsl:if></xsl:for-each>);
    function methods () { return MethodFactory::GetMethods(); }
}

class MethodFactory extends codegen_iface\MethodFactory
{
    <!-- array(<xml-method-name> => <local XmlSpecMethod impl. class name>) -->
    protected static $Cache = array(<xsl:for-each select="./method">'<xsl:value-of select="@name"/>' => '<xsl:value-of select="bl:getGenClassName(@name, 'Method')"/>'<xsl:if test="position() != last()">,</xsl:if></xsl:for-each>);
    protected static function I($name) { $s = '\\<xsl:value-of select="bl:getPackageName(@name, 1)"/>' . $name; return new $s; }
}

abstract class FieldFactory
{
    <!-- Map: array(array(<fname>, <meth>, <local XmlSpecField impl. class name>)+) -->
    protected static $Fmap = array(<xsl:for-each select=".//field">array('<xsl:value-of select="@name"/>', '<xsl:value-of select="parent::*[local-name() = 'method']/@name"/>', '<xsl:value-of select="bl:getFieldClassName()"/>')<xsl:if test="position() != last()">,</xsl:if></xsl:for-each>);
    protected static function I($name) { $s = sprintf("\\%s\%s", __NAMESPACE__, $name); return new $s; }
}


<xsl:apply-templates select="./method" mode="output-method-classes"/>


<xsl:apply-templates select=".//field" mode="output-method-fields"/>

    </exsl:document>
  </xsl:template>



  <xsl:template match="method" mode="output-method-classes">
class <xsl:value-of select="bl:getGenClassName(@name, 'Method')"/> extends codegen_iface\XmlSpecMethod
{
    protected $name = '<xsl:value-of select="@name"/>';
    protected $index = <xsl:value-of select="@index"/>;
    protected $synchronous = <xsl:choose><xsl:when test="@synchronous">true</xsl:when><xsl:otherwise>false</xsl:otherwise></xsl:choose>;
    protected $responseMethods = array(<xsl:for-each select="./response">'<xsl:value-of select="@name"/>'<xsl:if test="position() != last()">, </xsl:if></xsl:for-each>);
    protected $fields = array(<xsl:for-each select="./field">'<xsl:value-of select="@name"/>'<xsl:if test="position() != last()">, </xsl:if></xsl:for-each>);
    function fields () { return FieldFactory::GetFields($this->name); }
    function responses () { return FieldFactory::GetFields($this->responseMethods); }
}
  </xsl:template>


  <xsl:template match="field" mode="output-method-fields">
class <xsl:value-of select="bl:getFieldClassName()"/> extends codegen_iface\XmlSpecField
{
    protected $name = '<xsl:value-of select="@name"/>';
    protected $domain = '<xsl:value-of select="@domain"/>';
<xsl:if test="./assert">
    function validate($subject) {
        parent::validate($subject);
        <xsl:for-each select="./assert">
        $this->assert(<xsl:value-of select="bl:getCodeForAssert(@check, @value, '$subject')"/>);</xsl:for-each>
    }
</xsl:if>
}

  </xsl:template>


<!--
   Stylesheet ends - all exslt funcs from here
-->


  <func:function name="bl:uctoken">
    <xsl:param name="str"/>
    <func:result select="concat(bl:capitalize(substring($str, 1, 1)), substring($str, 2))" />
  </func:function>


  <func:function name="bl:capitalize">
    <xsl:param name="s"/>
    <func:result select="translate($s, 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ')"/>
  </func:function>



  <!-- Converts an amqp xml name (containing hyphen character) in to a PHP const  -->
  <func:function name="bl:convertToConst">
    <xsl:param name="constName"/>

    <xsl:variable name="result">
      <xsl:for-each select="str:tokenize($constName, '-')">
	<xsl:choose>
	  <xsl:when test="position() != last()">
	    <xsl:value-of select="concat(bl:capitalize(string(.)), '_')"/>
	  </xsl:when>
	  <xsl:otherwise>
	    <xsl:value-of select="bl:capitalize(.)"/>
	  </xsl:otherwise>
	</xsl:choose>
      </xsl:for-each>
    </xsl:variable>
    <func:result select="$result"/>
  </func:function>

  <!-- Guess the PHP type of the input (string, int) and return a the input as quoted PHP literal -->
  <func:function name="bl:quotePhp">
    <xsl:param name="val"/>

    <func:result>
      <xsl:choose>
	<xsl:when test="translate($val, '0123456789', '') = ''">
	  <xsl:value-of select="$val"/>
	</xsl:when>
	<xsl:otherwise>
	  <xsl:value-of select="concat('&quot;', $val, '&quot;')"/>
	</xsl:otherwise>
      </xsl:choose>
    </func:result>
  </func:function>


  <!-- Converts an Amqp xml name to camel case.  defaults to not UC on first char  -->
  <func:function name="bl:convertToCamel">
    <xsl:param name="subj"/>
    <xsl:param name="upper-first" select="false"/>

    <func:result>
      <xsl:for-each select="str:tokenize($subj, '-')">
	<xsl:choose>
	  <xsl:when test="position() &gt; 1 or $upper-first">
	    <xsl:value-of select="concat(bl:capitalize(substring(string(.), 1, 1)), substring(string(.), 2))"/>
	  </xsl:when>
	  <xsl:otherwise>
	    <xsl:value-of select="string(.)"/>
	  </xsl:otherwise>
	</xsl:choose>
      </xsl:for-each>
    </func:result>
  </func:function>


  <!-- Return a PHP expression to implement the given amqp spec assert -->
  <func:function name="bl:getCodeForAssert">
    <xsl:param name="assert-check"/> <!-- assert/@check  -->
    <xsl:param name="assert-value"/> <!-- assert/@value  -->
    <xsl:param name="subject"/> <!-- Subject var name (including dollar) -->

    <func:result>
      <xsl:choose>
	<xsl:when test="$assert-check = 'length'">
	  <xsl:value-of select="concat('strlen(', $subject, ') &lt; ', $assert-value)"/>
	</xsl:when>
	<xsl:when test="$assert-check = 'notnull'">
	  <xsl:value-of select="concat('! is_null(', $subject, ')')"/>
	</xsl:when>
	<xsl:when test="$assert-check = 'regexp'">
	  <xsl:value-of select="concat('preg_match(&quot;/', $assert-value, '/&quot;, ' , $subject, ')')"/>
	</xsl:when>
	<xsl:otherwise>
	  <xsl:value-of select="'true'"/>
	</xsl:otherwise>
      </xsl:choose>
    </func:result>
  </func:function>


  <func:function name="bl:getFileName">
    <xsl:param name="name" select="''"/>
    <xsl:choose>
      <xsl:when test="$name = ''">
	<func:result select="concat('amqp.', $VERSION_TOKEN, '.php')"/>
      </xsl:when>
      <xsl:otherwise>
	<func:result select="concat('amqp.', $VERSION_TOKEN, '.', $name, '.php')"/>
      </xsl:otherwise>
    </xsl:choose>
  </func:function>

  <func:function name="bl:getFilePath">
    <xsl:param name="name" select="''"/>
    <func:result select="concat($OUTPUT_DIR, bl:getFileName($name))"/>
  </func:function>


  <func:function name="bl:getPackageName">
    <xsl:param name="class"/>
    <xsl:param name="return-as-string" select="0"/>

    <xsl:choose>
      <xsl:when test="$return-as-string">
	<func:result select="concat('amqp_', $VERSION_TOKEN, '\\', $class, '\\')"/>
      </xsl:when>
      <xsl:otherwise>
	<func:result select="concat('amqp_', $VERSION_TOKEN, '\', $class)"/>
      </xsl:otherwise>
    </xsl:choose>
  </func:function>

  <func:function name="bl:getFQClassName">
    <xsl:param name="class"/>
    <xsl:param name="obj-name"/>
    <xsl:param name="postfix" select="''"/>
    <func:result select="concat(bl:getPackageName($class), '\', bl:getGenClassName($obj-name, $postfix))"/>
  </func:function>

  <func:function name="bl:getGenClassName">
    <xsl:param name="obj-name"/>
    <xsl:param name="postfix" select="''"/>
    <func:result select="concat(bl:convertToCamel($obj-name, 1), $postfix)"/>
  </func:function>

  <!-- Special naming fun for fields - these are named based on their position to avoid
       clashes between fields in a class.  Note: this method must be called with the target
       field as the context node -->
  <func:function name="bl:getFieldClassName">
    <xsl:choose>
      <xsl:when test="local-name(parent::*) = 'class'">
	<func:result select="bl:getGenClassName(@name, 'Field')"/>
      </xsl:when>
      <xsl:otherwise>
	<func:result select="concat(bl:getGenClassName(../@name), bl:getGenClassName(@name, 'Field'))"/>
      </xsl:otherwise>
    </xsl:choose>
  </func:function>


  <func:function name="bl:getElementaryDomainType">
    <xsl:param name="domain"/>
    <func:result select="document('')//bl:elk/bl:map[@version=$VERSION_STRING]/bl:domain[@name=$domain]/@type"/>
  </func:function>


</xsl:stylesheet>
