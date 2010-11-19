<?php

$tests = range(2356, 2362);

foreach ($tests as $t) {
    printf("Test %d: (Mine, PHP's) = (%s, %s)\n", $t, my_dechex($t), dechex($t));
}


function my_dechex($n) {
    if ($n === 0) {
        return 0;
    }
    $ret = '';
    while ($n > 0) {
        $tmp = bcdiv((string) $n, '16', 4);
        $n = bcdiv((string) $n, 16);
        $dec = bcmul(bcsub($tmp, $n, 4), 16);
        if ($dec > 9) {
            $dec = chr(87 + $dec);
        }
        $ret .= $dec;
    }
    return strrev($ret);
}