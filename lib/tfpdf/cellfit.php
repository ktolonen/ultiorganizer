<?php
require_once('tfpdf.php');

class tFPDF_CellFit extends tFPDF {

    //Cell with horizontal scaling if text is too wide
    function CellFit($w, $h=0, $txt='', $border=0, $ln=0, $align='', $fill=false, $link='', $scale=false, $force=true)
    {
        //Get string width
        $str_width=$this->GetStringWidth($txt);

        //Calculate ratio to fit cell
        if($w==0)
            $w = $this->w-$this->rMargin-$this->x;
        if ($str_width>0)
          $ratio = ($w-$this->cMargin*2)/$str_width;
        else
          $ratio = 1;

        $fit = ($ratio < 1 || ($ratio > 1 && $force));
        if ($fit)
        {
            if ($scale)
            {
                //Calculate horizontal scaling
                $horiz_scale=$ratio*100.0;
                //Set horizontal scaling
                $this->_out(sprintf('BT %.2F Tz ET',$horiz_scale));
            }
            else
            {
                //Calculate character spacing in points
                $char_space=($w-$this->cMargin*2-$str_width)/max($this->MBGetStringLength($txt)-1,1)*$this->k;
                //Set character spacing
                $this->_out(sprintf('BT %.2F Tc ET',$char_space));
            }
            //Override user alignment (since text will fill up cell)
            $align='';
        }

        //Pass on to Cell method
        $this->Cell($w,$h,$txt,$border,$ln,$align,$fill,$link);

        //Reset character spacing/horizontal scaling
        if ($fit)
            $this->_out('BT '.($scale ? '100 Tz' : '0 Tc').' ET');
    }

    //Cell with horizontal scaling only if necessary
    function CellFitScale($w, $h=0, $txt='', $border=0, $ln=0, $align='', $fill=false, $link='')
    {
        $this->CellFit($w,$h,$txt,$border,$ln,$align,$fill,$link,true,false);
    }

    //Cell with horizontal scaling always
    function CellFitScaleForce($w, $h=0, $txt='', $border=0, $ln=0, $align='', $fill=false, $link='')
    {
        $this->CellFit($w,$h,$txt,$border,$ln,$align,$fill,$link,true,true);
    }

    //Cell with character spacing only if necessary
    function CellFitSpace($w, $h=0, $txt='', $border=0, $ln=0, $align='', $fill=false, $link='')
    {
        $this->CellFit($w,$h,$txt,$border,$ln,$align,$fill,$link,false,false);
    }

    //Cell with character spacing always
    function CellFitSpaceForce($w, $h=0, $txt='', $border=0, $ln=0, $align='', $fill=false, $link='')
    {
        //Same as calling CellFit directly
        $this->CellFit($w,$h,$txt,$border,$ln,$align,$fill,$link,false,true);
    }

    //Patch to also work with CJK double-byte text
    function MBGetStringLength($s)
    {
        if($this->CurrentFont['type']=='Type0')
        {
            $len = 0;
            $nbbytes = strlen($s);
            for ($i = 0; $i < $nbbytes; $i++)
            {
                if (ord($s[$i])<128)
                    $len++;
                else
                {
                    $len++;
                    $i++;
                }
            }
            return $len;
        }
        else
            return strlen($s);
    }


    var $angle=0;

    function Rotate($angle,$x=-1,$y=-1)
    {
        if($x==-1)
            $x=$this->x;
        if($y==-1)
            $y=$this->y;
        if($this->angle!=0)
            $this->_out('Q');
        $this->angle=$angle;
        if($angle!=0)
        {
            $angle*=M_PI/180;
            $c=cos($angle);
            $s=sin($angle);
            $cx=$x*$this->k;
            $cy=($this->h-$y)*$this->k;
            $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm',$c,$s,-$s,$c,$cx,$cy,-$cx,-$cy));
        }
    }

    function _endpage()
    {
        if($this->angle!=0)
        {
            $this->angle=0;
            $this->_out('Q');
        }
        parent::_endpage();
    }

    function RotatedText($x,$y,$txt,$angle)
    {
        //Text rotated around its origin
        $this->Rotate($angle,$x,$y);
        $this->Text($x,$y,$txt);
        $this->Rotate(0);
    }

    function WriteText($text)
    {
        $intPosIni = 0;
        $intPosFim = 0;
        if (strpos($text,'<')!==false && strpos($text,'[')!==false)
        {
            if (strpos($text,'<')<strpos($text,'['))
            {
                $this->Write(5,substr($text,0,strpos($text,'<')));
                $intPosIni = strpos($text,'<');
                $intPosFim = strpos($text,'>');
                $this->SetFont('','B');
                $this->Write(5,substr($text,$intPosIni+1,$intPosFim-$intPosIni-1));
                $this->SetFont('','');
                $this->WriteText(substr($text,$intPosFim+1,strlen($text)));
            }
            else
            {
                $this->Write(5,substr($text,0,strpos($text,'[')));
                $intPosIni = strpos($text,'[');
                $intPosFim = strpos($text,']');
                $w=$this->GetStringWidth('a')*($intPosFim-$intPosIni-1);
                $this->Cell($w,$this->FontSize+0.75,substr($text,$intPosIni+1,$intPosFim-$intPosIni-1),1,0,'');
                $this->WriteText(substr($text,$intPosFim+1,strlen($text)));
            }
        }
        else
        {
            if (strpos($text,'<')!==false)
            {
                $this->Write(5,substr($text,0,strpos($text,'<')));
                $intPosIni = strpos($text,'<');
                $intPosFim = strpos($text,'>');
                $this->SetFont('','B');
                $this->WriteText(substr($text,$intPosIni+1,$intPosFim-$intPosIni-1));
                $this->SetFont('','');
                $this->WriteText(substr($text,$intPosFim+1,strlen($text)));
            }
            elseif (strpos($text,'[')!==false)
            {
                $this->Write(5,substr($text,0,strpos($text,'[')));
                $intPosIni = strpos($text,'[');
                $intPosFim = strpos($text,']');
                $w=$this->GetStringWidth('a')*($intPosFim-$intPosIni-1);
                $this->Cell($w,$this->FontSize+0.75,substr($text,$intPosIni+1,$intPosFim-$intPosIni-1),1,0,'');
                $this->WriteText(substr($text,$intPosFim+1,strlen($text)));
            }
            else
            {
                $this->Write(5,$text);
            }

        }
    }

}
?>
