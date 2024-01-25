<?php
//*********************************************************************//
//~~~~~~~~~~~~~~~~~~~~~~~~~~~~ Upload form ~~~~~~~~~~~~~~~~~~~~~~~~~~~~//
// This page relies on the TORRENT_FORM class. All it does is call	 //
// the necessary functions.											//
//---------------------------------------------------------------------//
// $Properties, $Err are set in takeupload.php, and                     //
// are only used when the form doesn't validate and this page must be  //
// called again.													   //
//*********************************************************************//

ini_set('max_file_uploads', '100');
show_header('Upload', 'upload,bbcode,jquery');

$TemplateID = null;
if (empty($Properties) && !empty($_POST['fill']) && is_integer_string($_POST['template']) && check_perms('site_use_templates')) {
    /* -------  Get template ------- */
    $TemplateID = (int) $_POST['template'];
    $Properties = $master->cache->getValue('template_' . $TemplateID);
    if ($Properties === FALSE) {
        $Properties = $master->db->rawQuery(
            "SELECT t.ID,
                    t.UserID,
                    t.Name,
                    t.Title,
                    t.CategoryID AS Category,
                    t.Title,
                    t.Image,
                    t.Body AS GroupDescription,
                    t.Taglist AS TagList,
                    t.TimeAdded,
                    t.Public,
                    u.Username AS Authorname
               FROM upload_templates as t
          LEFT JOIN users AS u ON u.ID = t.UserID
              WHERE t.ID = ?",
            [$TemplateID]
        )->fetch(\PDO::FETCH_BOTH);
        if ($Properties) {
            $Properties['TemplateFooter'] = "[bg=#0074b7][bg=#0074b7,90%][color=white][align=right][b][i][font=Courier New]{$Properties['Name']} template by {$Properties['Authorname']}[/font][/i][/b][/align][/color][/bg][/bg]";
            $Properties['TemplateID'] = $TemplateID;
            $master->cache->cacheValue('template_' .$TemplateID, $Properties, 96400 * 7);
        } else { // catch the case where a public template has been unexpectedly removed but left in a random users cache
            $master->cache->deleteValue('templates_ids_' .$activeUser['ID']); // remove from their template list
            $Err = "That template has been deleted - sorry!";
        }
    }
    if ($Properties) {
        // only the uploader can use this to prefill (if not a public template)
        if ($Properties['Public']==0 && $Properties['UserID'] != $activeUser['ID']) {
            unset($Properties);
        }
    }

} elseif (empty($Properties) && !empty($_GET['groupid']) && is_integer_string($_GET['groupid'])) {
    $Properties = $master->db->rawQuery(
        "SELECT tg.ID as GroupID,
                tg.NewCategoryID AS Category,
                tg.Name AS Title,
                tg.Image AS Image,
                tg.Body AS GroupDescription,
                t.UserID
           FROM torrents_group AS tg
      LEFT JOIN torrents AS t ON t.GroupID = tg.ID
          WHERE tg.ID = ?",
        [$_GET['groupid']]
    )->fetch(\PDO::FETCH_BOTH);
    if ($master->db->foundRows()) {
        // only the uploader can use this to prefill
        if ($Properties['UserID'] != $activeUser['ID']) {
            unset($Properties);
            unset($_GET['groupid']);
        } else {
            $tagList = $master->db->rawQuery(
                "SELECT GROUP_CONCAT(tags.Name SEPARATOR ', ') AS TagList
                   FROM torrents_tags AS tt JOIN tags ON tags.ID = tt.TagID
                  WHERE tt.GroupID = ?",
                [$_GET[groupid]]
            )->fetchColumn();

            $Properties['TagList'] = $tagList;
        }
    } else {
        unset($_GET['groupid']);
    }
    if (!empty($_GET['requestid']) && is_integer_string($_GET['requestid'])) {
        $Properties['RequestID'] = $_GET['requestid'];
    }
} elseif (empty($Properties) && !empty($_GET['requestid']) && is_integer_string($_GET['requestid'])) {
    include(SERVER_ROOT . '/Legacy/sections/requests/functions.php');
    $Properties = $master->db->rawQuery(
        "SELECT ID AS RequestID,
                CategoryID,
                Title,
                Image
           FROM requests
          WHERE ID = ?",
        [$_GET['requestid']]
    )->fetch(\PDO::FETCH_BOTH);

    $Properties['TagList'] = implode(" ", get_request_tags($_GET['requestid']));
}

$TorrentForm = new Luminance\Legacy\TorrentForm(($Properties ?? false), ($Err ?? false));

if (!isset($bbCode)) {
    $bbCode = new \Luminance\Legacy\Text;
}

/* -------  Draw a box with do_not_upload list  -------   */
$DNU = $master->cache->getValue('do_not_upload_list');
if ($DNU === FALSE) {
    $DNU = $master->db->rawQuery(
        "SELECT Name,
                Comment,
                Time
           FROM do_not_upload
       ORDER BY Time"
    )->fetchAll(\PDO::FETCH_BOTH);
    $master->cache->cacheValue('do_not_upload_list', $DNU);
}
list($Name, $Comment, $Updated) = end($DNU);
reset($DNU);
$NewDNU = $master->db->rawQuery(
    "SELECT IF(MAX(Time) < '{$Updated}' OR MAX(Time) IS NULL, 1, 0)
       FROM torrents
      WHERE UserID = ?",
    [$activeUser['ID']]
)->fetchColumn();
// test $HideDNU first as it may have been passed from upload_handle
if (!($HideDNU ?? false)) {
    $HideDNU = check_perms('torrent_hide_dnu') || !$NewDNU;
} else {
    $HideDNU = false;
}
?>


<div class="thin">
    <h2>Upload torrent</h2>
    <div class="head">Do not upload from the following list</div>
    <div class="box pad">
        <span style="float:right;clear:right">
            <p><?= $NewDNU ? '<strong class="important_text">' : '' ?>Last Updated: <?= time_diff($Updated) ?><?= $NewDNU ? '</strong>' : '' ?></p>
        </span>

        <p>The following releases are currently forbidden from being uploaded to the site. Make sure you have read the list.
            <?php  if ($HideDNU) { ?>
                <span id="showdnu"><a href="#" onclick="$('#dnulist').toggle(); this.innerHTML=(this.innerHTML=='(Hide)'?'(Show)':'(Hide)'); return false;">(Show)</a></span>
<?php  } ?>
        </p>
        <table id="dnulist" class="<?= ($HideDNU ? 'hidden' : '') ?>" style="">
            <tr class="colhead_dark">
                <td width="50%"><strong>Name</strong></td>
                <td><strong>Comment</strong></td>
            </tr>
            <?php
            foreach ($DNU as $BadUpload) {
                list($Name, $Comment, $Updated) = $BadUpload;
                ?>
                <tr>
                    <td><?= $bbCode->full_format($Name) ?></td>
                    <td><?= $bbCode->full_format($Comment) ?></td>
                </tr>
    <?php  } ?>
        </table>
    </div>
    <?php
    /* -------  Draw a box with imagehost whitelist  ------- */
    $Whitelist = $master->repos->imagehosts->find("Hidden='0'", null, 'Time DESC', null, 'imagehost_whitelist');
    list($Updated, $NewWL) = $master->db->rawQuery(
        "SELECT MAX(iw.Time),
                IF(MAX(t.Time) < MAX(iw.Time) OR MAX(t.Time) IS NULL, 1, 0)
           FROM imagehost_whitelist as iw
      LEFT JOIN torrents AS t ON t.UserID = ?",
        [$activeUser['ID']]
    )->fetch(\PDO::FETCH_NUM);
// test $HideWL first as it may have been passed from upload_handle
    if (!($HideWL ?? false)) {
        $HideWL = check_perms('torrent_hide_imagehosts') || !$NewWL;
    } else {
        $HideWL = false;
    }
    ?>
    <div class="head">Approved Imagehosts</div>
    <div class="box pad">
        <span style="float:right;clear:right"><p><?=$NewWL ? '<strong class="important_text">' : '' ?>Last Updated: <?= time_diff($Updated) ?><?= $NewWL ? '</strong>' : '' ?></p></span>

        <p>You must use one of the following approved imagehosts for all images.
<?php  if ($HideWL) { ?>
                <span><a href="#" onclick="$('#whitelist').toggle(); this.innerHTML=(this.innerHTML=='(Hide)'?'(Show)':'(Hide)'); return false;">(Show)</a></span>
<?php  } ?>
        </p>
        <table id="whitelist" class="<?= ($HideWL ? 'hidden' : '') ?>" style="">
            <tr class="colhead_dark">
                <td width="50%"><strong>Imagehost</strong></td>
                <td><strong>Comment</strong></td>
            </tr>
<?php
foreach ($Whitelist as $ImageHost) { ?>
          <tr>
              <td><?=$bbCode->full_format($ImageHost->Imagehost)?>
  <?php
  // if a goto link is supplied and is a validly formed url make a link icon for it
  if (!empty($ImageHost->Link) && $bbCode->valid_url($ImageHost->Link)) {
      ?><a href="<?= $ImageHost->Link ?>"  target="_blank"><img src="<?=STATIC_SERVER?>common/symbols/offsite.gif" width="16" height="16" style="" alt="Goto <?= $ImageHost->Imagehost ?>" /></a>
  <?php  } // endif has a link to imagehost  ?>
              </td>
              <td><?=$bbCode->full_format($ImageHost->Comment)?></td>
          </tr>
  <?php  } ?>
        </table>
    </div>
<?php
    if (check_perms('site_use_templates')) {
        $CanDelAny = check_perms('site_delete_any_templates')?'1':'0';
?>
        <div class="head">Templates</div>
        <div class="box pad shadow">
            <form action="" class="center" enctype="multipart/form-data"  method="post" onsubmit="return ($('#template').raw().value!=0);">
                <div style="margin:5px auto 10px;" class="nobr center">
                    <label for="template">select template: </label>
                    <div id="template_container" style="display: inline-block">
<?php
                        echo get_templatelist_html($activeUser['ID'], $TemplateID);
?>

                    </div>
                    <input type="submit" name="fill" id="fill" value="fill from" disabled="disabled" title="Fill the upload form from selected template" />
                    <input type="button" onclick="DeleteTemplate(<?=$CanDelAny?>);" name="delete" id="delete" value="delete" disabled="disabled" title="Delete selected template" />
                    <input type="button" onclick="OverwriteTemplate(<?=$CanDelAny?>);" name="save" id="save" value="save over" disabled="disabled" title="Save current form as selected template (overwrites data in this template)" />
                </div>
                <div style="margin:10px auto 5px;" class="nobr center">

<?php           if (check_perms('site_make_private_templates')) {
                    $addsep=true; ?>
                    <a href="#" onclick="AddTemplate(<?=$CanDelAny?>,0);" title="Make a private template from the details currently in the form">Add Private Template</a>
<?php           }
            if (check_perms('site_make_public_templates')) {
                 if ($addsep) echo "&nbsp;&nbsp;&nbsp|&nbsp;&nbsp;&nbsp;";  ?>
                    <a href="#" onclick="AddTemplate(<?=$CanDelAny?>,1);" title="Make a public template from the details currently in the form">Add Public Template</a>
<?php           }   ?>
                </div>
            </form>
        </div>
        <script type="text/javascript">//<![CDATA[
        function SynchTemplates()
        {
            SelectTemplate(<?=$CanDelAny?>);
        }

            document.addEventListener('LuminanceLoaded', function() {
                addDOMLoadEvent(SynchTemplates);
            });
        //]]></script>

        <script type="text/javascript">//<![CDATA[
            function change_tagtext()
            {
                var tags = [];
        <?php
        foreach ($openCategories as $cat) {
            echo 'tags[' . $cat['id'] . ']="' . $cat['tag'] . '"' . ";\n";
        }
        ?>
                if ($('#category').raw().value == 0) {
                    $('#tagtext').html("");
                } else {
                    $('#tagtext').html("<strong>The tag "+tags[$('#category').raw().value]+" will be added automatically.</strong>");
                }
            }
        <?php
        if (!empty($Properties))
            echo "document.addEventListener('LuminanceLoaded', function() {addDOMLoadEvent(SynchInterface);});";
        ?>
        //]]></script>
<?php
    }

?>
    <a id="startform"></a>
<?php

/* -------  Draw upload torrent form  ------- */
$TorrentForm->head();
$TorrentForm->simple_form();
$TorrentForm->foot();
?>
</div>
<?php
show_footer();
