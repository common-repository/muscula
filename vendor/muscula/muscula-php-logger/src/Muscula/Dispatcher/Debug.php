<?php

namespace Muscula\Dispatcher;
use Muscula\DebugMessage;
use Muscula\Dispatcher;

/**
 * Sends debug data to connector as client expected messages
 *
 * @package Muscula
 * @version 3.1
 * @link http://consle.com
 * @author Sergey Barbushin http://linkedin.com/in/barbushin
 * @copyright © Sergey Barbushin, 2011-2013. All rights reserved.
 * @license http://www.opensource.org/licenses/BSD-3-Clause "The BSD 3-Clause License"
 */
class Debug extends Dispatcher {

	/** @var bool Autodetect and append trace data to debug */
	public $detectTraceAndSource = false;

	/**
	 * Send debug data message to client
	 * @param mixed $data
	 * @param null|object $structuralData Tags separated by dot, e.g. "low.db.billing"
	 * @param int|array $ignoreTraceCalls Ignore tracing classes by name prefix `array('Muscula')` or fixed number of calls to ignore
	 */
	public function dispatchDebug($data, $structuralData = null, $ignoreTraceCalls = 0) {
        $message = new DebugMessage();
        $message->severity = 1;
        $message->message = $this->dumper->dump($data);
        if($structuralData) {

            $message->structuralData = (object) $structuralData;
        }
        if($this->detectTraceAndSource && $ignoreTraceCalls !== null) {
            $message->stackTrace = $this->fetchTrace(debug_backtrace(), $message->fileName, $message->lineNumber, $ignoreTraceCalls);
        }
        $this->sendMessage($message);
	}
}
