var net = require("net");
var fs = require("fs");
var path = require("path");

var bundlePath = "./var/webpack/";
var bundleFileName = "app.js";

var currentArg;

function Handler() {
  this.queue = [];
  this.initialized = false;
}


Handler.prototype.handle = function(connection) {

  var callback = function () {
      connection.setEncoding('utf8');
  
      let data = [];
      let lastChar;
      connection.on('data', (chunk)=> {
          data.push(chunk);
          lastChar = chunk.substr(chunk.length - 1);
  
          if(lastChar === '\0') {
            data = data.join('');
            let result = eval(data.slice(0, -1));
            connection.write(result);
            console.log("Request processed");
            connection.end();
          }
      });
  };
  if (this.initialized) {
    callback();
  } else {
    this.queue.push(callback);
  }
};

Handler.prototype.initialize = function() {
  console.log("Processing " + this.queue.length + " pending requests");
  var callback = this.queue.pop();
  while (callback) {
    callback();
    callback = this.queue.pop();
  }

  this.initialized = true;
};

var handler = new Handler();

process.argv.forEach(val => {
  if (val[0] == "-") {
    currentArg = val.slice(1);
    return;
  }

  if (currentArg == "s") {
    bundleFileName = val;
  }
});

try {
  fs.mkdirSync(bundlePath);
} catch (e) {
  if (e.code != "EEXIST") throw e;
}

require(bundlePath + bundleFileName);
console.log("Loaded server bundle: " + bundlePath + bundleFileName);
handler.initialize();

fs.watchFile(bundlePath + bundleFileName, curr => {
  if (curr && curr.blocks && curr.blocks > 0) {
    if (handler.initialized) {
      console.log(
        "Reloading server bundle must be implemented by restarting the node process!"
      );
      return;
    }

    require(bundlePath + bundleFileName);
    console.log("Loaded server bundle: " + bundlePath + bundleFileName);
    handler.initialize();
  }
});

var unixServer = net.createServer(function(connection) {
  handler.handle(connection);
});

unixServer.listen("var/node.sock");

process.on("SIGINT", () => {
  unixServer.close();
  process.exit();
});
