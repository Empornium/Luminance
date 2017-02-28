<?php
$GroupID = $_GET['groupid'];
if (!is_number($GroupID)) { error(404); }

include(SERVER_ROOT.'/classes/class_text.php');
$Text = new TEXT;

show_header("History for Group $GroupID");

$Groups = get_groups(array($GroupID), true, true);
if (!empty($Groups['matches'][$GroupID])) {
    $Group = $Groups['matches'][$GroupID];
    $Title = '<a href="torrents.php?id='.$GroupID.'">'.$Group['Name'].'</a>';
    list($tID, $Torrents) = each($Group['Torrents']);
    $IsAnon = $Torrents['Anonymous'];
    $AuthorID = $Torrents['UserID'];
} else {
    $Title = "Group $GroupID";
}

?>

<div class="thin">
    <h2>History for <?=$Title?></h2>

    <table>
        <tr class="colhead">
            <td>Date</td>
            <td>Torrent</td>
            <td>User</td>
            <td>Info</td>
        </tr>
<?php
    if (!$LoggedUser['DisplayStaff']=='1' && !$LoggedUser['SupportFor']!='')
        $XTRAWHERE = "AND Hidden='0'";

    $Log = $DB->query("SELECT TorrentID, t.Name, g.UserID, Username, Info, g.Time
                         FROM group_log AS g
                    LEFT JOIN users_main AS u ON u.ID=g.UserID
                    LEFT JOIN torrents_group AS t ON t.ID=g.GroupID
                        WHERE GroupID = ".$GroupID."  $XTRAWHERE
                     ORDER BY Time DESC");

    while (list($TorrentID, $Name, $UserID, $Username, $Info, $Time) = $DB->next_record()) {
?>
        <tr class="rowa">
            <td><?=$Time?></td>
            <td><?=$Name?></td>

<?php
            if ($AuthorID == $UserID) {
                $TorrentUsername = torrent_username($UserID, $Username, $IsAnon);
            } else {
                $TorrentUsername = format_username($UserID, $Username);
            }
?>
            <td><?=$TorrentUsername?></td>
            <td><?=$Text->full_format($Info)?></td>
        </tr>
<?php
    }
?>
    </table>
</div>
<?php
show_footer();
