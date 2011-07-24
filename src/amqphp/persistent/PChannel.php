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
 * is invoked by the containing Channel
 */
class PChannel extends \amqphp\Channel implements \Serializable
{

    private static $PersProps = array('chanId', 'frameMax', 'confirmSeqs',
                                      'confirmSeq', 'confirmMode');

    function serialize () {
        $data = array();
        foreach (self::$PersProps as $k) {
            $data[$k] = $this->$k;
        }
        $data['consumers'] = array();
        foreach ($this->consumers as $cons) {
            if ($cons[0] instanceof \Serializable && $cons[2] == 'READY') {
                error_log("Serialize consumer: {$cons[1]}");
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
            $this->consumers[$i] = array($c[0], $cons[1], $cons[2]);
        }
    }

}