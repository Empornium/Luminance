<?php
if (!check_perms('admin_reports')) {
    error(403);
}

show_header('Other reports stats');

?>
<div class="thin">
<h2>Other reports stats!</h2>
<br />
<div class="box pad thin" style="padding: 0px 0px 0px 20px; margin-left: auto; margin-right: auto">
<?php
$Results = $master->db->rawQuery(
    "SELECT u.Username,
            COUNT(r.ID) AS Reports
       FROM reports AS r
       JOIN users AS u ON u.ID=r.ResolverID
      WHERE r.ReportedTime > '2009-08-21 22:39:41'
        AND r.ReportedTime > NOW() - INTERVAL 24 HOUR
   GROUP BY r.ResolverID
   ORDER BY Reports DESC"
)->fetchAll(\PDO::FETCH_NUM);
?>
        <table>
        <tr>
        <td class="label"><strong>Reports resolved in the last 24h</strong></td>
        <td>
        <table style="width: 50%; margin-left: auto; margin-right: auto;" class="border">
            <tr>
                <td class="head colhead_dark">Username</td>
                <td class="head colhead_dark">Reports</td>
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
        </td>
        </tr>
        <tr>
<?php
$Results = $master->db->rawQuery(
    "SELECT u.Username,
            COUNT(r.ID) AS Reports
       FROM reports AS r
       JOIN users AS u ON u.ID=r.ResolverID
      WHERE r.ReportedTime > '2009-08-21 22:39:41'
        AND r.ReportedTime > NOW() - INTERVAL 1 WEEK
   GROUP BY r.ResolverID
   ORDER BY Reports DESC"
)->fetchAll(\PDO::FETCH_NUM);
?>
        <td class="label"><strong>Reports resolved in the last week</strong></td>
        <td>
        <table style="width: 50%; margin-left: auto; margin-right: auto;" class="border">
            <tr>
                <td class="head colhead_dark">Username</td>
                <td class="head colhead_dark">Reports</td>
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
        </td>
        <tr>
<?php
$Results = $master->db->rawQuery(
    "SELECT u.Username,
            COUNT(r.ID) AS Reports
       FROM reports AS r
       JOIN users AS u ON u.ID=r.ResolverID
      WHERE r.ReportedTime > '2009-08-21 22:39:41'
        AND r.ReportedTime > NOW() - INTERVAL 1 MONTH
   GROUP BY r.ResolverID
   ORDER BY Reports DESC"
)->fetchAll(\PDO::FETCH_NUM);
?>
        <td class="label"><strong>Reports resolved in the last month</strong></td>
        <td>
        <table style="width: 50%; margin-left: auto; margin-right: auto;" class="border">
            <tr>
                <td class="head colhead_dark">Username</td>
                <td class="head colhead_dark">Reports</td>
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
        </td>
        </tr>
        <tr>
<?php
$Results = $master->db->rawQuery(
    "SELECT u.Username,
            COUNT(r.ID) AS Reports
       FROM reports AS r
       JOIN users AS u ON u.ID=r.ResolverID
   GROUP BY r.ResolverID
   ORDER BY Reports DESC"
)->fetchAll(\PDO::FETCH_NUM);
?>
        <td class="label"><strong>Reports resolved since 'other' reports (2009-08-21)</strong></td>
        <td>
        <table style="width: 50%; margin-left: auto; margin-right: auto;" class="border">
            <tr>
                <td class="head colhead_dark">Username</td>
                <td class="head colhead_dark">Reports</td>
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
        </td>
        </tr>
        </table>
</div>
</div>
<?php
show_footer();
