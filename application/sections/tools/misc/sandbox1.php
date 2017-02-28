<?php
//echo "dont press that!<br/>";
$a = isset($_REQUEST['a'])?$_REQUEST['a']:0.74;
$b = isset($_REQUEST['b'])?$_REQUEST['b']:2;
$c = isset($_REQUEST['c'])?$_REQUEST['c']:6.5;
$d = isset($_REQUEST['d'])?$_REQUEST['d']:0;
$cap = isset($_REQUEST['cap'])?$_REQUEST['cap']:0;

include(SERVER_ROOT.'/classes/class_text.php');
$Text = new TEXT;

show_header();

?>
<div class="thin">
    <h2>sandbox 1</h2>
        <table style="width:100%">
            <tr>
                <td colspan="3" class="center">
                    <h3>round( ( sqrt( ( <span style="color:red">a</span> * count ) + <span style="color:red">b</span> ) - 1.0  ) *<span style="color:red">c</span> ) + <span style="color:red">d</span> </h3>
                    <form action="" method="post">
                        <input type="hidden" name="action" value="sandbox1" />
                        &nbsp;&nbsp;&nbsp;&nbsp;<label for="a" >a:</label>
                        <input size="6" type="text" name="a" value="<?=$a?>" />
                        &nbsp;&nbsp;&nbsp;&nbsp;<label for="b" >b:</label>
                        <input size="6" type="text" name="b" value="<?=$b?>" />
                        &nbsp;&nbsp;&nbsp;&nbsp;<label for="c" >c:</label>
                        <input size="6" type="text" name="c" value="<?=$c?>" />
                        &nbsp;&nbsp;&nbsp;&nbsp;<label for="d" >d:</label>
                        <input size="6" type="text" name="d" value="<?=$d?>" />
                        &nbsp;&nbsp;&nbsp;&nbsp;<label for="cap" >cap:</label>
                        <input size="6" type="text" name="cap" value="<?=$cap?>" />
                        <input type="submit" value="calculate"/>
                    </form><br/>
                </td>
            </tr>
            <tr>
                <td style="vertical-align: top">
                    <h4>your formula</h4>
                    <?=$Text->full_format( get_seed_values($cap, $a, $b, $c, $d));?>
                </td>
                <td  style="vertical-align: top">
                    <h4>current formula</h4>
                    <?=$Text->full_format( get_seed_values(0, 2/5, 1, 10));?>
                </td>
                <td  style="vertical-align: top">
                    <h4>new formula</h4>
                    <?=$Text->full_format( get_seed_values(200, 0.21, 1, 16, 1));?>
                </td>
            </tr>
        </table>

</div>

<?php
show_footer();

function get_bonus_points($cap, $count, $a = 8.0, $b = 1, $c=10, $d=0)
{
    if ($cap>0 && $count>$cap)$count=$cap;

    $num = round( ( sqrt( ( $a *  $count ) + $b ) - 1.0  ) *$c )+$d;

    return $num;
}

function get_seed_values($cap, $a = 8.0, $b = 1, $c=10, $d=0)
{
    $capm = $cap > 0 ? $cap : "no limit";

    $ret = "[size=1]round( ( sqrt( ( $a * count ) + $b ) - 1.0  ) *$c ) + $d\nmax torrents counted per hour = $capm [/size]\n\n";
    $ret .= "[code]";
    $ret .= "+----------+---------+---------+\n";
    $ret .= "| torrents | credits | max 1wk |\n";
    $ret .= "+----------+---------+---------+\n";
    $lastnum=-1;
    for ($count=1;$count<= 50;$count++) {
            $num = get_bonus_points($cap, $count, $a, $b, $c, $d);
            if ($count==10 || $count==20 || $count==30 || $count==40 || $count==50 || $lastnum !== $num) {
                $ret .= "| " . str_pad($count, 8)." | ". str_pad($num, 7)." | ". str_pad($num*168, 7)." |\n";
                $lastnum=$num;
            }
    }

    for ($i=0;$i<=20000;$i++) {

        if ($i <= 15)
            $count = 50 + ( $i * 10);
        elseif ($i <= 35)
            $count = -175 + ( $i * 25);
        elseif ($i <= 45)
            $count = -2800 + ( $i * 100);

        $num = get_bonus_points($cap, $count, $a, $b, $c, $d);

        if ($lastnum != $num) {
            $ret .= "| " . str_pad($count, 8)." | ". str_pad($num, 7)." | ". str_pad($num*168, 7)." |\n";
            $lastnum=$num;
        }
        if ($count >= 1000) break;
    }

    $ret .= "+----------+---------+---------+\n";
    $ret .="[/code]\n";

    return $ret;
}
