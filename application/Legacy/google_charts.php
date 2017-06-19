<?php
namespace Luminance\Legacy;
class google_charts
{
    protected $URL = 'http://chart.apis.google.com/chart';
    protected $Labels = array();
    protected $Data = array();
    protected $Options = array();

    public function __construct($Type, $Width, $Height, $Options)
    {
        if ($Width * $Height > 300000 || $Height > 1000 || $Width > 1000) {
            trigger_error('Tried to make chart too large.');
        }
        $this->URL .= '?cht='.$Type.'&amp;chs='.$Width.'x'.$Height;
        $this->Options = $Options;
    }

    protected function encode($Number)
    {
        if ($Number == -1) {
            return '__';
        }
        $CharKey = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-.';

        return $CharKey[floor($Number/64)].$CharKey[floor($Number%64)];
    }

    public function color($Colors)
    {
        $this->URL .= '&amp;chco='.$Colors;
    }

    public function lines($Thickness, $Solid=1, $Blank=0)
    {
        $this->URL .= '&amp;chls='.$Thickness.','.$Solid.','.$Blank;
    }

    public function title($Title, $Color='', $Size='')
    {
        $this->URL .= '&amp;chtt='.str_replace(array(' ',"\n"), array('+','|'), $Title);
        if (!empty($Color)) {
            $this->URL .= '&amp;chts='.$Color;
        }
        if (!empty($Size)) {
            $this->URL .= ','.$Size;
        }
    }

    public function legend($Items, $Placement='')
    {
        $this->URL .= '&amp;chdl='.str_replace(' ', '+', implode('|', $Items));
        if (!empty($Placement)) {
            if (!in_array($Placement, array('b','t','r','l','bv','tv'))) {
                trigger_error('Invalid legend placement.');
            }
            $this->URL .= '&amp;chdlp='.$Placement;
        }
    }

    public function add($Label, $Data)
    {
        if ($Label !== false) {
            $this->Labels[] = $Label;
        }
        $this->Data[] = $Data;
    }

    public function grid_lines($SpacingX=0, $SpacingY=-1, $Solid=1, $Blank=1)
    {
        //Can take 2 more parameters for offset, but we're not bothering with that right now
        $this->URL .= '&amp;chg='.$SpacingX.','.$SpacingY.','.$Solid.','.$Blank.'';
    }

    public function transparent()
    {
        $this->URL .= '&amp;chf=bg,s,FFFFFF00';
    }


    public function url()
    {
        return $this->URL;
    }
}
