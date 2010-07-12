LooPHP
=============

LooPHP is an event loop (http://en.wikipedia.org/wiki/Event_loop) for PHP. It provides an abstract base for programming PHP using an event driven style of programming. It can handle up to ~150,000 events/second. Due to PHP's  single threaded nature and lacks a unifying method of polling sockets/streams/etc, the implementation of the event source is left up to the user.

Few things to keep in mind:
	-Nothing should block, if you need to wait for something to happen, it should be scheduled using an event.
	-Events can be cancelled via $loophp_event->cancel()

Requirements:
	-PHP 5.3
		-Closures
		-Circular reference GC (http://php.net/manual/en/function.gc-enable.php)