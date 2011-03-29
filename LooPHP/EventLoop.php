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
	
	function __construct( Closure $callback, $timeout = NULL )
	{
		$this->time = is_null( $timeout )
			? NULL
			: microtime( TRUE ) + $timeout;
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
			return FALSE;
		
		usleep( $timeout * 1000000 );
		return TRUE;
	}
	
}

class LooPHP_EventLoop
{
	
	private $_event_heap;
	private $_event_queue;
	private $_event_source;
	
	function __construct( LooPHP_EventSource $event_source = NULL )
	{
		$this->_event_heap = new LooPHP_EventLoop_EventHeap();
		$this->_event_queue = new SplQueue();
		$this->_event_source = $event_source !== NULL
			? $event_source
			: new LooPHP_EventLoop_NullSource();
	}
	
	function addEvent( Closure $callback, $timeout = NULL )
	{
		$event = new LooPHP_EventLoop_Event( $callback, $timeout );
		if( is_null( $timeout ) ) {
			$this->_event_queue->enqueue( $event );
		} else {
			$this->_event_heap->insert( $event );
		}
		return $event;
	}
		
	function processEvents()
	{
		do {
			while( ! $this->_event_queue->isEmpty() ) {
				$current_event = $this->_event_queue->dequeue();
				$callback = $current_event->callback;
				if( $callback !== NULL ) //check if the event was cancelled
					$callback( $this );
			}
			while( $this->_event_heap->valid() ) {
				$current_event = $this->_event_heap->top();
				if( $current_event->time > microtime( TRUE ) ) {
					break;
				}
				$this->_event_heap->next();
				$callback = $current_event->callback;
				if( $callback !== NULL ) //check if the event was cancelled
					$callback( $this );
			}
		} while( ! $this->_event_queue->isEmpty() );
	}
	
	function run()
	{
		while( 1 ) {
			$time_until_next_event = $this->getTimeUntilNextEvent();
			
			if( ! is_null( $time_until_next_event ) and $time_until_next_event <= 0 ) {
				$this->processEvents();
			} else {
				$process_result = $this->_event_source->process( $this, $time_until_next_event );
				if( ! is_bool( $process_result ) ) throw new Exception( "process must return a boolean value" );
				if( ! $process_result ) break;
			}
		}
	}
	
	function getTimeUntilNextEvent()
	{
		if( count( $this->_event_queue ) > 0 )
			return 0;
		
		//remove all cancelled events off the top
		while( $this->_event_heap->valid() && is_null( $this->_event_heap->top()->callback ) ) $this->_event_heap->next();
		
		return $this->_event_heap->valid()
			? max( 0, $this->_event_heap->top()->time - microtime( TRUE ) )
			: NULL;
	}
	
}

?>