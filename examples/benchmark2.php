<?php

require( dirname( dirname( __FILE__ ) )."/LooPHP/Autoload.php" );
LooPHP_Autoload::register();

$loop = new LooPHP_EventLoop();

for( $i = 0; $i < 100000; $i++ )
	$loop->addEvent( function(){;} );

$loop->run();

?>