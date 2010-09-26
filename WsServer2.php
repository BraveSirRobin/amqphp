<?php

/**
 * This server keeps all connections globally and only calls socket_select in the main loop
 */

class WsServer2
{
    const HEARTBEAT_MILLIS = 1500000; // Number of millis to wait between heartbeat ticks
    const HEARTBEAT_PRECISION = 4; // bcmath precision on hearbeat timeout calculations (use microtime with millis)
    const SELECT_TIMEOUT_SECS = 0; // Select timeout seconds for reads and writes
    const SELECT_TIMEOUT_MILLIS = 3000; // .. millis ..

    const LOG_INFO = 1;
    const LOG_WARN = 2;
    const LOG_ERROR = 4;
    const LOG_FATAL = 8;

    private static $LogLevel = 15; // All on

    private $sock = null; // Server socket, used to listen for new connections

    private $clients = array(); // List of raw connected client sockets
    private $clientObjects = array(); // List of WsClient objects, each $n contain the raw socket in $this->clients[$n]

    private function msg($fmt, $args) {
        vprintf("$fmt\n", $args);
    }

    protected function info() {
        if (self::$LogLevel & self::LOG_INFO) {
            $args = func_get_args();
            $fmt = array_shift($args);
            if (! $fmt) {
                return;
            }
            $this->msg("[INFO][SRV] $fmt", $args);
        }
    }

    protected function warn() {
        if (self::$LogLevel & self::LOG_WARN) {
            $args = func_get_args();
            $fmt = array_shift($args);
            if (! $fmt) {
                return;
            }
            $this->msg("[WARN][SRV] $fmt", $args);
        }
    }

    protected function error() {
        if (self::$LogLevel & self::LOG_ERROR) {
            $args = func_get_args();
            $fmt = array_shift($args);
            if (! $fmt) {
                return;
            }
            $this->msg("[ERROR][SRV] $fmt", $args);
        }
    }

    protected function fatal() {
        if (self::$LogLevel & self::LOG_FATAL) {
            $args = func_get_args();
            $fmt = array_shift($args);
            if (! $fmt) {
                return;
            }
            $this->msg("[FATAL][SRV] $fmt", $args);
        }
    }



    /* Save the given client socket and create a wrapper for it */
    private function setNewClient($cSock, $loopId) {
        $n = array_push($this->clients, $cSock) - 1;
        $this->clientObjects[$n] = new WsClient($cSock, $this, $loopId);
        return $n;
    }

    /* Look up client wrapper object for the given client socket */
    private function getClientForSock($cSock) {
        if (($k = array_search($cSock, $this->clients, true)) === false) {
            $this->error("socket not found");
            throw new Exception('Socket not found', 8976);
        }
        return $this->clientObjects[$k];
    }

    private function removeClientBySock($sock) {
        // TODO: Locate and remove client & socket, collapse both sets of arrays
        // so that keys are contigous  ?Needed?
    }

    /**
     * Remove the given client and associated socket ref and re-index the
     * remaining arrays to be contigous.
     */
    private function removeClientByClient(WsClient $c) {
        foreach ($this->clientObjects as $i => $co) {
            if ($co === $c) {
                $co->close();
                unset($this->clients[$i]);
                unset($this->clientObjects[$i]);
                $this->clients = array_merge($this->clients);
                $this->clientObjects = array_merge($this->clientObjects);
                return;
            }
        }
        throw new Exception('Failed to remove client', 8793);
    }



    /**
     * Control the main loop heartbeat by setting and checking timeouts
     */
    private $hb = '';
    private function initHeartbeat() {
        $this->hb = bcadd((string) microtime(true), 
                          (string) ($this->getHeartbeatMillis() / 1000000),
                          self::HEARTBEAT_PRECISION);
    }
    private function shouldHeartBeat() {
        $diff = (float) bcsub((string) microtime(true), $this->hb, self::HEARTBEAT_PRECISION);
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
        $LDEBUG = array();
        $LDEBUG['dbgNExc'] = $LDEBUG['dbgNRead'] = $LDEBUG['dbgNWrite'] = 0;
        
        while (true) {
            $n++;
            pcntl_signal_dispatch();
            $all = array_merge($this->clients, array($this->sock));
            $read = $except = $all;
            // Only select on write is there's something to be written
            $written = array();
            foreach ($this->clientObjects as $i => $co) {
                if ($co->hasWriteBuffer()) {
                    // Only select clients to write if there's something to be written, otherwise
                    // socket_select() will return immediately for lots of needless writes, destroying performance.
                    $written[] = $this->clients[$i];
                }
            }

            $selN = @socket_select($read, $written, $except, $this->selToSecs, $this->selToMillis);
            if ($selN === false) {
                $errNo = socket_last_error();
                if ($errNo ===  SOCKET_EINTR) {
                    pcntl_signal_dispatch(); // select returned false due to signal, handle it ASAP
                } else {
                    $this->error("client read select failed:\n%s", socket_strerror());
                }
            } else if ($selN > 0) {
                if (in_array($this->sock, $read)) {
                    // New client is connecting
                    $cSock = @socket_accept($this->sock);
                    if (! $cSock) {
                        $this->error(" [%s] - socket is supposed to accept?!", $selN);
                    } else {
                        $this->setNewClient($cSock, $n);
                    }
                }
                foreach ($except as $exSock) {
                    if ($LDEBUG) {
                        $LDEBUG['dbgNExc']++;
                    }
                    $this->error("socket exception (?!)");
                }
                foreach ($read as $rSock) {
                    if ($rSock === $this->sock) {
                        continue;
                    }
                    $client = $this->getClientForSock($rSock);
                    $nr = $client->onReadReady($n);
                    if ($nr === 0) {
                        // Client has disconnected, remove from list of watched connections.
                        $this->removeClientByClient($client);
                    }
                    if ($LDEBUG) {
                        $LDEBUG['dbgNRead']++;
                    }
                }
                foreach ($written as $wSock) {
                    $client = $this->getClientForSock($wSock);
                    $client->onWriteReady($n);
                    if ($LDEBUG) {
                        $LDEBUG['dbgNWrite']++;
                    }
                }
            }

            if (($n % 10) == 0 && $this->shouldHeartBeat()) {
                if ($LDEBUG) {
                    $this->info("[heartbeat] %s Debug: (r,w,e) = (%d,%d,%d)", $n, $LDEBUG['dbgNRead'], $LDEBUG['dbgNWrite'], $LDEBUG['dbgNExc']);
                } else {
                    $this->info("[heartbeat] %d", $n);
                }
                $this->initHeartbeat();
                if ($LDEBUG) {
                    $LDEBUG['dbgNExc'] = $LDEBUG['dbgNRead'] = $LDEBUG['dbgNWrite'] = 0;
                }
                $n = 0;
            }
            $this->client = null;
        }
    }


    // pcntl signal handler
    function interrupt($signal) {
        $this->warn("received interrupt signal %d, closing", $signal);
        foreach ($this->clientObjects as $co) {
            $this->removeClientByClient($co);
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
            $this->error("invalid number of seconds: %s", $n);
            return;
        }
        $this->selToSecs = $n;
    }

    function getSelectTimeoutSecs() {
        return $this->selToSecs;
    }

    function setSelectTimeoutMillis($n) {
        if (! is_int($n)) {
            $this->error("invalid number of millis: %s", $n);
            return;
        }
        $this->selToMillis = $n;
    }
    function getSelectTimeoutMillis() {
        return $this->selToMillis;
    }

    function setHeartbeatMillis($n) {
        if (! is_int($n)) {
            $this->error("invalid number of millis: %s", $n);
            return;
        }
        $this->hbMillis = $n;
    }

    function getHeartbeatMillis() {
        return $this->hbMillis;
    }
}

/**
 * This class is designed to be over-ridden to implement per-client statuful handling.
 * Note that this would have to change for a different version of the protocol, particularly
 * is prototcol framing is introduced (not in Chromium @T.O.W.)
 */
class WsClient
{
    const CLOSE_WAIT = 100; // Millis delay for graceful shutdown
    const BUFF_LEN = 1024; // Buffer length for reads and writes
    const WRITE_LOOP_MAX = 5; // Number of 0 length writes to allow before abandoning ship

    const LOG_INFO = 1;
    const LOG_WARN = 2;
    const LOG_ERROR = 4;
    const LOG_FATAL = 8;

    private static $LogLevel = 15; // All on

    private $sock;
    private $server;
    private $loopId = 0;
    private $prevLoop = false;
    private $writeBuff = '';
    private $wbLoopId = -1;
    private $handshakeWritten = false;
    private $hs = null;

    final function __construct($sock, WsServer2 $server, $loopId) {
        $this->sock = $sock;
        $this->server = $server;
        $this->onConnect();
        $this->info("WsClient constructed in loop %d", $loopId);
    }

    private function msg($fmt, $args) {
        vprintf("$fmt\n", $args);
    }

    protected function info() {
        if (self::$LogLevel & self::LOG_INFO) {
            $args = func_get_args();
            $fmt = array_shift($args);
            if (! $fmt) {
                return;
            }
            $this->msg("[INFO][CLI] $fmt", $args);
        }
    }

    protected function warn() {
        if (self::$LogLevel & self::LOG_WARN) {
            $args = func_get_args();
            $fmt = array_shift($args);
            if (! $fmt) {
                return;
            }
            $this->msg("[WARN][CLI] $fmt", $args);
        }
    }

    protected function error() {
        if (self::$LogLevel & self::LOG_ERROR) {
            $args = func_get_args();
            $fmt = array_shift($args);
            if (! $fmt) {
                return;
            }
            $this->msg("[ERROR][CLI] $fmt", $args);
        }
    }

    protected function fatal() {
        if (self::$LogLevel & self::LOG_FATAL) {
            $args = func_get_args();
            $fmt = array_shift($args);
            if (! $fmt) {
                return;
            }
            $this->msg("[FATAL][CLI] $fmt", $args);
        }
    }


    /**
     * Low level socket read / write callbacks.  These are invoked from the main loop
     * only when a call to socket_select() has indicated that this socket is ready to
     * read / write.
     */
    final function onReadReady($loopId) {
        $buff = $tmp = '';
        $br = $expectedBytes = 0;
        while ($brNow = @socket_recv($this->sock, $tmp, self::BUFF_LEN, MSG_DONTWAIT)) {
            $buff .= $tmp;
            $br += $brNow;
        }
        if ($brNow === false) {
            $errNo = socket_last_error();
            if ($errNo != SOCKET_EAGAIN) {
                $this->error("[read %d] recv returned false: %d", $loopId, socket_strerror($errNo));
            } else {
                $errNo = 0;
            }
        }
        if (! $buff) {
            // This probably means that the client has disconnected - let the caller decide.
            // ?? Could this lead to undesired disconnects following temporary network disturbance ??
            return 0;
        }
        $this->info("[read %d] read %d bytes from network", $loopId, $br);
        $this->wbLoopId = $loopId;
        if (! $this->handshakeWritten) {
            $this->hs = new WebSocketHandshake($buff);
            $this->writeBuff = (string) $this->hs;
            $this->info("[read %d:%d] Prepare handshake", $loopId, $this->wbLoopId);
        } else {
            $this->onRead(substr($buff, 1, $br - 2));
            $this->writeBuff = $buff; // Raise event
        }
        return $br;
    }

    final function onWriteReady($loopId) {
        if (! $this->writeBuff) {
            return;
        }
        $bl = strlen($this->writeBuff);
        $nz = $bw = 0;
        while ($bw < $bl) {
            $tmp = socket_send($this->sock, substr($this->writeBuff, $bw, self::BUFF_LEN), self::BUFF_LEN, MSG_EOF);
            if ($tmp === false) {
                // Some kind of write error, exit early
                $errNo = socket_last_error();
                if (! $this->handshakeWritten) {
                    $this->handshakeWritten = true;
                    $this->onHandshake($this->hs, $errNo);
                    $this->hs = null;
                } else {
                    $this->onWrite($bw, $errNo);
                }
                return;
            } else if ($tmp === 0) {
                if ($nz++ > self::WRITE_LOOP_MAX) {
                    $this->error("[write %d:%d] Error: write loop hit empty write limit %d, only %d of %d bytes written", $loopId, $this->wbLoopId, self::WRITE_LOOP_MAX, $bw, $bl);
                    if (! $this->handshakeWritten) {
                        $this->handshakeWritten = true;
                        $this->onHandshake($this->hs, $errNo);
                        $this->hs = null;
                    } else {
                        $this->onWrite($bw);
                    }
                    $this->writeBuff = '';
                    return;
                }
            }
            $bw += $tmp;
        } // End write loop
        if (! $this->handshakeWritten) {
            $this->handshakeWritten = true;
            $this->onHandshake($this->hs);
            $this->hs = null;
        } else {
            $this->onWrite($bw);
        }
        $this->writeBuff = '';
        $this->info("[write %d:%d] %d bytes to network", $loopId, $this->wbLoopId, $bw);
    }

    /** Performs a graceful shutdown of the socket */
    final function close() {
        socket_shutdown($this->sock, 1); // Client connection can still read
        usleep(self::CLOSE_WAIT);
        @socket_shutdown($this->sock, 0); // Full disconnect
        $errNo = socket_last_error();
        if ($errNo) {
            if ($errNo != SOCKET_ENOTCONN) {
                $this->error("[client] socket error during socket close: %s", socket_strerror($errNo));
            } else {
                $errNo = 0;
            }
        }
        socket_close($this->sock);
        $this->onClose($errNo);
    }


    function hasWriteBuffer() {
        return ($this->writeBuff !== '');
    }

    function write($buff) {
        $this->writeBuff .= (string) $buff;
    }

    function getWriteBuffer() {
        return $this->writeBuff;
    }

    function clearWriteBuffer() {
        $this->writeBuff = '';
    }

    function getServer() {
        return $this->server;
    }


    /** High level socket connection callback, before handshake */
    function onConnect() { }

    /**
     * High level WS handshake complete callback, i.e. written response to socket
     * @param   integer    $errNo   If a socket error occured during the write this is set 
     *                              the return value of socket_last_error()
     */
    function onHandshake(WebSocketHandshake $hs, $errNo = 0) { }

    /**
     * High level read callback, Called immediately after the socket has received a message
     * @param   string     $buff    The raw content received from the client
     * @param   integer    $errNo   If a socket error occured during the read this is set 
     *                              the return value of socket_last_error() (Note SOCKET_EAGAIN is
     *                              NOT reported here)
     */
    function onRead($buff, $errNo = 0) { }

    /**
     * High level write callback, called after a write has occurred but before the
     * write buffer has been deleted.
     * @param   integer    $bw      Bytes written
     * @param   integer    $errNo   If a socket error occured during the write this is set 
     *                              the return value of socket_last_error()
     */
    function onWrite($bw, $errNo = 0) { }

    /**
     * High level client disconnected callback, called after the socket is fully closed 
     * @param   integer    $errNo   If a socket error occured during the close this is set 
     *                              the return value of socket_last_error() (Note SOCKET_ENOTCONN is
     *                              NOT reported here)
     */
    function onClose($errNo = 0) {
        $this->info("Bye-bye client ;-)");
    }

}




// From here: http://webreflection.blogspot.com/2010/06/websocket-handshake-76-simplified.html
class WebSocketHandshake {
    private $clientHs;
    private $__value__;

    public function __construct($buffer) {
        $this->clientHs = $buffer;
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

    public function getClientHandshake() {
        return $this->clientHs;
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

printf("[outer] Creating listener\n");
$l = new WsServer2('localhost', 7654);
printf("[outer] Enter listen loop...\n");
$l->listen();