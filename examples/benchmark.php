<?php

require( dirname( dirname( __FILE__ ) )."/LooPHP/Autoload.php" );
LooPHP_Autoloader::register();

$loop = new LooPHP_EventLoop();

$add_event = function( $loop ) use ( &$add_event ) {
	static $i = 0;
	if( ++$i >= 100000 ) return;
	$loop->addEvent( $add_event );
};

$add_event( $loop );

$loop->run();

?>