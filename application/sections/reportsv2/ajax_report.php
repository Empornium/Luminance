<?php
/*
 * The backend to changing the report type when making a report.
 * It prints out the relevant report_messages from the array, then
 * prints the relevant report_fields and whether they're required.
 */
authorize();

if (array_key_exists($_POST['type'], $Types)) {
    $ReportType = $Types[$_POST['type']];
} else {
    echo 'HAX IN REPORT TYPE';
    die();
}

if ( is_array($ReportType['article'])  ) {
?>
<p><strong>Relevant Rules section: <a href="articles.php?topic=<?=$ReportType['article'][0]?>" title="The rule infingement you are reporting"><?=$ReportType['article'][1]?></a></strong>
</p>
<br/>
    <?php
}

foreach ($ReportType['report_messages'] as $Message) {
?>
    <h3><?=$Message?></h3>
<?php
}


if ($ReportType['resolve_options']['bounty'] > 0) {
?>
<p><strong class="anchor">There is a bounty paid for valid <?=$ReportType['title']?> reports of <?=$ReportType['resolve_options']['bounty']?> credits.</strong>
</p>
<br/>
    <?php
}


?>
<br />
<table cellpadding="3" cellspacing="1" border="0" class="noborder" width="100%">
<?php
if (array_key_exists('image', $ReportType['report_fields'])) {
?>
    <tr>
        <td class="label">
            Image(s)<?=($ReportType['report_fields']['image'] == '1' ? ' <strong><br/><font color="red">(Required)</font></strong>' : '')?>
        </td>
        <td>
            <input id="image" type="text" name="image" class="long" value="<?=(!empty($_POST['image']) ? display_str($_POST['image']) : '')?>" />
        </td>
    </tr>
<?php
}
if (array_key_exists('track', $ReportType['report_fields'])) {
?>
    <tr>
        <td class="label">
            Track Number(s)<?=($ReportType['report_fields']['track'] == '1' || $ReportType['report_fields']['track'] == '2' ? ' <strong><br/><font color="red">(Required)</font></strong>' : '')?>
        </td>
        <td>
            <input id="track" type="text" name="track" class="long" value="<?=(!empty($_POST['track']) ? display_str($_POST['track']) : '')?>" /><?=($ReportType['report_fields']['track'] == '1' ? '<input id="all_tracks" type="checkbox" onclick="AllTracks()" /> All' : '')?>
        </td>
    </tr>
<?php
}
if (array_key_exists('link', $ReportType['report_fields'])) {
?>
    <tr>
        <td class="label">
            Link(s) to external source<?=($ReportType['report_fields']['link'] == '1' ? ' <strong><br/><font color="red">(Required)</font></strong>' : '')?>
        </td>
        <td>
            <input id="link" type="text" name="link" class="long" value="<?=(!empty($_POST['link']) ? display_str($_POST['link']) : '')?>" />
        </td>
    </tr>
<?php
}
if (array_key_exists('sitelink', $ReportType['report_fields'])) {
?>
    <tr>
        <td class="label">
            Permalink to <strong>relevant other</strong> torrent(s)<?=($ReportType['report_fields']['sitelink'] == '1' ? ' <strong><br/><font color="red">(Required)</font></strong>' : '')?>
        </td>
        <td>
            <input id="sitelink" type="text" name="sitelink" class="long" value="<?=(!empty($_POST['sitelink']) ? display_str($_POST['sitelink']) : '')?>" />
        </td>
    </tr>

<?php
}
?>
    <tr>
        <td class="label">
            Comments <strong><br/><font color="red">(Required)</font></strong>
        </td>
        <td>
            <textarea id="extra" rows="5" class="long" name="extra"><?=display_str($_POST['extra'])?></textarea>
        </td>
    </tr>
</table>
