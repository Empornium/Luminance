<?php
namespace Luminance\Services\Plotly;

use Luminance\Core\Master;
use Luminance\Errors\SystemError;

class ChoroplethChart extends Chart {
    public function __construct(Master $master) {
        parent::__construct($master);
        $this->data = new \stdClass;
        $this->data->type = 'choropleth';
        $this->data->locations = [];
        $this->data->locationmode = 'country names'; // "ISO-3" | "USA-states" | "country names"
        $this->data->z = [];

        $geo = new \stdClass;
        $geo->scope = 'world';
        $geo->showframe = false;
        $geo->showcoastlines = false;
        $geo->projection = new \stdClass;
        $geo->projection->type = 'equirectangular';
        $geo->bgcolor = '0000';

        $this->layout->geo = $geo;

        $this->config->topojsonURL = '/static/libraries/plotly/';
    }

    public function scope($scope) {
        if (!in_array($scope, ['world', 'usa', 'europe', 'asia', 'africa', 'north america', 'south america'])) {
            throw new SystemError('Invalid choropleth scope.');
        }

        $this->layout->geo->scope = $scope;
    }

    public function add($location, $data) {
        if (!($location === false)) {
            $this->data->locations[] = $location;
        }
        $this->data->z[] = $data;
    }

    public function generateColors($steps) {
        $step = 1/($steps-1);

        $color = $this->color;
        $colors = [];

        $startColor = sscanf($color, "%02x%02x%02x");
        $stopColor = sscanf('FFFFFF', "%02x%02x%02x");
        $colorStep = [];
        $colorStep[0] = ($stopColor[0] - $startColor[0])/($steps)/1.6;
        $colorStep[1] = ($stopColor[1] - $startColor[1])/($steps)/1.6;
        $colorStep[2] = ($stopColor[2] - $startColor[2])/($steps)/1.6;
        for ($i = $steps; $i > 0; $i--) {
            $color = ($startColor[0] + $colorStep[0] * $i);
            $color = $color << 8;
            $color += ($startColor[1] + $colorStep[1] * $i);
            $color = $color << 8;
            $color += ($startColor[2] + $colorStep[2] * $i);
            $colors[] = $hex = sprintf('%06X', $color);
        }

        $colorscale = [];
        for ($i = 0; $i < $steps; $i++) {
            $colorscale[$i] = [($step*($i)), $colors[$i]];
        }
        $colorscale[0] = [0, 'FFFFFF'];

        return $colorscale;
    }

    public function generate() {
        $this->data->colorscale = $this->generateColors(25);
        $this->data->autocolorscale = false;

        return [
            'data'    => [$this->data],
            'options' => $this->options,
            'layout'  => $this->layout,
            'config'  => $this->config
        ];
    }
}
