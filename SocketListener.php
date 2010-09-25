<?php

/**
 * @author  Robin Harvey
 * @date Sept 2010
 * A socket listener which runs in a non-blocking loop that can be interrupted
 * and closed gracefully.  This is achieved by using non-blocking socket IO that
 * sit inside loops that call pcntl_signal_dispatch().  
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

    private $client = null;

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
                // TODO: Fork at this point and offload the connection to a new process.
                printf("Socket connection accepted!\n");
                printf("%s\n", getSockOptionsDesc($cSock));
                $client = new SocketComms($cSock);
                $this->client = $client;
                if ($cBuff = $client->read()) {
                    $hs = new WebSocketHandshake($cBuff);
                    printf("Sent %d bytes Websocket response:\n%s\n", $client->write((string) $hs), (string) $hs);

                    // Increase the read timeout so that we're more responsive to client writes
                    $client->setSelectTimeoutSecs(1);
                    $m = 0;
                    while (true) {
                        pcntl_signal_dispatch();
                        // Naive loop - forever!  TODO: how to detect a closure?
                        if ($cBuff = $client->read()) {
                            $client->setSelectTimeoutSecs(0);
                            $client->write("You said $cBuff!!!!");
                            printf("Echo back to client: %s\n", $cBuff);
                            $client->setSelectTimeoutSecs(1);
                        }
                        if (++$m == 100) {
                            printf("   (Client read loop tick - 100)\n");
                        }
                    }
                }
                $client->close();
            }
            if ((++$n % 1500) == 0) {
                printf("Listener Looped %d\n", $n);
                $n = 0;
            }
            $this->client = null;
        }
    }


    // pcntl signal handler
    function interrupt($signal) {
        printf("Received interrupt signal %d, closing\n", $signal);
        if ($this->client) {
            $this->client->close();
        }
        socket_close($this->sock);
        exit(0);
    }
}

/**
 * Helper class to read / write from connected sockets
 */
class SocketComms
{
    const CLOSE_WAIT = 100; // Millis delay for graceful shutdown
    const SELECT_TIMEOUT_SECS = 0; // Select timeout seconds for reads and writes
    const SELECT_TIMEOUT_MILLIS = 100; // .. millis ..
    const BUFF_LEN = 1024; // Buffer length for reads and writes
    const IO_LOOP_MAX = 50; // TODO: This loop protection won't work for large payloads!

    private $cSock = null;
    private $gCloseWait = self::CLOSE_WAIT;
    private $buffLen = self::BUFF_LEN;
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
            printf("[ERROR]: client read select failed:\n%s\n", socket_strerror(socket_last_error()));
            return '';
        } else if ($n > 0) {
            printf("Begin client read (%d)\n", $this->getLastSocketError());
            $sane = 0;
            while ($buff = socket_read($this->cSock, $this->buffLen)) {
                $ret .= $buff;
                printf("  ..read %d bytes..\n", strlen($buff));
                pcntl_signal_dispatch();
                if ($sane++ > self::IO_LOOP_MAX) {
                    printf("Socket read not complete after max loop");
                }
            }
        } else {
            printf("No result from client read\n");
        }
        return $ret;
    }

    function write($msg) {
        if ($msg == '') {
            return;
        }
        $NULL = null;
        $bw = 0;
        $select = array($this->cSock);
        printf("Call select() for client write\n");
        $n = socket_select($NULL, $select, $NULL, $this->selToSecs, $this->selToMillis);
        if ($n === false) {
            printf("[ERROR]: client write select failed:\n%s\n", socket_strerror(socket_last_error()));
            return '';
        } else if ($n > 0) {
            $n = strlen($msg);
            printf("Begin client write (%d)\n", $this->getLastSocketError());
            $sane = 0;
            while ($bw < $n) {
                $dbg = socket_send($this->cSock, substr($msg, $bw, $this->buffLen), $this->buffLen, MSG_EOF);
                $bw += $dbg;
                printf("  ..written %d bytes..\n", $dbg);
                pcntl_signal_dispatch();
                if ($sane++ > self::IO_LOOP_MAX) {
                    printf("Socket write not complete after max loop");
                    break;
                }
            }
        } else {
            printf("No select result for client write\n");
        }
        return $bw;
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



    /**
     * Accessors for the select timeout parameters
     */
    function setSelectTimeoutSecs($n) {
        if (! is_int($n)) {
            printf("Error: invalid number of seconds: %s\n", $n);
            return;
        }
        $this->selToSecs = $n;
    }
    function getSelectTimeoutSecs() {
        return $this->selToSecs;
    }
    function setSelectTimeoutMillis($n) {
        if (! is_int($n)) {
            printf("Error: invalid number of millia: %s\n", $n);
            return;
        }
        $this->selToMillis = $n;
    }
    function getSelectTimeoutMillis() {
        return $this->selToMillis;
    }

}




// From here: http://webreflection.blogspot.com/2010/06/websocket-handshake-76-simplified.html
class WebSocketHandshake {
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

printf("[outer] Creating listener\n");
$l = new SocketListener('localhost', 7654);
printf("[outer] Enter listen loop...\n");
$l->listen();