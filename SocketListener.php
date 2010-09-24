<?php

/**
 * @author  Robin Harvey
 * @date Sept 2010
 * A socket listener which runs in a non-blocking loop that can be interrupted
 * and closed gracefully.
 */



/**
 * Helper, mostly for debugging
 */
function getSockOptionsDesc($sock) {
    $opts = array('SO_DEBUG', 'SO_REUSEADDR', 'SO_KEEPALIVE', 'SO_DONTROUTE', 'SO_LINGER',
                  'SO_BROADCAST', 'SO_OOBINLINE', 'SO_SNDBUF', 'SO_RCVBUF', 'SO_SNDLOWAT',
                  'SO_RCVLOWAT', 'SO_SNDTIMEO', 'SO_RCVTIMEO', 'SO_TYPE', 'SO_ERROR');
    $buff = "";
    $c = count($opts);
    foreach ($opts as $i => $opt) {
        $buff .= sprintf('%5s => %d, ', $opt, constant($opt));
        if ($i && $i < ($c - 1) && ($i % 4) == 0) {
            $buff .= "\n";
        }
    }
    return substr($buff, 0, -2);
}

class SocketListener
{
    private $sock = null;

    // localhost, 7654
    /**
     * Create the socket, bind to $host[:$port], listen, set non-blocking
     * and install a SIGINT handler
     */
    function __construct($host, $port) {
        if (! ($this->sock = socket_create(AF_INET, SOCK_STREAM, 0))) {
            throw new Exception('Failed to create socket', 9686);
        } else if (! socket_set_option($this->sock, SOL_SOCKET, SO_REUSEADDR, 1)) {
            throw new Exception("Failed to reuse socket address", 9662);
        } else if (! socket_bind($this->sock, $host, $port)) {
            socket_close($this->sock);
            throw new Exception('Failed to bind socket', 8624);
        } else if (! socket_listen($this->sock)) {
            socket_close($this->sock);
            throw new Exception('Failed to listen on socket', 6854);
        } else if (! socket_set_nonblock($this->sock)) {
            socket_close($this->sock);
            throw new Exception('Failed to set socket non-blocking', 5492);
        } else if (! pcntl_signal(SIGINT, array($this, 'interrupt'))) {
            throw new Exception('Failed to attach signal handler', 6217);
        }
    }


    /**
     * Socket listen loop
     */
    function listen() {
        $n = 0;
        while (true) {
            pcntl_signal_dispatch();
            $cSock = @socket_accept($this->sock);
            if (! $cSock) {
                usleep(500);
            } else {
                printf("Socket connection accepted!\n");
                printf("%s\n", getSockOptionsDesc($cSock));
                $client = new SocketComms($cSock);
                printf("Socket buffer contents:\n%s\n", $client->read());
                $client->close();
            }
            if ((++$n % 1500) == 0) {
                printf("Listener Looped %d\n", $n);
                $n = 0;
            }
        }
    }


    // pcntl signal handler
    function interrupt($signal) {
        printf("Received interrupt signal %d, closing\n", $signal);
        socket_close($this->sock);
        exit(0);
    }
}

/**
 * Helper class to read / write from connected sockets
 */
class SocketComms
{
    const CLOSE_WAIT = 100;
    const SELECT_TIMEOUT_SECS = 0;
    const SELECT_TIMEOUT_MILLIS = 100;
    const READ_LEN = 1024;

    private $cSock = null;
    private $gCloseWait = self::CLOSE_WAIT;
    private $readLen = self::READ_LEN;
    private $selToSecs = self::SELECT_TIMEOUT_SECS;
    private $selToMillis = self::SELECT_TIMEOUT_MILLIS;

    function __construct($cSock) {
        $this->cSock = $cSock;
        socket_set_nonblock($this->cSock);
    }

    function read() {
        $buff = $ret = '';

        $NULL = null;
        $select = array($this->cSock);
        printf("Call select() for client read\n");
        $n = socket_select($select, $NULL, $NULL, $this->selToSecs, $this->selToMillis);
        if ($n === false) {
            printf("[ERROR]: select failed:\n%s\n", socket_strerror(socket_last_error()));
            return '';
        } else if ($n > 0) {
            printf("Begin client read (%d)\n", $this->getLastSocketError());
            while ($buff = socket_read($this->cSock, $this->readLen)) {
                $ret .= $buff;
                printf("  ..read %d bytes..\n", strlen($buff));
                pcntl_signal_dispatch();
            }
        }
        /*
        while ($buff = socket_read($this->cSock, $this->readLen)) {
            $ret .= $buff;
            printf("  ..read %d bytes..\n", strlen($buff));
        }
        */
        return $ret;
    }

    function close() {
        socket_shutdown($this->cSock, 1); // Client connection can still read
        usleep($this->gCloseWait);
        socket_shutdown($this->cSock, 0); // Full disconnect
        socket_close($this->cSock);
    }

    function getLastSocketError() {
        return socket_last_error($this->cSock);
    }
}

printf("[outer] Creating listener\n");
$l = new SocketListener('localhost', 7654);
printf("[outer] Enter listen loop...\n");
$l->listen();