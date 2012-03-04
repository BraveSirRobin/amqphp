<?php

// Check all saved messages in given dir and report on disrepancies

if (! isset($argv[1]) || ! is_dir($argv[1])) {
    printf("USAGE: php %s <forker output dir>\n", __FILE__);
    die;
}

// Suck results files in to array.
$cons = $prods = $rCons = $rProds = array();
printf("Begin: scan output dir %s\n", $argv[1]);
foreach (scandir($argv[1]) as $item) {
    if ($item == '.' || $item == '..' ||
        ! (substr($item, 0, 8) != 'consumer' ||
           substr($item, 0, 8) != 'producer')) {
        continue;
    }
    if (substr($item, 0, 8) == 'consumer') {
        $folder = "{$argv[1]}/{$item}/";
        foreach (scandir("{$argv[1]}/$item") as $subItem) {
            if ($subItem == '.' || $subItem == '..') {
                continue;
            }
            if (in_array($subItem, $cons)) {
                printf("Consumer clash for %s\n", $subItem);
            }
            $cons[] = $subItem;
            $rCons[$subItem] = "{$folder}/{$subItem}";
        }
    } else {
        $folder = "{$argv[1]}/{$item}/";
        foreach (scandir("{$argv[1]}/$item") as $subItem) {
            if ($subItem == '.' || $subItem == '..') {
                continue;
            }
            if (in_array($subItem, $prods)) {
                printf("Producer clash for %s\n", $subItem);
            }
            $prods[] = $subItem;
            $rProds[$subItem] = "{$folder}/{$subItem}";
        }
    }
}

$numCons = count($cons);
$numProds = count($prods);
$numRCons = count($rCons);
$numRProds = count($rProds);
printf("Got %d consumed messages, %d produced messages\n", $numCons, $numProds);

if ($numRCons != $numCons) {
    printf(" [E] Mismatch between reverse / consumers - possible md5 clash? (R, C) = (%d, %d)\n", $numCons, $numRCons);
}
if ($numRProds != $numProds) {
    printf(" [E] Mismatch between reverse / producers - possible md5 clash? (R, C) = (%d, %d)\n", $numProds, $numRProds);
}

die;

// Compare file names and contents
if ($numCons == $numProds && count(array_intersect($cons, $prods)) == $numCons) {
    printf(" [1] File name comparison indicates all produced files were consumed\n");
} else {
    if ($notConsumed = array_diff($cons, $prods)) {
        printf(" [2] The following produced messages were not consumed:\n%s\n", word_wrap(implode(' ', $notConsumed)));
    } else if ($notProduced = array_diff($prods, $cons)) {
        printf(" [3] The following consumed messages were not produced:\n%s\n", word_wrap(implode(' ', $notProduced)));
    }
}

// Check file size and contents
printf("Attempt %d file checks: ", $numCons);
foreach ($cons as $c) {
    if (false !== ($p = array_search($c, $prods))) {
        $consFile = $rCons[$c];
        $prodFile = $rProds[$c];
        if (exec("diff {$consFile} {$prodFile}\n")) {
            printf("\n[E] File content mistmatch for md5 %s\n", $c);
        } else {
            printf(".");
        }
    }
}