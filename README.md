##PHP WebSockets

This is just another WebSocket server for PHP

###How to use it?

Create a PHP script and include the **init.php** file

Create a server instance like this `$server = new Server($ip, $port);`

Load your component `$server->loadComponent('MyComponent');`. Ideally you should not modify any of the server's files. You should work entirely in your component.

Call the start method of your server object `$server->start();`. You can use the **server.php** file as an example startup file.

At this point the server will enter the main loop and will start listening for connections.

When a connection is accepted your component will receive a call to its ***onConnect()*** method with information about the client id. You can store this id if you would like to send messages to it later.

Components reside in the components directory. The filename and the class in it must match. For example, the class **MyComponent** will be available in the **MyComponent.php** file. Every component must specify a protocol. Only one component can be assigned to server a single protocol.

Every component must extend the Component class `class MyComponent extends Component {...`

Every component must implement the ***onMessage()*** method. Below is a list of all the special methods that you can define in your component:

`Component::onLoad();` - Called when the component is loaded

`Component::onStart($ip, $port);` - Called when the server is started and just before it goes into the main loop. Holds information about the IP and the port on which the server is listening

`Component::parseCmd($cmd);` - Called when a command is sent to the server. This is a command that comes from the STDIN stream. You can use this to add runtime control over the server. For example you can implement a broadcast command for a chat which will send a message to all connected clients.

`Component::onConnect($client_id);` - Called when a client is connecting to your server. This method is called only on the component which handles the protocol specified by the client that is connecting.

`Component::onDisconnect($client_id);` - Same as onConnect() but fired when a client is disconnecting.

`Component::onStop();` - Called when the server is exiting the main loop (i.e. stopping).

Check out the **WebChat** component for a simple example of a component.