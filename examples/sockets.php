<?php

require( dirname( dirname( __FILE__ ) )."/LooPHP/Autoload.php" );
LooPHP_Autoloader::register();

class SocketSourceExample implements LooPHP_EventSource
{
	
	private $_listen_socket;
	private $_socket_array = array();
	
	function __construct( $port )
	{
		$this->_listen_socket = socket_create_listen( $port );
		if( ! $this->_listen_socket ) throw new Exception( "failed to bind to port" );
		$this->_socket_array[(int)$this->_listen_socket] = $this->_listen_socket;
	}
	
	public function process( LooPHP_EventLoop $event_loop, $timeout )
	{
		$read_resource_array = $this->_socket_array;
		$write_resource_array = NULL;
		$exception_resource_array = $this->_socket_array;
		$results = socket_select(
			$read_resource_array,
			$write_resource_array,
			$exception_resource_array,
			is_null( $timeout ) ? NULL : floor( $timeout ),
			is_null( $timeout ) ? NULL : fmod( $timeout, 1 )*1000000
		);
		if( $results === FALSE ) {
			throw new Exception( "stream_select failed" );
		} else if( $results > 0 ) {
			foreach( $read_resource_array as $read_resource ) {
				if( $this->_listen_socket === $read_resource ) {
					$client_resource = @socket_accept( $this->_listen_socket );
					$this->_socket_array[(int)$client_resource] = $client_resource;
				} else {
					//send http responce in 5 second (just to demo events)
					$event_loop->addEvent( function() use ( $read_resource ) {
						$send_data =
							"HTTP/1.0 200 OK\n".
							"Content-Type: text/html\n".
							"Server: LooPHP"."\r\n\r\n".
							"<body>Hello World</body>";
						socket_write( $read_resource, $send_data );
						socket_close( $read_resource );
					}, 5 );
					unset( $this->_socket_array[(int)$read_resource] );
				}
			}
			foreach( $exception_resource_array as $exception_resource ) {
				print "Socket had exception\n";
				unset( $this->_socket_array[(int)$exception_resource] );
			}
		}
		return TRUE;
	}
	
}

$loop = new LooPHP_EventLoop( new SocketSourceExample( 8118 ) );

$loop->run();

?>