<?php namespace Muscula\Test;

use Muscula\Handler;

define('Muscula\Test\BASE_DIR', realpath(__DIR__ . '/..'));
define('Muscula\Test\TMP_DIR', BASE_DIR . '/build');

if(!is_dir(TMP_DIR)) {
    mkdir(TMP_DIR);
}

require_once BASE_DIR . "/vendor/autoload.php";
require_once BASE_DIR. '/src/Muscula/Handler.php';

class_exists('Muscula\Connector'); // required to force autoload Muscula\Connector, to init models classes that can be used in tests before initializing PhpConsole\Connector

$handler = Handler::getInstance();
$handler->start('<YOUR LOG_ID>'); // initialize handlers

$someObj = (object)['car' => 'suv','engine' => 'v8'];

$handler->debug("Car information", $someObj);

$a = [];
$a->bbb();
$b = $a[1];


