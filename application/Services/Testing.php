<?php

namespace Luminance\Services;

use Luminance\Core\Service;
use PHPUnit\Framework\TestSuite;
use PHPUnit\TextUI\TestRunner;

/**
 * Luminance's wrapper for unit testing *
 * @package Luminance\Services
 */
class Testing extends Service
{
    /**
     * @var TestSuite
     */
    private $suite;

    /**
     * @var string
     */
    private $directory;

    /**
     * @var int
     */
    public $loadedTests = 0;

    /**
     * Testing constructor.
     * @param \Luminance\Core\Master $master
     */
    public function __construct($master) {
        parent::__construct($master);

        $this->setDirectory();

        $this->suite = new TestSuite();
        $this->load();
    }

    /**
     * @return string
     */
    public function run() {
        if (!$this->loadedTests) {
            return 'No test to run';
        }

        TestRunner::run($this->suite, ['verbose' => true]);
        return "Ran {$this->loadedTests} tests";
    }

    /**
     * Load unit tests files
     */
    private function load() {
        foreach (glob($this->getDirectory()) as $path) {
            require_once $path;
            $this->suite->addTestSuite($this->getClass($path));

            $this->loadedTests++;
        }
    }

    /**
     * @param $path
     * @return bool|mixed|string
     */
    private function getClass($path) {
        $file = substr(strrchr($path, "/"), 1);
        $file = preg_replace('/\.php$/i', '', $file);

        return $file;
    }

    /**
     * TODO:
     * @param $path
     * @return bool|mixed|string
     */
    private function setDirectory() {
        $this->directory = $this->master->application_path . "/../tests";
    }

    /**
     *
     * @param $path
     * @return bool|mixed|string
     */
    private function getDirectory() {
        return $this->directory."/*.php";
    }
}
