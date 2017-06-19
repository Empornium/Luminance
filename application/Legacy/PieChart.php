<?php
namespace Luminance\Legacy;
class PieChart extends google_charts
{
    public function __construct ($Width, $Height, $Options=array())
    {
        $Type = (isset($Options['3D']))?'p3':'p';
        parent::__construct($Type, $Width, $Height, $Options);
    }

    public function generate()
    {
        $Sum = array_sum($this->Data);
        $Other = isset($this->Options['Other'])? floatval($this->Options['Other']):false;
        $Sort = isset($this->Options['Sort']);
        $LabelPercent = isset($this->Options['Percentage']);

        if ($Sort && !empty($this->Labels)) {
            array_multisort($this->Data, SORT_DESC, $this->Labels);
        } elseif ($Sort) {
            sort($this->Data);
            $this->Data = array_reverse($this->Data);
        }

        $Data = array();
        $Labels = $this->Labels;
        $OtherPercentage = 0.00;
        $OtherData = 0;

        foreach ($this->Data as $Key => $Value) {
            $ThisPercentage = number_format(($Value/$Sum)*100, 2);
            $ThisData = ($Value/$Sum)*4095;
            if ($Other && $ThisPercentage < $Other) {
                $OtherPercentage += $ThisPercentage;
                $OtherData += $ThisData;
                unset($Data[$Key]);
                unset($Labels[$Key]);
                continue;
            }
            if ($LabelPercent) {
                $Labels[$Key] .= ' ('.$ThisPercentage.'%)';
            }
            $Data[] = $this->encode($ThisData);
        }
        if ($OtherPercentage > 0) {
            $OtherLabel = 'Other';
            if ($LabelPercent) {
                $OtherLabel .= ' ('.$OtherPercentage.'%)';
            }
            $Labels[] = $OtherLabel;
            $Data[] = $this->encode($OtherData);
        }
        $this->URL .= "&amp;chl=".implode('|', $Labels).'&amp;chd=e:'.implode('', $Data);
    }
}
