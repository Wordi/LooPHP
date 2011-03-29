<?php

abstract class LooPHP_EventSource
{
	
	//This function can do *whatever* it wants but it should try to return close to the $timeout time
	//Timeout is in seconds, but it can be NULL. If NULL there is no timeout (no events pending).
	//must return a boolean, TRUE -> keep going, FALSE -> stop
	abstract public function process( LooPHP_EventLoop $event_loop, $timeout );
	
}

?>