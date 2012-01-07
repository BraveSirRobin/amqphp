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


class Channel
{
    /** The parent Connection object */
    protected $myConn;

    /** The channel ID we're linked to */
    protected $chanId;

    /**
     * As  set  by  the  channel.flow Amqp  method,  controls  whether
     * content can be sent or not
     */
    protected $flow = true;

    /**
     * Flag set when  the underlying Amqp channel has  been closed due
     * to an exception
     */
    private $destroyed = false;

    /**
     * Set by negotiation during channel setup
     */
    protected $frameMax;

    /**
     * Used to track whether the channel.open returned OK.
     */
    protected $isOpen = false;

    /**
     * Consumers for this channel, format array(array(<Consumer>, <consumer-tag OR false>, <#FLAG#>)+)
     * #FLAG# is the consumer status, this is:
     *  'READY_WAIT' - not yet started, i.e. before basic.consume/basic.consume-ok
     *  'READY' - started and ready to recieve messages
     *  'CLOSED' - previously live but now closed, receiving a basic.cancel-ok triggers this.
     */
    protected $consumers = array();

    protected $callbackHandler;

    /** Store of basic.publish sequence numbers. */
    protected $confirmSeqs = array();
    protected $confirmSeq = 0;

    /** Flag set during RMQ confirm mode */
    protected $confirmMode = false;


    /**
     * Assigns a channel event handler
     */
    function setEventHandler (ChannelEventHandler $evh) {
        $this->callbackHandler = $evh;
    }

    function hasOutstandingConfirms () {
        return (bool) $this->confirmSeqs;
    }

    function setConfirmMode () {
        if ($this->confirmMode) {
            return;
        }
        $confSelect = $this->confirm('select');
        $confSelectOk = $this->invoke($confSelect);
        if (! ($confSelectOk instanceof wire\Method) ||
            $confSelectOk->amqpClass != 'confirm.select-ok') {
            throw new \Exception("Failed to set confirm mode", 8674);
        }
        $this->confirmMode = true;
    }


    function setConnection (Connection $rConn) {
        $this->myConn = $rConn;
    }

    function setChanId ($chanId) {
        $this->chanId = $chanId;
    }

    function getChanId () {
        return $this->chanId;
    }

    function setFrameMax ($frameMax) {
        $this->frameMax = $frameMax;
    }


    function initChannel () {
        $pl = $this->myConn->getProtocolLoader();
        $meth = new wire\Method($pl('ClassFactory', 'GetMethod', array('channel', 'open')), $this->chanId);
        $meth->setField('reserved-1', '');
        $resp = $this->myConn->invoke($meth);
    }

    /**
     * Factory method creates wire\Method  objects based on class name
     * and parameters.
     *
     * @arg  string   $class       Amqp class
     * @arg  array    $args       Format: array (<Amqp method name>,
     *                                           <Assoc method/class mixed field array>,
     *                                           <method content>)
     */
    function __call ($class, $args) {
        if ($this->destroyed) {
            throw new \Exception("Attempting to use a destroyed channel", 8766);
        }
        $m = $this->myConn->constructMethod($class, $args);
        $m->setWireChannel($this->chanId);
        $m->setMaxFrameSize($this->frameMax);
        return $m;
    }

    /**
     * A wrapper for Connection->invoke() specifically for messages on
     * this channel.
     */
    function invoke (wire\Method $m) {
        if ($this->destroyed) {
            throw new \Exception("Attempting to use a destroyed channel", 8767);
        } else if (! $this->flow) {
            trigger_error("Channel is closed", E_USER_WARNING);
            return;
        } else if (is_null($tmp = $m->getWireChannel())) {
            $m->setWireChannel($this->chanId);
        } else if ($tmp != $this->chanId) {
            throw new \Exception("Method is invoked through the wrong channel", 7645);
        }

        // Do numbering of basic.publish during confirm mode
        if ($this->confirmMode && $m->amqpClass == 'basic.publish') {
            $this->confirmSeq++;
            $this->confirmSeqs[] = $this->confirmSeq;
        }


        return $this->myConn->invoke($m);
    }

    /**
     * Callback  from the  Connection  object for  channel frames  and
     * messages.  Only channel class methods should be delivered here.
     * @param   $meth           A channel method for this channel
     * @return  boolean         True:  Add message to internal queue for regular delivery
     *                          False: Remove message from internal queue
     */
    function handleChannelMessage (wire\Method $meth) {
        switch ($meth->amqpClass) {
        case 'channel.flow':
            $this->flow = ! $this->flow;
            if ($r = $meth->getMethodProto()->getResponses()) {
                $meth = new wire\Method($r[0], $this->chanId);
                $this->invoke($meth);
            }
            return false;
        case 'channel.close':
            $pl = $this->myConn->getProtocolLoader();
            if ($culprit = $pl('ClassFactory', 'GetMethod', array($meth->getField('class-id'),
                                                                  $meth->getField('method-id')))) {
                $culprit = $culprit->getSpecName();
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
            $closeOk = new wire\Method($tmp[0], $this->chanId);
            $em = "[channel.close] reply-code={$errCode['name']} triggered by $culprit: $eb";

            try {
                $this->myConn->invoke($closeOk);
                $em .= " Channel closed OK";
                $n = 3687;
            } catch (\Exception $e) {
                $em .= " Additionally, channel closure ack send failed";
                $n = 2435;
            }
            throw new \Exception($em, $n);
        case 'channel.close-ok':
        case 'channel.open-ok':
        case 'channel.flow-ok':
            return true;
        default:
            throw new \Exception("Received unexpected channel message: {$meth->amqpClass}", 8795);
        }
    }


    /**
     * Delivery handler for all non-channel class input messages.
     */
    function handleChannelDelivery (wire\Method $meth) {
        switch ($meth->amqpClass) {
        case 'basic.deliver':
            return $this->deliverConsumerMessage($meth);
        case 'basic.return':
            if ($this->callbackHandler) {
                $this->callbackHandler->publishReturn($meth);
            }
            return false;
        case 'basic.ack':
            $this->removeConfirmSeqs($meth, 'publishConfirm');
            return false;
        case 'basic.nack':
            $this->removeConfirmSeqs($meth, 'publishNack');
            return false;
        case 'basic.cancel':
            // TODO: Allow the broker  to cancel this consume session,
            // ..as per spec: "It may also  be sent from the server to
            //   the  client  in  the  event  of  the  consumer  being
            //   unexpectedly cancelled (i.e. cancelled for any reason
            //   other  than the  server  receiving the  corresponding
            //   basic.cancel from the client). This allows clients to
            //   be notified  of the loss  of consumers due  to events
            //   such as queue deletion"
            // ALSO: this  has a bearing  on the implementation  of HA
            // queues.
            $this->handleConsumerCancel($meth);
        default:
            throw new \Exception("Received unexpected channel delivery:\n{$meth->amqpClass}", 87998);
        }
    }


    /**
     * Handle an  incoming consumer.cancel by  notifying the consumer,
     * removing  the local  consumer and  sending  the basic.cancel-ok
     * response.
     */
    private function handleConsumerCancel ($meth) {
        $ctag = $meth->getField('consumer-tag');
        list($cons, $status) = $this->getConsumerAndStatus($ctag);
        if ($cons && $status == 'READY') {
            $cons->handleCancel($meth, $this); // notify
            $this->removeConsumerByTag($cons, $ctag); // remove
            if (! $meth->getField('no-wait')) {
                $this->invoke($this->basic('cancel-ok', array('consumer-tag', $ctag))); // respond
            }
        } else if ($cons) {
            $m = sprintf("Cancellation message delivered to closed consumer %s", $ctag);
            trigger_error($m, E_USER_WARNING);
        } else {
            $m = sprintf("Unable to load consumer for consumer cancellation %s", $ctag);
            trigger_error($m, E_USER_WARNING);
        }
    }



    /**
     * Delivers 'Consume Session'  messages to channels consumers, and
     * handles responses.
     * @param    amqphp\wire\Method   $meth      Always basic.deliver
     */
    private function deliverConsumerMessage ($meth) {
        // Look up the target consume handler and invoke the callback
        $ctag = $meth->getField('consumer-tag');
        $response = false;
        list($cons, $status) = $this->getConsumerAndStatus($ctag);
        if ($cons && $status == 'READY') {
            $response = $cons->handleDelivery($meth, $this);
        } else if ($cons) {
            $m = sprintf("Message delivered to closed consumer %s in non-ready state %s -- reject %s",
                         $ctag, $status, $meth->getField('delivery-tag'));
            trigger_error($m, E_USER_WARNING);
            $response = CONSUMER_REJECT;
        } else {
            $m = sprintf("Unable to load consumer for delivery %s -- reject %s",
                         $ctag, $meth->getField('delivery-tag'));
            trigger_error($m, E_USER_WARNING);
            $response = CONSUMER_REJECT;
        }

        // Handle callback response signals,  i.e the CONSUMER_XXX API
        // messages, but  only for API responses  to the basic.deliver
        // message
        if (! $response) {
            return false;
        }

        if (! is_array($response)) {
            $response = array($response);
        }
        foreach ($response as $resp) {
            switch ($resp) {
            case CONSUMER_ACK:
                $ack = $this->basic('ack', array('delivery-tag' => $meth->getField('delivery-tag'),
                                                 'multiple' => false));
                $this->invoke($ack);
                break;
            case CONSUMER_DROP:
            case CONSUMER_REJECT:
                $rej = $this->basic('reject', array('delivery-tag' => $meth->getField('delivery-tag'),
                                                    'requeue' => ($resp == CONSUMER_REJECT)));
                $this->invoke($rej);
                break;
            case CONSUMER_CANCEL:
                // Basic.cancel this consumer, then change the it's status flag
                $this->removeConsumerByTag($cons, $ctag);
                break;
            }
        }

        return false;
    }



    /**
     * Helper:  remove  message   sequence  record(s)  for  the  given
     * basic.{n}ack (RMQ Confirm key)
     */
    private function removeConfirmSeqs (wire\Method $meth, $event) {
        if ($meth->getField('multiple')) {

            $dtag = $meth->getField('delivery-tag');
            $evh = $this->callbackHandler;
            $this->confirmSeqs = 
                array_filter($this->confirmSeqs,
                             function ($id) use ($dtag, $evh, $event, $meth) {
                                 if ($id <= $dtag) {
                                     if ($evh) {
                                         $evh->$event($meth);
                                     }
                                     return false;
                                 } else {
                                     return true;
                                 }
                             });
        } else {
            $dt = $meth->getField('delivery-tag');
            if (isset($this->confirmSeqs)) {
                if ($this->callbackHandler) {
                    $this->callbackHandler->$event($meth);
                }
                unset($this->confirmSeqs[array_search($dt, $this->confirmSeqs)]);
            }
        }
    }



    /**
     * Perform  a  protocol  channel  shutdown and  remove  self  from
     * containing Connection
     */
    function shutdown () {
        if (! $this->invoke($this->channel('close', array('reply-code' => '', 'reply-text' => '')))) {
            trigger_error("Unclean channel shutdown", E_USER_WARNING);
        }
        $this->myConn->removeChannel($this);
        $this->destroyed = true;
        $this->myConn = $this->chanId = $this->ticket = null;
    }

    /**
     * TODO: Add a second parameter so for basic.consume params (?)
     */
    function addConsumer (Consumer $cons) {
        foreach ($this->consumers as $c) {
            if ($c === $cons) {
                throw new \Exception("Consumer can only be added to channel once", 9684);
            }
        }
        $this->consumers[] = array($cons, false, 'READY_WAIT');
    }


    /**
     * Called from  select loop  to see whether  this object  wants to
     * continue looping.
     * @return  boolean      True:  Request Connection stays in select loop
     *                       False: Confirm to connection it's OK to exit from loop
     */
    function canListen (){
        return $this->hasListeningConsumers() || $this->hasOutstandingConfirms();
    }

    /**
     * Send basic.cancel to cancel the given consumer subscription and
     * mark as closed internally.
     */
    function removeConsumer (Consumer $cons) {
        foreach ($this->consumers as $c) {
            if ($c[0] === $cons) {
                if ($c[2] == 'READY') {
                    $this->removeConsumerByTag($c[0], $c[1]);
                }
                return;
            }
        }
        trigger_error("Consumer does not belong to this Channel", E_USER_WARNING);
    }


    /**
     * Cancel all consumers.
     */
    function removeAllConsumers () {
        foreach ($this->consumers as $c) {
            if ($c[2] == 'READY') {
                $this->removeConsumerByTag($c[0], $c[1]);
            }
        }
    }


    private function removeConsumerByTag (Consumer $cons, $ctag) {
        list(, $cstate) = $this->getConsumerAndStatus($ctag);
        if ($cstate == 'CLOSED') {
            trigger_error("Consumer is already removed", E_USER_WARNING);
            return;
        }
        $cnl = $this->basic('cancel', array('consumer-tag' => $ctag, 'no-wait' => false));
        $cOk = $this->invoke($cnl);
        if ($cOk->amqpClass == 'basic.cancel-ok') {
            $this->setConsumerStatus($ctag, 'CLOSED') OR
                trigger_error("Failed to set consumer status flag", E_USER_WARNING);

        } else {
            throw new \Exception("Failed to cancel consumer - bad broker response", 9768);
        }
        $cons->handleCancelOk($cOk, $this);
    }

    private function setConsumerStatus ($tag, $status) {
        foreach ($this->consumers as $k => $c) {
            if ($c[1] === $tag) {
                $this->consumers[$k][2] = $status;
                return true;
            }
        }
        return false;
    }


    private function getConsumerAndStatus ($tag) {
        foreach ($this->consumers as $c) {
            if ($c[1] == $tag) {
                return array($c[0], $c[2]);
            }
        }
        return array(null, 'INVALID');
    }


    function hasListeningConsumers () {
        foreach ($this->consumers as $c) {
            if ($c[2] === 'READY') {
                return true;
            }
        }
        return false;
    }

    /** Return the consumer associated with consumer tag $t  */
    function getConsumerByTag ($t) {
        foreach ($this->consumers as $c) {
            if ($c[2] == 'READY' && $c[1] === $t) {
                return $c[0];
            }
        }
    }

    /** Return an array of all consumer tags */
    function getConsumerTags () {
        $tags = array();
        foreach ($this->consumers as $c) {
            if ($c[2] == 'READY') {
                $tags[] = $c[1];
            }
        }
        return $tags;
    }


    /**
     * Invoke  the   basic.consume  amqp  command   for  all  attached
     * consumers which are in the READY_WAIT state.
     *
     * @return  boolean         Return true if any consumers were started
     */
    function startAllConsumers () {
        if (! $this->consumers) {
            return false;
        }
        $r = false;
        foreach (array_keys($this->consumers) as $cnum) {
            if (false === $this->consumers[$cnum][1]) {
                $this->_startConsumer($cnum);
                $r = true;
            }
        }
        return $r;
    }


    private function _startConsumer ($cnum) {
        $consume = $this->consumers[$cnum][0]->getConsumeMethod($this);
        $cOk = $this->invoke($consume);
        $this->consumers[$cnum][0]->handleConsumeOk($cOk, $this);
        $this->consumers[$cnum][2] = 'READY';
        $this->consumers[$cnum][1] = $cOk->getField('consumer-tag');
    }

    /**
     * Manually  start consuming  for the  given consumer.   Note that
     * this is normally done automatically.
     * @return       boolean        True if the consumer was actually started.
     */
    function startConsumer (Consumer $cons) {
        foreach ($this->consumers as $i => $c) {
            if ($c[0] === $cons && $c[1] === false) {
                $this->_startConsumer($i);
                return true;
            }
        }
        return false;
    }

    function onSelectEnd () {
        $this->consuming = false;
    }

    function isSuspended () {
        return ! $this->flow;
    }

    function toggleFlow () {
        $flow = ! $this->flow;
        $this->flow = true; // otherwise the message won't send
        $meth = $this->channel('flow', array('active' => $flow));
        $fr = $this->invoke($meth);
        $newFlow = $fr->getField('active');
        if ($newFlow != $flow) {
            trigger_error(sprintf("Flow Unexpected channel flow response, expected %d, got %d", ! $this->flow, $this->flow), E_USER_WARNING);
        }
        $this->flow = $newFlow;
    }
}