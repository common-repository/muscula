<?php

namespace Muscula\Dispatcher;
use Muscula\Dispatcher;
use Muscula\ErrorMessage;
use Muscula\Message;

/**
 * Sends system errors and exceptions to connector as client expected messages
 *
 * @package Muscula
 * @version 3.1
 * @link http://consle.com
 * @author Sergey Barbushin http://linkedin.com/in/barbushin
 * @copyright © Sergey Barbushin, 2011-2013. All rights reserved.
 * @license http://www.opensource.org/licenses/BSD-3-Clause "The BSD 3-Clause License"
 */
class Errors extends Dispatcher {

	/** @var array PHP errors constants values => names(will be initialized in first call) */
	protected static $errorsConstantsValues = array();
	/** @var array PHP errors constants names */
	protected static $errorsConstantsNames = array(
		'E_STRICT',
		'E_DEPRECATED',
		'E_RECOVERABLE_ERROR',
		'E_NOTICE',
		'E_WARNING',
		'E_ERROR',
		'E_PARSE',
		'E_USER_DEPRECATED',
		'E_USER_NOTICE',
		'E_USER_WARNING',
		'E_USER_ERROR',
		'E_CORE_WARNING',
		'E_CORE_ERROR',
		'E_COMPILE_ERROR',
		'E_COMPILE_WARNING',
	);

	/** @var bool Don't send errors messages with same file, line & class */
	public $ignoreRepeatedSource = true;
	/** @var bool Dispatch $exception->getPrevious() if not empty */
	public $dispatchPreviousExceptions = true;

	/** @var ErrorMessage[] */
	protected $sentMessages = array();

	/**
	 * Send error message to client
	 * @param null|integer $code
	 * @param null|string $text
	 * @param null|string $file
	 * @param null|integer $line
	 * @param int|array $ignoreTraceCalls Ignore tracing classes by name prefix `array('Muscula')` or fixed number of calls to ignore
	 */
	public function dispatchError($code = null, $text = null, $file = null, $line = null, $ignoreTraceCalls = 0) {
        $message = new ErrorMessage();
        $message->code = $code;
        $message->className = $this->getErrorTypeByCode($code);
        $message->message = $this->dumper->dump($text);
        $message->fileName = $file;
        $message->lineNumber = $line;
        if($ignoreTraceCalls !== null) {
            $message->stackTrace = $this->fetchTrace(debug_backtrace(), $file, $line, is_array($ignoreTraceCalls) ? $ignoreTraceCalls : $ignoreTraceCalls + 1);
        }
        $this->sendMessage($message);
	}

	/**
	 * Send exception message to client
	 * @param \Exception|\Throwable $exception
	 */
	public function dispatchException($exception) {
        if($this->dispatchPreviousExceptions && $exception->getPrevious()) {
            $this->dispatchException($exception->getPrevious());
        }
        $message = new ErrorMessage();
        $message->code = $exception->getCode();
        $message->className = get_class($exception);
        $message->message = $this->dumper->dump($exception->getMessage());
        $message->fileName = $exception->getFile();
        $message->lineNumber = $exception->getLine();
        $message->stackTrace = self::fetchTrace($exception->getTrace(), $message->fileName, $message->lineNumber);
        $this->sendMessage($message);
	}

	/**
	 * Send message to Muscula connector
	 * @param Message $message
	 */
	protected function sendMessage(Message $message) {
		if(!$this->isIgnored($message)) {
			parent::sendMessage($message);
			$this->sentMessages[] = $message;
		}
	}

	/**
	 * Get PHP error constant name by value
	 * @param int $code
	 * @return string
	 */
	protected function getErrorTypeByCode($code) {
		if(!static::$errorsConstantsValues) {
			foreach(static::$errorsConstantsNames as $constantName) {
				if(defined($constantName)) {
					static::$errorsConstantsValues[constant($constantName)] = $constantName;
				}
			}
		}
		if(isset(static::$errorsConstantsValues[$code])) {
			return static::$errorsConstantsValues[$code];
		}
		return (string)$code;
	}

	/**
	 * Return true if message with same file, line & class was already sent
	 * @param ErrorMessage $message
	 * @return bool
	 */
	protected function isIgnored(ErrorMessage $message) {
		if($this->ignoreRepeatedSource && $message->fileName) {
			foreach($this->sentMessages as $sentMessage) {
				if($message->fileName == $sentMessage->fileName && $message->lineNumber == $sentMessage->lineNumber && $message->className == $sentMessage->className) {
					return true;
				}
			}
		}
		return false;
	}
}
