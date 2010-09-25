var STORAGE = null;
var MON = null;

// Initialise the WS
function setupConnection() {
    if (STORAGE === null) {
	console.log("Begin Connection Setup....");
	try {
	    STORAGE = new WebSocket('ws://localhost:7654/', 'sample');
	    STORAGE.onopen = function () {
		console.log("Default openHandler invoked", arguments);
	    };
	    STORAGE.onmessage = function () {
		console.log("Default openHandler invoked", arguments);
	    };
	    STORAGE.onerror = function () {
		console.log("Default openHandler invoked", arguments);
	    };
	    STORAGE.onclose = function () {
		console.log("Default openHandler invoked", arguments);
	    };
	    MON = setTimeout('setStatus()', 1000);
	} catch (e) {
	    console.log("Fault during setup: " + e.message);
	}
	console.log("...Done.");
    }
}

function teardownConnection() {
    try {
	clearTimeout(MON);
	STORAGE.close();
	document.getElementById('status').innerHTML = '[Socket Not Active]';
	STORAGE = null;
    } catch (e) {
	console.log("Fault during connection teardown:" + e.message);
	return;
    }
    console.log("Teardown completed OK");
}

// Switch the status of the @id="status"
function setStatus() {
    console.log("MON TICK");
    document.getElementById('status').innerHTML = readyState();
}

// Invoked to send the @id="writeWindow" content to the websocket
function writeToSocket() {
    var buff = document.getElementById('writeWindow').value;
    try {
	STORAGE.send(buff);
    } catch (e) {
	console.log("[Sock>] ERROR: " + e.message);
	return;
    }
    console.log("[Sock>] " + buff);
}

function readyState () {
    switch (STORAGE.readyState) {
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
