<?php
namespace Luminance\Services;

use Luminance\Core\Master;
use Luminance\Core\Service;

use Luminance\Services\Plotly\PieChart;
use Luminance\Services\Plotly\LineChart;
use Luminance\Services\Plotly\ChoroplethChart;

class Plotly extends Service {

    protected static $useServices = [
        'secretary' => 'Secretary',
    ];

    public function __construct(Master $master) {
        parent::__construct($master);
    }

    public function newPieChart() {
        return new PieChart($this->master);
    }

    public function newLineChart() {
        return new LineChart($this->master);
    }

    public function newChoroplethChart() {
        return new ChoroplethChart($this->master);
    }

    public function update() {
        $cdn = 'https://cdn.plot.ly/';

        $javascript = [
            'plotly.js'     => 'plotly-1.54.1.js',
            'plotly.min.js' => 'plotly-1.54.1.min.js',
        ];

        $topographies = [
            'world_50m.json',
            'world_110m.json',
            'usa_50m.json',
            'usa_110m.json',
            'europe_50m.json',
            'europe_110m.json',
            'asia_50m.json',
            'asia_110m.json',
            'africa_50m.json',
            'africa_110m.json',
            'north-america_50m.json',
            'north-america_110m.json',
            'south-america_50m.json',
            'south-america_110m.json',
        ];

        foreach ($javascript as $dst => $src) {
            $dst = $this->master->publicPath . '/static/libraries/' . $dst;
            $cdnSrc = $cdn.$src;
            if ($this->secretary->checkRemoteUpdate($cdnSrc, $dst)) {
                print("Fetching {$src}".PHP_EOL);
                $this->secretary->getHttpRemoteFile($cdnSrc, $dst);
            }
        }

        foreach ($topographies as $src) {
            $dst = $this->master->publicPath . '/static/libraries/plotly/';
            if (!file_exists($dst)) {
                mkdir($dst, 0755, true);
            }
            $dst = $dst.$src;
            $cdnSrc = $cdn.$src;
            if ($this->secretary->checkRemoteUpdate($cdnSrc, $dst)) {
                print("Fetching {$src}".PHP_EOL);
                $this->secretary->getHttpRemoteFile($cdnSrc, $dst);
            }
        }
    }
}
