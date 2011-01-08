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

/**
 * This one simply runs theXSLT transformation which generates the protocol
 * bindings from the XML psec document.  An alternative method is this (from pwd):
 * xsltproc amqp-binding-gen.xslt amqp-xml-spec-0.9.1.xml
 * mv gencode ../
 */

define('OUTPUT_DIR', __DIR__ . '/gencode');
define('SS_FILE', 'amqp-binding-gen.xslt');
define('SPEC_FILE', 'amqp-xml-spec-0.9.1.xml');

if (! is_dir(OUTPUT_DIR)) {
    if (! mkdir(OUTPUT_DIR)) {
        printf("Error!  Failed to create output dir %s\n", OUTPUT_DIR);
        die;
    }
}

$proc = new XsltProcessor;
$ssDom = new DomDocument;
$ssDom->load(SS_FILE);
$proc->importStylesheet($ssDom);
$specDom = new DomDocument;
$specDom->load(SPEC_FILE);
echo $proc->transformToXml($specDom); // Don't expect any output, SS uses xslt:document to output directly to file

echo wordwrap(sprintf("\nThe protocol binding classes have been generated in %s, now simply copy this whole " .
                      "directory in to the same directory as the ampq class file (usually the parent directory, %s)\n", OUTPUT_DIR, dirname(__DIR__)));