<?php

$tests = array(0,1,2,3,4,5);


//echo "It is..." . array_reduce($tests, function ($a, $b) { printf("Look for %d, %d\n", $a, $b); return 1; });

echo $bn = getNByteInt(25, 4);
echo pack('i', -2);
//printf("Read back binary int: %d\n", readNByteInt($bn, 8));
//echo getIntSize();

//echo pack('N', 257);




function getSignedNByteInt($i, $nBytes) {
    //
}


// Convert $i to a binary ($nBytes)-byte integer
function getNByteInt($i, $nBytes) {
    return array_reduce(bytesplit($i, $nBytes), function ($buff, $item) {
            return $buff . chr($item);
        });
}

// Read an ($nBytes)-byte integer from $bin
function readNByteInt($bin, $nBytes) {
    $byte = substr($bin, 0, 1);
    $ret = ord($byte);
    for ($i = 1; $i < $nBytes; $i++) {
        $ret = ($ret << 8) + ord(substr($bin, $i, 1));
    }
    return $ret;
}

function getIntSize() {
    // Choose between 32 and 64 only
    return (gettype(pow(2, 31)) == 'integer') ? 64 : 32;
}

function bytesplit($x, $bytes) {
    // Needed as stand-alone func?
    $ret = array();
    for ($i = 0; $i < $bytes; $i++) {
        $ret[] = $x & 255;
        $x = ($x >> 8);
    }
    return array_reverse($ret);
}
