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
		if( $this->isCancelled() )
			throw new Exception( "Already cancelled" );
		
		$this->callback = NULL;
	}
	
	function isCancelled()
	{
		return is_null( $this->callback );
	}
	
}

class LooPHP_EventLoop_NullSource implements LooPHP_EventSource
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
			while( $this->_event_heap->valid() && $this->_event_queue->isEmpty() ) {
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
		$source_is_processing = TRUE;
		while( ! is_null( $time_until_next_event = $this->getTimeUntilNextEvent() ) || $source_is_processing ) {
			if( ! is_null( $time_until_next_event ) && $time_until_next_event <= 0 ) {
				$this->processEvents();
			} else if( $source_is_processing ) {
				$process_result = $this->_event_source->process( $this, $time_until_next_event );
				if( ! is_bool( $process_result ) ) throw new Exception( "process must return a boolean value" );
				if( ! $process_result )
					$source_is_processing = FALSE;
			}
		}
	}
	
	function getTimeUntilNextEvent()
	{
		if( ! $this->_event_queue->isEmpty() )
			return 0;
		
		//remove all cancelled events off the top
		while( $this->_event_heap->valid() && $this->_event_heap->top()->isCancelled() ) $this->_event_heap->next();
		
		return $this->_event_heap->valid()
			? max( 0, $this->_event_heap->top()->time - microtime( TRUE ) )
			: NULL;
	}
	
}

?>