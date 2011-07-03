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


/**
 * Definition  of an  object  to hold  connection  / channel  metadata
 * between  requests  for Persistent  connections.   Data  is saved  /
 * loaded based on the current process ID
 */
interface PersistenceHelper
{
    /**
     * Set the processID of the persistent connection, usually use the
     * result of getmypid()
     *  @return void
     */
    function setProcessId ($pid);

    /**
     * Stored a named value in the data store
     * @return void
     */
    function setDataItem ($key, $value);

    /**
     * Return a named  value from the data store.
     * @return mixed
     */
    function getDataItem ($key);

    /**
     * Return true if the given data value exists in the data store.
     * @return boolean
     */
    function hasDataItem ($key);

    /**
     * Flush  all data  to the  data  store, usually  used during  the
     * p-connection sleep procedure.
     * @return boolean
     */
    function save ();

    /**
     * Load all data from the persistent store.
     * @return boolean
     */
    function load ();
}