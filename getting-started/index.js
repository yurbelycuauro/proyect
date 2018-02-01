
function MessageHandler(server, filter, time, count) {
    var stream = 'message';
    var obj = {};
    obj.ws = undefined;
    obj.data = [];
    obj.push = function(nd) {
        obj.data.unshift(nd);
        if (obj.data.length > count) {obj.data.pop();};
    };
    obj.current = new Date().getTime;
    obj.process = function(callback) {
        var url = "ws://" + server + "/stream/" + stream + "/" + filter + "/" + time;
        var ws = new WebSocket(url);
        ws.onopen = function(event){console.log(event);};
        ws.onclose = function(event){console.log("Connection closed");};
        ws.onmessage = function(event){
            var array = JSON.parse(event.data);
            obj.push(array);
            obj.current = new Date().getTime;
            callback(obj.data, obj.current, time, count);
        };
        obj.ws = ws;
    };
    obj.close = function() {if(obj.ws) {obj.ws.close();}};
    return obj;
};