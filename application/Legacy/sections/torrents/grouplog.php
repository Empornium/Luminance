<?php
$GroupID = $_GET['groupid'];
if (!is_integer_string($GroupID)) { error(404); }

$bbCode = new \Luminance\Legacy\Text;

show_header("History for Group $GroupID");

$Groups = get_groups([$GroupID], true, true);
if (!empty($Groups['matches'][$GroupID])) {
    $Group = $Groups['matches'][$GroupID];
    $Title = '<a href="/torrents.php?id='.$GroupID.'">'.display_str($Group['Name']).'</a>';
    $Torrents = array_values($Group['Torrents'])[0];
    $tID = $Torrents['ID'];
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
    $extraWhere = '';
    if (!$activeUser['DisplayStaff']=='1' && !$activeUser['SupportFor']!='') {
        $extraWhere = "AND Hidden='0'";
    }

    $logs = $master->db->rawQuery(
        "SELECT TorrentID,
                t.Name,
                g.UserID,
                Username,
                Info,
                g.Time
           FROM group_log AS g
      LEFT JOIN users AS u ON u.ID = g.UserID
      LEFT JOIN torrents_group AS t ON t.ID = g.GroupID
          WHERE GroupID = ? {$extraWhere}
       ORDER BY Time DESC",
        [$GroupID]
    )->fetchAll(\PDO::FETCH_NUM);

    foreach ($logs as $log) {
    list($torrentID, $Name, $userID, $Username, $Info, $time) = $log;
?>
        <tr class="rowa">
            <td><?=$time?></td>
            <td><?=$Name?></td>

<?php
            if ($AuthorID == $userID) {
                $TorrentUsername = torrent_username($userID, $IsAnon);
            } else {
                $TorrentUsername = format_username($userID);
            }
?>
            <td><?=$TorrentUsername?></td>
            <td><?=$bbCode->full_format($Info)?></td>
        </tr>
<?php
    }
?>
    </table>
</div>
<?php
show_footer();
