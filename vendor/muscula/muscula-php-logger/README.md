# Muscula PHP Logger

Muscula PHP Logger allows you to send all your PHP errors to Muscula service. Code is completely based on 
great PHP Console library https://github.com/barbushin/muscula


### Requirements

* PHP 5.4 (or later)

# Installation

### Composer

	{
		"require": {
			"muscula/muscula-php-logger": "^1.0"
		}
	}

Or

	$ composer require muscula/muscula-php-logger


# Usage



## Connector

There is a [Muscula\Connector](src/Muscula/Connector.php) class that initializes connection between PHP server and Musucla system. Connection is initialized when [Muscula\Connector](src/Muscula/Connector.php) instance is initialized:

	$connector = Muscula\Connector::getInstance();
	$connector->logId = 'LOG_ID';

Also it will be initialized when you call `Muscula\Handler::getInstance()` or `Muscula\Helper::register()`.


## Handle errors

There is a [Muscula\Handler](src/Muscula/Handler.php) class that initializes PHP errors & exceptions handlers and provides the next features:

* Handle PHP errors (+fatal & memory limit errors) and exceptions.
* Ignore repeated errors.
* Call previously defined errors and exceptions handlers.
* Handle caught exceptions using `$handler->handleException($exception)`.
* Debug vars using `$handler->debug($var, $someObj)`.

Initialize `Muscula\Handler` in the top of your main project script:

	$handler = Muscula\Handler::getInstance();
	/* You can override default Handler behavior:
		$handler->setHandleErrors(false);  // disable errors handling
		$handler->setHandleExceptions(false); // disable exceptions handling
		$handler->setCallOldHandlers(false); // disable passing errors & exceptions to prviously defined handlers
	*/
	$handler->start($logId); // initialize handlers, provide logId


## Structural logging

Muscula has multifunctional and smart vars dumper that allows to

* Dump any type variable.
* Dump protected and private objects properties.
* Limit dump by level, items count, item size and total size(see `$connector->getDumper()`).
* Dump objects class name.
* Smart dump of callbacks and Closure.
* Detect dump call source & trace(call `$connector->getDebugDispatcher()->detectTraceAndSource = true`).


### How to call

**Longest** native debug method call:
    $someObj = (object)['car' => 'suv','engine' => 'v8'];
	Muscula\Connector::getInstance()->getDebugDispatcher()->dispatchDebug($var, $someObj);

**Shorter** call debug from Handler:
    $someObj = (object)['car' => 'suv','engine' => 'v8'];
	Muscula\Handler::getInstance()->debug($var, $someObj);

**Shortest** call debug using global `M` class

	Muscula\Helper::register(); // it will register global M class
	// ...
    $someObj = (object)['car' => 'suv','engine' => 'v8'];
	M::debug($var, $someObj);
	M::tag($var);

**Custom** call debug by user defined function

	function d($var, $structuralData = null) {
		Muscula\Connector::getInstance()->getDebugDispatcher()->dispatchDebug($var, $structuralData, 1);
	}\
    $someObj = (object)['car' => 'suv','engine' => 'v8'];
	d($var, $someObj);


### Configuration

	$connector = Muscula\Connector::getInstance();
	$connector->logId = 'LOG_ID';

	// Configure eval provider
	$evalProvider = $connector->getEvalDispatcher()->getEvalProvider();
	$evalProvider->addSharedVar('post', $_POST); // so "return $post" code will return $_POST
	$evalProvider->setOpenBaseDirs(array(__DIR__)); // see http://php.net/open-basedir

	$connector->startEvalRequestsListener(); // must be called in the end of all configurations


## PSR-3 logger implementation

There is Muscula implementation of [PSR-3](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md) interface. to integrate Muscula with PSR-3 compitable loggers(e.g. [Monolog](https://github.com/Seldaek/monolog)). See [Muscula\PsrLogger](src/Muscula/PsrLogger.php).

