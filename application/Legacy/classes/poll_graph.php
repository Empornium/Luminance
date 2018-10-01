<?php
namespace Luminance\Legacy;
class poll_graph extends google_charts
{
    public function __construct()
    {
        $this->URL .= '?cht=bhg';
    }

    public function add($Label, $Data)
    {
        if ($Label !== false) {
            $this->Labels[] = cut_string($Label,35);
        }
        $this->Data[] = $Data;
    }

    public function generate()
    {
        $Count = count($this->Data);
        $Height = (30*$Count)+20;
        $Max = max($this->Data);
        $Sum = array_sum($this->Data);
        $Increment = ($Max/$Sum)*25; // * 100% / 4divisions
        $Data = array();
        $Labels = array();
        foreach ($this->Data as $Key => $Value) {
            $Data[] = $this->encode(($Value/$Max)*4095);
            $Labels[] = '@t'.str_replace(array(' ',','),array('+','\,'),$this->Labels[$Key]).',000000,1,'.round((($Key + 1)/$Count) - (12/$Height),2).':0,12';
        }
        $this->URL .= "&amp;chbh=25,0,5&amp;chs=214x$Height&amp;chl=0%|".round($Increment,1)."%|".round($Increment * 2,1)."%|".round($Increment * 3,1)."%|".round($Increment * 4,1)."%&amp;chm=".implode('|', $Labels).'&amp;chd=e:'.implode('', $Data);
    }
}
