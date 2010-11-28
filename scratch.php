<?php


function writeOut($level, $args) {
    $s = array_shift($args);
    vprintf("[$level] {$s}\n", $args);
}
function info() { writeOut('info', func_get_args()); }
function error() { writeOut('error', func_get_args()); }


$host = 'localhost';
$port = '6969';

if (isset($argv[1]) && strpos($argv[1], 'server') !== false) {
    info("Running as server.");


    if (! ($sock = socket_create(AF_INET, SOCK_STREAM, 0))) {
        throw new Exception('Failed to create socket', 9686);
    } else if (! socket_set_option($sock, SOL_SOCKET, SO_REUSEADDR, 1)) {
        throw new Exception("Failed to reuse socket address", 9662);
    } else if (! socket_bind($sock, $host, $port)) {
        socket_close($sock);
        throw new Exception('Failed to bind socket', 8624);
    } else if (! socket_listen($sock)) {
        socket_close($sock);
        throw new Exception('Failed to listen on socket', 6854);
    } else if (! socket_set_nonblock($sock)) {
        socket_close($sock);
        throw new Exception('Failed to set socket non-blocking', 5492);
    }
    info("Connected to $host, $port, loop waiting for a connection");

    while (true) {
        $read = array($sock);
        $written = $except = null;
        $selN = @socket_select($read, $written, $except, 3, 0);
        if ($selN === false) {
            $errNo = socket_last_error();
            if ($errNo ===  SOCKET_EINTR) {
                error("Server read select failed due to signal (?):\n%s", socket_strerror());
            } else {
                error("Server read select failed:\n%s", socket_strerror());
            }
            socket_close($sock);
            die();
        } else if (in_array($sock, $read)) {
            $cSock = @socket_accept($sock);
            if (! $cSock) {
                error(" [%s] - Server socket is supposed to accept?!", $selN);
                socket_close($sock);
                die();
            }
            info("Got a connection, break to next loop!");
            break;
        }
        info("still waiting..");
    }


    info("\nGot a single client, now wait 3 seconds and read till EOF\n");

    while (true) {
        $read = array($cSock);
        $written = $except = null;
        $selN = @socket_select($read, $written, $except, 3, 0);
        if ($selN === false) {
            $errNo = socket_last_error();
            if ($errNo ===  SOCKET_EINTR) {
                error("Client read select failed due to signal (?):\n%s", socket_strerror());
            } else {
                error("Client read select failed:\n%s", socket_strerror());
            }
            socket_close($sock);
            die();
        } else if (in_array($cSock, $read)) {
            $buff = '';
            while ($tmp = socket_read($cSock, 1024)) {
                $buff .= $tmp;
            }
            info("Mission Complete, buffer that was read is:\n%s\n", $buff);
         }
        info("still waiting..");
    }

    socket_shutdown($cSock, 1); // Client connection can still read
    usleep(100);
    @socket_shutdown($cSock, 0); // Full disconnect
    $errNo = socket_last_error();
    if ($errNo) {
        if ($errNo != SOCKET_ENOTCONN) {
            error("[client] socket error during socket close: %s", socket_strerror($errNo));
        }
    }
    socket_close($cSock);
    socket_close($sock);



} else {
    info("Running as client");

    if (! ($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP))) {
        throw new \Exception("Failed to create inet socket", 7895);
    } else if (! socket_connect($sock, $host, $port)) {
        throw new \Exception("Failed to connect inet socket {$sock}, {$host}, {$port}", 7564);
    }

    $guff = "Hi, I'm a fairly un-spectacular client...\n";

    $bw = 0;
    $contentLength = strlen($guff);
    while ($bw < $contentLength) {
        if (($tmp = socket_write($sock, $guff, $contentLength)) === false) {
            throw new \Exception(sprintf("\nSocket write failed: %s\n",
                                         socket_strerror(socket_last_error())), 7854);
        }
        $bw += $tmp;
    }
    info("Client is complete, %d bytes written", $bw);
}