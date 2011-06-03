<?php
 namespace amqphp\wire; use amqphp\protocol as proto; use amqphp\protocol\abstrakt; class Method { const ST_METH_READ = 1; const ST_CHEAD_READ = 2; const ST_BODY_READ = 4; const PARTIAL_FRAME = 7; private $rcState = 0; private $methProto; private $classProto; private $mode; private $fields = array(); private $classFields = array(); private $content; private $frameMax; private $wireChannel = null; private $isHb = false; private $wireMethodId; private $wireClassId; private $contentSize; private $protoLoader; function __construct (abstrakt\XmlSpecMethod $src = null, $chan = 0) { if ($src instanceof abstrakt\XmlSpecMethod) { $this->methProto = $src; $this->classProto = $this->methProto->getClass(); $this->mode = 'write'; $this->wireChannel = $chan; } } function canReadFrom (Reader $src) { if (is_null($this->wireChannel)) { return true; } if (true === ($_fh = $this->extractFrameHeader($src))) { return false; } list($wireType, $wireChannel, $wireSize) = $_fh; $ret = ($wireChannel == $this->wireChannel); $src->rewind(7); return $ret; } function readConstruct (Reader $src, \Closure $protoLoader) { if ($this->mode == 'write') { trigger_error('Invalid read construct operation on a read mode method', E_USER_WARNING); return false; } $FRME = 206; $break = false; $ret = true; $this->protoLoader = $protoLoader; while (! $src->isSpent()) { if (true === ($_fh = $this->extractFrameHeader($src))) { $ret = self::PARTIAL_FRAME; break; } else { list($wireType, $wireChannel, $wireSize) = $_fh; } if (! $this->wireChannel) { $this->wireChannel = $wireChannel; } else if ($this->wireChannel != $wireChannel) { $src->rewind(7); return true; } if ($src->isSpent($wireSize + 1)) { $src->rewind(7); $ret = self::PARTIAL_FRAME; break; } switch ($wireType) { case 1: $this->readMethodContent($src, $wireSize); if (! $this->methProto->getSpecHasContent()) { $break = true; } break; case 2: $this->readContentHeaderContent($src, $wireSize); break; case 3: $this->readBodyContent($src, $wireSize); if ($this->readConstructComplete()) { $break = true; } break; case 8: $break = $ret = $this->isHb = true; break; default: throw new \Exception(sprintf("Unsupported frame type %d", $wireType), 8674); } if ($src->read('octet') != $FRME) { throw new \Exception(sprintf("Framing exception - missed frame end (%s.%s) - (%d,%d,%d,%d) [%d, %d]", $this->classProto->getSpecName(), $this->methProto->getSpecName(), $this->rcState, $break, $src->isSpent(), $this->readConstructComplete(), strlen($this->content), $this->contentSize ), 8763); } if ($break) { break; } } return $ret; } private function extractFrameHeader(Reader $src) { if ($src->isSpent(7)) { return true; } if (null === ($wireType = $src->read('octet'))) { throw new \Exception('Failed to read type from frame', 875); } else if (null === ($wireChannel = $src->read('short'))) { throw new \Exception('Failed to read channel from frame', 9874); } else if (null === ($wireSize = $src->read('long'))) { throw new \Exception('Failed to read size from frame', 8715); } return array($wireType, $wireChannel, $wireSize); } private function readMethodContent (Reader $src, $wireSize) { $st = $src->getReadPointer(); $this->wireClassId = $src->read('short'); $this->wireMethodId = $src->read('short'); $protoLoader = $this->protoLoader; if (! ($this->classProto = $protoLoader('ClassFactory', 'GetClassByIndex', array($this->wireClassId)))) { throw new \Exception(sprintf("Failed to construct class prototype for class ID %s", $this->wireClassId), 9875); } else if (! ($this->methProto = $this->classProto->getMethodByIndex($this->wireMethodId))) { throw new \Exception("Failed to construct method prototype", 5645); } foreach ($this->methProto->getFields() as $f) { $this->fields[$f->getSpecFieldName()] = $src->read($f->getSpecDomainType()); } $en = $src->getReadPointer(); if ($wireSize != ($en - $st)) { throw new \Exception("Invalid method frame size", 9845); } $this->rcState = $this->rcState | self::ST_METH_READ; } private function readContentHeaderContent (Reader $src, $wireSize) { $st = $src->getReadPointer(); $wireClassId = $src->read('short'); $src->read('short'); $this->contentSize = $src->read('longlong'); if ($wireClassId != $this->wireClassId) { throw new \Exception(sprintf("Unexpected class in content header (%d, %d) - read state %d", $wireClassId, $this->wireClassId, $this->rcState), 5434); } $binFlags = ''; while (true) { if (null === ($fBlock = $src->read('short'))) { throw new \Exception("Failed to read property flag block", 4548); } $binFlags .= str_pad(decbin($fBlock), 16, '0', STR_PAD_LEFT); if (0 !== (strlen($binFlags) % 16)) { throw new \Exception("Unexpected message property flags", 8740); } if (substr($binFlags, -1) == '1') { $binFlags = substr($binFlags, 0, -1); } else { break; } } foreach ($this->classProto->getFields() as $i => $f) { if ($f->getSpecFieldDomain() == 'bit') { $this->classFields[$f->getSpecFieldName()] = (boolean) substr($binFlags, $i, 1); } else if (substr($binFlags, $i, 1) == '1') { $this->classFields[$f->getSpecFieldName()] = $src->read($f->getSpecFieldDomain()); } else { $this->classFields[$f->getSpecFieldName()] = null; } } $en = $src->getReadPointer(); if ($wireSize != ($en - $st)) { throw new \Exception("Invalid content header frame size", 2546); } $this->rcState = $this->rcState | self::ST_CHEAD_READ; } private function readBodyContent (Reader $src, $wireSize) { $this->content .= $src->readN($wireSize); $this->rcState = $this->rcState | self::ST_BODY_READ; } function readConstructComplete () { if ($this->isHb) { return true; } else if (! $this->methProto) { return false; } else if (! $this->methProto->getSpecHasContent()) { return (boolean) $this->rcState & self::ST_METH_READ; } else { return ($this->rcState & self::ST_CHEAD_READ) && (strlen($this->content) >= $this->contentSize); } } function setField ($name, $val) { if ($this->mode === 'read') { trigger_error('Setting field value for read constructed method', E_USER_WARNING); } else { $this->fields[$name] = $val; } } function getField ($name) { return isset($this->fields[$name]) ? $this->fields[$name] : null; } function getFields () { return $this->fields; } function setClassField ($name, $val) { if ($this->mode === 'read') { trigger_error('Setting class field value for read constructed method', E_USER_WARNING); } else if (! $this->methProto->getSpecHasContent()) { trigger_error('Setting class field value for a method which doesn\'t take content (' . $this->classProto->getSpecName() . '.' . $this->methProto->getSpecName() . ')', E_USER_WARNING); } else { $this->classFields[$name] = $val; } } function getClassField ($name) { return isset($this->classFields[$name]) ? $this->classFields[$name] : null; } function getClassFields () { return $this->classFields; } function setContent ($content) { if (! $content) { return; } else if ($this->mode === 'read') { trigger_error('Setting content value for read constructed method', E_USER_WARNING); } else if (! $this->methProto->getSpecHasContent()) { trigger_error('Setting content value for a method which doesn\'t take content', E_USER_WARNING); } else { $this->content = $content; } } function getContent () { if (! $this->methProto->getSpecHasContent()) { trigger_error('Invalid serialize operation on a method which doesn\'t take content', E_USER_WARNING); return ''; } return $this->content; } function getMethodProto () { return $this->methProto; } function getClassProto () { return $this->classProto; } function getWireChannel () { return $this->wireChannel; } function getWireSize () { return $this->wireSize; } function getWireClassId () { return $this->wireClassId; } function getWireMethodId () { return $this->wireMethodId; } function setMaxFrameSize ($max) { $this->frameSize = $max; } function setWireChannel ($chan) { $this->wireChannel = $chan; } function isHeartbeat () { return $this->isHb; } function toBin (\Closure $protoLoader) { if ($this->mode == 'read') { trigger_error('Invalid serialize operation on a read mode method', E_USER_WARNING); return ''; } $frme = $protoLoader('ProtoConsts', 'GetConstant', array('FRAME_END')); $w = new Writer; $tmp = $this->getMethodBin(); $w->write(1, 'octet'); $w->write($this->wireChannel, 'short'); $w->write(strlen($tmp), 'long'); $buff = $w->getBuffer() . $tmp . $frme; $ret = array($buff); if ($this->methProto->getSpecHasContent()) { $w = new Writer; $tmp = $this->getContentHeaderBin(); $w->write(2, 'octet'); $w->write($this->wireChannel, 'short'); $w->write(strlen($tmp), 'long'); $ret[] = $w->getBuffer() . $tmp . $frme; $tmp = (string) $this->content; $i = 0; $frameSize = $this->frameSize - 8; while (true) { $chunk = substr($tmp, ($i * $frameSize), $frameSize); if (strlen($chunk) == 0) { break; } $w = new Writer; $w->write(3, 'octet'); $w->write($this->wireChannel, 'short'); $w->write(strlen($chunk), 'long'); $ret[] = $w->getBuffer() . $chunk . $frme; $i++; } } return $ret; } private function getMethodBin () { if ($this->mode == 'read') { trigger_error('Invalid serialize operation on a read mode method', E_USER_WARNING); return ''; } $src = new Writer; $src->write($this->classProto->getSpecIndex(), 'short'); $src->write($this->methProto->getSpecIndex(), 'short'); foreach ($this->methProto->getFields() as $f) { $name = $f->getSpecFieldName(); $type = $f->getSpecDomainType(); if (! isset($this->fields[$name])) { trigger_error("Missing field {$name} of method {$this->methProto->getSpecName()}", E_USER_WARNING); return ''; } else if (! $f->validate($this->fields[$name])) { trigger_error("Field {$name} of method {$this->methProto->getSpecName()} is not valid", E_USER_WARNING); return ''; } $src->write($this->fields[$name], $type); } return $src->getBuffer(); } private function getContentHeaderBin () { if ($this->mode == 'read') { trigger_error('Invalid serialize operation on a read mode method', E_USER_WARNING); return ''; } else if (! $this->methProto->getSpecHasContent()) { trigger_error('Invalid serialize operation on a method which doesn\'t take content', E_USER_WARNING); return ''; } $src = new Writer; $src->write($this->classProto->getSpecIndex(), 'short'); $src->write(0, 'short'); $src->write(strlen($this->content), 'longlong'); $pFlags = ''; $pChunks = 0; $pList = ''; $src2 = new Writer; foreach ($this->classProto->getFields() as $i => $f) { if (($i % 15) == 0) { if ($i > 0) { $pFlags .= '1'; } $pChunks++; } $fName = $f->getSpecFieldName(); $dName = $f->getSpecFieldDomain(); if (isset($this->classFields[$fName]) && ! ($dName == 'bit' && ! $this->classFields[$fName])) { $pFlags .= '1'; } else { $pFlags .= '0'; } if (isset($this->classFields[$fName]) && $dName != 'bit') { if (! $f->validate($this->classFields[$fName])) { trigger_error("Field {$fName} of method {$this->methProto->getSpecName()} is not valid", E_USER_WARNING); return ''; } $src2->write($this->classFields[$fName], $f->getSpecDomainType()); } } if ($pFlags && (strlen($pFlags) % 16) !== 0) { $pFlags .= str_repeat('0', 16 - (strlen($pFlags) % 16)); } $pBuff = ''; for ($i = 0; $i < $pChunks; $i++) { $pBuff .= pack('n', bindec(substr($pFlags, $i*16, 16))); } return $src->getBuffer() . $pBuff . $src2->getBuffer(); } function isResponse (Method $other) { if ($exp = $this->methProto->getSpecResponseMethods()) { if ($this->classProto->getSpecName() != $other->classProto->getSpecName() || $this->wireChannel != $other->wireChannel) { return false; } else { return in_array($other->methProto->getSpecName(), $exp); } } else { trigger_error("Method does not expect a response", E_USER_WARNING); return false; } } } function hexdump ($buff) { static $f, $f2; if ($buff === '') { return "00000000\n"; } if (is_null($f)) { $f = function ($char) { return sprintf('%02s', dechex(ord($char))); }; $f2 = function ($char) { $ord = ord($char); return ($ord > 31 && $ord < 127) ? chr($ord) : '.'; }; } $l = strlen($buff); $ret = ''; for ($i = 0; $i < $l; $i += 16) { $line = substr($buff, $i, 8); $ll = $offLen = strlen($line); $rem = (8 - $ll) * 3; $hexes = vsprintf(str_repeat('%3s', $ll), array_map($f, str_split($line, 1))); $chars = '|' . vsprintf(str_repeat('%s', $ll), array_map($f2, str_split($line, 1))); $lBuff = sprintf("%08s %s", dechex($i), $hexes); if ($line = substr($buff, $i + 8, 8)) { $ll = strlen($line); $offLen += $ll; $rem = (8 - $ll) * 3 + 1; $hexes = vsprintf(str_repeat('%3s', $ll), array_map($f, str_split($line, 1))); $chars .= ' '. vsprintf(str_repeat('%s', $ll), array_map($f2, str_split($line, 1))); $lBuff .= sprintf(" %s%{$rem}s %s|\n", $hexes, ' ', $chars); } else { $lBuff .= ' ' . str_repeat(" ", $rem + 26) . $chars . "|\n"; } $ret .= $lBuff; } return sprintf("%s%08s\n", $ret, dechex($l)); }