<?php
namespace Luminance\Services\Plotly;

use Luminance\Core\Master;

class Chart {
    protected $data = null;
    protected $layout = null;
    protected $options = null;
    protected $config = null;
    protected $color = null;
    private $master;

    public function __construct(Master $master) {
        $this->master = $master;
        $this->options = new \stdClass;
        $this->layout = new \stdClass;
        $this->config = new \stdClass;

        $this->layout->autosize = true;

        $margin = new \stdClass;
        $margin->t = 20; //top margin
        $margin->l = 20; //left margin
        $margin->r = 20; //right margin
        $margin->b = 20; //bottom margin
        $this->layout->margin = $margin;
    }

    public function size($width = null, $height = null) {
        if (is_int($width) === true) {
            $this->layout->width = $width;
        }
        if (is_int($height) === true) {
            $this->layout->height = $height;
        }
    }

    public function color($color) {
        $this->color = $color;
    }

    public function generateColors($steps) {
        if (is_null($this->color) === true) {
            return;
        }
        $color = $this->color;
        $colors = [];

        $startColor = sscanf($color, "%02x%02x%02x");
        $stopColor = sscanf('FFFFFF', "%02x%02x%02x");
        $colorStep = [];
        $colorStep[0] = ($stopColor[0] - $startColor[0])/($steps)/2;
        $colorStep[1] = ($stopColor[1] - $startColor[1])/($steps)/2;
        $colorStep[2] = ($stopColor[2] - $startColor[2])/($steps)/2;
        for ($i = 0; $i < $steps; $i++) {
            $color = ($startColor[0] + $colorStep[0] * $i);
            $color = $color << 8;
            $color += ($startColor[1] + $colorStep[1] * $i);
            $color = $color << 8;
            $color += ($startColor[2] + $colorStep[2] * $i);
            $colors[] = $hex = sprintf('%06X', $color);
        }
        return $colors;
    }

    public function generate() {
    }
}
