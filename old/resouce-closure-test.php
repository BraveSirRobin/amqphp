<?php

// Can resources be used in an enclosure?

class Demo
{
    private $fh = null;
    function __construct() {
        $this->fh = fopen('scratch/test1file.txt', 'c+');
    }

    function __destruct() {
        fclose($this->fh);
    }

    function getReader() {
        $fh = $this->fh;
        return function ($rewind = false) use ($fh) {
            if ($rewind) {
                rewind($fh);
            }
            $buff = '';
            while (! feof($fh)) {
                $buff .= fread($fh, 1024);
            }
            return $buff;
        };
    }

    function getWriter() {
        $fh =& $this->fh;
        return function ($data) use ($fh) {
            rewind($fh);
            return fwrite($fh, $data);
        };
    }
}

// Yes, they can!

$demo = new Demo;
$reader = $demo->getReader();
$writer = $demo->getWriter();
printf("Demo read value is:\n%s\n  (DONE)\n", $reader());
printf("**Do demo write, bw=%d**\n", $writer("Content from writer call"));
printf("Final read value is:\n%s\n  (DONE)\n", $reader());