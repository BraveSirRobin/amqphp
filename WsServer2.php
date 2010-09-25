<?php

/**
 * This server keeps all connections globally and only calls socket_select in the main loop
 */

class WsServer2
{
    const HEARTBEAT_MILLIS = 1500000; // Number of millis to wait between heartbeat ticks
    const SELECT_TIMEOUT_SECS = 0; // Select timeout seconds for reads and writes
    const SELECT_TIMEOUT_MILLIS = 300; // .. millis ..

    private $sock = null; // Server socket, used to listen for new connections

    private $clients = array(); // List of raw connected client sockets
    private $clientObjects = array(); // List of WsClient objects, each $n contain the raw socket in $this->clients[$n]

    /* Save the given client socket and create a wrapper for it */
    private function setNewClient($cSock, $loopId) {
        $n = array_push($this->clients, $cSock) - 1;
        $this->clientObjects[$n] = new WsClient($cSock, $loopId);
        return $n;
    }

    /* Look up client wrapper object for the given client socket */
    private function getClientForSock($cSock) {
        if (($k = array_search($cSock, $this->clients, true)) === false) {
            printf("[server] Error, socket not found\n");
            throw new Exception('Socket not found', 8976);
        }
        return $this->clientObjects[$k];
    }

    private function unsetClientBySock($sock) {
        // TODO: Locate and remove client & socket, collapse both sets of arrays
        // so that keys are contigous
    }

    private function unsetClientByClient(WsClient $c) {
        // TODO: Locate and remove client & socket, collapse both sets of arrays
        // so that keys are contigous
    }


    /**
     * Control the main loop heartbeat by setting and checking timeouts
     */
    private $hb = '';
    private function initHeartbeat() {
        $this->hb = bcadd((string) microtime(true), 
                          (string) ($this->getHeartbeatMillis() / 1000000),
                          6);
        //        printf("Init hearbeat to %s\n", $this->hb);
        //        $this->shouldHeartBeat(); // DELETE
    }

    private function shouldHeartBeat() {
        //        $this->HBDBG++;
        $diff = (float) bcsub((string) microtime(true), $this->hb, 6);
        /*if  ($this->HBDBG == 1500 || $this->HBDBG == 0) {
            printf("Check hearbeat, works out at %s\n", $diff);
            $this->HBDBG = 0;
            }*/
        return ($diff > 0);
    }




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
        $this->initHeartbeat();
        while (true) {
            pcntl_signal_dispatch();
            $all = array_merge($this->clients, array($this->sock));
            $read = $written = $except = $all;

            $selN = socket_select($read, $written, $except, $this->selToSecs, $this->selToMillis);
            if ($selN === false) {
                printf("[ERROR]: client read select failed:\n%s\n", socket_strerror(socket_last_error()));
            } else if ($selN > 0) {
                if (in_array($this->sock, $read)) {
                    // New client is connecting
                    $cSock = @socket_accept($this->sock);
                    if (! $cSock) {
                        printf("[ERROR] [%s] - socket is supposed to accept?!\n", $selN);
                    } else {
                        $this->setNewClient($cSock, $n);
                    }
                }
                foreach ($except as $exSock) {
                    printf("Socket exception?!\n");
                }
                foreach ($read as $rSock) {
                    if ($rSock === $this->sock) {
                        continue;
                    }
                    $client = $this->getClientForSock($rSock);
                    $client->onReadReady($n);
                }
                foreach ($written as $wSock) {
                    $client = $this->getClientForSock($wSock);
                    $client->onWriteReady($n);
                }
            }

            if ($this->shouldHeartBeat()) {
                printf(" [heartbeat] %d\n", $n);
                $this->initHeartbeat();
                $n = 0;
            }
            $n++;
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


    /**
     * Accessors for the select timeout & heartbeat parameters
     */
    private $selToSecs = self::SELECT_TIMEOUT_SECS;
    private $selToMillis = self::SELECT_TIMEOUT_MILLIS;
    private $hbMillis = self::HEARTBEAT_MILLIS;
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

    function setHeartbeatMillis($n) {
        if (! is_int($n)) {
            printf("Error: invalid number of millia: %s\n", $n);
            return;
        }
        $this->hbMillis = $n;
    }

    function getHeartbeatMillis() {
        return $this->hbMillis;
    }
}

// Simple blocking client.  Assume that socket_select does the business and reads &
// writes will never fail.
class WsClient
{
    const BUFF_LEN = 1024; // Buffer length for reads and writes
    const WRITE_LOOP_MAX = 5; // Number of 0 length writes to allow before abandoning ship

    private $sock = null;
    private $loopId = 0;
    private $prevLoop = false;
    private $writeBuff = '';
    private $wbLoopId = -1;
    private $handshakeWritten = false;

    function __construct($sock, $loopId) {
        $this->sock = $sock;
        printf("WsClient constructed in loop %d\n", $loopId);
    }

    // Invoked when the main loop socket_select indicates this one is ready to read
    function onReadReady($loopId) {
        $buff = $tmp = '';
        $br = $DBG = 0;
        while ($brNow = @socket_recv($this->sock, $tmp, self::BUFF_LEN, MSG_DONTWAIT)) {
            $buff .= $tmp;
            $br += $brNow;
        }
        if ($brNow === false) {
            $errNo = socket_last_error();
            if ($errNo != 11) {
                printf("[read %d] Recv returned false: %d\n", $loopId, socket_strerror($errNo));
            }
        }
        if (! $buff) {
            return;
        }
        printf("[read %d] read %d bytes from network\n", $loopId, $br);
        $this->wbLoopId = $loopId;
        $this->writeBuff = $buff;
    }

    // Invoked when the main loop socket_select indicates this one is ready to write
    function onWriteReady($loopId) {
        if (! $this->writeBuff) {
            return;
        }
        if (! $this->handshakeWritten) {
            $this->writeBuff = (string) new WebSocketHandshake($this->writeBuff);
            $this->handshakeWritten = true;
            printf("[write %d:%d] Prepare handshake\n", $loopId, $this->wbLoopId);
        }
        $bl = strlen($this->writeBuff);
        $nz = $bw = 0;
        while ($bw < $bl) {
            $tmp = socket_send($this->sock, substr($this->writeBuff, $bw, self::BUFF_LEN), self::BUFF_LEN, MSG_EOF);
            $bw += $tmp;
            if ($tmp === 0) {
                if ($nz++ > self::WRITE_LOOP_MAX) {
                    printf("[write %d:%d] Error: write loop hit empty write limit %d, only %d of %d bytes written\n", $loopId, $this->wbLoopId, self::WRITE_LOOP_MAX, $bw, $bl);
                    $this->writeBuff = '';
                    return;
                }
            }
        }
        $this->writeBuff = '';
        printf("[write %d:%d] %d bytes to network\n", $loopId, $this->wbLoopId, $bw);
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
$l = new WsServer2('localhost', 7654);
printf("[outer] Enter listen loop...\n");
$l->listen();