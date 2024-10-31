<?php

namespace Muscula\Dispatcher;
use Muscula\Connector;
use Muscula\Dispatcher;
use Muscula\Dumper;
use Muscula\EvalProvider;
use Muscula\EvalResultMessage;

/**
 * Executes client code and sends result data to connector as client expected messages
 *
 * @package Muscula
 * @version 3.1
 * @link http://consle.com
 * @author Sergey Barbushin http://linkedin.com/in/barbushin
 * @copyright © Sergey Barbushin, 2011-2013. All rights reserved.
 * @license http://www.opensource.org/licenses/BSD-3-Clause "The BSD 3-Clause License"
 */
class Evaluate extends Dispatcher {

	/** @var EvalProvider */
	protected $evalProvider;

	/**
	 * @param Connector $connector
	 * @param EvalProvider $evalProvider
	 * @param Dumper $dumper
	 */
	public function __construct(Connector $connector, EvalProvider $evalProvider, Dumper $dumper) {
		$this->evalProvider = $evalProvider;
		parent::__construct($connector, $dumper);
	}

	/**
	 * Override eval provider
	 * @param EvalProvider $evalProvider
	 */
	public function setEvalProvider(EvalProvider $evalProvider) {
		$this->evalProvider = $evalProvider;
	}

	/**
	 * Get eval provider
	 * @return EvalProvider
	 */
	public function getEvalProvider() {
		return $this->evalProvider;
	}

	/**
	 * Execute PHP code and send result message in connector
	 * @param $code
	 */
	public function dispatchCode($code) {
        $previousLastError = error_get_last();
        $oldDisplayErrors = ini_set('display_errors', false);
        $result = $this->evalProvider->evaluate($code);
        ini_set('display_errors', $oldDisplayErrors);

        $message = new EvalResultMessage();
        $message->return = $this->dumper->dump($result->return);
        $message->output = $this->dumper->dump($result->output);
        $message->time = round($result->time, 6);

        $newLastError = error_get_last();
        if($newLastError && $newLastError != $previousLastError) {
            $this->connector->getErrorsDispatcher()->dispatchError($newLastError ['type'], $newLastError ['message'], $newLastError ['file'], $newLastError ['line'], 999);
        }
        if($result->exception) {
            $this->connector->getErrorsDispatcher()->dispatchException($result->exception);
        }
        $this->sendMessage($message);
	}
}
