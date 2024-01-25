<?php
namespace Luminance\Services;

use Luminance\Core\Master;
use Luminance\Core\Service;
use Monolog\Logger;
use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

class Log extends Service {

    public $shortFormat = "%datetime% %message% %context% %extra%\n";
    public $longFormat = "%datetime% %channel%.%level_name% %message% %context% %extra%\n'";
    public $timeFormat = 'Y-m-d_H:i:s';

    protected $pageLogger;

    public function __construct(Master $master) {
        parent::__construct($master);
        $this->shortFormatter = new LineFormatter($this->shortFormat, $this->timeFormat, false, true);
        $this->longFormatter = new LineFormatter($this->longFormat, $this->longFormat, false, true);
        $this->initLoggers($this->master->settings->logs);
    }

    protected function initLoggers($logSettings) {
        $this->pageLogger = new Logger('page');
        if (!empty($logSettings->page_file)) {
            $stream = new StreamHandler($logSettings->page_file, $logSettings->page_level);
            $stream->setFormatter($this->shortFormatter);
        } else {
            $stream = new NullHandler();
        }
        $this->pageLogger->pushHandler($stream);
    }

    public function getMessagePrefix($request) {
        return $request->user ?
            "{$request->reference} {$request->ip} {$request->user->ID}/{$request->user->Username}" :
            "{$request->reference} {$request->ip} -";
    }

    public function logRequest($request) {
        if (!($request->method === 'CLI')) {
            $prefix = $this->getMessagePrefix($request);
            $this->pageLogger->info("{$prefix} {$request->method} {$request->uri}");
        }
    }

    public function logEvent($request, $message) {
        if (!($request->method === 'CLI')) {
            $prefix = $this->getMessagePrefix($request);
            $this->pageLogger->info("{$prefix} $message");
        }
    }
}
