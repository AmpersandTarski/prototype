<?php

use Ampersand\Log\RequestIDProcessor;
use Monolog\Handler\FingersCrossed\ErrorLevelActivationStrategy;
use Monolog\Handler\FingersCrossedHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger as MonologLogger;
use Monolog\Processor\WebProcessor;
use Monolog\Registry;

// PHP log

ini_set('error_reporting', E_ALL & ~E_NOTICE); // @phan-suppress-current-line PhanTypeMismatchArgumentInternal
ini_set("display_errors", '0');
ini_set("log_errors", '1');

// Processors
$webProcessor = new WebProcessor(extraFields: [
    'ip' => 'REMOTE_ADDR',
    'method' => 'REQUEST_METHOD',
    'url' => 'REQUEST_URI',
]);
$requestIDProcessor = new RequestIDProcessor();
$processors = [$requestIDProcessor, $webProcessor];

// Handlers
$stdoutStream = new StreamHandler('php://stdout', level: MonologLogger::DEBUG);
$stdout = new FingersCrossedHandler($stdoutStream, activationStrategy: new ErrorLevelActivationStrategy(MonologLogger::ERROR), passthruLevel: MonologLogger::NOTICE);
$stderr = new StreamHandler('php://stderr', level: MonologLogger::WARNING);
$handlers = [$stdout, $stderr];

// Loggers
foreach (['EXECENGINE', 'IO', 'API', 'APPLICATION', 'DATABASE', 'CORE', 'RULEENGINE', 'TRANSACTION', 'INTERFACING'] as $name) {
    $logger = new MonologLogger($name, $handlers, $processors);
    Registry::addLogger($logger);
}
