<?xml version="1.0"?>

<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
		xmlns:func="http://exslt.org/functions"
		xmlns:str="http://exslt.org/strings"
		xmlns:exsl="http://exslt.org/common"
		xmlns:bl="http://www.bluelines.org/"
		version="1.0"
		extension-element-prefixes="func str exsl">
  <xsl:variable name="CRLF"><xsl:text>
</xsl:text></xsl:variable>

  <xsl:template match="/">
    <!--<xsl:value-of select="bl:strReverse('987654321')"/>-->

    <!--<xsl:value-of select="bl:makeHexDuplets('dcba')"/>-->
    <!--<xsl:for-each select="//n">
      <xsl:value-of select="number(.)"/>=<xsl:value-of select="bl:intToPHPHexLiteral(number(.))"/><xsl:value-of select="$CRLF"/>
    </xsl:for-each>-->

    <xsl:call-template name="testPHPHexThing">
      <xsl:with-param name="n" select="1"/>
      <xsl:with-param name="max" select="2577"/>
    </xsl:call-template>
  </xsl:template>



  <xsl:template name="testPHPHexThing">
    <xsl:param name="n"/>
    <xsl:param name="max"/>

    <xsl:if test="$n &lt;= $max">
      <xsl:value-of select="number($n)"/>=<xsl:value-of select="bl:intToPHPHexLiteral(number($n))"/><xsl:value-of select="$CRLF"/>
      <xsl:call-template name="testPHPHexThing">
	<xsl:with-param name="n" select="$n + 1"/>
	<xsl:with-param name="max" select="$max"/>
      </xsl:call-template>
    </xsl:if>
  </xsl:template>



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



  <func:function name="bl:strReverse">
    <xsl:param name="s"/>
    <xsl:if test="$s">
      <func:result select="concat(substring($s, string-length($s)), bl:strReverse(substring($s, 1, string-length($s) - 1)))"/>
    </xsl:if>
  </func:function>

</xsl:stylesheet>
