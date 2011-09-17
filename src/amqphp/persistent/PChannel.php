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

namespace amqphp\persistent;


/**
 * Simple persistence  extension for the  standard Channel.  Serialise
 * is invoked by the containing Channel.
 *
 * TODO  :  Implement  a  channel suspend  feature  (optional?)  using
 * channel.flow.  Must be called in both serialise methods.
 */
class PChannel extends \amqphp\Channel implements \Serializable
{

    /**
     * Flag  - when  set,  the  serialize methods  will  use the  amqp
     * channel.flow to suspend and resume message delivery.
     * TODO : Set default back to false!!!!
     * TODO : Replace with 2 flags: suspendDuringSleep and unsuspendDuringWakeup
     */
    public $suspendFlow = false;

    private static $PersProps = array('chanId', 'flow', 'frameMax', 'confirmSeqs',
                                      'confirmSeq', 'confirmMode', 'isOpen', 
                                      'callbackHandler', 'suspendFlow');

    function serialize () {
        $data = array();
        foreach (self::$PersProps as $k) {
            $data[$k] = $this->$k;
        }
        $data['consumers'] = array();
        foreach ($this->consumers as $cons) {
            if ($cons[0] instanceof \Serializable && $cons[2] == 'READY') {
                $data['consumers'][] = $cons;
            }
        }
        return serialize($data);
    }

    /**
     * Called when rehydrating a serialised channel
     */
    function unserialize ($data) {
        $data = unserialize($data);
        foreach (self::$PersProps as $p) {
            $this->$p = $data[$p];
        }
        foreach ($data['consumers'] as $i => $c) {
            $this->consumers[$i] = array($c[0], $c[1], $c[2]);
        }
    }

}