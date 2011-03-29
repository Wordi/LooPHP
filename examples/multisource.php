<?php

require( dirname( dirname( __FILE__ ) )."/LooPHP/Autoload.php" );
LooPHP_Autoloader::register();


$multi_event_source = new LooPHP_EventSourceMulti( array(
	array(
		function () {
			static $i = 0;
			usleep( 100000 );
			return (string)++$i."\n";
		}, //first function doesn't have access to the main thread
		function ( $input, $loophp ) {
			print $input;
		} //has access to main thread
	),
	array(
		function () {
			static $i = 0;
			usleep( 100000 );
			return (string)++$i."\n";
		}, //first function doesn't have access to the main thread
		function ( $input, $loophp ) {
			print $input;
		} //has access to main thread
	)
) );

$loop = new LooPHP_EventLoop( $multi_event_source );
$loop->run();

?>