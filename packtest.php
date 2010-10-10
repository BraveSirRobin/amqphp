<?php

// Packing!!
$quiet = false;

$testInt = (isset($argv[1])) ? (int) $argv[1] : rand(0,100);
$quiet = (array_search('-q', $argv) !== false) || (array_search('--quiet', $argv) !== false);


echo packInt64($testInt);

/*
info("Run all pack32 tests");
testPackInt32();
testUnpackInt32();

*/

/**
 * Test the packInt32 function over a set of ranges.  Test is to pack the subject using
 * pack('N', $N) then compare against packInt32 - if the return values are the same the
 * test passes.  Note: the packed value isn't neccesarily correct (e.g. for values outside
 * 2^32 range).
 */
function testPackInt32() {
    $buff = 100;
    $pows = array(2, 4, 8, 16, 31, 32);
    foreach ($pows as $pow) {
        $to = pow(2, $pow) + $buff;
        $from = pow(2, $pow) - $buff;
        $nFails = 0;
        if ($from < 0) {
            $from = 0;
        }
        for ($i = $from; $i < $to; $i++) {
            $phpPacked = pack('N', $i);
            $myPacked = packInt32($i);
            if ($myPacked !== $phpPacked) {
                info(sprintf("Pack32 failed for %dn\ttest power %d (%d to %d)",
                             $i, $pow, $from, $to));
                if ($nFails++ > 5) {
                    return;
                }
            }
        }
        info(sprintf("All pack tests passed for power %d (%d - %d)", $pow, $from, $to));
    }
    info("All Pack tests complete.");
}


/**
 * For a set of ranges, pack then unpack all int values using the PHP pack/unpack functions and
 * with unPackInt32.  Test that the unpacked value from unPackInt32 is the same as the PHP unpacked
 * version.  Note that the unpacked values aren't necessarily correct if the test int is outside the
 * int32 range, this function only reports differences between the unpacked ints, not differences between
 * the unpacked int and the original test subject
 */
function testUnpackInt32() {
    $buff = 100;
    $pows = array(2, 4, 8, 16, 31, 32);
    foreach ($pows as $pow) {
        $to = pow(2, $pow) + $buff;
        $from = pow(2, $pow) - $buff;
        $nFails = 0;
        if ($from < 0) {
            $from = 0;
        }
        for ($i = $from; $i < $to; $i++) {
            $packed = pack('N', $i);
            $phpUnpacked = unpack('N', $packed);
            $phpUnpacked = $phpUnpacked[1];
            $myUnpacked = unpackInt32($packed);
            if ($myUnpacked !== $phpUnpacked) {
                $pUnpacked = unpack('N', $packed);
                info(sprintf("Unpack failed for %d (unpacked to %d, should be %d)\n\ttest power %d (%d to %d)",
                             $i, $myUnpacked, $phpUnpacked, $pow, $from, $to));
                if ($nFails++ > 5) {
                    return;
                }
            }
        }
        info(sprintf("All tests passed for power %d (%d - %d)", $pow, $from, $to));
    }
    info("All unpack tests complete.");
}

function info($msg) {
    global $quiet;
    if (! $quiet) {
        echo "[INFO] $msg\n";
    }
}

// Split the 32 bit in 2 and return packed 32 as combination of packed 16s
function packInt32($n) {
    static $lbMask = null;
    if (is_null($lbMask)) {
        $lbMask = (pow(2, 16) - 1);
    }
    $hb = $n >> 16;
    $lb = $n & $lbMask;
    return pack('n', $hb) . pack('n', $lb);
}

// Counterpart to packInt32
function unpackInt32($pInt) {
    $plb = substr($pInt, 0, 2);
    $phb = substr($pInt, 2, 2);
    $lb = (int) array_shift(unpack('n', $plb));
    $hb = (int) array_shift(unpack('n', $phb));
    return (int) $hb + (((int) $lb) << 16);
}



// Split the 64 bit in 2 and return packed 32 as combination of packed 16s
function packInt64($n) {
    static $lbMask = null;
    if (is_null($lbMask)) {
        $lbMask = (pow(2, 32) - 1);
    }
    $hb = $n >> 16;
    $lb = $n & $lbMask;
    return pack('N', $hb) . pack('N', $lb);
}

// Counterpart to packInt64
function unpackInt64($pInt) {
    $plb = substr($pInt, 0, 2);
    $phb = substr($pInt, 2, 2);
    $lb = (int) array_shift(unpack('N', $plb));
    $hb = (int) array_shift(unpack('N', $phb));
    return (int) $hb + (((int) $lb) << 16);
}
