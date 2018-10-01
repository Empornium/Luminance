<?php
show_header();
?>
<div class="thin">
    <h2>testing ducky award</h2>

    <div class="head"></div>
    <div class="box pad shadow">
        <form action="" method="post" onsubmit="return Validate_Form('message','quickpost')" style="display: block; text-align: center;">
            <input type="hidden" name="action" value="public_sandbox" />
            <input type="hidden" name="simulate" value="1" />
            <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
            <div>
                <input type="submit" value="Run Ducky Award Schedule" tabindex="1" />
            </div>
        </form>
    </div>
<?php
    $text = '';
    $title = '';
    if ($_POST['simulate']=='1') {
        // do the schedule
        $results = award_ducky_pending();
        $title = "Ran the schedule: ".count($results)." awards made";
        $text = print_r($results, true);
    } else {
        // preview
        $minSnatched=1;
        // get all the users who have a pending torrent ducky award - torrents that have been okayed that now have Snatched>1
        $pending = $master->db->raw_query("SELECT t.ID, t.UserID
                                             FROM torrents AS t
                                             JOIN torrents_awards AS ta ON ta.TorrentID=t.ID
                                            WHERE ta.Ducky = '0'
                                              AND t.Snatched >= :minsnatched",
                                                  [':minsnatched' => $minSnatched])->fetchAll(\PDO::FETCH_ASSOC);

        $title = "Preview of ".count($pending)." awards that will be made when the schedule runs";
        $text = print_r($pending, true);
    }


?>
    <div class="head"><?=$title?></div>
    <div class="box pad shadow">
        <?=$text?>
    </div>

</div>
<?php
show_footer();
