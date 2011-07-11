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
    private $myConn;

    /** The channel ID we're linked to */
    protected $chanId;

    /**
     * As  set  by  the  channel.flow Amqp  method,  controls  whether
     * content can be sent or not
     */
    private $flow = true;

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
    private $isOpen = false;

    /**
     * Consumers for this channel, format array(array(<Consumer>, <consumer-tag OR false>, <#FLAG#>)+)
     * #FLAG# is the consumer status, this is:
     *  'READY_WAIT' - not yet started, i.e. before basic.consume/basic.consume-ok
     *  'READY' - started and ready to recieve messages
     *  'CLOSED' - previously live but now closed, receiving a basic.cancel-ok triggers this.
     */
    protected $consumers = array();

    /** Channel level callbacks for basic.ack (RMQ confirm feature) and basic.return */
    private $callbacks = array('publishConfirm' => null,
                               'publishReturn' => null,
                               'publishNack' => null);

    /** Store of basic.publish sequence numbers. */
    protected $confirmSeqs = array();
    protected $confirmSeq = 0;

    /** Flag set during RMQ confirm mode */
    protected $confirmMode = false;


    function setPublishConfirmCallback (\Closure $c) {
        $this->callbacks['publishConfirm'] = $c;
    }


    function setPublishReturnCallback (\Closure $c) {
        $this->callbacks['publishReturn'] = $c;
    }

    function setPublishNackCallback (\Closure $c) {
        $this->callbacks['publishNack'] = $c;
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
            ! ($confSelectOk->getClassProto()->getSpecName() == 'confirm' &&
               $confSelectOk->getMethodProto()->getSpecName() == 'select-ok')) {
            throw new \Exception("Failed to selectg confirm mode", 8674);
        }
        $this->confirmMode = true;
    }


    function __construct (Connection $rConn, $chanId, $frameMax) {
        $this->myConn = $rConn;
        $this->chanId = $chanId;
        $this->frameMax = $frameMax;
        $this->callbacks['publishConfirm'] = $this->callbacks['publishReturn'] = function () {};
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
     * @arg  array    $_args       Format: array (<Amqp method name>,
     *                                            <Assoc method/class mixed field array>,
     *                                            <method content>)
     */
    function __call ($class, $_args) {
        if ($this->destroyed) {
            throw new \Exception("Attempting to use a destroyed channel", 8766);
        }
        $m = $this->myConn->constructMethod($class, $_args);
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
        if ($this->confirmMode && $m->getClassProto()->getSpecName() == 'basic'
            && $m->getMethodProto()->getSpecName() == 'publish') {
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
        $sid = "{$meth->getClassProto()->getSpecName()}.{$meth->getMethodProto()->getSpecName()}";

        switch ($sid) {
        case 'channel.flow':
            $this->flow = ! $this->flow;
            if ($r = $meth->getMethodProto()->getResponses()) {
                $meth = new wire\Method($r[0]);
                $meth->setWireChannel($this->chanId);
                $this->invoke($meth);
            }
            return false;
            break;
        case 'channel.close':
            $pl = $this->myConn->getProtocolLoader();
            //if ($culprit = protocol\ClassFactory::GetMethod($meth->getField('class-id'), $meth->getField('method-id'))) {
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
            return true;
        default:
            throw new \Exception("Received unexpected channel message: $sid", 8795);
        }
    }


    /**
     * Delivery handler for all non-channel class input messages.
     */
    function handleChannelDelivery (wire\Method $meth) {
        $sid = "{$meth->getClassProto()->getSpecName()}.{$meth->getMethodProto()->getSpecName()}";

        switch ($sid) {
        case 'basic.deliver':
            return $this->deliverConsumerMessage($meth, $sid);
        case 'basic.return':
            $cb = $this->callbacks['publishReturn'];
            return false;
        case 'basic.ack':
            $cb = $this->callbacks['publishConfirm'];
            $this->removeConfirmSeqs($meth, $cb);
            return false;
        case 'basic.nack':
            $cb = $this->callbacks['publishNack'];
            $this->removeConfirmSeqs($meth, $cb);
            return false;
        default:
            throw new \Exception("Received unexpected channel delivery:\n$sid", 87998);
        }
    }


    /**
     * Delivers 'Consume Session'  messages to channels consumers, and
     * handles responses.
     */
    private function deliverConsumerMessage ($meth, $sid) {
        // Look up the target consume handler and invoke the callback
        $ctag = $meth->getField('consumer-tag');
        list($cons, $status) = $this->getConsumerAndStatus($ctag);
        $response = $cons->handleDelivery($meth, $this);

        // Handle callback response signals, i.e the CONSUMER_XXX API messages, but only
        // for API responses to the basic.deliver message
        if ($sid !== 'basic.deliver' || ! $response) {
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
                $cnl = $this->basic('cancel', array('consumer-tag' => $ctag, 'no-wait' => false));
                $cOk = $this->invoke($cnl);
                if ($cOk && ($cOk->getClassProto()->getSpecName() == 'basic'
                             && $cOk->getMethodProto()->getSpecName() == 'cancel-ok')) {
                    $this->setConsumerStatus($ctag, 'CLOSED') OR
                        trigger_error("Failed to set consumer status flag", E_USER_WARNING);

                } else {
                    throw new \Exception("Failed to cancel consumer - bad broker response", 9768);
                }
                $cons->handleCancelOk($cOk, $this);
                break;
            }
        }

        return false;
    }


    /**
     * Helper:  remove  message   sequence  record(s)  for  the  given
     * basic.{n}ack (RMQ Confirm key)
     */
    private function removeConfirmSeqs (wire\Method $meth, \Closure $handler = null) {
        if ($meth->getField('multiple')) {

            $dtag = $meth->getField('delivery-tag');
            $this->confirmSeqs = 
                array_filter($this->confirmSeqs,
                             function ($id) use ($dtag, $handler, $meth) {
                                 if ($id <= $dtag) {
                                     if ($handler) {
                                         $handler($meth);
                                     }
                                     return false;
                                 } else {
                                     return true;
                                 }
                             });
        } else {
            $dt = $meth->getField('delivery-tag');
            if (isset($this->confirmSeqs)) {
                if ($handler) {
                    $handler($meth);
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

    function removeConsumer (Consumer $cons) {
        trigger_error("Consumers can no longer be directly removed", E_USER_DEPRECATED);
        return;
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



    /**
     * Channel  callback from  Connection->select()  - prepare  signal
     * raised just before entering the select loop.
     * @return  boolean         Return true if there are consumers present
     */
    function onSelectStart () {
        if (! $this->consumers) {
            return false;
        }
        foreach (array_keys($this->consumers) as $cnum) {
            if (false === $this->consumers[$cnum][1]) {
                $consume = $this->consumers[$cnum][0]->getConsumeMethod($this);
                $cOk = $this->invoke($consume);
                $this->consumers[$cnum][0]->handleConsumeOk($cOk, $this);
                $this->consumers[$cnum][2] = 'READY';
                $this->consumers[$cnum][1] = $cOk->getField('consumer-tag');
            }
        }
        return true;
    }

    function onSelectEnd () {
        $this->consuming = false;
    }
}
