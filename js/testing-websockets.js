var STORAGE = [null, null, null, null];
var MON = [null, null, null, null];

// Initialise the WS
function setupConnection(n) {
    if (STORAGE[n-1] === null) {
	console.log("Begin Connection Setup for socket " + n.toString());
	try {
	    STORAGE[n-1] = new WebSocket('ws://localhost:7654/', 'sample');
	    STORAGE[n-1].onopen = function (e) {
		console.log('[' + n.toString() + "] Default openHandler invoked", e);
	    };
	    STORAGE[n-1].onmessage = function (e) {
		console.log('[' + n.toString() + "-Sock-input] ", e.data);
	    };
	    STORAGE[n-1].onerror = function (e) {
		console.log('[' + n.toString() + "] Default openHandler invoked", e);
	    };
	    STORAGE[n-1].onclose = function (e) {
		console.log('[' + n.toString() + "] Default openHandler invoked", e);
	    };
	    MON[n-1] = setInterval('setStatus(' + n.toString() + ')', 1000);
	} catch (e) {
	    console.log('[' + n.toString() + "] Fault during setup: " + e.message);
	}
	console.log("...Done.");
    }
}

function teardownConnection(n) {
    try {
	clearTimeout(MON[n-1]);
	STORAGE[n-1].close();
	document.getElementById('status' + n.toString()).innerHTML = '[Socket Not Active]';
	STORAGE[n-1] = null;
    } catch (e) {
	console.log('[' + n.toString() + "] Fault during connection teardown:" + e.message);
	return;
    }
    console.log('[' + n.toString() + "]Teardown completed OK");
}

// Switch the status of the @id="status"
function setStatus(n) {
//    console.log("MON TICK");
    document.getElementById('status' + n.toString()).innerHTML = readyState(n);
}

// Invoked to send the @id="writeWindow" content to the websocket
function writeToSocket(n) {
    var buff = document.getElementById('writeWindow' + n.toString()).value;
    try {
	STORAGE[n-1].send(buff);
    } catch (e) {
	console.log('[' + n.toString() + "-Sock-write] ERROR: " + e.message);
	return;
    }
    console.log('[' + n.toString() + "-Sock-write] " + buff);
}

function readyState (n) {
    switch (STORAGE[n-1].readyState) {
    case WebSocket.CONNECTING:
	return 'CONNECTING'
    case WebSocket.OPEN:
	return 'OPEN';
    case WebSocket.CLOSING:
	return 'CLOSING'
    case WebSocket.CLOSED:
	return 'CLOSED';
    }
}
