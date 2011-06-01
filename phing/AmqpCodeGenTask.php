<?php

require_once "phing/Task.php";

/**
 * Runs  the  stylesheet  to   generate  Amqp  binding  classes.   The
 * stylesheet, XML spec file and output dir are all specified as phing
 * xml parameters.
 */
class AmqpCodeGenTask extends Task {

    private $stylesheet;
    private $specfile;
    private $outputDir;

    function setStylesheet ($file) {
        $this->stylesheet = $file;
    }

    function setSpecfile ($file) {
        $this->specfile = $file;
    }

    function setOutputDir ($dir) {
        $this->outputDir = $dir;
    }

    /**
     * The init method: Do init steps.
     */
    function init () {}

    /**
     * The main entry point method.
     */
    function main () {
        if (! $this->stylesheet || ! is_file($this->stylesheet)) {
            throw new \Exception("codegen task requires a stylesheet", 8478);
        }
        if (! $this->specfile || ! is_file($this->specfile)) {
            throw new \Exception("codegen task requires a specfile", 8474);
        }
        if (! $this->outputDir || ! is_dir($this->outputDir)) {
            throw new \Exception("codegen task requires an output directory", 8471);
        }

        $proc = new XsltProcessor;
        $ssDom = new DomDocument;
        if (! $ssDom->load($this->stylesheet)) {
            throw new \Exception("codegen task failed to load stylesheet dom", 8475);
        }
        $proc->registerPHPFunctions();
        $proc->importStylesheet($ssDom);
        $specDom = new DomDocument;
        if (! $specDom->load($this->specfile)) {
            throw new \Exception("codegen task failed to load spec file dom", 8472);
        }
        $proc->setParameter('', 'OUTPUT_DIR', $this->outputDir);
        $proc->transformToXml($specDom);

    }


    /**
     * Callback is invoked from  the stylesheet to pre-create required
     * output directories on the fly.
     */
    static function MkdirXslCallback ($file) {
        $dir = dirname($file);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

}