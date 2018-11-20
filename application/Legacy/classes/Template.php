<?php
namespace Luminance\Legacy;

// Example :
// $TPL = new TEMPLATE;
// $TPL->open('inv.tpl');
// $TPL->set('ADDRESS1',$TPL->str_align(57,$UADDRESS1,'l',' '));
// $TPL->get();

class Template
{
    public $file='';
    public $vars=array();

    public function open($file)
    {
        $this->file=file($file);
    }

    public function set($name, $var, $ifnone = "<i>-None-</i>")
    {
        if ($name!='') {
            $this->vars[$name][0]=$var;
            $this->vars[$name][1]=$ifnone;
        }
    }

    public function show()
    {
        $TMPVAR='';
        for ($i=0; $i<sizeof($this->file); $i++) {
            $TMPVAR=$this->file[$i];
            foreach ($this->vars as $k => $v) {
                if ($v[1]!="" && $v[0]=="") {
                    $v[0]=$v[1];
                }
                $TMPVAR=str_replace('{{'.$k.'}}', $v[0], $TMPVAR);
            }
            print $TMPVAR;
        }
    }

    public function get()
    {
        $RESULT='';
        $TMPVAR='';
        for ($i=0; $i<sizeof($this->file); $i++) {
            $TMPVAR=$this->file[$i];
            foreach ($this->vars as $k => $v) {
                if ($v[1]!="" && $v[0]=="") {
                    $v[0]=$v[1];
                }
                $TMPVAR=str_replace('{{'.$k.'}}', $v[0], $TMPVAR);
            }
            $RESULT.=$TMPVAR;
        }

        return $RESULT;
    }

    public function str_align($len, $str, $align, $fill)
    {
        $strlen=strlen($str);
        if ($strlen>$len) {
            return substr($str, 0, $len);
        } elseif (($strlen==0)||($len==0)) {
            return '';
        } else {
            if (($align=='l')||($align=='left')) {
                $result=$str.str_repeat($fill, ($len-$strlen));
            } elseif (($align=='r')||($align=='right')) {
                $result=str_repeat($fill, ($len-$strlen)).$str;
            } elseif (($align=='c')||($align=='center')) {
                $snm=intval(($len-$strlen)/2);
                if (($strlen+($snm*2))==$len) {
                    $result=str_repeat($fill, $snm).$str;
                } else {
                    $result=str_repeat($fill, $snm+1).$str;
                }

                $result.=str_repeat($fill, $snm);
            }

            return $result;
        }
    }
}
