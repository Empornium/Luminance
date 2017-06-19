<?php
namespace Luminance\Legacy;

class area_graph extends google_charts
{
    public function __construct ($Width, $Height, $Options=array())
    {
        parent::__construct('lc', $Width, $Height, $Options);
    }

    public function color($Color)
    {
        $this->URL .= '&amp;chco='.$Color.'&amp;chm=B,'.$Color.'50,0,0,0';
    }

    public function generate()
    {
        $Max = max($this->Data);
        $Min = (isset($this->Options['Break']))?$Min=min($this->Data):0;
        $Data = array();
        foreach ($this->Data as $Value) {
            $Data[] = $this->encode((($Value-$Min)/($Max-$Min))*4095);
        }
        $this->URL .= "&amp;chxt=y,x&amp;chxs=0,h&amp;chxl=1:|".implode('|', $this->Labels).'&amp;chxr=0,'.$Min.','.($Max-$Min).'&amp;chd=e:'.implode('', $Data);
    }
}
