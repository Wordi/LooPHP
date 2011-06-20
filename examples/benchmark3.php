<?php

require( dirname( dirname( __FILE__ ) )."/LooPHP/Autoload.php" );
LooPHP_Autoload::register();

$loop = new LooPHP_EventLoop();

$target_time = microtime( TRUE ) + 0;

for( $i = 0; $i < 100000; $i++ )
	$loop->addEvent( function(){;}, $target_time - microtime( TRUE ) );

$loop->run();

?>