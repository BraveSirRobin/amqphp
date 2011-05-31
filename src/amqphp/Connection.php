<?php
/**
 *
 * Copyright (C) 2010, 2011  Robin Harvey (harvey.robin@gmail.com)
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.

 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.

 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 */

namespace amqphp;

use amqphp\protocol;
use amqphp\wire;

const DEBUG = false;

const SELECT_TIMEOUT_ABS = 1;
const SELECT_TIMEOUT_REL = 2;
const SELECT_MAXLOOPS = 3;
const SELECT_CALLBACK = 4;
const SELECT_COND = 5;
const SELECT_INFINITE = 6;


/**
 * Standard  "consumer   signals"  -   these  can  be   returned  from
 * Consumer->handleDelivery  method and  trigger the  API to  send the
 * corresponding messages.
 */
const CONSUMER_ACK = 1; // basic.ack (multiple=false)
const CONSUMER_REJECT = 2; // basic.reject (requeue=true)
const CONSUMER_DROP = 3; // basic.reject (requeue=false)
const CONSUMER_CANCEL = 4; // basic.cancel (no-wait=false)






/**
 * Wraps  a  single TCP  connection  to the  amqp  broker,  acts as  a
 * demultiplexer for many channels.   Event looping behaviours are set
 * here,   and   there    is   a   simple   single-connection   select
 * implementation.
 */
class Connection
{
    // DEPRECATED - these consts are now stand-alone consts as they are used by more than one class in this package.
    const SELECT_TIMEOUT_ABS = SELECT_TIMEOUT_ABS;
    const SELECT_TIMEOUT_REL = SELECT_TIMEOUT_REL;
    const SELECT_MAXLOOPS = SELECT_MAXLOOPS;
    const SELECT_CALLBACK = SELECT_CALLBACK;
    const SELECT_COND = SELECT_COND;
    const SELECT_INFINITE = SELECT_INFINITE;

    /** Default client-properties field used during connection setup */
    private static $ClientProperties = array(
        'product' => ' BraveSirRobin/amqphp',
        'version' => '0.9-beta',
        'platform' => 'PHP 5.3 +',
        'copyright' => 'Copyright (c) 2010,2011 Robin Harvey (harvey.robin@gmail.com)',
        'information' => 'This software is released under the terms of the GNU LGPL: http://www.gnu.org/licenses/lgpl-3.0.txt');

    /** For RMQ 2.4.0+, server capabilites are stored here, as a plain array */
    public $capabilities;

    /** List of class fields that are settable connection params */
    private static $CProps = array(
        'socketImpl', 'socketParams', 'username', 'userpass', 'vhost', 'frameMax', 'chanMax', 'signalDispatch', 'heartbeat');
    //'blockTmSecs', 'blockTmMillis');

    /** Connection params */
    private $sock; // Socket wrapper object
    private $socketImpl = '\amqphp\Socket'; // Socket impl class name
    private $protoImpl = 'v0_9_1'; // Protocol implementation namespace (generated code)
    private $protoLoader; // Closeure, set up in getProtocolLoader()
    private $socketParams = array('host' => 'localhost', 'port' => 5672); // Construct params for $socketImpl
    private $username;
    private $userpass;
    private $vhost;
    private $frameMax = 65536; // Negotated during setup.
    private $chanMax = 50; // Negotated during setup.
    private $heartbeat = 0; // Negotated during setup.
    private $signalDispatch = true;


    private $chans = array(); // Format: array(<chan-id> => Channel)
    private $nextChan = 1;


    /** Flag set when connection is in read blocking mode, waiting for messages */
    private $blocking = false;


    private $unDelivered = array(); // List of undelivered messages, Format: array(<wire\Method>)
    private $unDeliverable = array(); // List of undeliverable messages, Format: array(<wire\Method>)
    private $incompleteMethods = array(); // List of partial messages, Format: array(<wire\Method>)
    private $readSrc = null; // wire\Reader, used between reads when partial frames are read from the wire

    private $connected = false; // Flag flipped after protcol connection setup is complete

    private $slHelper;



    function __construct (array $params = array()) {
        $this->setConnectionParams($params);
        $this->setSelectMode(SELECT_COND);
    }

    /**
     * Assoc array sets the connection parameters
     */
    function setConnectionParams (array $params) {
        foreach (self::$CProps as $pn) {
            if (isset($params[$pn])) {
                $this->$pn = $params[$pn];
            }
        }
    }


    /**
     * Return a function that loads protocol binding classes 
     */
    function getProtocolLoader () {
        if (is_null($this->protoLoader)) {
            $protoImpl = $this->protoImpl;
            $this->protoLoader = function ($class, $method, $args) use ($protoImpl) {
                $fqClass = '\\amqphp\\protocol\\' . $protoImpl . '\\' . $class;
                return call_user_func_array(array($fqClass, $method), $args);
            };
        }
        return $this->protoLoader;
    }


    /** Shutdown child channels and then the connection  */
    function shutdown () {
        if (! $this->connected) {
            trigger_error("Cannot shut a closed connection", E_USER_WARNING);
            return;
        }
        foreach (array_keys($this->chans) as $cName) {
            $this->chans[$cName]->shutdown();
        }

        $pl = $this->getProtocolLoader();
        $meth = new wire\Method($pl('ClassFactory', 'GetMethod', array('connection', 'close')));
        $meth->setField('reply-code', '');
        $meth->setField('reply-text', '');
        $meth->setField('class-id', '');
        $meth->setField('method-id', '');
        if (! $this->write($meth->toBin($this->getProtocolLoader()))) {
            trigger_error("Unclean connection shutdown (1)", E_USER_WARNING);
            return;
        }
        if (! ($raw = $this->read())) {
             trigger_error("Unclean connection shutdown (2)", E_USER_WARNING);
             return;
        }

        $meth = new wire\Method();
        $meth->readConstruct(new wire\Reader($raw), $this->getProtocolLoader());
        if (! ($meth->getClassProto() &&
               $meth->getClassProto()->getSpecName() == 'connection' &&
               $meth->getMethodProto() &&
               $meth->getMethodProto()->getSpecName() == 'close-ok')) {
            trigger_error("Channel protocol shudown fault", E_USER_WARNING);
        }
        $this->sock->close();
        $this->connected = false;
    }


    private function initSocket () {
        if (! isset($this->socketImpl)) {
            throw new \Exception("No socket implementation specified", 7545);
        }
        $this->sock = new $this->socketImpl($this->socketParams);
    }


    /**
     * If not already  connected, connect to the target  broker and do
     * Amqp connection setup
     */
    function connect (array $params = array()) {
        if ($this->connected) {
            trigger_error("Connection is connected already", E_USER_WARNING);
            return;
        }
        $this->setConnectionParams($params);
        $this->initSocket();
        $this->sock->connect();
        if (! $this->write(wire\PROTOCOL_HEADER)) {
            throw new \Exception("Connection initialisation failed (1)", 9873);
        }
        if (! ($raw = $this->read())) {
            throw new \Exception("Connection initialisation failed (2)", 9874);
        }
        if (substr($raw, 0, 4) == 'AMQP' && $raw !== wire\PROTOCOL_HEADER) {
            // Unexpected AMQP version
            throw new \Exception("Connection initialisation failed (3)", 9875);
        }
        $meth = new wire\Method();
        $meth->readConstruct(new wire\Reader($raw), $this->getProtocolLoader());
        if (($startF = $meth->getField('server-properties'))
            && isset($startF['capabilities'])
            && ($startF['capabilities']->getType() == 'F')) {
            // Captures RMQ 2.4.0+ capabilities
            $this->capabilities = $startF['capabilities']->getValue()->getArrayCopy();
        }

        // Expect start
        if ($meth->getMethodProto()->getSpecIndex() == 10 && $meth->getClassProto()->getSpecIndex() == 10) {
            $resp = $meth->getMethodProto()->getResponses();
            $meth = new wire\Method($resp[0]);
        } else {
            throw new \Exception("Connection initialisation failed (5)", 9877);
        }
        $meth->setField('client-properties', $this->getClientProperties());
        $meth->setField('mechanism', 'AMQPLAIN');
        $meth->setField('response', $this->getSaslResponse());
        $meth->setField('locale', 'en_US');
        // Send start-ok
        if (! ($this->write($meth->toBin($this->getProtocolLoader())))) {
            throw new \Exception("Connection initialisation failed (6)", 9878);
        }

        if (! ($raw = $this->read())) {
            throw new \Exception("Connection initialisation failed (7)", 9879);
        }
        $meth = new wire\Method();
        $meth->readConstruct(new wire\Reader($raw), $this->getProtocolLoader());

        $chanMax = $meth->getField('channel-max');
        $frameMax = $meth->getField('frame-max');

        $this->chanMax = ($chanMax < $this->chanMax) ? $chanMax : $this->chanMax;
        $this->frameMax = ($this->frameMax == 0 || $frameMax < $this->frameMax) ? $frameMax : $this->frameMax;

        // Expect tune
        if ($meth->getMethodProto()->getSpecIndex() == 30 && $meth->getClassProto()->getSpecIndex() == 10) {
            $resp = $meth->getMethodProto()->getResponses();
            $meth = new wire\Method($resp[0]);
        } else {
            throw new \Exception("Connection initialisation failed (9)", 9881);
        }
        $meth->setField('channel-max', $this->chanMax);
        $meth->setField('frame-max', $this->frameMax);
        $meth->setField('heartbeat', $this->heartbeat);
        // Send tune-ok
        if (! ($this->write($meth->toBin($this->getProtocolLoader())))) {
            throw new \Exception("Connection initialisation failed (10)", 9882);
        }

        // Now call connection.open
        $meth = $this->constructMethod('connection', array('open', array('virtual-host' => $this->vhost)));
        $meth = $this->invoke($meth);
        if (! $meth || ! ($meth->getMethodProto()->getSpecIndex() == 41 && $meth->getClassProto()->getSpecIndex() == 10)) {
            throw new \Exception("Connection initialisation failed (13)", 9885);
        }
        $this->connected = true;
    }

    /**
     * Helper:  return   the  client  properties   parameter  used  in
     * connection setup.
     */
    private function getClientProperties () {
        /* Build table to use long strings - RMQ seems to require this. */
        $t = new wire\Table;
        foreach (self::$ClientProperties as $pn => $pv) {
            $t[$pn] = new wire\TableField($pv, 'S');
        }
        return $t;
    }

    /**
     * Helper: return  the Sasl response parameter  used in connection
     * setup.
     */
    private function getSaslResponse () {
        $t = new wire\Table();
        $t['LOGIN'] = new wire\TableField($this->username, 'S');
        $t['PASSWORD'] = new wire\TableField($this->userpass, 'S');
        $w = new wire\Writer();
        $w->write($t, 'table');
        return substr($w->getBuffer(), 4);
    }

    /**
     * Channel  accessor /  factory  method, call  with  no params  to
     * create a  new channel,  or with a  channel number to  access an
     * existing channel by number
     */
    function getChannel ($num = false) {
        return ($num === false) ? $this->initNewChannel() : $this->chans[$num];
    }

    function getChannels () {
        return $this->chans;
    }

    /** Flip internal flag the decides if pcntl_signal_dispatch() gets called in consume loop */
    function setSignalDispatch ($val) {
        $this->signalDispatch = (boolean) $val;
    }

    function removeChannel (Channel $chan) {
        if (false !== ($k = array_search($chan, $this->chans))) {
            unset($this->chans[$k]);
        } else {
            trigger_error("Channel not found", E_USER_WARNING);
        }
    }

    function getSocketId () {
        return $this->sock->getId();
    }

    private function initNewChannel () {
        if (! $this->connected) {
            trigger_error("Connection is not connected - cannot create Channel", E_USER_WARNING);
            return null;
        }
        $newChan = $this->nextChan++;
        if ($this->chanMax > 0 && $newChan > $this->chanMax) {
            throw new \Exception("Channels are exhausted!", 23756);
        }
        $this->chans[$newChan] = new Channel($this, $newChan, $this->frameMax);
        $this->chans[$newChan]->initChannel();
        return $this->chans[$newChan];
    }


    function getVHost () { return $this->vhost; }


    function getSocketImplClass () { return $this->socketImpl; }

    /**
     * Returns  the  status of  the  connection  class protocol  state
     * tracking flag.  Note: doesn't not check the underlying socket.
     */
    function isConnected () { return $this->connected; }


    /**
     * Read  all  available content  from  the  wire,  if an  error  /
     * interrupt is  detected, dispatch  signal handlers and  raise an
     * exception
     **/
    private function read () {
        $ret = $this->sock->read();
        if ($ret === false) {
            $errNo = $this->sock->lastError();
            if ($this->signalDispatch && $this->sock->selectInterrupted()) {
                pcntl_signal_dispatch();
            }
            $errStr = $this->sock->strError();
            throw new \Exception ("[1] Read block select produced an error: [$errNo] $errStr", 9963);
        }
        return $ret;
    }



    /** Low level protocol write function.  Accepts either single values or arrays of content */
    private function write ($buffs) {
        $bw = 0;
        foreach ((array) $buffs as $buff) {
            $bw += $this->sock->write($buff);
        }
        return $bw;
    }



    /**
     * Handle global connection messages.
     *  The channel number is 0 for all frames which are global to the connection (4.2.3)
     */
    private function handleConnectionMessage (wire\Method $meth) {
        if ($meth->isHeartbeat()) {
            $resp = "\x08\x00\x00\x00\x00\x00\x00\xce";
            $this->write($resp);
            return;
        }
        $clsMth = "{$meth->getClassProto()->getSpecName()}.{$meth->getMethodProto()->getSpecName()}";
        switch ($clsMth) {
        case 'connection.close':
            $pl = $this->getProtocolLoader();
            if ($culprit = $pl('ClassFactory', 'GetMethod', array($meth->getField('class-id'), $meth->getField('method-id')))) {
                $culprit = "{$culprit->getSpecClass()}.{$culprit->getSpecName()}";
            } else {
                $culprit = '(Unknown or unspecified)';
            }
            // Note: ignores the soft-error, hard-error distinction in the xml
            $errCode = $pl('ProtoConsts', 'Konstant', array($meth->getField('reply-code')));
            $eb = '';
            foreach ($meth->getFields() as $k => $v) {
                $eb .= sprintf("(%s=%s) ", $k, $v);
            }
            $tmp = $meth->getMethodProto()->getResponses();
            $closeOk = new wire\Method($tmp[0]);
            $em = "[connection.close] reply-code={$errCode['name']} triggered by $culprit: $eb";
            if ($this->write($closeOk->toBin($this->getProtocolLoader()))) {
                $em .= " Connection closed OK";
                $n = 7565;
            } else {
                $em .= " Additionally, connection closure ack send failed";
                $n = 7566;
            }
            $this->sock->close();
            throw new \Exception($em, $n);
        default:
            $this->sock->close();
            throw new \Exception(sprintf("Unexpected channel message (%s.%s), connection closed",
                                         $meth->getClassProto()->getSpecName(), $meth->getMethodProto()->getSpecName()), 96356);
        }
    }


    function isBlocking () { return $this->blocking; }

    function setBlocking ($b) { $this->blocking = (boolean) $b; }


    /**
     * Enter  a select  loop in  order  to receive  messages from  the
     * broker.  Use setSelectMode() to  set an  exit strategy  for the
     * loop.  Do not call  concurrently, this will raise an exception.
     * Use  isBlocking() to  test whether  select() should  be called.
     * @throws Exception
     */
    function select () {
        $evl = new EventLoop;
        $evl->addConnection($this);
        $evl->select();
    }

    /**
     * Set  parameters that  control  how the  connection select  loop
     * behaves, implements the following exit strategies:
     *  1)  Absolute timeout -  specify a  {usec epoch}  timeout, loop
     *  breaks after this.  See the PHP man page for microtime(false).
     *  Example: "0.025 1298152951"
     *  2) Relative timeout - same as Absolute timeout except the args
     *  are  specified relative  to microtime()  at the  start  of the
     *  select loop.  Example: "0.75 2"
     *  3) Max loops
     *  4) Conditional exit (callback)
     *  5) Conditional exit (automatic) (current impl)
     *  6) Infinite

     * @param   integer    $mode      One of the SELECT_XXX consts.
     * @param   ...                   Following 0 or more params are $mode dependant
     * @return  boolean               True if the mode was set OK
     */
    function setSelectMode () {
        if ($this->blocking) {
            trigger_error("Select mode - cannot switch mode whilst blocking", E_USER_WARNING);
            return false;
        }
        $_args = func_get_args();
        if (! $_args) {
            trigger_error("Select mode - no select parameters supplied", E_USER_WARNING);
            return false;
        }
        switch ($mode = array_shift($_args)) {
        case SELECT_TIMEOUT_ABS:
        case SELECT_TIMEOUT_REL:
            @list($epoch, $usecs) = $_args;
            $this->slHelper = new TimeoutSelectHelper;
            return $this->slHelper->configure($mode, $epoch, $usecs);
        case SELECT_MAXLOOPS:
            $this->slHelper = new MaxloopSelectHelper;
            return $this->slHelper->configure(SELECT_MAXLOOPS, array_shift($_args));
        case SELECT_CALLBACK:
            $cb = array_shift($_args);
            $this->slHelper = new CallbackSelectHelper;
            return $this->slHelper->configure(SELECT_CALLBACK, $cb, $_args);
        case SELECT_COND:
            $this->slHelper = new ConditionalSelectHelper;
            return $this->slHelper->configure(SELECT_COND, $this);
        case SELECT_INFINITE:
            $this->slHelper = new InfiniteSelectHelper;
            return $this->slHelper->configure(SELECT_INFINITE);
        default:
            trigger_error("Select mode - mode not found", E_USER_WARNING);
            return false;
        }
    }


    /**
     * Internal - proxy EventLoop "notify pre-select" signal to select
     * helper
     */
    function notifyPreSelect () {
        return $this->slHelper->preSelect();
    }

    /**
     * Internal  -  proxy EventLoop  "select  init"  signal to  select
     * helper
     */
    function notifySelectInit () {
        $this->slHelper->init($this);
        // Notify all channels
        foreach ($this->chans as $chan) {
            $chan->onSelectStart();
        }
    }

    /**
     * Internal - proxy EventLoop "complete" signal to select helper
     */
    function notifyComplete () {
        $this->slHelper->complete();
    }


    /**
     * Internal - used by EventLoop to instruct the connection to read
     * and deliver incoming messages.
     */
    function doSelectRead () {
        $buff = $this->sock->readAll();
        if ($buff && ($meths = $this->readMessages($buff))) {
            $this->unDelivered = array_merge($this->unDelivered, $meths);
        } else if ($buff == '') {
            $this->blocking = false;
            throw new \Exception("Empty read in blocking select loop : " . strlen($buff), 9864);
        }
    }


    /**
     * Send the given method immediately, optionally wait for the response.
     * @arg  Method     $inMeth         The method to send
     * @arg  boolean    $noWait         Flag that prevents the default behaviour of immediately
     *                                  waiting for a response - used mainly during consume.  NOTE
     *                                  that this mechanism can also be triggered via. the use of
     *                                  an Amqp no-wait domain field set to true
     */
    function invoke (wire\Method $inMeth, $noWait=false) {
        if (! ($this->write($inMeth->toBin($this->getProtocolLoader())))) {
            throw new \Exception("Send message failed (1)", 5623);
        }
        if (! $noWait && $inMeth->getMethodProto()->getSpecResponseMethods()) {
            if ($inMeth->getMethodProto()->hasNoWaitField()) {
                foreach ($inMeth->getMethodProto()->getFields() as $f) {
                    if ($f->getSpecDomainName() == 'no-wait' && $inMeth->getField($f->getSpecFieldName())) {
                        return;
                    }
                }
            }
            while (true) {
                if (! ($buff = $this->read())) {
                    throw new \Exception(sprintf("(2) Send message failed for %s.%s:\n",
                                                 $inMeth->getClassProto()->getSpecName(),
                                                 $inMeth->getMethodProto()->getSpecName()), 5624);
                }

                $meths = $this->readMessages($buff);
                foreach (array_keys($meths) as $k) {
                    $meth = $meths[$k];
                    unset($meths[$k]);
                    if ($inMeth->isResponse($meth)) {
                        if ($meths) {
                            $this->unDelivered = array_merge($this->unDelivered, $meths);
                        }
                        return $meth;
                    } else {
                        $this->unDelivered[] = $meth;
                    }
                }
            }
        }
    }




    /**
     * Convert  the  given raw  wire  content  in  to Method  objects.
     * Connection and  channel messages are  delivered immediately and
     * not returned.
     */
    private function readMessages ($buff) {
        if (is_null($this->readSrc)) {
            $src = new wire\Reader($buff);
        } else {
            $src = $this->readSrc;
            $src->append($buff);
            $this->readSrc = null;
        }

        $allMeths = array(); // Collect all method here
        while (true) {
            $meth = null;
            // Check to see if the content can complete any locally held incomplete messages
            if ($this->incompleteMethods) {
                foreach ($this->incompleteMethods as $im) {
                    if ($im->canReadFrom($src)) {
                        $meth = $im;
                        $rcr = $meth->readConstruct($src, $this->getProtocolLoader());
                        break;
                    }
                }
            }
            if (! $meth) {
                $meth = new wire\Method;
                $this->incompleteMethods[] = $meth;
                $rcr = $meth->readConstruct($src, $this->getProtocolLoader());
            }

            if ($meth->readConstructComplete()) {
                if (false !== ($p = array_search($meth, $this->incompleteMethods, true))) {
                    unset($this->incompleteMethods[$p]);
                }
                if ($this->connected && $meth->getWireChannel() == 0) {
                    // Deliver Connection messages immediately, but only if the connection
                    // is already set up.
                    $this->handleConnectionMessage($meth);
                } else if ($meth->getWireClassId() == 20 &&
                           ($chan = $this->chans[$meth->getWireChannel()])) {
                    // Deliver Channel messages immediately
                    $chanR = $chan->handleChannelMessage($meth);
                    if ($chanR === true) {
                        $allMeths[] = $meth;
                    }
                } else {
                    $allMeths[] = $meth;
                }
            }

            if ($rcr === wire\Method::PARTIAL_FRAME) {
                $this->readSrc = $src;
                break;
            } else if ($src->isSpent()) {
                break;
            }
        }
        return $allMeths;
    }


    function getUndeliveredMessages () {
        return $this->unDelivered;
    }


    /**
     * Deliver  all   undelivered  messages,  collect   and  send  all
     * responses  after incoming  messages are  all dealt  with. NOTE:
     * while / array_shift loop is used in case onDelivery call causes
     * more messages to be placed in local queue
     */
    function deliverAll () {
        while ($this->unDelivered) {
            $meth = array_shift($this->unDelivered);
            if (isset($this->chans[$meth->getWireChannel()])) {
                $this->chans[$meth->getWireChannel()]->handleChannelDelivery($meth);
            } else {
                trigger_error("Message delivered on unknown channel", E_USER_WARNING);
                $this->unDeliverable[] = $meth;
            }
        }
    }

    function getUndeliverableMessages ($chan) {
        $r = array();
        foreach (array_keys($this->unDeliverable) as $k) {
            if ($this->unDeliverable[$k]->getWireChannel() == $chan) {
                $r[] = $this->unDeliverable[$k];
            }
        }
        return $r;
    }

    /**
     * Remove all undeliverable messages for the given channel
     */
    function removeUndeliverableMessages ($chan) {
        foreach (array_keys($this->unDeliverable) as $k) {
            if ($this->unDeliverable[$k]->getWireChannel() == $chan) {
                unset($this->unDeliverable[$k]);
            }
        }
    }


    /**
     * Factory method creates wire\Method  objects based on class name
     * and parameters.
     *
     * @arg  string   $class       Amqp class
     * @arg  array    $_args       Format: array (<Amqp method name>,
     *                                            <Assoc method/class mixed field array>,
     *                                            <method content>)
     */
    function constructMethod ($class, $_args) {
        $method = (isset($_args[0])) ? $_args[0] : null;
        $args = (isset($_args[1])) ? $_args[1] : array();
        $content = (isset($_args[2])) ? $_args[2] : null;

        $pl = $this->getProtocolLoader();
        if (! ($cls = $pl('ClassFactory', 'GetClassByName', array($class)))) {
            throw new \Exception("Invalid Amqp class or php method", 8691);
        } else if (! ($meth = $cls->getMethodByName($method))) {
            throw new \Exception("Invalid Amqp method", 5435);
        }

        $m = new wire\Method($meth);
        $clsF = $cls->getSpecFields();
        $mthF = $meth->getSpecFields();

        if ($meth->getSpecHasContent() && $clsF) {
            foreach (array_merge(array_combine($clsF, array_fill(0, count($clsF), null)), $args) as $k => $v) {
                $m->setClassField($k, $v);
            }
        }
        if ($mthF) {
            foreach (array_merge(array_combine($mthF, array_fill(0, count($mthF), '')), $args) as $k => $v) {
                $m->setField($k, $v);
            }
        }
        $m->setContent($content);
        return $m;
    }
}
