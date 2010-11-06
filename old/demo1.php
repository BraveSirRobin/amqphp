<?php
require '/home/robin/Downloads/php-amqplib-read-only/hexdump.inc';

define('OUT_DIR', __DIR__ . '/scratch');

define('COMMS_SOCK', OUT_DIR . '/test-comms.sock');

//runTest1(4);
//runTest2(4);
//dumbSockListener();
//dumbInetSockListener();
demoTalkToRabbit();
//demoPacking();

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
        unlink(IPC_SOCK); // TODO: Close sock first?
        msg("isParent process ends");
    }
}


/**
 * V Simple function to listen to a unix socket forever and print out what it receives.
 * Talk to this dummy with Socat like this:
 * cat demo1.php | socat - scratch/dumb.sock
 */
function dumbSockListener() {
    printf("Starting dumb socket listener\n");
    define('IPC_SOCK', OUT_DIR . '/dumb.sock');
    define('READ_BYTES', 1024);
    if (file_exists(IPC_SOCK)) { // NB: must use file_exists - is_file doesn't work
        echo("Pre-deleted old socket\n");
        if (! unlink(IPC_SOCK)) {
            echo("\nFailed to remove old sumb socket!\n");
            die;
        }
    }

    $sock = socket_create(AF_UNIX, SOCK_STREAM, 0) OR die("\n\nFailed to open dumb socket\n\n");
    socket_bind($sock, IPC_SOCK);
    socket_listen($sock);
    $cleanup = function ($sigNo) use ($sock) {
        // TODO: Find somewhere to run me from!
        socket_close($sock);
        unlink(IPC_SOCK);
        echo("Run post cleanup from signal handler\n");
    };
    $j = 0;

    while (true) {
        printf("Wait for a connection\n");
        $cs = socket_accept($sock);
        vprintf("Socket connection accepted in loop  %s\n", array(++$j));
        if ($cs === false) {
            vprintf("Socket error for select: %s\n", array(socket_strerror(socket_last_error())));
        } else if ($cs === 0) {
            printf("No event for select\n");
        } else {
            $sMsg = $b2 = '';
            $c = 0;
            while ($buff = socket_read($cs, 1024)) {
                $sMsg .= $buff;
                $b2 = $buff;
                $c++;
            }
            $len = (($c - 1) * READ_BYTES) + strlen($b2);
            printf("Read %s chars from socket: \n", $len, substr($sMsg, 10));
        }
        socket_close($cs);
    }
    unlink(IPC_SOCK);
    socket_close($sock);
    echo("\nRemoved sock file at end of infinite loop\n");
}



/**
 * Clone of dumbSockListener() which listens on a network socket for
 * incoming Websocket Connections
 */
function dumbInetSockListener() {
    printf("Starting dumb Inet socket listener\n");
    define('READ_BYTES', 1024);

    $sock = socket_create(AF_INET, SOCK_STREAM, 0) OR die("\n\nFailed to open dumb Inet socket\n\n");
    socket_bind($sock, 'localhost', 7654);
    socket_listen($sock);
    $cleanup = function ($sigNo) use ($sock) {
        // TODO: Find somewhere to run me from!
        socket_close($sock);
        unlink(IPC_SOCK);
        echo("Run post cleanup from signal handler\n");
    };
    $j = 0;
    $handler1 = function ($sig) use ($sock) {
        printf(" [SIGINT] : close and exit");
        socket_close($sock);
        exit();
    };
    pcntl_signal(SIGINT, $handler1);

    while (true) {
        printf("Wait for a connection\n");
        $cs = socket_accept($sock);
        vprintf("Socket connection accepted in loop  %s\n\n", array(++$j));
        if ($cs === false) {
            vprintf("Socket error for select: %s\n", array(socket_strerror(socket_last_error())));
        } else if ($cs === 0) {
            printf("No event for select\n");
        } else {
            $sMsg = $b2 = '';
            $c = 0;
            $in = false;
            while ($buff = socket_read($cs, 1024)) {
                if (! $in) {
                    printf("Begin Handshake, input headers:\n%s\n\n", $buff);
                    $hs = new WebSocketHandshake($buff);
                    $resp = (string) $hs;
                    $bw = socket_write($cs, $resp, strlen($resp));
                    printf("Sent %d bytes of connection response:\n%s\n", $bw, $resp);
                    // Lifetime loop, V basic
                    $j = 0;
                    while ($j++ < 5) {
                        $read = array($cs);
                        if ($ssRet = socket_recv($sock, $buff, 8, MSG_WAITALL)) {
                            $msg = '';
                            while ($buff = socket_read($cs, 1024)) {
                                $msg .= $buff;
                            }
                            socket_write($cs, "Hi - I'm in PHP");
                            printf("Connect socket recieved data: %s (type %s)\n", $buff, gettype($buff));
                        } else {
                            printf("Select didn't return, error: %s\n", socket_strerror(socket_last_error()));
                        }
                    }
                }
            }
        }
        socket_close($cs);
    }
    unlink(IPC_SOCK);
    socket_close($sock);
    echo("\nRemoved sock file at end of infinite loop\n");
}

// As per http://www.whatwg.org/specs/web-socket-protocol/
function parseWebSocketHandshake($hs) {
    $hs = new WebSocketHandshake($hs);
    return (string) $hs;

    $bits = explode("\r\n", $hs);
    $binStr = array_pop($bits);
    $k1 = $k2 = '';

    foreach ($bits as $v) {
        if (stripos($v, 'sec-websocket-key1') === 0) {
            $k1 = substr($v, strpos($v, ':') + 2);
        }
        if (stripos($v, 'sec-websocket-key2') === 0) {
            $k2 = substr($v, strpos($v, ':') + 2);
        }
    }
    if (! $k1 || ! $k2) {
        printf("Error: failed to find websocket keys (%s, %s)\n", $k1, $k2);
        return false;
    }
    $n1 = preg_replace('/[^0-9]/', '', $k1);
    $n2 = preg_replace('/[^0-9]/', '', $k2);
    $giaN1 = _doStuffToObtainAnInt32($k1);
    $giaN2 = _doStuffToObtainAnInt32($k2);
    printf("(n1, n2), (giaN1, giaN2) = (%d, %d), (%d, %d)\n", $n1, $n2, $giaN1, $giaN2);
    $n1 = $giaN1;
    $n2 = $giaN2;
    $sp1 = substr_count($k1, ' ');
    $sp2 = substr_count($k2, ' ');
    if ($sp1 < 1 || $sp2 < 1) {
        printf("Error: incorrect number of spaces in keys, bailing\n");
        return false;
    }
    $rn1 = ($n1 / $sp1);
    $rn2 = ($n2 / $sp2);
    //    printf("Key data: (num, spaces, divided) (%s, %s):\n", $k1, $k2);
    //    printf("%d, %d, %d\n%d, %d, %d\n", $n1, $sp1, $rn1, $n2, $sp2, $rn2);
    //    printf("Binary string: %s\n", strlen($binStr));
    $fullResp = (string) $rn1 . (string) $rn2;
    $fullResp = pack('N', $rn1) . pack('N', $rn2) . $binStr;
    printf("  (RawResponse-Length=%d) : %s\n", strlen($fullResp), $fullResp);
    $clientResp =  md5($fullResp, true);
    //    printf("  (Response-Length=%d)\n", strlen($clientResp));
    //    printf("Full response: %s (%d)\nMD5 Response: %s\n", $fullResp, strlen($fullResp), $clientResp);
    return $clientResp;
}



// Get used to the pack / unpack functions
function demoPacking() {
    $i = 99;
    $p1 = pack('nn', $i, $i + 1); // format n = unsigned short
    printf("Packed int(%d):\n%s\n", $i, hexdump($p1, false, false, true));
    file_put_contents('deleteme.txt', $p1);
}


/**
 * Playground for routines that talk to a RabbitMQ server
 */
function demoTalkToRabbit() {
    $host = '127.0.0.1';
    $port = 5672;

    printf("Running test: basic RMQ\n");
    if (! ($rSock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP))) {
        printf("Failed to open socket!\n");
        return;
    } else if (! socket_connect($rSock, $host, $port)) {
        printf("Failed to connect to Rabbit Socket\n");
        return;
    }

    $m1 = "AMQP\x01\x01\x09\x01";
    $bw = 0;
    $contentLength = strlen($m1);
    printf("Ready to write protocol header:\n");
    while ($bw < $contentLength) {
        if (($tmp = socket_write($rSock, $m1, $contentLength)) === false) {
            printf("\nSocket write failed: %s\n", socket_strerror(socket_last_error()));
            return;
        }
        printf("Written %d bytes:\n%s\n", $tmp, hexdump($m1, false, false, true));
        $bw += $tmp;
    }
    printf("\nHeader sent, now listen for a response\n");

    $br = 0;
    $response = '';
    while ($buff = socket_read($rSock, 1024)) {
        $br += strlen($buff);
        $response .= $buff;
        printf("Read %d bytes\n", strlen($buff));
    }
    printf("Read is complete, read %d bytes:\n%s\n%s\n", $br, $response, hexdump($response, false, false, true));

    printf("\nResponse Breakdown\n");
    var_dump(unpack('n2', substr($response, 0, 4)));

    socket_shutdown($rSock);
    socket_close($rSock);
    printf("Test complete\n");
}



function msg($msg, $args = array()) {
    array_unshift($args, posix_getpid());
    vprintf("[%s] $msg\n", $args);
}


function _doStuffToObtainAnInt32($key) {
    return preg_match_all('#[0-9]#', $key, $number) && preg_match_all('# #', $key, $space) ?
        implode('', $number[0]) / count($space[0]) :
        ''
        ;
}






///





class WebSocketHandshake {

    /*! Easy way to handshake a WebSocket via draft-ietf-hybi-thewebsocketprotocol-00
     * @link    http://www.ietf.org/id/draft-ietf-hybi-thewebsocketprotocol-00.txt
     * @author  Andrea Giammarchi
     * @blog    webreflection.blogspot.com
     * @date    4th June 2010
     * @example
     *          // via function call ...
     *          $handshake = WebSocketHandshake($buffer);
     *          // ... or via class
     *          $handshake = (string)new WebSocketHandshake($buffer);
     *
     *          socket_write($socket, $handshake, strlen($handshake));
     */

    private $__value__;

    public function __construct($buffer) {
        $resource = $host = $origin = $key1 = $key2 = $protocol = $code = $handshake = null;
        preg_match('#GET (.*?) HTTP#', $buffer, $match) && $resource = $match[1];
        preg_match("#Host: (.*?)\r\n#", $buffer, $match) && $host = $match[1];
        preg_match("#Sec-WebSocket-Key1: (.*?)\r\n#", $buffer, $match) && $key1 = $match[1];
        preg_match("#Sec-WebSocket-Key2: (.*?)\r\n#", $buffer, $match) && $key2 = $match[1];
        preg_match("#Sec-WebSocket-Protocol: (.*?)\r\n#", $buffer, $match) && $protocol = $match[1];
        preg_match("#Origin: (.*?)\r\n#", $buffer, $match) && $origin = $match[1];
        preg_match("#\r\n(.*?)\$#", $buffer, $match) && $code = $match[1];
        $this->__value__ =
            "HTTP/1.1 101 WebSocket Protocol Handshake\r\n".
            "Upgrade: WebSocket\r\n".
            "Connection: Upgrade\r\n".
            "Sec-WebSocket-Origin: {$origin}\r\n".
            "Sec-WebSocket-Location: ws://{$host}{$resource}\r\n".
            ($protocol ? "Sec-WebSocket-Protocol: {$protocol}\r\n" : "").
            "\r\n".
            $this->_createHandshakeThingy($key1, $key2, $code)
            ;
    }

    public function __toString() {
        return $this->__value__;
    }
    
    private function _doStuffToObtainAnInt32($key) {
        return preg_match_all('#[0-9]#', $key, $number) && preg_match_all('# #', $key, $space) ?
            implode('', $number[0]) / count($space[0]) :
            ''
            ;
    }

    private function _createHandshakeThingy($key1, $key2, $code) {
        return md5(
                   pack('N', $this->_doStuffToObtainAnInt32($key1)).
                   pack('N', $this->_doStuffToObtainAnInt32($key2)).
                   $code,
                   true
                   );
    }
}