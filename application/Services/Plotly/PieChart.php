<?php
namespace Luminance\Services\Plotly;

use Luminance\Core\Master;

class PieChart extends Chart {
    public function __construct(Master $master) {
        parent::__construct($master);
        $this->data = new \stdClass;
        $this->data->marker = new \stdClass;
        $this->data->type = 'pie';
        $this->data->values = [];
        $this->data->labels = [];
        $this->data->textposition = 'outside';
        $this->data->textinfo = 'label';
        $this->data->showlegend = false;
        $this->data->rotation = 90;
        $this->data->pull = 0.01;
        $this->data->direction = 'clockwise';
        $this->data->sort = false;
    }

    public function add($label, $data) {
        if (!($label === false)) {
            $this->data->labels[] = $label;
        }
        $this->data->values[] = $data;
    }

    public function generate($maxSegments = 20) {
        $data = $this->data;
        array_multisort($data->values, SORT_DESC, SORT_NUMERIC, $data->labels);
        $total = array_sum($data->values);
        $others = array_sum(array_slice($data->values, $maxSegments));
        $data->values = array_slice($data->values, 0, $maxSegments);
        $data->labels = array_slice($data->labels, 0, $maxSegments);

        if ($others > 0) {
            $data->values[] = $others;
            $data->labels[] = "Other";
        }

        # Create percentages
        foreach ($data->values as $key => $value) {
            $percentage = number_format(($value/$total)*100, 2);
            $data->labels[$key] .= " ({$percentage}%)";
        }

        $data->marker->colors = $this->generateColors(count($data->values));

        return [
            'data'    => [$data],
            'options' => $this->options,
            'layout'  => $this->layout,
            'config'  => $this->config
        ];
    }
}
