<?php
namespace amqphp;
 use amqphp\protocol, amqphp\wire;  class EventLoop { private $cons = array(); private static $In = false; function addConnection (Connection $conn) { $this->cons[$conn->getSocketId()] = $conn; } function removeConnection (Connection $conn) { if (array_key_exists($conn->getSocketId(), $this->cons)) { unset($this->cons[$conn->getSocketId()]); } } function select () { $sockImpl = false; foreach ($this->cons as $c) { if ($c->isBlocking()) { throw new \Exception("Event loop cannot start - connection is already blocking", 3267); } if ($sockImpl === false) { $sockImpl = $c->getSocketImplClass(); } else if ($sockImpl != $c->getSocketImplClass()) { throw new \Exception("Event loop doesn't support mixed socket implementations", 2678); } if (! $c->isConnected()) { throw new \Exception("Connection is not connected", 2174); } } foreach ($this->cons as $c) { $c->setBlocking(true); $c->notifySelectInit(); } while (true) { $tv = array(); foreach ($this->cons as $cid => $c) { $c->deliverAll(); $tv[] = array($cid, $c->notifyPreSelect()); } $psr = $this->processPreSelects($tv); if (is_array($psr)) { list($tvSecs, $tvUsecs) = $psr; } else if (is_null($psr) && empty($this->cons)) { return; } else { throw new \Exception("Unexpected PSR response", 2758); } $this->signal(); if (is_null($tvSecs)) { list($ret, $read, $ex) = call_user_func(array($sockImpl, 'Zelekt'), array_keys($this->cons), null, 0); } else { list($ret, $read, $ex) = call_user_func(array($sockImpl, 'Zelekt'), array_keys($this->cons), $tvSecs, $tvUsecs); } if ($ret === false) { $this->signal(); $errNo = $errStr = array('??'); if ($ex) { $errNo = $errStr = array(); foreach ($ex as $sock) { $errNo[] = $sock->lastError(); $errStr[] = $sock->strError(); } } $eMsg = sprintf("[2] Read block select produced an error: [%s] (%s)", implode(",", $errNo), implode("),(", $errStr)); throw new \Exception ($eMsg, 9963); } else if ($ret > 0) { foreach ($read as $sock) { $c = $this->cons[$sock->getId()]; $c->doSelectRead(); $c->deliverAll(); } foreach ($ex as $sock) { printf("--(Socket Exception (?))--\n"); } } } } private function processPreSelects (array $tvs) { $wins = null; foreach ($tvs as $tv) { $sid = $tv[0]; $tv = $tv[1]; if ($tv === false) { $this->cons[$sid]->notifyComplete(); $this->cons[$sid]->setBlocking(false); $this->removeConnection($this->cons[$sid]); } else if (is_null($wins)) { $wins = $tv; $winSum = is_null($tv[0]) ? 0 : bcadd($tv[0], $tv[1], 5); } else if (! is_null($tv[0])) { if (is_null($wins[0])) { $wins = $tv; } else { $diff = bccomp($wins[0], $tv[0]); if ($diff == -1) { continue; } else if ($diff == 0) { $diff = bccomp($wins[1], $tv[1]); if ($diff == -1) { continue; } else if ($diff == 0) { continue; } else { $wins = $tv; } } else { $wins = $tv; } } } } return $wins; } private function signal () { if (true) { pcntl_signal_dispatch(); } } } 
   class ConditionalSelectHelper implements SelectLoopHelper { private $conn; function configure ($sMode) {} function init (Connection $conn) { $this->conn = $conn; } function preSelect () { $hasConsumers = false; foreach ($this->conn->getChannels() as $chan) { if ($chan->canListen()) { $hasConsumers = true; break; } } if (! $hasConsumers) { return false; } else { return array(null, 0); } } function complete () { $this->conn = null; } } 
   class Channel { private $myConn; private $chanId; private $flow = true; private $destroyed = false; private $frameMax; private $isOpen = false; private $consumers = array(); private $callbacks = array('publishConfirm' => null, 'publishReturn' => null, 'publishNack' => null); private $confirmSeqs = array(); private $confirmSeq = 0; private $confirmMode = false; function setPublishConfirmCallback (\Closure $c) { $this->callbacks['publishConfirm'] = $c; } function setPublishReturnCallback (\Closure $c) { $this->callbacks['publishReturn'] = $c; } function setPublishNackCallback (\Closure $c) { $this->callbacks['publishNack'] = $c; } function hasOutstandingConfirms () { return (bool) $this->confirmSeqs; } function setConfirmMode () { if ($this->confirmMode) { return; } $confSelect = $this->confirm('select'); $confSelectOk = $this->invoke($confSelect); if (! ($confSelectOk instanceof wire\Method) || ! ($confSelectOk->getClassProto()->getSpecName() == 'confirm' && $confSelectOk->getMethodProto()->getSpecName() == 'select-ok')) { throw new \Exception("Failed to selectg confirm mode", 8674); } $this->confirmMode = true; } function __construct (Connection $rConn, $chanId, $frameMax) { $this->myConn = $rConn; $this->chanId = $chanId; $this->frameMax = $frameMax; $this->callbacks['publishConfirm'] = $this->callbacks['publishReturn'] = function () {}; } function initChannel () { $pl = $this->myConn->getProtocolLoader(); $meth = new wire\Method($pl('ClassFactory', 'GetMethod', array('channel', 'open')), $this->chanId); $meth->setField('reserved-1', ''); $resp = $this->myConn->invoke($meth); } function __call ($class, $_args) { if ($this->destroyed) { throw new \Exception("Attempting to use a destroyed channel", 8766); } $m = $this->myConn->constructMethod($class, $_args); $m->setWireChannel($this->chanId); $m->setMaxFrameSize($this->frameMax); return $m; } function invoke (wire\Method $m) { if ($this->destroyed) { throw new \Exception("Attempting to use a destroyed channel", 8767); } else if (! $this->flow) { trigger_error("Channel is closed", E_USER_WARNING); return; } else if (is_null($tmp = $m->getWireChannel())) { $m->setWireChannel($this->chanId); } else if ($tmp != $this->chanId) { throw new \Exception("Method is invoked through the wrong channel", 7645); } if ($this->confirmMode && $m->getClassProto()->getSpecName() == 'basic' && $m->getMethodProto()->getSpecName() == 'publish') { $this->confirmSeq++; $this->confirmSeqs[] = $this->confirmSeq; } return $this->myConn->invoke($m); } function handleChannelMessage (wire\Method $meth) { $sid = "{$meth->getClassProto()->getSpecName()}.{$meth->getMethodProto()->getSpecName()}"; switch ($sid) { case 'channel.flow': $this->flow = ! $this->flow; if ($r = $meth->getMethodProto()->getResponses()) { $meth = new wire\Method($r[0]); $meth->setWireChannel($this->chanId); $this->invoke($meth); } return false; break; case 'channel.close': $pl = $this->myConn->getProtocolLoader(); if ($culprit = $pl('ClassFactory', 'GetMethod', array($meth->getField('class-id'), $meth->getField('method-id')))) { $culprit = "{$culprit->getSpecClass()}.{$culprit->getSpecName()}"; } else { $culprit = '(Unknown or unspecified)'; } $errCode = $pl('ProtoConsts'. 'Konstant', array($meth->getField('reply-code'))); $eb = ''; foreach ($meth->getFields() as $k => $v) { $eb .= sprintf("(%s=%s) ", $k, $v); } $tmp = $meth->getMethodProto()->getResponses(); $closeOk = new wire\Method($tmp[0]); $em = "[channel.close] reply-code={$errCode['name']} triggered by $culprit: $eb"; try { $this->myConn->invoke($closeOk); $em .= " Channel closed OK"; $n = 3687; } catch (\Exception $e) { $em .= " Additionally, channel closure ack send failed"; $n = 2435; } throw new \Exception($em, $n); case 'channel.close-ok': case 'channel.open-ok': return true; default: throw new \Exception("Received unexpected channel message: $sid", 8795); } } function handleChannelDelivery (wire\Method $meth) { $sid = "{$meth->getClassProto()->getSpecName()}.{$meth->getMethodProto()->getSpecName()}"; switch ($sid) { case 'basic.deliver': return $this->deliverConsumerMessage($meth, $sid); case 'basic.return': $cb = $this->callbacks['publishReturn']; return false; case 'basic.ack': $cb = $this->callbacks['publishConfirm']; $this->removeConfirmSeqs($meth, $cb); return false; case 'basic.nack': $cb = $this->callbacks['publishNack']; $this->removeConfirmSeqs($meth, $cb); return false; default: throw new \Exception("Received unexpected channel delivery:\n$sid", 87998); } } private function deliverConsumerMessage ($meth, $sid) { $ctag = $meth->getField('consumer-tag'); list($cons, $status) = $this->getConsumerAndStatus($ctag); $response = $cons->handleDelivery($meth, $this); if ($sid !== 'basic.deliver' || ! $response) { return false; } if (! is_array($response)) { $response = array($response); } foreach ($response as $resp) { switch ($resp) { case CONSUMER_ACK: $ack = $this->basic('ack', array('delivery-tag' => $meth->getField('delivery-tag'), 'multiple' => false)); $this->invoke($ack); break; case CONSUMER_DROP: case CONSUMER_REJECT: $rej = $this->basic('reject', array('delivery-tag' => $meth->getField('delivery-tag'), 'requeue' => ($resp == CONSUMER_REJECT))); $this->invoke($rej); break; case CONSUMER_CANCEL: $cnl = $this->basic('cancel', array('consumer-tag' => $ctag, 'no-wait' => false)); $cOk = $this->invoke($cnl); if ($cOk && ($cOk->getClassProto()->getSpecName() == 'basic' && $cOk->getMethodProto()->getSpecName() == 'cancel-ok')) { $this->setConsumerStatus($ctag, 'CLOSED') OR trigger_error("Failed to set consumer status flag", E_USER_WARNING); } else { throw new \Exception("Failed to cancel consumer - bad broker response", 9768); } $cons->handleCancelOk($cOk, $this); break; } } return false; } private function removeConfirmSeqs (wire\Method $meth, \Closure $handler = null) { if ($meth->getField('multiple')) { $dtag = $meth->getField('delivery-tag'); $this->confirmSeqs = array_filter($this->confirmSeqs, function ($id) use ($dtag, $handler, $meth) { if ($id <= $dtag) { if ($handler) { $handler($meth); } return false; } else { return true; } }); } else { $dt = $meth->getField('delivery-tag'); if (isset($this->confirmSeqs)) { if ($handler) { $handler($meth); } unset($this->confirmSeqs[array_search($dt, $this->confirmSeqs)]); } } } function shutdown () { if (! $this->invoke($this->channel('close', array('reply-code' => '', 'reply-text' => '')))) { trigger_error("Unclean channel shutdown", E_USER_WARNING); } $this->myConn->removeChannel($this); $this->destroyed = true; $this->myConn = $this->chanId = $this->ticket = null; } function addConsumer (Consumer $cons) { foreach ($this->consumers as $c) { if ($c === $cons) { throw new \Exception("Consumer can only be added to channel once", 9684); } } $this->consumers[] = array($cons, false, 'READY_WAIT'); } function canListen (){ return $this->hasListeningConsumers() || $this->hasOutstandingConfirms(); } function removeConsumer (Consumer $cons) { trigger_error("Consumers can no longer be directly removed", E_USER_DEPRECATED); return; } private function setConsumerStatus ($tag, $status) { foreach ($this->consumers as $k => $c) { if ($c[1] === $tag) { $this->consumers[$k][2] = $status; return true; } } return false; } private function getConsumerAndStatus ($tag) { foreach ($this->consumers as $c) { if ($c[1] == $tag) { return array($c[0], $c[2]); } } return array(null, 'INVALID'); } function hasListeningConsumers () { foreach ($this->consumers as $c) { if ($c[2] === 'READY') { return true; } } return false; } function onSelectStart () { if (! $this->consumers) { return false; } foreach (array_keys($this->consumers) as $cnum) { if (false === $this->consumers[$cnum][1]) { $consume = $this->consumers[$cnum][0]->getConsumeMethod($this); $cOk = $this->invoke($consume); $this->consumers[$cnum][0]->handleConsumeOk($cOk, $this); $this->consumers[$cnum][2] = 'READY'; $this->consumers[$cnum][1] = $cOk->getField('consumer-tag'); } } return true; } function onSelectEnd () { $this->consuming = false; } } 
   const DEBUG = false; const PROTOCOL_HEADER = "AMQP\x00\x00\x09\x01"; const SELECT_TIMEOUT_ABS = 1; const SELECT_TIMEOUT_REL = 2; const SELECT_MAXLOOPS = 3; const SELECT_CALLBACK = 4; const SELECT_COND = 5; const SELECT_INFINITE = 6; const CONSUMER_ACK = 1; const CONSUMER_REJECT = 2; const CONSUMER_DROP = 3; const CONSUMER_CANCEL = 4; class Connection { const SELECT_TIMEOUT_ABS = SELECT_TIMEOUT_ABS; const SELECT_TIMEOUT_REL = SELECT_TIMEOUT_REL; const SELECT_MAXLOOPS = SELECT_MAXLOOPS; const SELECT_CALLBACK = SELECT_CALLBACK; const SELECT_COND = SELECT_COND; const SELECT_INFINITE = SELECT_INFINITE; private static $ClientProperties = array( 'product' => ' BraveSirRobin/amqphp', 'version' => '0.9-beta', 'platform' => 'PHP 5.3 +', 'copyright' => 'Copyright (c) 2010,2011 Robin Harvey (harvey.robin@gmail.com)', 'information' => 'This software is released under the terms of the GNU LGPL: http://www.gnu.org/licenses/lgpl-3.0.txt'); public $capabilities; private static $CProps = array( 'socketImpl', 'socketParams', 'username', 'userpass', 'vhost', 'frameMax', 'chanMax', 'signalDispatch', 'heartbeat'); private $sock; private $socketImpl = '\amqphp\Socket'; private $protoImpl = 'v0_9_1'; private $protoLoader; private $socketParams = array('host' => 'localhost', 'port' => 5672); private $username; private $userpass; private $vhost; private $frameMax = 65536; private $chanMax = 50; private $heartbeat = 0; private $signalDispatch = true; private $chans = array(); private $nextChan = 1; private $blocking = false; private $unDelivered = array(); private $unDeliverable = array(); private $incompleteMethods = array(); private $readSrc = null; private $connected = false; private $slHelper; function __construct (array $params = array()) { $this->setConnectionParams($params); $this->setSelectMode(SELECT_COND); } function setConnectionParams (array $params) { foreach (self::$CProps as $pn) { if (isset($params[$pn])) { $this->$pn = $params[$pn]; } } } function getProtocolLoader () { if (is_null($this->protoLoader)) { $protoImpl = $this->protoImpl; $this->protoLoader = function ($class, $method, $args) use ($protoImpl) { $fqClass = '\\amqphp\\protocol\\' . $protoImpl . '\\' . $class; return call_user_func_array(array($fqClass, $method), $args); }; } return $this->protoLoader; } function shutdown () { if (! $this->connected) { trigger_error("Cannot shut a closed connection", E_USER_WARNING); return; } foreach (array_keys($this->chans) as $cName) { $this->chans[$cName]->shutdown(); } $pl = $this->getProtocolLoader(); $meth = new wire\Method($pl('ClassFactory', 'GetMethod', array('connection', 'close'))); $meth->setField('reply-code', ''); $meth->setField('reply-text', ''); $meth->setField('class-id', ''); $meth->setField('method-id', ''); if (! $this->write($meth->toBin($this->getProtocolLoader()))) { trigger_error("Unclean connection shutdown (1)", E_USER_WARNING); return; } if (! ($raw = $this->read())) { trigger_error("Unclean connection shutdown (2)", E_USER_WARNING); return; } $meth = new wire\Method(); $meth->readConstruct(new wire\Reader($raw), $this->getProtocolLoader()); if (! ($meth->getClassProto() && $meth->getClassProto()->getSpecName() == 'connection' && $meth->getMethodProto() && $meth->getMethodProto()->getSpecName() == 'close-ok')) { trigger_error("Channel protocol shudown fault", E_USER_WARNING); } $this->sock->close(); $this->connected = false; } private function initSocket () { if (! isset($this->socketImpl)) { throw new \Exception("No socket implementation specified", 7545); } $this->sock = new $this->socketImpl($this->socketParams); } function connect (array $params = array()) { if ($this->connected) { trigger_error("Connection is connected already", E_USER_WARNING); return; } $this->setConnectionParams($params); $this->initSocket(); $this->sock->connect(); if (! $this->write(PROTOCOL_HEADER)) { throw new \Exception("Connection initialisation failed (1)", 9873); } if (! ($raw = $this->read())) { throw new \Exception("Connection initialisation failed (2)", 9874); } if (substr($raw, 0, 4) == 'AMQP' && $raw !== PROTOCOL_HEADER) { throw new \Exception("Connection initialisation failed (3)", 9875); } $meth = new wire\Method(); $meth->readConstruct(new wire\Reader($raw), $this->getProtocolLoader()); if (($startF = $meth->getField('server-properties')) && isset($startF['capabilities']) && ($startF['capabilities']->getType() == 'F')) { $this->capabilities = $startF['capabilities']->getValue()->getArrayCopy(); } if ($meth->getMethodProto()->getSpecIndex() == 10 && $meth->getClassProto()->getSpecIndex() == 10) { $resp = $meth->getMethodProto()->getResponses(); $meth = new wire\Method($resp[0]); } else { throw new \Exception("Connection initialisation failed (5)", 9877); } $meth->setField('client-properties', $this->getClientProperties()); $meth->setField('mechanism', 'AMQPLAIN'); $meth->setField('response', $this->getSaslResponse()); $meth->setField('locale', 'en_US'); if (! ($this->write($meth->toBin($this->getProtocolLoader())))) { throw new \Exception("Connection initialisation failed (6)", 9878); } if (! ($raw = $this->read())) { throw new \Exception("Connection initialisation failed (7)", 9879); } $meth = new wire\Method(); $meth->readConstruct(new wire\Reader($raw), $this->getProtocolLoader()); $chanMax = $meth->getField('channel-max'); $frameMax = $meth->getField('frame-max'); $this->chanMax = ($chanMax < $this->chanMax) ? $chanMax : $this->chanMax; $this->frameMax = ($this->frameMax == 0 || $frameMax < $this->frameMax) ? $frameMax : $this->frameMax; if ($meth->getMethodProto()->getSpecIndex() == 30 && $meth->getClassProto()->getSpecIndex() == 10) { $resp = $meth->getMethodProto()->getResponses(); $meth = new wire\Method($resp[0]); } else { throw new \Exception("Connection initialisation failed (9)", 9881); } $meth->setField('channel-max', $this->chanMax); $meth->setField('frame-max', $this->frameMax); $meth->setField('heartbeat', $this->heartbeat); if (! ($this->write($meth->toBin($this->getProtocolLoader())))) { throw new \Exception("Connection initialisation failed (10)", 9882); } $meth = $this->constructMethod('connection', array('open', array('virtual-host' => $this->vhost))); $meth = $this->invoke($meth); if (! $meth || ! ($meth->getMethodProto()->getSpecIndex() == 41 && $meth->getClassProto()->getSpecIndex() == 10)) { throw new \Exception("Connection initialisation failed (13)", 9885); } $this->connected = true; } private function getClientProperties () { $t = new wire\Table; foreach (self::$ClientProperties as $pn => $pv) { $t[$pn] = new wire\TableField($pv, 'S'); } return $t; } private function getSaslResponse () { $t = new wire\Table(); $t['LOGIN'] = new wire\TableField($this->username, 'S'); $t['PASSWORD'] = new wire\TableField($this->userpass, 'S'); $w = new wire\Writer(); $w->write($t, 'table'); return substr($w->getBuffer(), 4); } function getChannel ($num = false) { return ($num === false) ? $this->initNewChannel() : $this->chans[$num]; } function getChannels () { return $this->chans; } function setSignalDispatch ($val) { $this->signalDispatch = (boolean) $val; } function removeChannel (Channel $chan) { if (false !== ($k = array_search($chan, $this->chans))) { unset($this->chans[$k]); } else { trigger_error("Channel not found", E_USER_WARNING); } } function getSocketId () { return $this->sock->getId(); } private function initNewChannel () { if (! $this->connected) { trigger_error("Connection is not connected - cannot create Channel", E_USER_WARNING); return null; } $newChan = $this->nextChan++; if ($this->chanMax > 0 && $newChan > $this->chanMax) { throw new \Exception("Channels are exhausted!", 23756); } $this->chans[$newChan] = new Channel($this, $newChan, $this->frameMax); $this->chans[$newChan]->initChannel(); return $this->chans[$newChan]; } function getVHost () { return $this->vhost; } function getSocketImplClass () { return $this->socketImpl; } function isConnected () { return $this->connected; } private function read () { $ret = $this->sock->read(); if ($ret === false) { $errNo = $this->sock->lastError(); if ($this->signalDispatch && $this->sock->selectInterrupted()) { pcntl_signal_dispatch(); } $errStr = $this->sock->strError(); throw new \Exception ("[1] Read block select produced an error: [$errNo] $errStr", 9963); } return $ret; } private function write ($buffs) { $bw = 0; foreach ((array) $buffs as $buff) { $bw += $this->sock->write($buff); } return $bw; } private function handleConnectionMessage (wire\Method $meth) { if ($meth->isHeartbeat()) { $resp = "\x08\x00\x00\x00\x00\x00\x00\xce"; $this->write($resp); return; } $clsMth = "{$meth->getClassProto()->getSpecName()}.{$meth->getMethodProto()->getSpecName()}"; switch ($clsMth) { case 'connection.close': $pl = $this->getProtocolLoader(); if ($culprit = $pl('ClassFactory', 'GetMethod', array($meth->getField('class-id'), $meth->getField('method-id')))) { $culprit = "{$culprit->getSpecClass()}.{$culprit->getSpecName()}"; } else { $culprit = '(Unknown or unspecified)'; } $errCode = $pl('ProtoConsts', 'Konstant', array($meth->getField('reply-code'))); $eb = ''; foreach ($meth->getFields() as $k => $v) { $eb .= sprintf("(%s=%s) ", $k, $v); } $tmp = $meth->getMethodProto()->getResponses(); $closeOk = new wire\Method($tmp[0]); $em = "[connection.close] reply-code={$errCode['name']} triggered by $culprit: $eb"; if ($this->write($closeOk->toBin($this->getProtocolLoader()))) { $em .= " Connection closed OK"; $n = 7565; } else { $em .= " Additionally, connection closure ack send failed"; $n = 7566; } $this->sock->close(); throw new \Exception($em, $n); default: $this->sock->close(); throw new \Exception(sprintf("Unexpected channel message (%s.%s), connection closed", $meth->getClassProto()->getSpecName(), $meth->getMethodProto()->getSpecName()), 96356); } } function isBlocking () { return $this->blocking; } function setBlocking ($b) { $this->blocking = (boolean) $b; } function select () { $evl = new EventLoop; $evl->addConnection($this); $evl->select(); } function setSelectMode () { if ($this->blocking) { trigger_error("Select mode - cannot switch mode whilst blocking", E_USER_WARNING); return false; } $_args = func_get_args(); if (! $_args) { trigger_error("Select mode - no select parameters supplied", E_USER_WARNING); return false; } switch ($mode = array_shift($_args)) { case SELECT_TIMEOUT_ABS: case SELECT_TIMEOUT_REL: @list($epoch, $usecs) = $_args; $this->slHelper = new TimeoutSelectHelper; return $this->slHelper->configure($mode, $epoch, $usecs); case SELECT_MAXLOOPS: $this->slHelper = new MaxloopSelectHelper; return $this->slHelper->configure(SELECT_MAXLOOPS, array_shift($_args)); case SELECT_CALLBACK: $cb = array_shift($_args); $this->slHelper = new CallbackSelectHelper; return $this->slHelper->configure(SELECT_CALLBACK, $cb, $_args); case SELECT_COND: $this->slHelper = new ConditionalSelectHelper; return $this->slHelper->configure(SELECT_COND, $this); case SELECT_INFINITE: $this->slHelper = new InfiniteSelectHelper; return $this->slHelper->configure(SELECT_INFINITE); default: trigger_error("Select mode - mode not found", E_USER_WARNING); return false; } } function notifyPreSelect () { return $this->slHelper->preSelect(); } function notifySelectInit () { $this->slHelper->init($this); foreach ($this->chans as $chan) { $chan->onSelectStart(); } } function notifyComplete () { $this->slHelper->complete(); } function doSelectRead () { $buff = $this->sock->readAll(); if ($buff && ($meths = $this->readMessages($buff))) { $this->unDelivered = array_merge($this->unDelivered, $meths); } else if ($buff == '') { $this->blocking = false; throw new \Exception("Empty read in blocking select loop : " . strlen($buff), 9864); } } function invoke (wire\Method $inMeth, $noWait=false) { if (! ($this->write($inMeth->toBin($this->getProtocolLoader())))) { throw new \Exception("Send message failed (1)", 5623); } if (! $noWait && $inMeth->getMethodProto()->getSpecResponseMethods()) { if ($inMeth->getMethodProto()->hasNoWaitField()) { foreach ($inMeth->getMethodProto()->getFields() as $f) { if ($f->getSpecDomainName() == 'no-wait' && $inMeth->getField($f->getSpecFieldName())) { return; } } } while (true) { if (! ($buff = $this->read())) { throw new \Exception(sprintf("(2) Send message failed for %s.%s:\n", $inMeth->getClassProto()->getSpecName(), $inMeth->getMethodProto()->getSpecName()), 5624); } $meths = $this->readMessages($buff); foreach (array_keys($meths) as $k) { $meth = $meths[$k]; unset($meths[$k]); if ($inMeth->isResponse($meth)) { if ($meths) { $this->unDelivered = array_merge($this->unDelivered, $meths); } return $meth; } else { $this->unDelivered[] = $meth; } } } } } private function readMessages ($buff) { if (is_null($this->readSrc)) { $src = new wire\Reader($buff); } else { $src = $this->readSrc; $src->append($buff); $this->readSrc = null; } $allMeths = array(); while (true) { $meth = null; if ($this->incompleteMethods) { foreach ($this->incompleteMethods as $im) { if ($im->canReadFrom($src)) { $meth = $im; $rcr = $meth->readConstruct($src, $this->getProtocolLoader()); break; } } } if (! $meth) { $meth = new wire\Method; $this->incompleteMethods[] = $meth; $rcr = $meth->readConstruct($src, $this->getProtocolLoader()); } if ($meth->readConstructComplete()) { if (false !== ($p = array_search($meth, $this->incompleteMethods, true))) { unset($this->incompleteMethods[$p]); } if ($this->connected && $meth->getWireChannel() == 0) { $this->handleConnectionMessage($meth); } else if ($meth->getWireClassId() == 20 && ($chan = $this->chans[$meth->getWireChannel()])) { $chanR = $chan->handleChannelMessage($meth); if ($chanR === true) { $allMeths[] = $meth; } } else { $allMeths[] = $meth; } } if ($rcr === wire\Method::PARTIAL_FRAME) { $this->readSrc = $src; break; } else if ($src->isSpent()) { break; } } return $allMeths; } function getUndeliveredMessages () { return $this->unDelivered; } function deliverAll () { while ($this->unDelivered) { $meth = array_shift($this->unDelivered); if (isset($this->chans[$meth->getWireChannel()])) { $this->chans[$meth->getWireChannel()]->handleChannelDelivery($meth); } else { trigger_error("Message delivered on unknown channel", E_USER_WARNING); $this->unDeliverable[] = $meth; } } } function getUndeliverableMessages ($chan) { $r = array(); foreach (array_keys($this->unDeliverable) as $k) { if ($this->unDeliverable[$k]->getWireChannel() == $chan) { $r[] = $this->unDeliverable[$k]; } } return $r; } function removeUndeliverableMessages ($chan) { foreach (array_keys($this->unDeliverable) as $k) { if ($this->unDeliverable[$k]->getWireChannel() == $chan) { unset($this->unDeliverable[$k]); } } } function constructMethod ($class, $_args) { $method = (isset($_args[0])) ? $_args[0] : null; $args = (isset($_args[1])) ? $_args[1] : array(); $content = (isset($_args[2])) ? $_args[2] : null; $pl = $this->getProtocolLoader(); if (! ($cls = $pl('ClassFactory', 'GetClassByName', array($class)))) { throw new \Exception("Invalid Amqp class or php method", 8691); } else if (! ($meth = $cls->getMethodByName($method))) { throw new \Exception("Invalid Amqp method", 5435); } $m = new wire\Method($meth); $clsF = $cls->getSpecFields(); $mthF = $meth->getSpecFields(); if ($meth->getSpecHasContent() && $clsF) { foreach (array_merge(array_combine($clsF, array_fill(0, count($clsF), null)), $args) as $k => $v) { $m->setClassField($k, $v); } } if ($mthF) { foreach (array_merge(array_combine($mthF, array_fill(0, count($mthF), '')), $args) as $k => $v) { $m->setField($k, $v); } } $m->setContent($content); return $m; } } 
   class MaxloopSelectHelper implements SelectLoopHelper { private $maxLoops; private $nLoops; function configure ($sMode, $ml=null) { if (! is_int($ml) || $ml == 0) { trigger_error("Select mode - invalid maxloops params", E_USER_WARNING); return false; } else { $this->maxLoops = $ml; return true; } } function init (Connection $conn) { $this->nLoops = 0; } function preSelect () { if (++$this->nLoops > $this->maxLoops) { return false; } else { return array(null, 0); } } function complete () {} } 
   class StreamSocket { const READ_SELECT = 1; const WRITE_SELECT = 2; const READ_LENGTH = 4096; private static $All = array(); private static $Counter = 0; private $host; private $id; private $port; private $connected; private $interrupt = false; function __construct ($params) { $this->url = $params['url']; $this->context = isset($params['context']) ? $params['context'] : array(); $this->id = ++self::$Counter; } function connect () { $context = stream_context_create($this->context); $this->sock = stream_socket_client($this->url, $errno, $errstr, ini_get("default_socket_timeout"), STREAM_CLIENT_CONNECT, $context); if (! $this->sock) { throw new \Exception("Failed to connect stream socket {$this->url}, ($errno, $errstr)", 7568); } $this->connected = false; self::$All[] = $this; } function select ($tvSec, $tvUsec = 0, $rw = self::READ_SELECT) { $read = $write = $ex = null; if ($rw & self::READ_SELECT) { $read = $ex = array($this->sock); } if ($rw & self::WRITE_SELECT) { $write = array($this->sock); } if (! $read && ! $write) { throw new \Exception("Select must read and/or write", 9864); } $this->interrupt = false; $ret = stream_select($read, $write, $ex, $tvSec, $tvUsec); if ($ret === false) { $this->interrupt = true; } return $ret; } static function Zelekt (array $incSet, $tvSec, $tvUsec) { $write = null; $read = $all = array(); foreach (self::$All as $i => $o) { if (in_array($o->id, $incSet)) { $read[$i] = $all[$i] = $o->sock; } } $ex = $read; $ret = false; if ($read) { $ret = stream_select($read, $write, $ex, $tvSec, $tvUsec); } if ($ret === false) { return false; } $_read = $_ex = array(); foreach ($read as $sock) { if (false !== ($key = array_search($sock, $all, true))) { $_read[] = self::$All[$key]; } } foreach ($ex as $k => $sock) { if (false !== ($key = array_search($sock, $all, true))) { $_ex[] = self::$All[$key]; } } return array($ret, $_read, $_ex); } function selectInterrupted () { return $this->interrupt; } function lastError () { return 0; } function strError () { return ''; } function readAll ($readLen = self::READ_LENGTH) { $buff = ''; do { $buff .= fread($this->sock, $readLen); $smd = stream_get_meta_data($this->sock); $readLen = min($smd['unread_bytes'], $readLen); } while ($smd['unread_bytes'] > 0); if (DEBUG) { echo "\n<read>\n"; echo wire\hexdump($buff); } return $buff; } function read () { return $this->readAll(); } function write ($buff) { $bw = 0; $contentLength = strlen($buff); while (true) { if (DEBUG) { echo "\n<write>\n"; echo wire\hexdump($buff); } if (($tmp = fwrite($this->sock, $buff)) === false) { throw new \Exception(sprintf("\nStream write failed: %s\n", $this->strError()), 7854); } $bw += $tmp; if ($bw < $contentLength) { $buff = substr($buff, $bw); } else { break; } } fflush($this->sock); return $bw; } function close () { $this->connected = false; fclose($this->sock); $this->detach(); } private function detach () { if (false !== ($k = array_search($this, self::$All))) { unset(self::$All[$k]); } } function getId () { return $this->id; } } 
   class SimpleConsumer implements Consumer { protected $consumeParams; protected $consuming = false; function __construct (array $consumeParams) { $this->consumeParams = $consumeParams; } function handleCancelOk (wire\Method $meth, Channel $chan) {} function handleConsumeOk (wire\Method $meth, Channel $chan) { $this->consuming = true; } function handleDelivery (wire\Method $meth, Channel $chan) {} function handleRecoveryOk (wire\Method $meth, Channel $chan) {} function getConsumeMethod (Channel $chan) { return $chan->basic('consume', $this->consumeParams); } }
   class CallbackSelectHelper implements SelectLoopHelper { private $cb; private $args; function configure ($sMode, $cb=null, $args=null) { if (! is_callable($cb)) { trigger_error("Select mode - invalid callback params", E_USER_WARNING); return false; } else { $this->cb = $cb; $this->args = $args; return true; } } function init (Connection $conn) {} function preSelect () { if (true !== call_user_func_array($this->cb, $this->args)) { return false; } else { return array(null, 0); } } function complete () {} } 
   interface SelectLoopHelper { function configure ($sMode); function init (Connection $conn); function preSelect (); function complete (); } 
   class InfiniteSelectHelper implements SelectLoopHelper { function configure ($sMode) {} function init (Connection $conn) {} function preSelect () { return array(null, 0); } function complete () {} } 
   class Socket { const READ_SELECT = 1; const WRITE_SELECT = 2; const READ_LENGTH = 4096; private static $All = array(); private static $Counter = 0; private $sock; private $id; private $connected = false; private static $interrupt = false; function __construct ($params) { $this->host = $params['host']; $this->port = $params['port']; $this->id = ++self::$Counter; } function connect () { if (! ($this->sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP))) { throw new \Exception("Failed to create inet socket", 7895); } else if (! socket_connect($this->sock, $this->host, $this->port)) { throw new \Exception("Failed to connect inet socket ({$this->host}, {$this->port})", 7564); } $this->connected = true; self::$All[] = $this; } function select ($tvSec, $tvUsec = 0, $rw = self::READ_SELECT) { $read = $write = $ex = null; if ($rw & self::READ_SELECT) { $read = $ex = array($this->sock); } if ($rw & self::WRITE_SELECT) { $write = $ex = array($this->sock); } if (! $read && ! $write) { throw new \Exception("Select must read and/or write", 9864); } self::$interrupt = false; $ret = socket_select($read, $write, $ex, $tvSec, $tvUsec); if ($ret === false && $this->lastError() == SOCKET_EINTR) { self::$interrupt = true; } return $ret; } static function Zelekt (array $incSet, $tvSec, $tvUsec) { $write = null; $read = $all = array(); foreach (self::$All as $i => $o) { if (in_array($o->id, $incSet)) { $read[$i] = $all[$i] = $o->sock; } } $ex = $read; $ret = false; if ($read) { $ret = socket_select($read, $write, $ex, $tvSec, $tvUsec); } if ($ret === false && socket_last_error() == SOCKET_EINTR) { self::$interrupt = true; return false; } $_read = $_ex = array(); foreach ($read as $sock) { if (false !== ($key = array_search($sock, $all, true))) { $_read[] = self::$All[$key]; } } foreach ($ex as $k => $sock) { if (false !== ($key = array_search($sock, $all, true))) { $_ex[] = self::$All[$key]; } } return array($ret, $_read, $_ex); } function selectInterrupted () { return self::$interrupt; } function read () { $buff = ''; $select = $this->select(5); if ($select === false) { return false; } else if ($select > 0) { $buff = $this->readAll(); } return $buff; } function lastError () { return socket_last_error(); } function strError () { return socket_strerror($this->lastError()); } function readAll ($readLen = self::READ_LENGTH) { $buff = ''; while (@socket_recv($this->sock, $tmp, $readLen, MSG_DONTWAIT)) { $buff .= $tmp; } if (DEBUG) { echo "\n<read>\n"; echo wire\hexdump($buff); } return $buff; } function write ($buff) { $bw = 0; $contentLength = strlen($buff); while (true) { if (DEBUG) { echo "\n<write>\n"; echo wire\hexdump($buff); } if (($tmp = socket_write($this->sock, $buff)) === false) { throw new \Exception(sprintf("\nSocket write failed: %s\n", $this->strError()), 7854); } $bw += $tmp; if ($bw < $contentLength) { $buff = substr($buff, $bw); } else { break; } } return $bw; } function close () { $this->connected = false; socket_close($this->sock); $this->detach(); } private function detach () { if (false !== ($k = array_search($this, self::$All))) { unset(self::$All[$k]); } } function getId () { return $this->id; } } 
   interface Consumer { function handleCancelOk (wire\Method $meth, Channel $chan); function handleConsumeOk (wire\Method $meth, Channel $chan); function handleDelivery (wire\Method $meth, Channel $chan); function handleRecoveryOk (wire\Method $meth, Channel $chan); function getConsumeMethod (Channel $chan); } 
   class TimeoutSelectHelper implements SelectLoopHelper { private $toStyle; private $secs; private $usecs; private $epoch; function configure ($sMode, $secs=null, $usecs=null) { $this->toStyle = $sMode; $this->secs = (string) $secs; $this->usecs = (string) $usecs; return true; } function init (Connection $conn) { if ($this->toStyle == Connection::SELECT_TIMEOUT_REL) { list($uSecs, $epoch) = explode(' ', microtime()); $uSecs = bcmul($uSecs, '1000000'); $this->usecs = bcadd($this->usecs, $uSecs); $this->epoch = bcadd($this->secs, $epoch); if (! (bccomp($this->usecs, '1000000') < 0)) { $this->epoch = bcadd('1', $this->epoch); $this->usecs = bcsub($this->usecs, '1000000'); } } else { $this->epoch = $this->secs; } } function preSelect () { list($uSecs, $epoch) = explode(' ', microtime()); $epDiff = bccomp($epoch, $this->epoch); if ($epDiff == 1) { return false; } $uSecs = bcmul($uSecs, '1000000'); if ($epDiff == 0 && bccomp($uSecs, $this->usecs) >= 0) { return false; } $udiff = bcsub($this->usecs, $uSecs); if (substr($udiff, 0, 1) == '-') { $blockTmSecs = (int) bcsub($this->epoch, $epoch) - 1; $udiff = bcadd($udiff, '1000000'); } else { $blockTmSecs = (int) bcsub($this->epoch, $epoch); } return array($blockTmSecs, $udiff); } function complete () {} } 