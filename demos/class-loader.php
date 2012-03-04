<?php
/**
 * 
 * Copyright (C) 2010, 2011, 2012  Robin Harvey (harvey.robin@gmail.com)
 *
 * This  library is  free  software; you  can  redistribute it  and/or
 * modify it under the terms  of the GNU Lesser General Public License
 * as published by the Free Software Foundation; either version 2.1 of
 * the License, or (at your option) any later version.

 * This library is distributed in the hope that it will be useful, but
 * WITHOUT  ANY  WARRANTY;  without   even  the  implied  warranty  of
 * MERCHANTABILITY or  FITNESS FOR A PARTICULAR PURPOSE.   See the GNU
 * Lesser General Public License for more details.

 * You should  have received a copy  of the GNU  Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation,  Inc.,  51 Franklin  Street,  Fifth  Floor, Boston,  MA
 * 02110-1301 USA
 */

/**
 * This file shows you how to use the different builds.  For nspf, you
 * must include the library and generated files in the shown sequence,
 * for  cpf put  the  amqp  base dir  on  the system  path  and use  a
 * "standard" class loader.
 *
 * For the purposes of running the demos, both modes can be run either
 * from a given  directory (set via. the DEMO_LOADER_DIR)  or from the
 * distribution build dir (this is the default)
 */

if (defined('DEMO_LOAD_NSPF')) {
    $DIR = defined('DEMO_LOADER_DIR')
        ? constant('DEMO_LOADER_DIR')
        : sprintf("%s%s%s%s%s%s",
                  dirname(__DIR__),
                  DIRECTORY_SEPARATOR,
                  'build',
                  DIRECTORY_SEPARATOR,
                  'nspf',
                  DIRECTORY_SEPARATOR);

    include $DIR . 'amqphp.protocol.abstrakt.php';
    include $DIR . 'amqphp.wire.php';
    include $DIR . 'amqphp.php';
    include $DIR . 'amqphp.persistent.php';
    include $DIR . 'amqphp.protocol.v0_9_1.php';
    include $DIR . 'amqphp.protocol.v0_9_1.basic.php';
    include $DIR . 'amqphp.protocol.v0_9_1.channel.php';
    include $DIR . 'amqphp.protocol.v0_9_1.confirm.php';
    include $DIR . 'amqphp.protocol.v0_9_1.connection.php';
    include $DIR . 'amqphp.protocol.v0_9_1.exchange.php';
    include $DIR . 'amqphp.protocol.v0_9_1.queue.php';
    include $DIR . 'amqphp.protocol.v0_9_1.tx.php';
} else {
    $DIR = defined('DEMO_LOADER_DIR')
        ? constant('DEMO_LOADER_DIR')
        : sprintf("%s%s%s%s%s%s%s",
                  dirname(__DIR__),
                  DIRECTORY_SEPARATOR,
                  'build',
                  DIRECTORY_SEPARATOR,
                  'cpf',
                  DIRECTORY_SEPARATOR,
                  PATH_SEPARATOR);

    set_include_path($DIR . get_include_path());

    class DefaultLoader
    {
        function load ($class) {
            $target = implode(DIRECTORY_SEPARATOR, explode('\\', $class)) . '.php';
            include $target;
            if (! (class_exists($class, false) || interface_exists($class, false))) {
                throw new Exception("Failed to load {$class} (2)", 6473);
            }
        }
    }

    // Ronseal
    function RegisterDefaultLoader () {
        AddLoader(array(new DefaultLoader(), 'load'));
    }


    /**
     * Wrapper around spl_autoload_register, defaults to prepending loaders
     * to the spl stack, this is the opposite of the default spl behaviour.
     */
    function AddLoader ($loaderClass, $append=false) {
        if (false === spl_autoload_register($loaderClass, false, !$append)) {
            throw new Exception("Failed to register loader", 8754);
        }
    }


    RegisterDefaultLoader();
}