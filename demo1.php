<?php

define('OUT_DIR', __DIR__ . '/scratch');

define('COMMS_SOCK', OUT_DIR . '/test-comms.sock');

runTest2(4);

//
// Script ends.
//

/**
 * Simple Forker, splits off $n processes with each one set to wait a
 * specific time, then waits for each to complete before completing itself
 */
function runTest1($n) {
    $fp = fopen(OUT_DIR . '/test1file.txt', 'a+') OR die("\nCouldn't create test file\n");
    fwrite($fp, "Running test 1 with parent ID " . posix_getpid() . "\n");
    $pGid = posix_getpgrp();
    msg("Run test 1, GID=%s,PID=%s,Pri=%s\n", array($pGid, posix_getpid(), pcntl_getpriority()));

    $k = array();
    $isParent = false;

    for ($i = 0; $i < $n; $i++) {
        $pid = pcntl_fork();
        if ($pid == -1) {
            die("[P] Couldn't fork");
        } else if ($pid) {
            // Parent thread
            $k[] = $pid;
            $isParent = true;
            msg("Child thread created: %s", array($pid));
        } else {
            // Child thread
            $isParent = false;
            msg("Thread Started");
            fwrite($fp, "Hi from child thread " . posix_getpid(). "\n");
            sleep(5 + $i);
            break;
        }
    }

    if ($isParent) {
        msg("Children created OK:\n%s", array(print_r($k, true)));
        while ($k) {
            $ended = pcntl_wait($status);
            unset($k[array_search($ended, $k)]);
            msg("Wait returns (status=%s)", array($status));
        }
        msg("isParent process ends");
        fclose($fp);
    } else {
        msg("NOT(isParent) process ends");
    }
}


/**
 * Initial stab at IPC using unix sockets.  Works reliably enough, up to a point:
 * (1) Don't use fsockopen for the writer threads, this leads to problems really
 * quickly.
 * (2) When called with very high number of child threads, seems to leave Zombies
 * at the end of the loop.
 */
function runTest2($n) {
    $pGid = posix_getpgrp();
    msg("Run test 2, GID=%s,PID=%s,Pri=%s\n", array($pGid, posix_getpid(), pcntl_getpriority()));
    define('IPC_SOCK', OUT_DIR . '/test2-ipc.sock');

    $k = array();
    $isParent = false;

    for ($i = 0; $i < $n; $i++) {
        $pid = pcntl_fork();
        if ($pid == -1) {
            echo("\n\nFATAL ERROR: Couldn't fork\n\n");
            die;
        } else if ($pid) {
            // Parent thread
            $k[] = $pid;
            $isParent = true;
            msg("Child thread created: %s", array($pid));
        } else {
            $isParent = false;
            $myPid = posix_getpid();
            msg("Thread %s Started", array($myPid));
            while (true) {
                $sSecs = (int) rand(1,4);
                $msg = sprintf("Child %s slept for %s seconds!\n", $myPid, $sSecs);
                sleep($sSecs);
                if (! ($cSock = socket_create(AF_UNIX, SOCK_STREAM, 0))) {
                    msg("Failed to create socket");
                    continue;
                } else if (! socket_connect($cSock, IPC_SOCK)) {
                    msg("Failed to connect socket");
                } else {
                    $bw = socket_write($cSock, $msg);
                    if ($bw === false) {
                        msg("Socket write failed, %s", array(socket_strerror(socket_last_error())));
                    } else {
                        msg("Wrote $bw bytes to socket");
                    }
                }
                socket_shutdown($cSock);
                socket_close($cSock);
            }
            break;
        }
    }

    if ($isParent) {
        msg("Children created OK:\n%s", array(print_r($k, true)));
        $sock = socket_create(AF_UNIX, SOCK_STREAM, 0) OR die("\n\nFailed to open comms socket\n\n");
        socket_bind($sock, IPC_SOCK);
        socket_listen($sock);
        msg("Comms socket bound");
        $ee = array();
        $j = 0;
        $sockReads = array($sock);
        while ($j < ($n * 5)) {
            $cs = socket_accept($sock);
            msg("Socket connection accepted in loop  %s", array($j));
            if ($cs === false) {
                msg("Socket error for select: %s", array(socket_strerror(socket_last_error())));
            } else if ($cs === 0) {
                msg("No event for select");
            } else {
                $sMsg = '';
                $sMsg = socket_read($cs, 1024);
                msg("Socket read message: %s", array($sMsg));
            }
            $j++;
            socket_close($cs);
        }
        msg("Socket functions complete, teardown children");
        foreach ($k as $pid) {
            posix_kill($pid, 9);
        }
        unlink(IPC_SOCK);
        msg("isParent process ends");
    }
}





function msg($msg, $args = array()) {
    array_unshift($args, posix_getpid());
    vprintf("[%s] $msg\n", $args);
}

