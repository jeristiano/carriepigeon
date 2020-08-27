
var protocol="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJqdGkiOiJkZWZhdWx0XzVmNDcxYTlhMzM0M2QyLjc1MjE2OTY1IiwiaWF0IjoxNTk4NDk1Mzg2LCJuYmYiOjE1OTg0OTUzODYsImV4cCI6MTU5ODUwMjU4NiwidWlkIjozOSwidXNlcm5hbWUiOiJhZG1pbkBxcS5jb20iLCJqd3Rfc2NlbmUiOiJkZWZhdWx0In0.zBKxlDfP0dRJUlk4MG8Xw9nUCGsXXgrQLe_FzAGMYRE";
var ws = new WebSocket("ws://127.0.0.1:9502/ws",protocol);

ws.onopen = function(evt) {
    console.log("Connection open ...");
    ws.send("Hello WebSockets!");
};

ws.onmessage = function(evt) {
    console.log("Received Message: " + evt.data);
    ws.close();
};

ws.onclose = function(evt) {
    console.log("Connection closed.");
};