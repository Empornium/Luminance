<?php
set_time_limit(50000);

$Limit = isset($_REQUEST['limit']) ? (int) $_REQUEST['limit'] : 100;
if ($Limit <= 10) $Limit = 10;
elseif ($Limit > 100000) $Limit = 100000;

$View = isset($_REQUEST['view']) ? (int) $_REQUEST['view'] : 100;
if ($View <= 10) $View = 10;
elseif ($View > 1000) $View = 1000;

$DB->query("SELECT SQL_CALC_FOUND_ROWS
                   UserID, TorrentID, Count(TorrentID), Max( Time )
              FROM users_downloads
          GROUP BY UserID, TorrentID
            HAVING Count(TorrentID)>1
          ORDER BY Count(TorrentID) DESC
             LIMIT $Limit");

$Dupes = $DB->to_array(false, MYSQLI_NUM);

$DB->query("SELECT FOUND_ROWS()");
list($NumResults) = $DB->next_record();

$DoFix = isset($_POST['submit']) && $_POST['submit']=='Fix Dupes';

if ($DoFix) {
    $total =0;
    foreach ($Dupes as $Dupe) {
        list($UserID, $TorrentID, $Count, $Time) = $Dupe;

        $DB->query("DELETE FROM users_downloads WHERE UserID='$UserID' AND TorrentID='$TorrentID' AND Time != '$Time'");
        $num = $DB->affected_rows();
        $total += $num;
    }
}

show_header("Fix dupe torrent grabs");

?>
<div class="thin">
    <h2>Fix dupe torrent grabs</h2>

    <form method="post" action="" >
        <div class="head"></div>
        <div class="box pad">
            <input type="hidden" name="action" value="sandbox7" />
            <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
            count torrent/user dupes: <?=$NumResults?><br/>
            Limit (number to process at once): <input type="text" name="limit" size="3" value="<?=$Limit?>" /><br/>
            View amount: <input type="text" name="view" size="3" value="<?=$View?>" /><br/>
            <input type="submit" name="submit" value="Just View" />
        </div>

<?php  //if (!$DoFix) {  ?>
        <div class="head"></div>
        <div class="box pad">
            <input type="submit" name="submit" value="Fix Dupes" />
        </div>
<?php  //}  ?>

        <div class="head"></div>
        <div class="box pad">

<?php           if ($DoFix) {  ?>
                Removed <?=$total?> total dupes from <?=$NumResults?> user/torrent groups<br/>
<?php           }  ?>
                Viewing first <?=$View?> of <?=$NumResults?> records <br/><br/>

            <table>
                <tr class="colhead">
                    <td>UserID</td><td>TorrentID</td><td>Count</td><td>Time</td>
                </tr>
<?php
                $i=0;
                foreach ($Dupes as $Dupe) {
                    list($UserID, $TorrentID, $Count, $Time) = $Dupe;
?>
                    <tr>
                        <td><?=$UserID?></td><td><?=$TorrentID?></td><td><?=$Count?></td><td><?=$Time?></td>
                    </tr>
<?php
                    $i++;
                    if($i>=$View) break;
                }
?>
            </table>
        </div>
    </form>
</div>
<?php
show_footer();
