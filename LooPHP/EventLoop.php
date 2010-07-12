<?php

class LooPHP_EventLoop_EventHeap extends SplMinHeap
{

	/* Event management */
		
	function compare( $event1, $event2 )
	{
		if( $event1->time < $event2->time )
			return 1;
		else if( $event1->time > $event2->time )
			return -1;
		else
			return 0;
	}
	
}

class LooPHP_EventLoop_Event
{
	
	/* Time is left public to decrease overhead, don't alter $time once an event is inserted into EventHeap */
	public $time;
	public $callback;
	
	function __construct( Closure $callback, $timeout = 0 )
	{
		$this->time = microtime( TRUE ) + $timeout;
		$this->callback = $callback;
	}
	
	function cancel()
	{
		if( $this->callback === NULL )
			throw new Exception( "Already cancelled" );
		
		$this->callback = NULL;
	}
	
}

class LooPHP_EventLoop_NullSource extends LooPHP_EventSource
{
	
	public function process( LooPHP_EventLoop $event_loop, $timeout )
	{
		if( is_null( $timeout ) )
			exit();
		
		usleep( $timeout * 1000000 );
	}
	
}

class LooPHP_EventLoop
{
	
	private $_event_heap;
	private $_event_source;
	
	function __construct( LooPHP_EventSource $event_source = NULL )
	{
		$this->_event_heap = new LooPHP_EventLoop_EventHeap();
		$this->_event_source = $event_source !== NULL
			? $event_source
			: new LooPHP_EventLoop_NullSource();
	}
	
	function addEvent( Closure $callback, $timeout = 0 )
	{	
		$event = new LooPHP_EventLoop_Event( $callback, $timeout );
		$this->_event_heap->insert( $event );
		return $event;
	}
		
	function processEvents()
	{
		while( $this->_event_heap->valid() && $this->_event_heap->top()->time <= microtime( TRUE ) ) {
			$current_event = $this->_event_heap->extract();
			$callback = $current_event->callback;
			if( $callback !== NULL ) //check if the event was cancelled
				$callback( $this );
		}
	}
	
	function run()
	{
		while( 1 ) {
			$time_until_next_event = $this->_event_heap->valid()
				? $this->_event_heap->top()->time - microtime( TRUE )
				: NULL;
			
			if( $time_until_next_event > 0 or is_null( $time_until_next_event ) ) {
				$this->_event_source->process( $this, $time_until_next_event );
			} else {
				$this->processEvents();
			}
		}
	}
	
}

?>