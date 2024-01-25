<?php
namespace Luminance\Services\Plotly;

use Luminance\Core\Master;

class LineChart extends Chart {
    public function __construct(Master $master) {
        parent::__construct($master);
        $this->data = [];
    }

    public function add($label, $x, $y, $fill = null) {
        $data = new \stdClass;

        $data->x = $x;
        $data->y = $y;
        $data->mode = 'lines';
        $data->type = 'scatter';
        $data->name = $label;
        $data->connectgaps = true;
        if (!is_null($fill)) {
            $data->fill = 'tozeroy';
            $data->fillcolor = $fill;
        }

        $this->data[] = $data;
    }

    public function timeSeries($initialRange, $fullRange) {
        $xaxis = new \stdClass;
        $xaxis->range = $initialRange;
        $xaxis->rangeselector = new \stdClass;
        $xaxis->rangeselector->buttons = [];

        $button = new \stdClass;
        $button->count = 1;
        $button->label = '1m';
        $button->step = 'month';
        $button->stepmode = 'backwards';
        $xaxis->rangeselector->buttons[] = $button;

        $button = new \stdClass;
        $button->count = 6;
        $button->label = '6m';
        $button->step = 'month';
        $button->stepmode = 'backwards';
        $xaxis->rangeselector->buttons[] = $button;

        $button = new \stdClass;
        $button->count = 1;
        $button->label = '1y';
        $button->step = 'year';
        $button->stepmode = 'backwards';
        $button->active = true;
        $xaxis->rangeselector->buttons[] = $button;

        $button = new \stdClass;
        $button->step = 'all';
        $xaxis->rangeselector->buttons[] = $button;

        $xaxis->rangeslider = new \stdClass;
        $xaxis->rangeslider->range = $fullRange;
        $xaxis->type = 'date';

        $this->layout->xaxis = $xaxis;
    }

    public function generate() {
        return [
            'data'    => $this->data,
            'options' => $this->options,
            'layout'  => $this->layout,
            'config'  => $this->config
        ];
    }
}
