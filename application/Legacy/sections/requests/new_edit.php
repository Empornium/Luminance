<?php

/*
 * Yeah, that's right, edit and new are the same place again.
 * It makes the page uglier to read but ultimately better as the alternative means
 * maintaining 2 copies of almost identical files.
 */

$bbCode = new \Luminance\Legacy\Text;

if (!check_perms('site_submit_requests')) error(403);

$NewRequest   = ($_GET['action'] == "new" ? true : false);

if (!$NewRequest) {
    $RequestID = $_GET['id'];
    if (!is_integer_string($RequestID)) {
        error(404);
    }
}

if ($NewRequest && ($activeUser['BytesUploaded'] < 250*1024*1024 || !check_perms('site_submit_requests'))) {
    error('You do not have enough uploaded to make a request.');
}

if (!$NewRequest) {
    if (empty($ReturnEdit)) {

        $Request = get_requests([$RequestID]);
        $Request = $Request['matches'][$RequestID];
        if (empty($Request)) {
            error(404);
        }

        list(
            'ID'          => $RequestID,
            'UserID'      => $RequestorID,
            'Username'    => $RequestorName,
            'TimeAdded'   => $timeAdded,
            'LastVote'    => $LastVote,
            'CategoryID'  => $CategoryID,
            'Title'       => $Title,
            'Image'       => $Image,
            'Description' => $Description,
            'FillerID'    => $FillerID,
            'TorrentID'   => $torrentID,
            'TimeFilled'  => $timeFilled,
            'GroupID'     => $GroupID,
            'UploaderID'  => $UploaderID,
            'Anonymous'   => $IsAnon,
            'Tags'        => $Tags
        ) = $Request;
        $VoteArray = get_votes_array($RequestID);
        $VoteCount = count($VoteArray['Voters']);

        $IsFilled = !empty($torrentID);
        $CategoryName = $openCategories[$CategoryID]['name'];
        $ProjectCanEdit = (check_perms('site_project_team') && !$IsFilled && (($CategoryID == 0)));
        $CanEdit = ((!$IsFilled && $activeUser['ID'] == $RequestorID && $VoteCount < 2) || $ProjectCanEdit || check_perms('site_moderate_requests'));

        if (!$CanEdit) {
            error(403);
        }

        $Tags = implode(" ", $Request['Tags']);
    }
}

if ($NewRequest && !empty($_GET['groupid']) && is_integer_string($_GET['groupid'])) {
    list($Title, $Image, $Tags) = $master->db->rawQuery(
        "SELECT tg.Name,
                tg.Image,
                GROUP_CONCAT(t.Name SEPARATOR ', '),
           FROM torrents_group AS tg
           JOIN torrents_tags AS tt ON tt.GroupID = tg.ID
           JOIN tags AS t ON t.ID = tt.TagID
          WHERE tg.ID = ?",
        [$_GET['groupid']]
    )->fetch(\PDO::FETCH_NUM);
    if ($master->db->foundRows() > 0) {
        $GroupID = trim($_REQUEST['groupid']);
    }
} elseif ($NewRequest) {
    if (isset($_GET['title'])) {
        $Title = $_GET['title'];
    }

    if (isset($_GET['image'])) {
        $Image = $_GET['image'];
    }

    if (isset($_GET['tags'])) {
        $Tags = $_GET['tags'];
    }

    if (isset($_GET['description'])) {
        $Description = $_GET['description'];
    }

    if (isset($_GET['bounty'])) {
        $Bounty = $_GET['bounty'];
    }

    if (isset($_GET['category_id'])) {
        $CategoryID = $_GET['category_id'];
    }
}

show_header(($NewRequest ? "Create a request" : "Edit a request"), 'requests,bbcode,jquery,jquery.cookie');
?>
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
    echo "addDOMLoadEvent(SynchInterface);";
?>
//]]></script>

<div class="thin">
    <h2><?=($NewRequest ? "Create a request" : "Edit a request")?></h2>

    <div class="linkbox">
            <a href="/requests.php">[Search requests]</a>
            <a href="/requests.php?type=created">[My requests]</a>
<?php 	 if (check_perms('site_vote')) { ?>
            <a href="/requests.php?type=voted">[Requests I've voted on]</a>
<?php 		}  ?>

    </div>
<?php
    /* -------  Draw a box with imagehost whitelist  ------- */
    $Whitelist = $master->repos->imagehosts->find("Hidden='0'", null, 'Time DESC', null, 'imagehost_whitelist');
    list($Updated, $NewWL) = $master->db->rawQuery(
        "SELECT MAX(iw.Time),
                IF (MAX(t.Time) < MAX(iw.Time) OR MAX(t.Time) IS NULL, 1, 0)
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
      <div class="head"><?=($NewRequest ? "Create New Request" : "Edit Request")?></div>
    <div class="box pad">
        <form action="" method="post" id="request_form" onsubmit="Calculate();">
<?php  if (!$NewRequest) { ?>
                <input type="hidden" name="requestid" value="<?=$RequestID?>" />
<?php  } ?>
                <input type="hidden" name="auth" value="<?=$activeUser['AuthKey']?>" />
                <input type="hidden" name="action" value="<?=$NewRequest ? 'takenew' : 'takeedit'?>" />

            <table>
                <tr>
                    <td colspan="2" class="center">Please make sure your request follows <a href="/articles/view/requests">the request rules!</a></td>
                </tr>
<?php 	if ($NewRequest || $CanEdit) { ?>
                <tr class="pad">
                    <td colspan="2" class="center">
                        <strong class="important_text">NOTE: Requests automatically expire after 90 days. At this time if the bounty has not been filled all outstanding bounties are returned to those who placed them</strong>
                    </td>
                </tr>
                <tr class="hidden_request">
                    <td class="label">
                        Category
                    </td>
                    <td> <div class="box pad hidden"></div>
                        <select id="category" name="category" onchange="change_tagtext();">
                                        <option value="0">---</option>
                                    <?php  foreach ($openCategories as $category) { ?>
                                        <option value="<?=$category['id']?>"<?php
                                            if (isset($CategoryID) && $CategoryID==$category['id']) {
                                                echo ' selected="selected"';
                                            }   ?>><?=$category['name']?></option>
                                    <?php  } ?>
                        </select>
                    </td>
                </tr>
                <tr class="hidden_request">
                    <td class="label">Title</td>
                    <td>
                        <input type="text" name="title" class="long" value="<?=(!empty($Title) ? display_str($Title) : '')?>" />
                    </td>
                </tr>
                <tr id="image_tr">
                            <td class="label">Cover Image</td>
                            <td> <div id="preview_image" class="box pad hidden"></div>
                                <div  id="image" class="hidden_request">
                                    <strong class="hidden_request">Enter the full url for your image.</strong><br/>
                                     <input type="text" id="image_id" class="long" name="image" value="<?=(!empty($Image) ? display_str($Image) : '')?>" />
                                 </div>
                            </td>
                        </div>
                </tr>
<?php 	} ?>
                <tr class="hidden_request">
                    <td class="label">Tags</td>
                    <td>
                    <div id="tagtext"></div>
<?php
    $GenreTags = $master->cache->getValue('genre_tags');
    if (!$GenreTags) {
        $GenreTags = $master->db->rawQuery(
            "SELECT Name
               FROM tags
              WHERE TagType = 'genre'
           ORDER BY Name"
        )->fetchAll(\PDO::FETCH_COLUMN);
        $master->cache->cacheValue('genre_tags', $GenreTags, 3600*6);
    }
?>
                        <select id="genre_tags" name="genre_tags" onchange="add_tag();return false;" >
                            <option>---</option>
<?php 	foreach (display_array($GenreTags) as $Genre) { ?>
                            <option value="<?=$Genre ?>"><?=$Genre ?></option>
<?php 	} ?>
                        </select>

                        <br>
                        <textarea id="taginput" name="taglist" class="medium"><?=(!empty($Tags) ? display_str($Tags) : '')?></textarea>
                        <br>
                        <label class="checkbox_label" title="Toggle autocomplete mode on or off.&#10;When turned off, you can access your browser's form history.">
                            <input id="autocomplete_toggle" type="checkbox" name="autocomplete_toggle" checked/>
                            Autocomplete tags
                        </label>

                        <br />
                    <?php
                                      $taginfo = get_article('tagrulesinline');
                                      if ($taginfo) echo $bbCode->full_format($taginfo, true);
                              ?>
                    </td>
                </tr>
                <tr>
                    <td class="label">Description</td>
                    <td>  <div id="preview_description" class="box pad hidden"></div>
                                    <div  id="editor">
                                         <?php  $bbCode->display_bbcode_assistant("quickcomment", get_permissions_advtags($activeUser['ID'], $activeUser['CustomPermissions'])); ?>
                                        <textarea  id="quickcomment" name="description" class="long" rows="7"><?=(!empty($Description) ? $Description : '')?></textarea>
                                    </div>
                                    <!--<input type="button" id="previewbtn" value="Preview" style="margin-right: 40px;" onclick="Preview_Request();" />-->
                              </td>
                </tr>

<?php 	if ($NewRequest) { ?>
                <tr id="voting" class="hidden_request">
                    <td class="label" id="bounty">Bounty</td>
                    <td>
                        <input type="text" id="amount_box" size="8" value="<?=((!empty($Bounty) ? $Bounty : $master->options->MinCreateBounty) / (1024*1024*1024))?>" onchange="Calculate();" onkeyup="Calculate();" />
                        <select id="unit" name="unit" onchange="Calculate();">
                            <option value='gb'<?=(!empty($_POST['unit']) && $_POST['unit'] == 'gb' ? ' selected="selected"' : '') ?>>GiB</option>
                            <option value='tb'<?=(!empty($_POST['unit']) && $_POST['unit'] == 'tb' ? ' selected="selected"' : '') ?>>TiB</option>
                        </select>
                        <input type="button" value="Preview" onclick="Calculate();"/>
                        <strong id="inform"></strong>
                    </td>
                </tr>
                <tr class="hidden_request">
                    <td class="label">Post request information</td>
                    <td>
                        <input type="hidden" id="amount" name="amount" value="<?=(!empty($Bounty) ? $Bounty : $master->options->MinCreateBounty)?>" />
                        <input type="hidden" id="current_uploaded" value="<?=$activeUser['BytesUploaded']?>" />
                        <input type="hidden" id="current_downloaded" value="<?=$activeUser['BytesDownloaded']?>" />
                        If you add the entered <strong><span id="new_bounty"><?=get_size($master->options->MinCreateBounty);?></span></strong> of bounty, your new stats will be: <br/>
                        Uploaded: <span id="new_uploaded"><?=get_size($activeUser['BytesUploaded'])?></span><br/>
                        Ratio: <span id="new_ratio"><?=ratio($activeUser['BytesUploaded'], $activeUser['BytesDownloaded'])?></span>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" class="center">
                        <input type="button" id="previewbtn" value="Preview" onclick="Preview_Request();" />
                        <input type="submit" id="button_vote" value="Create request" />
                    </td>
                </tr>
<?php 	} else { ?>
                <tr>
                    <td colspan="2" class="center">
                        <input type="submit" id="button_vote" value="Edit request" />
                    </td>
                </tr>
<?php 	} ?>
            </table>
        </form>
    </div>
</div>
<?php
show_footer();
