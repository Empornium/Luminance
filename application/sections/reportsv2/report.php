<?php
/*
 * This is the frontend of reporting a torrent, it's what users see when
 * they visit reportsv2.php?id=xxx
 */

//If we're not coming from torrents.php, check we're being returned because of an error.
if (!isset($_GET['id']) || !is_number($_GET['id'])) {
    if (!isset($Err)) {
        error(0);
    }
} else {
    $TorrentID = (int) $_GET['id'];
    $DB->query("SELECT GroupID, Name FROM torrents_group AS tg JOIN torrents AS t ON t.GroupID=tg.ID WHERE t.ID=$TorrentID");
    if ($DB->record_count()==0) error("Not a valid torrentid! ($TorrentID)");
    list($GroupID, $TorrentName) = $DB->next_record();
}

show_header('Report Torrent', 'reportsv2');
?>

<div class="thin">
    <h2>Report a torrent</h2>

    <div class="head">Report</div>
     <div class="box pad">
    <form action="reportsv2.php?action=takereport" enctype="multipart/form-data" method="post" id="report_table">
        <div>
            <input type="hidden" name="submit" value="true" />
            <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
            <input type="hidden" name="torrentid" value="<?=$TorrentID?>" />
        </div>
        <table>
            <tr>
                <td class="label">Torrent :</td>
                <td><a href="torrents.php?id=<?=$GroupID?>"> <?=$TorrentName?> </a></td>
            </tr>
            <tr>
                <td class="label">Reason :</td>
                <td>
                    <select id="type" name="type" onchange="ChangeReportType()">
<?php
        $TypeList = $Types;
        $Priorities = array();
        foreach ($TypeList as $Key => $Value) {
            $Priorities[$Key] = $Value['priority'];
        }
        array_multisort($Priorities, SORT_ASC, $TypeList);
    foreach ($TypeList as $Type => $Data) {
?>
                        <option value="<?=$Type?>"><?=$Data['title']?></option>
<?php  } ?>
                    </select>
                </td>
            </tr>
        </table>
            <p><strong>Please give as much as information as you can to help us resolve this quickly.</strong></p>
            <div id="dynamic_form">
                <?php
                /*
                 * THIS IS WHERE SEXY AJAX COMES IN
                 * The following malarky is needed so that if you get sent back here the fields are filled in
                 */
                ?>
                <input id="sitelink" type="hidden" name="sitelink" size="50" value="<?=(!empty($_POST['sitelink']) ? display_str($_POST['sitelink']) : '')?>" />
                <input id="image" type="hidden" name="image" size="50" value="<?=(!empty($_POST['image']) ? display_str($_POST['image']) : '')?>" />
                <input id="track" type="hidden" name="track" size="8" value="<?=(!empty($_POST['track']) ? display_str($_POST['track']) : '')?>" />
                <input id="link" type="hidden" name="link" size="50" value="<?=(!empty($_POST['link']) ? display_str($_POST['link']) : '')?>" />
                <input id="extra" type="hidden" name="extra" value="<?=(!empty($_POST['extra']) ? display_str($_POST['extra']) : '')?>" />

                <script type="text/javascript">ChangeReportType();</script>
            </div>

        <div class="pad center">
            <input type="submit" value="Submit report" />
        </div>
    </form>
        </div>
</div>
<?php
show_footer();
