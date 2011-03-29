<?php

class LooPHP_EventSourceMulti extends LooPHP_EventSource
{
	
	private $_listen_socket;
	private $_listen_socket_address;
	private $_listen_socket_port;
	
	private $_socket_array = array();
	private $_socket_data = array();
	
	private $_client_data_array = array();
	
	public function __construct( array $event_source_closure_array )
	{
		if( ! $event_source_closure_array )
			throw new Exception( "\$event_source_closure_array must contain at least 1 item" );
		
		$this->_listen_socket = socket_create_listen(0);
		$this->_socket_array[(int)$this->_listen_socket] = $this->_listen_socket;
		socket_getsockname( $this->_listen_socket, $this->_listen_socket_address, $this->_listen_socket_port );
		
		foreach( $event_source_closure_array as $event_source_closure_info ) {
			list( $event_source_closure_writer, $event_source_closure_reader ) = $event_source_closure_info;
			$this->forkEventSourceClosure( $event_source_closure_writer, $event_source_closure_reader );
		}
	}
	
	private function forkEventSourceClosure( Closure $event_source_closure_writer, Closure $event_source_closure_reader )
	{
		do {
			$client_id = rand( 1, min(getrandmax(), 4294967295 /* UINT_MAX */ ) );
		} while( array_key_exists( $client_id, $this->_client_data_array ) );
		
		$pid = pcntl_fork();
		if( $pid == -1 ) {
			die('could not fork');
		} else if( ! $pid ) {
			$this->childLoop( $event_source_closure_writer, $client_id );
			exit(0);
		} else {
			$this->_client_data_array[$client_id] = $event_source_closure_reader;
		}
	}
	
	private function childLoop( Closure $event_source_closure, $client_id )
	{
		$socket = socket_create( AF_INET, SOCK_STREAM, SOL_TCP );
		socket_connect( $socket, $this->_listen_socket_address, $this->_listen_socket_port );
		socket_write( $socket, pack( "V", $client_id ), 4 ); //send client id
		
		while( ( $result = $event_source_closure() ) !== FALSE ) {
			if( is_null( $result ) )
				continue;
			
			if( ! is_string( $result ) )
				throw new Exception( "result isn't a string or null" );
			
			$result_size = strlen( $result );
			$header = pack( "V", $result_size ); //header is of size 4
			$write_status = @socket_write( $socket, $header.$result, 4 + $result_size );
			if( $write_status === FALSE )
				throw new Exception( "Link with server closed" );
		}
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
		if( $results === FALSE )
			throw new Exception( "stream_select failed" );
		
		if( $results > 0 ) {
			foreach( $read_resource_array as $read_resource ) {
				if( $this->_listen_socket === $read_resource ) {
					$client_resource = @socket_accept( $this->_listen_socket );
					socket_set_nonblock( $client_resource );
					$this->_socket_array[(int)$client_resource] = $client_resource;
					$this->_socket_data[(int)$client_resource] = array( "client_id" => NULL, "buffer" => "" );
				} else {
					$this->readClient( $event_loop, $read_resource );
				}
			}
			foreach( $exception_resource_array as $exception_resource ) {
				if( $this->_listen_socket === $read_resource ) {
					throw new Exception( "listen socket had exception" );
				} else {
					print "Socket had exception\n";
					unset( $this->_socket_array[(int)$exception_resource] );
					unset( $this->_socket_data[(int)$exception_resource] );
				}
			}
		}
		
		return TRUE;
	}
	
	private function readClient( LooPHP_EventLoop $event_loop, $read_resource )
	{
		while( $read_buffer = socket_read( $read_resource, 1024 ) )
			$this->_socket_data[(int)$read_resource]["buffer"] .= $read_buffer;		
		
		$buffer_size = strlen( $this->_socket_data[(int)$read_resource]["buffer"] );
		
		if( is_null( $this->_socket_data[(int)$read_resource]["client_id"] ) ) {
			if( $buffer_size < 4 ) return;
			
			$header = substr( $this->_socket_data[(int)$read_resource]["buffer"], 0, 4 );
			list( $client_id ) = array_values( unpack( "V", $header ) );
			$this->_socket_data[(int)$read_resource]["client_id"] = $client_id;
			$this->_socket_data[(int)$read_resource]["buffer"] = substr(
				$this->_socket_data[(int)$read_resource]["buffer"], 4
			);
			$buffer_size -= 4;
			
			if( ! array_key_exists( $client_id, $this->_client_data_array ) ) {
				unset( $this->_socket_array[(int)$read_resource] );
				unset( $this->_socket_data[(int)$read_resource] );
				socket_close($read_resource);
				//print "bad client: $client_id\n";
				return;
			}
			
			//print "good client: $client_id\n";
		}
		
		while( $buffer_size >= 4 ) {
			$header = substr( $this->_socket_data[(int)$read_resource]["buffer"], 0, 4 );
			list( $length ) = array_values( unpack( "V", $header ) );
			if( $buffer_size < 4 + $length )
				break;
			
			$data = substr( $this->_socket_data[(int)$read_resource]["buffer"], 4, $length );
			$this->_socket_data[(int)$read_resource]["buffer"] = substr(
				$this->_socket_data[(int)$read_resource]["buffer"],
				4 + $length
			);
			$buffer_size -= 4 + $length;
			
			$callback = $this->_client_data_array[$this->_socket_data[(int)$read_resource]["client_id"]];
			$callback( $data, $event_loop );
		}
	}
	
}

?>