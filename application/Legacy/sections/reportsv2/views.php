<?php
/*
 * This page is to outline all the sexy views build into reports v2.
 * It's used as the main page as it also lists the current reports by type
 * and also current in progress reports by staff member.
 * All the different views are self explanatory by their names.
 */
if (!check_perms('admin_reports')) {
    error(403);
}

show_header('Reports V2!', 'reportsv2');
include 'header.php';

//Grab owners ID, just for examples
list($OwnerID, $Owner) = $master->db->rawQuery(
    "SELECT ID,
            Username
       FROM users
   ORDER BY ID ASC
      LIMIT 1"
)->fetch(\PDO::FETCH_NUM);
$Owner = display_str($Owner);

?>
<div class="thin">
<h2>Torrent Reports</h2>
<br />
<div class="head">history</div>
<div class="box pad">
    <table><tr><td style="width: 50%;">
<?php
$Results = $master->db->rawQuery(
    "SELECT u.ID,
            u.Username,
            COUNT(r.ID) AS Reports
       FROM reportsv2 AS r
       JOIN users AS u ON u.ID=r.ResolverID
      WHERE r.LastChangeTime > NOW() - INTERVAL 24 HOUR
   GROUP BY r.ResolverID
   ORDER BY Reports DESC"
)->fetchAll(\PDO::FETCH_NUM);
?>
        <strong>Reports resolved in the last 24 hours</strong>
        <table class="border">
            <tr class="colhead">
                <td>Username</td>
                <td>Reports</td>
            </tr>
<?php  foreach ($Results as $Result) {
    list($userID, $Username, $Reports) = $Result;
?>
            <tr>
                <td><a href="/reportsv2.php?view=resolver&amp;id=<?=$userID?>"><?=$Username?></a></td>
                <td><?=$Reports?></td>
            </tr>
<?php  } ?>
        </table>
        <br />
<?php
$Results = $master->db->rawQuery(
    "SELECT u.Username,
            COUNT(r.ID) AS Reports
       FROM reportsv2 AS r
       JOIN users AS u ON u.ID=r.ResolverID
      WHERE r.LastChangeTime > NOW() - INTERVAL 1 WEEK
   GROUP BY r.ResolverID
   ORDER BY Reports DESC"
)->fetchAll(\PDO::FETCH_NUM);
?>
        <strong>Reports resolved in the last week</strong>
        <table class="border">
            <tr class="colhead">
                <td>Username</td>
                <td>Reports</td>
            </tr>
<?php  foreach ($Results as $Result) {
    list($Username, $Reports) = $Result;
?>
            <tr>
                <td><?=$Username?></td>
                <td><?=$Reports?></td>
            </tr>
<?php  } ?>
        </table>
        <br />
<?php
$Results = $master->db->rawQuery(
    "SELECT u.Username,
            COUNT(r.ID) AS Reports
       FROM reportsv2 AS r
       JOIN users AS u ON u.ID=r.ResolverID
      WHERE r.LastChangeTime > NOW() - INTERVAL 1 MONTH
   GROUP BY r.ResolverID
   ORDER BY Reports DESC"
)->fetchAll(\PDO::FETCH_NUM);
?>
        <strong>Reports resolved in the last month</strong>
        <table class="border">
            <tr class="colhead">
                <td>Username</td>
                <td>Reports</td>
            </tr>
<?php  foreach ($Results as $Result) {
    list($Username, $Reports) = $Result;
?>
            <tr>
                <td><?=$Username?></td>
                <td><?=$Reports?></td>
            </tr>
<?php  } ?>
        </table>
        <br />
<?php
$Results = $master->db->rawQuery(
    "SELECT u.Username,
            COUNT(r.ID) AS Reports
       FROM reportsv2 AS r
       JOIN users AS u ON u.ID=r.ResolverID
   GROUP BY r.ResolverID
   ORDER BY Reports DESC"
)->fetchAll(\PDO::FETCH_NUM);
?>
        <strong>Reports resolved since reportsv2 (2009-07-27)</strong>
        <table class="border">
            <tr class="colhead">
                <td>Username</td>
                <td>Reports</td>
            </tr>
<?php  foreach ($Results as $Result) {
    list($Username, $Reports) = $Result;
?>
            <tr>
                <td><?=$Username?></td>
                <td><?=$Reports?></td>
            </tr>
<?php  } ?>
        </table>
        <br />
        <h3>Different view modes by person</h3>
        <br />
        <strong>By ID of torrent reported.</strong>
        <ul>
            <li>
                <a href="/reportsv2.php?view=torrent&amp;id=1">Reports of torrents with ID = 1</a>
            </li>
        </ul>
        <br />
        <strong>By GroupID of torrent reported.</strong>
        <ul>
            <li>
                <a href="/reportsv2.php?view=group&amp;id=1">Reports of torrents within the group with ID = 1</a>
            </li>
        </ul>
        <br />
        <strong>By Report ID.</strong>
        <ul>
            <li>
                <a href="/reportsv2.php?view=report&amp;id=1">The report with ID = 1</a>
            </li>
        </ul>
        <br />
        <strong>By Reporter ID.</strong>
        <ul>
            <li>
                <a href="/reportsv2.php?view=reporter&amp;id=<?=$OwnerID?>">Reports created by <?=$Owner?></a>
            </li>
        </ul>
        <br />
        <strong>By uploader ID.</strong>
        <ul>
            <li>
                <a href="/reportsv2.php?view=uploader&amp;id=<?=$OwnerID?>">Reports for torrents uploaded by <?=$Owner?></a>
            </li>
        </ul>
        <br />
        <strong>By resolver ID.</strong>
        <ul>
            <li>
                <a href="/reportsv2.php?view=resolver&amp;id=<?=$OwnerID?>">Reports for torrents resolved by <?=$Owner?></a>
            </li>
        </ul>
        <br /><!--<br />
        <strong>For browsing anything more complicated than these, use the search feature.</strong>-->
    </td>
    <td style="vertical-align: top;">
<?php
    $Staff = $master->db->rawQuery(
        "SELECT r.ResolverID,
                u.Username,
                COUNT(r.ID) AS Count
           FROM reportsv2 AS r
      LEFT JOIN users AS u ON r.ResolverID=u.ID
          WHERE r.Status = 'InProgress'
       GROUP BY r.ResolverID"
    )->fetchAll(\PDO::FETCH_ASSOC);
?>
        <strong>Currently assigned reports by staff member</strong>
        <table>
            <tr>
                <td class="colhead">Staff member</td>
                <td class="colhead">Current Count</td>
            </tr>

    <?php
        foreach ($Staff as $Array) {	?>
            <tr>
                <td>
                    <a href="/reportsv2.php?view=staff&amp;id=<?=$Array['ResolverID']?>"><?=display_str($Array['Username'])?>'s reports</a>
                </td>
                <td><?=$Array['Count']?></td>
            </tr>
    <?php
        }
    ?>
        </table>
        <br />
        <h3>Different view modes by report type</h3>
<?php
    $Current = $master->db->rawQuery(
        "SELECT r.Type,
                COUNT(r.ID) AS Count
           FROM reportsv2 AS r
          WHERE r.Status='New'
       GROUP BY r.Type"
    )->fetchAll(\PDO::FETCH_ASSOC);
    if (!empty($Current)) {
?>
        <table>
            <tr>
                <td class="colhead">Type</td>
                <td class="colhead">Current Count</td>
            </tr>
<?php
        foreach ($Current as $Array) {
            //Ugliness
                if (!empty($types[$Array['Type']])) {
                    $Title = $types[$Array['Type']]['title'];
                }
?>
            <tr>
                <td>
                    <a href="/reportsv2.php?view=type&amp;id=<?=display_str($Array['Type'])?>"><?=display_str($Title)?></a>
                </td>
                <td>
                    <?=$Array['Count']?>
                </td>
            </tr>
<?php
        }
    }
?>
        </table>
    </td></tr></table>
</div>
</div>
<?php
show_footer();
