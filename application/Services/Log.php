<?php
namespace Luminance\Services;

use Luminance\Core\Master;
use Luminance\Errors\ConfigurationError;
use Luminance\Errors\SystemError;

use Monolog\Logger;
use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

class Log extends Service {

    public $ShortFormat = "%datetime% %message% %context% %extra%\n";
    public $LongFormat = "%datetime% %channel%.%level_name% %message% %context% %extra%\n'";
    public $TimeFormat = 'Y-m-d_H:i:s';

    protected $PageLogger;

    public function __construct(Master $master) {
        parent::__construct($master);
        $this->ShortFormatter = new LineFormatter($this->ShortFormat, $this->TimeFormat, false, true);
        $this->LongFormatter = new LineFormatter($this->LongFormat, $this->LongFormat, false, true);
        $this->init_loggers($this->master->settings->logs);
    }

    protected function init_loggers($LogSettings) {
        $this->PageLogger = new Logger('page');
        if (isset($LogSettings->page_file)) {
            $Stream = new StreamHandler($LogSettings->page_file, $LogSettings->page_level);
        } else {
            $Stream = new NullHandler();
        }
        $Stream->setFormatter($this->ShortFormatter);
        $this->PageLogger->pushHandler($Stream);
    }

    public function get_message_prefix($request) {
        $ip = $this->master->server['REMOTE_ADDR'];
        if ($request->user) {
            $prefix = "{$request->reference} {$request->IP} {$request->user->ID}/{$request->user->Username}";
        } else {
            $prefix = "{$request->reference} {$request->IP} -";
        }
        return $prefix;
    }

    public function log_request($request) {
        if ($request->method !== 'CLI') {
            $prefix = $this->get_message_prefix($request);
            $this->PageLogger->addInfo("{$prefix} {$request->method} {$request->uri}");
        }
    }
}
