<?php

namespace Muscula;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;

/**
 * Muscula client connector that encapsulates client-server protocol implementation
 *
 * You will need to install Google Chrome extension "Muscula"
 * https://chrome.google.com/webstore/detail/muscula/nfhmhhlpfleoednkpnnnkolmclajemef
 *
 * @package Muscula
 * @version 3.1
 * @link http://consle.com
 * @author Sergey Barbushin http://linkedin.com/in/barbushin
 * @copyright © Sergey Barbushin, 2011-2013. All rights reserved.
 * @license http://www.opensource.org/licenses/BSD-3-Clause "The BSD 3-Clause License"
 * @codeCoverageIgnore
 */
class Connector {

	const SERVER_PROTOCOL = 5;
	const SERVER_COOKIE = 'muscula-server';
	const CLIENT_INFO_COOKIE = 'muscula-client';
	const CLIENT_ENCODING = 'UTF-8';
	const POST_VAR_NAME = '__Muscula';
	const POSTPONE_REQUESTS_LIMIT = 10;
	const PHP_HEADERS_SIZE = 1000; // maximum PHP response headers size
	const CLIENT_HEADERS_LIMIT = 200000;

	/** @var Connector */
	protected static $instance;

	/** @var  Dumper|null */
	protected $dumper;
	/** @var  Dispatcher\Debug|null */
	protected $debugDispatcher;
	/** @var  Dispatcher\Errors|null */
	protected $errorsDispatcher;
	/** @var  Dispatcher\Evaluate|null */
	protected $evalDispatcher;
	/** @var  string */
	protected $serverEncoding = self::CLIENT_ENCODING;
	protected $sourcesBasePath;
	protected $headersLimit;

	/** @var Client|null */
	private $client;
	/** @var Message[] */
	private $messages = array();
    public $logId;

    /**
	 * @return static
	 */
	public static function getInstance() {
		if(!self::$instance) {
			self::$instance = new static();
		}
		return self::$instance;
	}

	protected function __construct() {
        $this->client = new \GuzzleHttp\Client();
		$this->setServerEncoding(ini_get('mbstring.internal_encoding') ? : self::CLIENT_ENCODING);
	}

	private function __clone() {
	}

	/**
	 * Detect script is running in command-line mode
	 * @return int
	 */
	protected function isCliMode() {
		return PHP_SAPI == 'cli';
	}


	/**
	 * @return Dumper
	 */
	public function getDumper() {
		if(!$this->dumper) {
			$this->dumper = new Dumper();
		}
		return $this->dumper;
	}

	/**
	 * Override default errors dispatcher
	 * @param Dispatcher\Errors $dispatcher
	 */
	public function setErrorsDispatcher(Dispatcher\Errors $dispatcher) {
		$this->errorsDispatcher = $dispatcher;
	}

	/**
	 * Get dispatcher responsible for sending errors/exceptions messages
	 * @return Dispatcher\Errors
	 */
	public function getErrorsDispatcher() {
		if(!$this->errorsDispatcher) {
			$this->errorsDispatcher = new Dispatcher\Errors($this, $this->getDumper());
		}
		return $this->errorsDispatcher;
	}

	/**
	 * Override default debug dispatcher
	 * @param Dispatcher\Debug $dispatcher
	 */
	public function setDebugDispatcher(Dispatcher\Debug $dispatcher) {
		$this->debugDispatcher = $dispatcher;
	}

	/**
	 * Get dispatcher responsible for sending debug messages
	 * @return Dispatcher\Debug
	 */
	public function getDebugDispatcher() {
		if(!$this->debugDispatcher) {
			$this->debugDispatcher = new Dispatcher\Debug($this, $this->getDumper());
		}
		return $this->debugDispatcher;
	}

	/**
	 * Override default eval requests dispatcher
	 * @param Dispatcher\Evaluate $dispatcher
	 */
	public function setEvalDispatcher(Dispatcher\Evaluate $dispatcher) {
		$this->evalDispatcher = $dispatcher;
	}

	/**
	 * Get dispatcher responsible for handling eval requests
	 * @return Dispatcher\Evaluate
	 */
	public function getEvalDispatcher() {
		if(!$this->evalDispatcher) {
			$this->evalDispatcher = new Dispatcher\Evaluate($this, new EvalProvider(), $this->getDumper());
		}
		return $this->evalDispatcher;
	}


	/**
	 * Set bath to base dir of project source code(so it will be stripped in paths displaying on client)
	 * @param $sourcesBasePath
	 * @throws Exception
	 */
	public function setSourcesBasePath($sourcesBasePath) {
		$sourcesBasePath = realpath($sourcesBasePath);
		if(!$sourcesBasePath) {
			throw new Exception('Path "' . $sourcesBasePath . '" not found');
		}
		$this->sourcesBasePath = $sourcesBasePath;
	}

	/**
	 * Encode var to JSON with errors & encoding handling
	 * @param $var
	 * @return string
	 * @throws Exception
	 */
	protected function jsonEncode($var) {
		return json_encode($var, defined('JSON_UNESCAPED_UNICODE') ? JSON_UNESCAPED_UNICODE : null);
	}

	/**
	 * Recursive var data encoding conversion
	 * @param $data
	 * @param $fromEncoding
	 * @param $toEncoding
	 */
	protected function convertArrayEncoding(&$data, $toEncoding, $fromEncoding) {
		array_walk_recursive($data, array($this, 'convertWalkRecursiveItemEncoding'), array($toEncoding, $fromEncoding));
	}

    /**
     * Encoding conversion callback for array_walk_recursive()
     * @param string $string
     * @param array $args
     * @throws Exception
     */
	protected function convertWalkRecursiveItemEncoding(&$string, array $args) {
		$this->convertEncoding($string, $args[0], $args[1]);
	}

	/**
	 * Convert string encoding
	 * @param string $string
	 * @param string $toEncoding
	 * @param string|null $fromEncoding
	 * @throws Exception
	 */
	protected function convertEncoding(&$string, $toEncoding, $fromEncoding) {
		if($string && is_string($string) && $toEncoding != $fromEncoding) {
			static $isMbString;
			if($isMbString === null) {
				$isMbString = extension_loaded('mbstring');
			}
			if($isMbString) {
				$string = @mb_convert_encoding($string, $toEncoding, $fromEncoding) ? : $string;
			}
			else {
				$string = @iconv($fromEncoding, $toEncoding . '//IGNORE', $string) ? : $string;
			}
			if(!$string && $toEncoding == 'UTF-8') {
				$string = utf8_encode($string);
			}
		}
	}

	/**
	 * Set headers size limit for your web-server. You can auto-detect headers size limit by /examples/utils/detect_headers_limit.php
	 * @param $bytes
	 * @throws Exception
	 */
	public function setHeadersLimit($bytes) {
		if($bytes < static::PHP_HEADERS_SIZE) {
			throw new Exception('Headers limit cannot be less then ' . __CLASS__ . '::PHP_HEADERS_SIZE');
		}
		$bytes -= static::PHP_HEADERS_SIZE;
		$this->headersLimit = $bytes < static::CLIENT_HEADERS_LIMIT ? $bytes : static::CLIENT_HEADERS_LIMIT;
	}

	/**
	 * Set your server PHP internal encoding, if it's different from "mbstring.internal_encoding" or UTF-8
	 * @param $encoding
	 */
	public function setServerEncoding($encoding) {
		if($encoding == 'utf8' || $encoding == 'utf-8') {
			$encoding = 'UTF-8'; // otherwise mb_convert_encoding() sometime fails with error(thanks to @alexborisov)
		}
		$this->serverEncoding = $encoding;
	}

	/**
	 * Send data message to Muscula client
	 * @param EventMessage $message
	 */
	public function sendMessage(EventMessage $message) {
	    $message->logId = $this->logId;

        if (!$message->logId) {
            echo('Muscula error: logID not found');
            return;
        }

        if (is_array($message->stackTrace) && count($message->stackTrace) >0 && is_object($message->stackTrace[0])) {
            foreach ($message->stackTrace as &$s) {
                $s = $s->call.'@'.$s->fileName.':'.$s->lineNumber;
            }
        }

        if($message->stackTrace) {
            /**
             * @param TraceCall $value
             * @return string
             */
            $stackMapping = function(TraceCall $value) {
                return $value->call.'@'.$value->fileName.':'.$value->lineNumber;
            };

            $newStackTrace = array_map($stackMapping,$message->stackTrace);
            $message->stackTrace = $newStackTrace;
        }

        $request = new \GuzzleHttp\Psr7\Request('POST', 'https://harvester.muscula.com/log', [
            'content-type' => 'application/json',
            'verify' => false,
        ], json_encode($message));

        try {
            $this->client->send($request);
        } catch (GuzzleException $e) {
            echo('Error when sending to Muscula ' . $e->getMessage());
        }
	}

	protected function objectToArray(&$var) {
		if(is_object($var)) {
			$var = get_object_vars($var);
			array_walk_recursive($var, array($this, 'objectToArray'));
		}
	}

	protected function serializeResponse(DataObject $response) {
		if($this->serverEncoding != self::CLIENT_ENCODING) {
			$this->objectToArray($response);
			$this->convertArrayEncoding($response, self::CLIENT_ENCODING, $this->serverEncoding);
		}
		return $this->jsonEncode($response);
	}
}

abstract class DataObject {

	public function __construct(array $properties = array()) {
		foreach($properties as $property => $value) {
			$this->$property = $value;
		}
	}
}

final class Client extends DataObject {

	public $protocol;
	/** @var ClientAuth|null */
	public $auth;
}

final class ClientAuth extends DataObject {

	public $publicKey;
	public $token;
}

final class ServerAuthStatus extends DataObject {

	public $publicKey;
	public $isSuccess;
}

final class Response extends DataObject {

	public $protocol = Connector::SERVER_PROTOCOL;
	/** @var  ServerAuthStatus */
	public $auth;
	public $docRoot;
	public $sourcesBasePath;
	public $getBackData;
	public $isLocal;
	public $isSslOnlyMode;
	public $isEvalEnabled;
	public $messages = array();
}

final class PostponedResponse extends DataObject {

	public $protocol = Connector::SERVER_PROTOCOL;
	public $isPostponed = true;
	public $id;
}

abstract class Message extends DataObject {

	public $type;
    /**
     * @var string
     */
    public $logId;
}

abstract class EventMessage extends Message {

	public $message;
	public $fileName;
	public $lineNumber;
	/** @var  null|TraceCall[] */
	public $stackTrace;
}

final class TraceCall extends DataObject {

	public $fileName;
	public $lineNumber;
	public $call;
}

final class DebugMessage extends EventMessage {

	public $type = 'debug';
	public $structuralData;
    /**
     * @var int
     */
    public $severity;
}

final class ErrorMessage extends EventMessage {

	public $type = 'error';
	public $code;
	public $className;
}

final class EvalResultMessage extends Message {

	public $type = 'eval_result';
	public $return;
	public $output;
	public $time;
}
