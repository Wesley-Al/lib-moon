<?php

namespace Moontec\Config;

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

//PARA USAR ESSE CLASS, É NECESSÁRIO USAR O SEGUINTE COMANDO: require(__DIR__."/../vendor/moontec/lib-moon/src/config/logging.php")
return [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['single'],
        'ignore_exceptions' => false,
    ],

    'single' => [
        'driver' => 'single',
        'path' => storage_path('logs/laravel.log'),
        'level' => env('LOG_LEVEL', 'debug'),
        'replace_placeholders' => true,
    ],

    'daily' => [
        'driver' => 'daily',
        'path' => storage_path('logs/laravel.log'),
        'level' => env('LOG_LEVEL', 'debug'),
        'days' => 14,
        'replace_placeholders' => true,
    ],

    'slack' => [
        'driver' => 'slack',
        'url' => env('LOG_SLACK_WEBHOOK_URL'),
        'username' => 'Laravel Log',
        'emoji' => ':boom:',
        'level' => env('LOG_LEVEL', 'critical'),
        'replace_placeholders' => true,
    ],

    'papertrail' => [
        'driver' => 'monolog',
        'level' => env('LOG_LEVEL', 'debug'),
        'handler' => env('LOG_PAPERTRAIL_HANDLER', SyslogUdpHandler::class),
        'handler_with' => [
            'host' => env('PAPERTRAIL_URL'),
            'port' => env('PAPERTRAIL_PORT'),
            'connectionString' => 'tls://' . env('PAPERTRAIL_URL') . ':' . env('PAPERTRAIL_PORT'),
        ],
        'processors' => [PsrLogMessageProcessor::class],
    ],

    'stderr' => [
        'driver' => 'monolog',
        'level' => env('LOG_LEVEL', 'debug'),
        'handler' => StreamHandler::class,
        'formatter' => env('LOG_STDERR_FORMATTER'),
        'with' => [
            'stream' => 'php://stderr',
        ],
        'processors' => [PsrLogMessageProcessor::class],
    ],

    'syslog' => [
        'driver' => 'syslog',
        'level' => env('LOG_LEVEL', 'debug'),
        'facility' => LOG_USER,
        'replace_placeholders' => true,
    ],

    'errorlog' => [
        'driver' => 'errorlog',
        'level' => env('LOG_LEVEL', 'debug'),
        'replace_placeholders' => true,
    ],

    'null' => [
        'driver' => 'monolog',
        'handler' => NullHandler::class,
    ],

    'emergency' => [
        'path' => storage_path('logs/laravel.log'),
    ],

    /***************************LOGS CUSTOMIZADOS ***************************/

    'exception' => [
        'driver' => 'single',
        'path' => storage_path('logs/error.log'),
        'level' => env('LOG_LEVEL', 'debug'),
        'replace_placeholders' => true,
    ],

    'payloads' => [
        'driver' => 'single',
        'path' => storage_path('logs/payloads.log'),
        'level' => env('LOG_LEVEL', 'debug'),
        'replace_placeholders' => true,
    ],

    'information' => [
        'driver' => 'single',
        'path' => storage_path('logs/info.log'),
        'level' => env('LOG_LEVEL', 'debug'),
        'replace_placeholders' => true,
    ],

    'cronjob' => [
        'driver' => 'single',
        'path' => storage_path('logs/cronjob.log'),
        'level' => env('LOG_LEVEL', 'debug'),
        'replace_placeholders' => true,
    ]
];
