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



class PChannel extends \amqphp\Channel implements \Serializable
{

    private static $PersProps = array('chanId', 'frameMax', 'confirmSeqs',
                                      'confirmSeq', 'confirmMode');

    /**
     * Called  when the  connection is  sleeping to  save  the channel
     * state.
     */
    function serialize () {
        $data = array();
        foreach (self::$PersProps as $k) {
            $data[$k] = $this->$k;
        }
        $data['consumers'] = array();
        foreach ($this->consumers as $cons) {
            if ($cons instanceof \Serializable) {
                $data['consumers'][] = array(serailize($cons[0]),
                                             $cons[1], $cons[2]);
            }
        }
        return $data;
    }

    /**
     * Called when rehydrating a serialised channel
     */
    function unserialize ($data) {
        foreach (self::$PersProps as $p) {
            $this->p = $data[$p];
        }
        foreach ($data['consumers'] as $i => $c) {
            $this->consumers[$i] = array(unserialize($c[0]), $cons[1], $cons[2]);
        }
    }
}