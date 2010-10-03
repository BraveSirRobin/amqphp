<?php

// Packing!!
$quiet = false;

$testInt = (isset($argv[1])) ? (int) $argv[1] : rand(0,100);
$quiet = (array_search('-q', $argv) !== false) || (array_search('--quiet', $argv) !== false);




info("Run all pack32 tests");
testPackInt32();
testUnpackInt32();

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

function packInt64($n) {
    $highBits = $n >> 32;
    $lowBits = $n & (pow(2, 32) - 1);
    return pack('N', $highBits) . pack('N', $lowBits);
}



function hexdump ($data, $htmloutput = false, $uppercase = true, $return = true)
{
    // Init
    $hexi   = '';
    $ascii  = '';
    $dump   = ($htmloutput === true) ? '<pre>' : '';
    $offset = 0;
    $len    = strlen($data);

    // Upper or lower case hexidecimal
    $x = ($uppercase === false) ? 'x' : 'X';

    // Iterate string
    for ($i = $j = 0; $i < $len; $i++)
    {
        // Convert to hexidecimal
        $hexi .= sprintf("%02$x ", ord($data[$i]));

        // Replace non-viewable bytes with '.'
        if (ord($data[$i]) >= 32) {
            $ascii .= ($htmloutput === true) ?
                            htmlentities($data[$i]) :
                            $data[$i];
        } else {
            $ascii .= '.';
        }

        // Add extra column spacing
        if ($j === 7) {
            $hexi  .= ' ';
            $ascii .= ' ';
        }

        // Add row
        if (++$j === 16 || $i === $len - 1) {
            // Join the hexi / ascii output
            $dump .= sprintf("%04$x  %-49s  %s", $offset, $hexi, $ascii);
            
            // Reset vars
            $hexi   = $ascii = '';
            $offset += 16;
            $j      = 0;
            
            // Add newline            
            if ($i !== $len - 1) {
                $dump .= "\n";
            }
        }
    }

    // Finish dump
    $dump .= $htmloutput === true ?
                '</pre>' :
                '';

    // Output method
    if ($return === false) {
        echo $dump;
    } else {
        return $dump;
    }
}

























function _Make64 ( $hi, $lo )
{
    // on x64, we can just use int
    if ( ((int)4294967296)!=0 )
        return (((int)$hi)<<32) + ((int)$lo);
 
    // workaround signed/unsigned braindamage on x32
    $hi = sprintf ( "%u", $hi );
    $lo = sprintf ( "%u", $lo );
 
    // use GMP or bcmath if possible
    if ( function_exists("gmp_mul") )
        return gmp_strval ( gmp_add ( gmp_mul ( $hi, "4294967296" ), $lo ) );
 
    if ( function_exists("bcmul") )
        return bcadd ( bcmul ( $hi, "4294967296" ), $lo );
 
    // compute everything manually
    $a = substr ( $hi, 0, -5 );
    $b = substr ( $hi, -5 );
    $ac = $a*42949; // hope that float precision is enough
    $bd = $b*67296;
    $adbc = $a*67296+$b*42949;
    $r4 = substr ( $bd, -5 ) +  + substr ( $lo, -5 );
    $r3 = substr ( $bd, 0, -5 ) + substr ( $adbc, -5 ) + substr ( $lo, 0, -5 );
    $r2 = substr ( $adbc, 0, -5 ) + substr ( $ac, -5 );
    $r1 = substr ( $ac, 0, -5 );
    while ( $r4>100000 ) { $r4-=100000; $r3++; }
    while ( $r3>100000 ) { $r3-=100000; $r2++; }
    while ( $r2>100000 ) { $r2-=100000; $r1++; }
 
    $r = sprintf ( "%d%05d%05d%05d", $r1, $r2, $r3, $r4 );
    $l = strlen($r);
    $i = 0;
    while ( $r[$i]=="0" && $i<$l-1 )
        $i++;
    return substr ( $r, $i );         
}
/* 
list(,$a) = unpack ( "N", "\xff\xff\xff\xff" );
list(,$b) = unpack ( "N", "\xff\xff\xff\xff" );
$q = _Make64($a,$b);
var_dump($q);
*/