<?xml version="1.0"?>

<!-- 
 Copyright (C) 2010, 2011  Robin Harvey (harvey.robin@gmail.com)

 This library is free software; you can redistribute it and/or
 modify it under the terms of the GNU Lesser General Public
 License as published by the Free Software Foundation; either
 version 2.1 of the License, or (at your option) any later version.

 This library is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 Lesser General Public License for more details.

 You should have received a copy of the GNU Lesser General Public
 License along with this library; if not, write to the Free Software
 Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
-->
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
		xmlns:func="http://exslt.org/functions"
		xmlns:str="http://exslt.org/strings"
		xmlns:exsl="http://exslt.org/common"
		xmlns:bl="http://www.bluelines.org/"
		version="1.0"
		extension-element-prefixes="func str exsl">

  <xsl:param name="NS_PREPEND" select="'amqp_091\protocol'"/>
  <xsl:param name="IMPL_NS" select="'amqp_091\protocol\abstrakt'"/>
  <xsl:param name="WIRE_NS" select="'amqp_091\wire'"/>
  <xsl:param name="OUTPUT_DIR" select="'gencode/'"/>

  <xsl:variable name="VERSION_TOKEN" select="concat(string(/amqp/@major), '_', string(/amqp/@minor), '_', string(/amqp/@revision))"/>
  <xsl:variable name="VERSION_STRING" select="concat(string(/amqp/@major), '.', string(/amqp/@minor), '.', string(/amqp/@revision))"/>

  <!-- Normalise the input to remove leading and trailing slashes -->
  <xsl:variable name="_NS_PREPEND" select="bl:normalisePhpNsIdentifier($NS_PREPEND)"/>
  <xsl:variable name="_IMPL_NS" select="bl:normalisePhpNsIdentifier($IMPL_NS)"/>
  <xsl:variable name="_WIRE_NS" select="bl:normalisePhpNsIdentifier($WIRE_NS)"/>


  <xsl:output method="text"/>


  <xsl:template match="/">
    <xsl:call-template name="output-global-code"/>
    <xsl:apply-templates select="/amqp/class" mode="output-class-classes"/>
  </xsl:template>


  <!-- Output the top level generated code file -->
  <xsl:template name="output-global-code">
    <xsl:variable name="file-name" select="bl:getFilePath()"/>
    <exsl:document href="{$file-name}" method="text" omit-xml-declaration="yes">&lt;?php
namespace <xsl:value-of select="bl:getPhpNamespace()"/>;
/** Ampq binding code, generated from doc version <xsl:value-of select="$VERSION_STRING"/> */
use <xsl:value-of select="$_WIRE_NS"/> as wire;
<!-- Output constants -->
<xsl:for-each select="/amqp/constant"> <!-- TODO: Convert to hex consts -->
const <xsl:value-of select="bl:convertToConst(@name)"/> = &quot;<xsl:value-of select="bl:intToPHPHexLiteral(@value)"/>&quot;;</xsl:for-each>

<!-- Constant lookup function -->
function Konstant($c) {
    static $kz = array(<xsl:for-each select="/amqp/constant"><xsl:value-of select="@value"/> => array('value' => '<xsl:value-of select="@value"/>', 'name' => '<xsl:value-of select="bl:convertToConst(@name)"/>', 'class' => '<xsl:value-of select="@class"/>')<xsl:if test="position()!=last()">, </xsl:if></xsl:for-each>);
    return isset($kz[$c]) ? $kz[$c] : null;
}

<!-- Output method lookup factory -->
class ClassFactory extends \<xsl:value-of select="bl:getPhpParentNs()"/>\ClassFactory
{
    <!-- Format: array(array(<class-idx>, <class-name>, <fully-qualified XmlSpecMethod impl. class name>))+ -->
    protected static $Cache = array(<xsl:for-each select="//class">array(<xsl:value-of select="@index"/>, '<xsl:value-of select="@name"/>', '\\<xsl:value-of select="bl:getPhpClassName('Class', true(), true())"/>')<xsl:if test="position() != last()">,</xsl:if></xsl:for-each>);

}

<!-- Ouptput global static domain loader map -->
class DomainFactory extends \<xsl:value-of select="bl:getPhpParentNs()"/>\DomainFactory
{
    <!-- Map: array(<xml-domain-name> => <local XmlSpecDomain impl. class name>) -->
    protected static $Cache = array(<xsl:for-each select="/amqp/domain">'<xsl:value-of select="@name"/>' => '\\<xsl:value-of select="bl:getPhpClassName('Domain', true(), true())"/>'<xsl:if test="position() != last()">, </xsl:if></xsl:for-each>);
}

<!-- Output the fundamental domain objects -->
// Fundamental domains
<xsl:apply-templates select="/amqp/domain[@name = @type]" mode="output-fundamental-domain-class"/>
<!-- Output the global domain objects -->
// Global domains
<xsl:apply-templates select="/amqp/domain[@name != @type]" mode="output-domain-class"/>
// Include generated sub-namespaces
<xsl:for-each select="/amqp/class">
require '<xsl:value-of select="bl:getFileName(@name)"/>';</xsl:for-each>
    </exsl:document>
  </xsl:template>


  <!-- Output the domain class implementation for a single domain -->
  <xsl:template match="domain" mode="output-domain-class">
class <xsl:value-of select="bl:getPhpClassName('Domain')"/> extends <xsl:value-of select="bl:getGenClassName(@type, 'Domain')"/>
{
    protected $name = '<xsl:value-of select="@name"/>';
    protected $protocolType = '<xsl:value-of select="@type"/>';
    <xsl:if test="./assert">
    function validate($subject) {
        return (parent::validate($subject) &amp;&amp; <xsl:for-each select="./assert"><xsl:value-of select="bl:getCodeForAssert(@check, @value, '$subject')"/><xsl:if test="position() != last()"> &amp;&amp; </xsl:if></xsl:for-each>);
    }
    </xsl:if>
}
  </xsl:template>


  <!-- Output classes for fundamental domains, these use the internal protocol mappinging functions -->
  <xsl:template match="domain" mode="output-fundamental-domain-class">
class <xsl:value-of select="bl:getPhpClassName('Domain')"/> extends \<xsl:value-of select="bl:getPhpParentNs()"/>\XmlSpecDomain
{
    protected $name = '<xsl:value-of select="@name"/>';
    protected $protocolType = '<xsl:value-of select="@type"/>';
}
  </xsl:template>





  <!-- Output lower level class file/namespaces -->
  <xsl:template match="class" mode="output-class-classes">
    <xsl:variable name="file-name" select="bl:getFilePath(@name)"/>
    <exsl:document href="{$file-name}" method="text" omit-xml-declaration="yes">&lt;?php
namespace <xsl:value-of select="bl:getPhpNamespace(@name)"/>;
/** Ampq binding code, generated from doc version <xsl:value-of select="$VERSION_STRING"/> */
class <xsl:value-of select="bl:getPhpClassName('Class')"/> extends \<xsl:value-of select="bl:getPhpParentNs()"/>\XmlSpecClass
{
    protected $name = '<xsl:value-of select="@name"/>';
    protected $index = <xsl:value-of select="@index"/>;
    protected $fields = array(<xsl:for-each select="./field">'<xsl:value-of select="@name"/>'<xsl:if test="position() != last()">,</xsl:if></xsl:for-each>);
    protected $methods = array(<xsl:for-each select="./method"><xsl:value-of select="@index"/> => '<xsl:value-of select="@name"/>'<xsl:if test="position() != last()">,</xsl:if></xsl:for-each>);
    protected $methFact = '\\<xsl:value-of select="bl:getPhpNamespace(@name, true())"/>\\MethodFactory';
    protected $fieldFact = '\\<xsl:value-of select="bl:getPhpNamespace(@name, true())"/>\\FieldFactory';
}

abstract class MethodFactory extends \<xsl:value-of select="bl:getPhpParentNs()"/>\MethodFactory
{
    protected static $Cache = array(<xsl:for-each select="./method">array(<xsl:value-of select="@index"/>, '<xsl:value-of select="@name"/>', '\\<xsl:value-of select="bl:getPhpClassName('Method', true(), true())"/>')<xsl:if test="position() != last()">,</xsl:if></xsl:for-each>);
}

abstract class FieldFactory  extends \<xsl:value-of select="bl:getPhpParentNs()"/>\FieldFactory
{
    protected static $Cache = array(<xsl:for-each select=".//field[@domain or @type]">array('<xsl:value-of select="@name"/>', '<xsl:value-of select="parent::*[local-name() = 'method']/@name"/>', '\\<xsl:value-of select="bl:getPhpClassName('Field', true(), true())"/>')<xsl:if test="position() != last()">,</xsl:if></xsl:for-each>);
}


<xsl:apply-templates select="./method" mode="output-method-classes"/>


<xsl:apply-templates select=".//field[@domain or @type]" mode="output-method-fields"/>

    </exsl:document>
  </xsl:template>



  <xsl:template match="method" mode="output-method-classes">
class <xsl:value-of select="bl:getPhpClassName('Method')"/> extends \<xsl:value-of select="bl:getPhpParentNs()"/>\XmlSpecMethod
{
    protected $class = '<xsl:value-of select="../@name"/>';
    protected $name = '<xsl:value-of select="@name"/>';
    protected $index = <xsl:value-of select="@index"/>;
    protected $synchronous = <xsl:choose><xsl:when test="@synchronous">true</xsl:when><xsl:otherwise>false</xsl:otherwise></xsl:choose>;
    protected $responseMethods = array(<xsl:for-each select="./response">'<xsl:value-of select="@name"/>'<xsl:if test="position() != last()">, </xsl:if></xsl:for-each>);
    protected $fields = array(<xsl:for-each select="./field">'<xsl:value-of select="@name"/>'<xsl:if test="position() != last()">, </xsl:if></xsl:for-each>);
    protected $methFact = '\\<xsl:value-of select="bl:getPhpNamespace(../@name, true())"/>\\MethodFactory';
    protected $fieldFact = '\\<xsl:value-of select="bl:getPhpNamespace(../@name, true())"/>\\FieldFactory';
    protected $classFact = '\\<xsl:value-of select="str:replace($_NS_PREPEND, '\', '\\')"/>\\ClassFactory';
    protected $content = <xsl:choose><xsl:when test="@content">true</xsl:when><xsl:otherwise>false</xsl:otherwise></xsl:choose>;
    protected $hasNoWait = <xsl:choose><xsl:when test="./field[@domain='no-wait']">true</xsl:when><xsl:otherwise>false</xsl:otherwise></xsl:choose>;
}
  </xsl:template>


  <xsl:template match="field" mode="output-method-fields">
    <xsl:variable name="daType"><!-- @domain or @type -->
    <xsl:choose>
      <xsl:when test="@domain">
	<xsl:value-of select="@domain"/>
      </xsl:when>
      <xsl:otherwise>
	<xsl:value-of select="@type"/>
      </xsl:otherwise>
    </xsl:choose>
      
    </xsl:variable>
class <xsl:value-of select="bl:getPhpClassName('Field')"/> extends \<xsl:value-of select="bl:getPhpNamespace()"/>\<xsl:value-of select="bl:getGenClassName($daType)"/>Domain implements \<xsl:value-of select="bl:getPhpParentNs()"/>\XmlSpecField
{
    function getSpecFieldName() { return '<xsl:value-of select="@name"/>'; }
    function getSpecFieldDomain() { return '<xsl:value-of select="$daType"/>'; }
<xsl:if test="./assert">
    function validate($subject) {
        return (parent::validate($subject) &amp;&amp; <xsl:for-each select="./assert"><xsl:value-of select="bl:getCodeForAssert(@check, @value, '$subject')"/><xsl:if test="position() != last()"> &amp;&amp; </xsl:if></xsl:for-each>);
    }
</xsl:if>
}

  </xsl:template>


<!--
   Stylesheet ends - all exslt funcs from here
-->


  <!-- Helper: double slash the given ns ID -->
  <func:function name="bl:escapePhpNsIdentifier">
    <xsl:param name="s"/>

    <xsl:variable name="res">
      <xsl:for-each select="str:tokenize($s, '\')">
	<xsl:choose>
	  <xsl:when test="position() = last()">
	    <xsl:value-of select="string(.)"/>
	  </xsl:when>
	  <xsl:otherwise>
	    <xsl:value-of select="concat(string(.), '\\')"/>
	  </xsl:otherwise>
	</xsl:choose>
      </xsl:for-each>
    </xsl:variable>
    <func:result select="$res"/>
  </func:function>


  <func:function name="bl:normalisePhpNsIdentifier">
    <xsl:param name="s"/>

    <xsl:variable name="res">
      <xsl:for-each select="str:tokenize($s, '\')">
	<xsl:choose>
	  <xsl:when test="position() = last()">
	    <xsl:value-of select="string(.)"/>
	  </xsl:when>
	  <xsl:otherwise>
	    <xsl:value-of select="concat(string(.), '\')"/>
	  </xsl:otherwise>
	</xsl:choose>
      </xsl:for-each>
    </xsl:variable>
    <func:result select="$res"/>
  </func:function>


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
    <xsl:param name="upper-first" select="false()"/>

    <func:result>
      <xsl:for-each select="str:tokenize($subj, '-')">
	<xsl:choose>
	  <xsl:when test="position() &gt; 1 or $upper-first != false()">
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



<!--
Refactor to always output fully qualified class names, allow for optional namespace
prepend via. SS input params.

 + $NS_PREPEND ... Optional prepend (FQ) for all generated namespaces
 + $IMPL_NS ... Optional namespace (FQ) of all parent abstract classes (default to \codegen_iface\)

getPhpNamespace($amqpClass = '', $asLiteral=false) ... return $NS_PREPEND\amqp_<version>{\<$class>})

getPhpClassName($amqpClass, $amqpName, $append = '', $asLiteral=false)
getFQPhpClassName($amqpClass, $amqpName, $append = '', $asLiteral=false)
-->

  <func:function name="bl:getPhpNamespace">
    <xsl:param name="amqpClass" select="''"/>
    <xsl:param name="asLiteral" select="false()"/>

    <xsl:variable name="ret">
      <xsl:choose>
	<xsl:when test="$amqpClass != ''">
	  <xsl:value-of select="concat($_NS_PREPEND, '\', $amqpClass)"/>
	</xsl:when>
	<xsl:otherwise>
	  <xsl:value-of select="$_NS_PREPEND"/>
	</xsl:otherwise>
      </xsl:choose>
    </xsl:variable>
    <xsl:choose>
      <xsl:when test="$asLiteral = false()">
	<func:result select="$ret"/>
      </xsl:when>
      <xsl:otherwise>
	<func:result select="bl:escapePhpNsIdentifier($ret)"/>
      </xsl:otherwise>
    </xsl:choose>
  </func:function>

  <func:function name="bl:getPhpParentNs">
    <xsl:param name="asLiteral" select="false()"/>
    <xsl:choose>
      <xsl:when test="$asLiteral = false()">
	<func:result select="$_IMPL_NS"/>
      </xsl:when>
      <xsl:otherwise>
	<func:result select="str:replace($_IMPL_NS, '\', '\\')"/>
      </xsl:otherwise>
    </xsl:choose>
  </func:function>


  <!-- Unified class generation function, is sensitive to context, call with the object
       that you want to name as context node -->
  <func:function name="bl:getPhpClassName">
    <xsl:param name="append" select="''"/>
    <xsl:param name="fQual" select="false()"/>
    <xsl:param name="asLiteral" select="false()"/>


    <!-- Calculate the Unqualified name -->
    <!--<xsl:message>Get PHP class for (name = <xsl:value-of select="local-name()"/>), (parent name = <xsl:value-of select="local-name(..)"/>)</xsl:message>-->
    <xsl:variable name="uq-name">
      <xsl:choose>
	<xsl:when test="local-name() = 'field' and local-name(..) = 'method'">
	  <!--<xsl:message>Hi!</xsl:message>-->
	  <xsl:value-of select="concat(bl:convertToCamel(string(../@name), true()), bl:convertToCamel(@name, true()), $append)"/>
	</xsl:when>
	<xsl:otherwise>
	  <xsl:value-of select="concat(bl:convertToCamel(string(@name), true()), $append)"/>
	</xsl:otherwise>
      </xsl:choose>
    </xsl:variable>

    <!-- Return early for QU result -->
    <xsl:choose>
      <xsl:when test="$fQual = false()">
	<func:result select="$uq-name"/>
      </xsl:when>
      <xsl:otherwise>
	<!-- Figure out the enclosing class -->
	<xsl:variable name="class">
	  <xsl:choose>
	    <xsl:when test="local-name() = 'class'">
	      <xsl:value-of select="string(@name)"/>
	    </xsl:when>
	    <xsl:otherwise>
	      <xsl:value-of select="ancestor::class[1]/@name"/>
	    </xsl:otherwise>
	  </xsl:choose>
	</xsl:variable>

	<!--<xsl:message>Enclosed: '<xsl:value-of select="$tmp"/>' for <xsl:value-of select="local-name()"/> '<xsl:value-of select="@name"/>'</xsl:message>-->
	<xsl:choose>
	  <xsl:when test="$asLiteral = false()">
	    <func:result select="concat(bl:getPhpNamespace(string($class)), '\', $uq-name)"/>
	  </xsl:when>
	  <xsl:otherwise>
	    <func:result select="bl:escapePhpNsIdentifier(concat(bl:getPhpNamespace(string($class)), '\', $uq-name))"/>
	  </xsl:otherwise>
	</xsl:choose>
      </xsl:otherwise>
    </xsl:choose>
  </func:function>

  <!-- Converts an integer to PHP hex literal -->
  <func:function name="bl:intToPHPHexLiteral">
    <xsl:param name="i" select="0"/>
    <xsl:param name="ret" select="''"/>

    <xsl:choose>
      <xsl:when test="$i = 0">
	<!-- Recursion ends -->
	<xsl:choose>
	  <xsl:when test="(string-length($ret) &gt; 0) and ((string-length($ret) mod 2) = 0)">
	    <func:result select="bl:makeHexDuplets(string($ret))"/>
	  </xsl:when>
	  <xsl:when test="(string-length($ret) &gt; 0)">
	    <func:result select="bl:makeHexDuplets(concat(string($ret), '0'))"/>
	  </xsl:when>
	  <xsl:otherwise>
	    <func:result select="'\x00'"/>
	  </xsl:otherwise>
	</xsl:choose>
      </xsl:when>
      <xsl:otherwise>
	<xsl:variable name="tmp" select="$i div 16"/>
	<xsl:variable name="newI" select="floor($i div 16)"/>
	<xsl:variable name="dec" select="($tmp - $newI) * 16"/>
	<xsl:variable name="newDec">
	  <xsl:choose>
	    <xsl:when test="$dec > 9">
	      <xsl:if test="$dec = 10">A</xsl:if>
	      <xsl:if test="$dec = 11">B</xsl:if>
	      <xsl:if test="$dec = 12">C</xsl:if>
	      <xsl:if test="$dec = 13">D</xsl:if>
	      <xsl:if test="$dec = 14">E</xsl:if>
	      <xsl:if test="$dec = 15">F</xsl:if>
	    </xsl:when>
	    <xsl:otherwise>
	      <xsl:value-of select="$dec"/>
	    </xsl:otherwise>
	  </xsl:choose>
	</xsl:variable>
	<!-- Recurses -->
	<func:result select="bl:intToPHPHexLiteral($newI, concat($ret, $newDec))"/>
      </xsl:otherwise>
    </xsl:choose>
  </func:function>


  <func:function name="bl:makeHexDuplets">
    <xsl:param name="s"/>
    
    <xsl:if test="$s and ((string-length($s) mod 2) = 0)">
      <func:result select="concat('\x', substring($s, string-length($s), 1), substring($s, string-length($s) - 1, 1), bl:makeHexDuplets(substring($s, 1, string-length($s) - 2)))"/>
    </xsl:if>
  </func:function>
</xsl:stylesheet>
