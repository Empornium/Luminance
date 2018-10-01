<?php
if (!check_perms('site_mass_pm_snatchers')) {
    error(403);
}

if ( !isset($_GET['torrentid']) || !is_number($_GET['torrentid']) ) {
    error(0);
}

$TorrentID = $_GET['torrentid'];

$DB->query("SELECT
        tg.Name AS Title,
        t.GroupID
        FROM torrents AS t
        JOIN torrents_group AS tg ON tg.ID=t.GroupID
        WHERE t.ID='$TorrentID'");

list($Properties) = $DB->to_array(false,MYSQLI_BOTH);

if (!$Properties) { error(404); }

if (isset($_GET['type']) && in_array($_GET['type'], ['reseed'])) {
    $Type = $_GET['type'];
} else {
    $Type = 'message';
}

switch ($Type) {
    case 'reseed':
        $MessageTitle = "Re-seed request for torrent ".$Properties['Title'];
        $Message  = "Hi [you],\n\n";
        $Message .= "This is a re-seed request for [torrent]{$Properties['GroupID']}[/torrent], which you downloaded. The torrent is now un-seeded, and we need your help to resurrect it!\n\n";
        $Message .= "The exact process for re-seeding a torrent is slightly different for each client, but the concept is the same. The idea is to download the .torrent file and open it in your client, and point your client to the location where the data files are, then initiate a hash check.\n\n";
        $Message .= "Thanks!  :emplove:";
        break;
    default:
        $MessageTitle = "Message to all snatchers of '{$Properties['Title']}'";
        $Message = "Torrent: [url=/torrents.php?id={$Properties['GroupID']}]{$Properties['Title']}[/url]";
}

// Legacy code, I'm not sure where $Body and $Subject are supposed to come from
if (!empty($Body)) $Message = $Body;
if (!empty($Subject)) $MessageTitle = $Subject;

show_header('Send Mass PM', 'upload,bbcode,inbox');

$Text = new Luminance\Legacy\Text;

?>
<div class="thin">
    <h2>Send PM To All Snatchers Of "<?=$Properties['Title']?>"</h2>

    <div id="preview" class="hidden"></div>
    <form action="torrents.php" method="post" id="messageform">
        <div id="quickpost">
            <br/>
            <div class="box pad">
                <input type="hidden" name="action" value="takemasspm" />
                <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                <input type="hidden" name="torrentid" value="<?=$TorrentID?>" />
                <input type="hidden" name="groupid" value="<?=$Properties['GroupID']?>" />
                <h3>Subject</h3>
                <input type="text" name="subject" class="long" value="<?= display_str($MessageTitle) ?>"/>
                <br />
                <h3>Message</h3>
                <?php  $Text->display_bbcode_assistant("message", get_permissions_advtags($LoggedUser['ID'], $LoggedUser['CustomPermissions'])); ?>
                <textarea id="message" name="message" class="long" rows="10"><?= display_str($Message) ?></textarea>
            </div>
        </div>
        <div class="center">
             <input type="button" id="previewbtn" value="Preview" onclick="Inbox_Preview();" />
             <input type="submit" value="Send Mass PM" />
        </div>
    </form>
</div>
<?php
show_footer();
