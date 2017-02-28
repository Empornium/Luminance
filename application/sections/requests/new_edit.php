<?php

/*
 * Yeah, that's right, edit and new are the same place again.
 * It makes the page uglier to read but ultimately better as the alternative means
 * maintaining 2 copies of almost identical files.
 */

include(SERVER_ROOT.'/classes/class_text.php');
$Text = new TEXT;

if(!check_perms('site_submit_requests')) error(403);

$NewRequest = ($_GET['action'] == "new" ? true : false);

if (!$NewRequest) {
    $RequestID = $_GET['id'];
    if (!is_number($RequestID)) {
        error(404);
    }
}

if ($NewRequest && ($LoggedUser['BytesUploaded'] < 250*1024*1024 || !check_perms('site_submit_requests'))) {
    error('You do not have enough uploaded to make a request.');
}

if (!$NewRequest) {
    if (empty($ReturnEdit)) {

        $Request = get_requests(array($RequestID));
        $Request = $Request['matches'][$RequestID];
        if (empty($Request)) {
            error(404);
        }

        list($RequestID, $RequestorID, $RequestorName, $TimeAdded, $LastVote, $CategoryID, $Title, $Image, $Description,
             $FillerID, $FillerName, $TorrentID, $TimeFilled, $GroupID) = $Request;
        $VoteArray = get_votes_array($RequestID);
        $VoteCount = count($VoteArray['Voters']);

        $IsFilled = !empty($TorrentID);
        $CategoryName = $NewCategories[$CategoryID]['name'];
        $ProjectCanEdit = (check_perms('site_project_team') && !$IsFilled && (($CategoryID == 0)));
        $CanEdit = ((!$IsFilled && $LoggedUser['ID'] == $RequestorID && $VoteCount < 2) || $ProjectCanEdit || check_perms('site_moderate_requests'));

        if (!$CanEdit) {
            error(403);
        }

        $Tags = implode(" ", $Request['Tags']);
    }
}

if ($NewRequest && !empty($_GET['groupid']) && is_number($_GET['groupid'])) {
    $DB->query("SELECT
                            tg.Name,
                            tg.Image,
                            GROUP_CONCAT(t.Name SEPARATOR ', '),
                    FROM torrents_group AS tg
                            JOIN torrents_tags AS tt ON tt.GroupID=tg.ID
                            JOIN tags AS t ON t.ID=tt.TagID
                    WHERE tg.ID = ".$_GET['groupid']);
    if (list($Title, $Image, $Tags) = $DB->next_record()) {
        $GroupID = trim($_REQUEST['groupid']);
    }
}

show_header(($NewRequest ? "Create a request" : "Edit a request"), 'requests,bbcode,jquery,jquery.cookie');
?>
<script type="text/javascript">//<![CDATA[
    public function change_tagtext()
    {
        var tags = new Array();
<?php
foreach ($NewCategories as $cat) {
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
            <a href="requests.php">[Search requests]</a>
            <a href="requests.php?type=created">[My requests]</a>
<?php 	 if (check_perms('site_vote')) { ?>
            <a href="requests.php?type=voted">[Requests I've voted on]</a>
<?php 		}  ?>

    </div>
<?php
    /* -------  Draw a box with imagehost whitelist  ------- */
    $Whitelist = $Cache->get_value('imagehost_whitelist');
    if ($Whitelist === FALSE) {
        $DB->query("SELECT
                    Imagehost,
                    Link,
                    Comment,
                    Time,
                    Hidden
                    FROM imagehost_whitelist
                    WHERE Hidden='0'
                    ORDER BY Time DESC");
        $Whitelist = $DB->to_array();
        $Cache->cache_value('imagehost_whitelist', $Whitelist);
    }
    $DB->query("SELECT MAX(iw.Time), IF(MAX(t.Time) < MAX(iw.Time) OR MAX(t.Time) IS NULL,1,0)
                  FROM imagehost_whitelist as iw
             LEFT JOIN torrents AS t ON t.UserID = '$LoggedUser[ID]' ");
    list($Updated, $NewWL) = $DB->next_record();
// test $HideWL first as it may have been passed from upload_handle
    if (!$HideWL)
        $HideWL = check_perms('torrents_hide_imagehosts') || !$NewWL;
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
foreach ($Whitelist as $ImageHost) {
    list($Host, $Link, $Comment, $Updated) = $ImageHost;
    ?>
                <tr>
                    <td><?=$Text->full_format($Host)?>
    <?php
    // if a goto link is supplied and is a validly formed url make a link icon for it
    if (!empty($Link) && $Text->valid_url($Link)) {
        ?><a href="<?= $Link ?>"  target="_blank"><img src="<?=STATIC_SERVER?>common/symbols/offsite.gif" width="16" height="16" style="" alt="Goto <?= $Host ?>" /></a>
    <?php  } // endif has a link to imagehost  ?>
                    </td>
                    <td><?=$Text->full_format($Comment)?></td>
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
                <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                <input type="hidden" name="action" value="<?=$NewRequest ? 'takenew' : 'takeedit'?>" />

            <table>
                <tr>
                    <td colspan="2" class="center">Please make sure your request follows <a href="articles.php?topic=requests">the request rules!</a></td>
                </tr>
<?php 	if ($NewRequest || $CanEdit) { ?>
                <tr class="pad">
                    <td colspan="2" class="center">
                        <strong class="important_text">NOTE: Requests automatically expire after 90 days. At this time if the bounty has not been filled all outstanding bounties are returned to those who placed them</strong>
                    </td>
                </tr>
                <tr>
                    <td class="label">
                        Category
                    </td>
                    <td>
                        <select id="category" name="category" onchange="change_tagtext();">
                                        <option value="0">---</option>
                                    <?php  foreach ($NewCategories as $category) { ?>
                                        <option value="<?=$category['id']?>"<?php
                                            if (isset($CategoryID) && $CategoryID==$category['id']) {
                                                echo ' selected="selected"';
                                            }   ?>><?=$category['name']?></option>
                                    <?php  } ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td class="label">Title</td>
                    <td>
                        <input type="text" name="title" class="long" value="<?=(!empty($Title) ? display_str($Title) : '')?>" />
                    </td>
                </tr>
                <tr id="image_tr">
                            <td class="label">Cover Image</td>
                            <td>    <strong>Enter the full url for your image.</strong><br/>
                                        Note: Do not add a thumbnail image as cover, rather leave this field blank if you don't have a good cover image or an image of the actor(s).
                                 <input type="text" id="image" class="long" name="image" value="<?=(!empty($Image) ? $Image : '')?>" />
                            </td>
                </tr>
<?php 	} ?>
                <tr>
                    <td class="label">Tags</td>
                    <td>
                    <div id="tagtext"></div>
<?php
    $GenreTags = $Cache->get_value('genre_tags');
    if (!$GenreTags) {
        $DB->query('SELECT Name FROM tags WHERE TagType=\'genre\' ORDER BY Name');
        $GenreTags =  $DB->collect('Name');
        $Cache->cache_value('genre_tags', $GenreTags, 3600*6);
    }
?>
                        <select id="genre_tags" name="genre_tags" onchange="add_tag();return false;" >
                            <option>---</option>
<?php 	foreach (display_array($GenreTags) as $Genre) { ?>
                            <option value="<?=$Genre ?>"><?=$Genre ?></option>
<?php 	} ?>
                        </select>
                    <textarea id="tags" name="tags" class="medium" style="height:1.4em;" ><?=(!empty($Tags) ? display_str($Tags) : '')?></textarea>

                        <br />
                    <?php
                                      $taginfo = get_article('tagrulesinline');
                                      if($taginfo) echo $Text->full_format($taginfo, true);
                              ?>
                    </td>
                </tr>
                <tr>
                    <td class="label">Description</td>
                    <td>  <div id="preview" class="box pad hidden"></div>
                                    <div  id="editor">
                                         <?php  $Text->display_bbcode_assistant("quickcomment", get_permissions_advtags($LoggedUser['ID'], $LoggedUser['CustomPermissions'])); ?>
                                        <textarea  id="quickcomment" name="description" class="long" rows="7"><?=(!empty($Description) ? $Description : '')?></textarea>
                                    </div>
                                    <input type="button" id="previewbtn" value="Preview" style="margin-right: 40px;" onclick="Preview_Request();" />
                              </td>
                </tr>

<?php 	if ($NewRequest) { ?>
                <tr id="voting">
                    <td class="label" id="bounty">Bounty</td>
                    <td>
                        <input type="text" id="amount_box" size="8" value="<?=(!empty($Bounty) ? $Bounty : '100')?>" onchange="Calculate();" onkeyup="Calculate();" />
                        <select id="unit" name="unit" onchange="Calculate();">
                            <option value='mb'<?=(!empty($_POST['unit']) && $_POST['unit'] == 'mb' ? ' selected="selected"' : '') ?>>MB</option>
                            <option value='gb'<?=(!empty($_POST['unit']) && $_POST['unit'] == 'gb' ? ' selected="selected"' : '') ?>>GB</option>
                                                        <option value='tb'<?=(!empty($_POST['unit']) && $_POST['unit'] == 'tb' ? ' selected="selected"' : '') ?>>TB</option>
                        </select>
                        <input type="button" value="Preview" onclick="Calculate();"/>
                        <strong id="inform"></strong>
                    </td>
                </tr>
                <tr>
                    <td class="label">Post request information</td>
                    <td>
                        <input type="hidden" id="amount" name="amount" value="<?=(!empty($Bounty) ? $Bounty : $MinimumBounty / (1024*1024) )?>" />
                        <input type="hidden" id="current_uploaded" value="<?=$LoggedUser['BytesUploaded']?>" />
                        <input type="hidden" id="current_downloaded" value="<?=$LoggedUser['BytesDownloaded']?>" />
                        If you add the entered <strong><span id="new_bounty"><?=get_size($MinimumBounty);?></span></strong> of bounty, your new stats will be: <br/>
                        Uploaded: <span id="new_uploaded"><?=get_size($LoggedUser['BytesUploaded'])?></span><br/>
                        Ratio: <span id="new_ratio"><?=ratio($LoggedUser['BytesUploaded'],$LoggedUser['BytesDownloaded'])?></span>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" class="center">
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
