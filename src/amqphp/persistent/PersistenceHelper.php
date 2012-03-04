<?php
/**
 *
 * Copyright (C) 2010, 2011, 2012  Robin Harvey (harvey.robin@gmail.com)
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
 * Definition  of an  object  to hold  connection  / channel  metadata
 * between  requests  for Persistent  connections.   Data  is saved  /
 * loaded based on the current process ID
 */
interface PersistenceHelper
{

    /** Passed a url-specific identifier for this helper. */
    function setUrlKey ($k);

    function getData ();

    function setData ($data);

    function save ();

    function load ();

    function destroy ();
}