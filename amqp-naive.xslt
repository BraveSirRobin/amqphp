<?xml version="1.0"?>

<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
		xmlns:func="http://exslt.org/functions"
		xmlns:str="http://exslt.org/strings"
		xmlns:bl="http://bluelines.org"
		version="1.0"
		extension-element-prefixes="func">

  <xsl:variable name="VERSION_POSTFIX" select="concat(string(/amqp/@major), '_', string(/amqp/@minor), '_', string(/amqp/@revision))"/>

  <xsl:output method="text"/>
  <!--
     A Naive code generator to run through and get a feel for the AMQP client XML spec
    -->
  <xsl:template match="/">&lt;?php
/**
 * Generated from AMQP client XML spec, version <xsl:value-of select="/amqp/@major"/>.<xsl:value-of select="/amqp/@minor"/>.<xsl:value-of select="/amqp/@revision"/>
 *
 * TODO: Write common method assert() to support handle generated assertions
 * TODO: Write a common method loadParams() - func to allow flexibility in
 *       calling amqp methods (i.e. as assoc array or individual params)
 */
abstract class Amqp_<xsl:value-of select="$VERSION_POSTFIX"/>
{
<xsl:apply-templates select="/amqp/constant"/>

<xsl:apply-templates select="/amqp/domain"/>

}

<xsl:apply-templates select="/amqp/class"/>
  </xsl:template>



  <!-- Outputs constants as constants (suprise...)  -->
  <xsl:template match="constant">
    const <xsl:value-of select="bl:convertToConst(@name)"/> = <xsl:value-of select="@value"/>;</xsl:template>


  <!-- Output domains as validation methods -->
  <xsl:template match="domain">
    <!-- Don't output accessors for elementary domains -->
    <xsl:if test="@name != @type">
    static function Read<xsl:value-of select="bl:convertToCamel(@name, true())"/> (AMQPReader $r) {
      $val = $r->read_<xsl:value-of select="@type"/>();
    <xsl:for-each select="./assert">
      $this->assert(<xsl:value-of select="bl:getCodeForAssert(@check, @value, '$val')"/>);</xsl:for-each>
      return $val;
    }
    static function Write<xsl:value-of select="bl:convertToCamel(@name, true())"/> (AMQPWriter $w, $c) {
    <xsl:for-each select="./assert">
      $this->assert(<xsl:value-of select="bl:getCodeForAssert(@check, @value, '$c')"/>);</xsl:for-each>
      return $w->write_<xsl:value-of select="@type"/>($content);
    }
    </xsl:if>
  </xsl:template>



  <!-- Output classes that descend from the version base -->
  <xsl:template match="class">
    <xsl:variable name="implements">
      <xsl:choose>
	<xsl:when test="./field">
	  <xsl:value-of select="' implements ArrayAccess'"/>
	</xsl:when>
	<xsl:otherwise>
	  <xsl:value-of select="string('')"/>
	</xsl:otherwise>
      </xsl:choose>
    </xsl:variable>
class <xsl:value-of select="bl:convertToCamel(@name, true())"/>_<xsl:value-of select="$VERSION_POSTFIX"/> extends Amqp_<xsl:value-of select="$VERSION_POSTFIX"/><xsl:value-of select="$implements"/>
{
    protected $classProps = array(
      <xsl:for-each select="@*">'<xsl:value-of select="name()"/>' =&gt; <xsl:value-of select="bl:quotePhp(string(.))"/><xsl:if test="position() != last()">, </xsl:if></xsl:for-each>
    );
    <xsl:if test="$implements != ''">
<xsl:call-template name="output-array-access-impl"/>
    </xsl:if>

<xsl:apply-templates select="./method"/>
}
  </xsl:template>


  <!-- Map amqp methods to PHP methods -->
  <xsl:template match="method">
    function <xsl:value-of select="bl:convertToCamel(@name)"/>() {
      <xsl:if test="./chassis">/* Chassis: <xsl:for-each select="./chassis"><xsl:value-of select="concat(@name, ' ', @implement)"/><xsl:if test="position() != last()">, </xsl:if></xsl:for-each> */</xsl:if>
      <xsl:if test="./response">/* Response: <xsl:for-each select="./response"><xsl:value-of select="@name"/><xsl:if test="position() != last()">, </xsl:if></xsl:for-each>  */</xsl:if>
      $defs = array(<xsl:for-each select="./field[@domain]">'<xsl:value-of select="@name"/>'<xsl:if test="position() != last()">, </xsl:if></xsl:for-each>);
      $p = $this->loadParams(func_get_args(), $defs);
      <xsl:for-each select="./field[@domain]/assert">
      $this->assert(<xsl:value-of select="bl:getCodeForAssert(@check, @value, concat('$p[&quot;', ../@name, '&quot;]'))"/>);</xsl:for-each>
      return $defs;
    }
  </xsl:template>





  <!-- Helper for class template: write out the PHP code to implement the
       ArrayAccess feature on classes with direct field members -->
  <xsl:template name="output-array-access-impl">
    // ?! Not sure what these are for - input XML @domain data un-used
    private $_fields = array(<xsl:for-each select="./field">'<xsl:value-of select="string(@name)"/>' => null<xsl:if test="position() != last()">, </xsl:if></xsl:for-each>);

    function offsetSet($k, $v) {
      if (isset($this->_fields[$k])) {
        $this->_fields[$k] = $v;
      } else {
        trigger_error(&quot;Unknown offset $k&quot;, E_USER_WARNING);
      }
    }
    function offsetGet($k) {
      if (isset($this->_fields[$k])) {
        return $this->_fields[$k];
      } else {
        trigger_error(&quot;Unknown offset $k&quot;, E_USER_WARNING);
        return null;
      }
    }
    function offsetExists($k) {
      return isset($this->_fields[$k]);
    }
    function offsetUnset($k) {
      throw new Exception(&quot;Cannot remove - offsets are fixed&quot;);
    }
  </xsl:template>


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
	  <xsl:value-of select="concat('preg_match(&quot;', $assert-value, '&quot;, ' , $subject, ')')"/>
	</xsl:when>
	<xsl:otherwise>
	  <xsl:value-of select="'true'"/>
	</xsl:otherwise>
      </xsl:choose>
    </func:result>
  </func:function>

  <!-- Convert input string to upper case -->
  <func:function name="bl:capitalize">
    <xsl:param name="s"/>
    <func:result select="translate($s, 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ')"/>
  </func:function>

</xsl:stylesheet>
