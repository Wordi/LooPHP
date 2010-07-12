<?php

require( dirname( dirname( __FILE__ ) )."/LooPHP/Autoload.php" );
LooPHP_Autoloader::register();

$loop = new LooPHP_EventLoop();

$loop->addEvent( function() {
	print "Event 1\n";
}, 1 );

$loop->addEvent( function( $loop ) {
	print "Event 3\n";
	print "Adding Event 6\n";
	$loop->addEvent( function() {
		print "Event 6\n";
	}, 3 );
}, 3 );

$event_4 = $loop->addEvent( function() {
	print "Event 4\n";
}, 4 );

$loop->addEvent( function() use ( $event_4 ) {
	print "Event 2\n";
	print "Cancelling Event 4\n";
	$event_4->cancel();
}, 2 );

$loop->addEvent( function() {
	print "Event 5\n";
}, 5 );

print "Starting Run Loop:\n";
$loop->run();

?>