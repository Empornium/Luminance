<?php
/*
 * This is the page that displays the request to the end user after being created.
 */

$bbCode = new \Luminance\Legacy\Text;

if (empty($_GET['id']) || !is_integer_string($_GET['id'])) {
    error(0);
}

$RequestID = $_GET['id'];

//First things first, lets get the data for the request.
$Request = get_requests([$RequestID]);
$Request = $Request['matches'][$RequestID];
if (empty($Request)) {
    header('Location: log.php?search=Request+'.$RequestID);
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


include(SERVER_ROOT.'/Legacy/sections/torrents/functions.php');
$TorrentCache = get_group_info($torrentID, true, false);
$TorrentDetails = $TorrentCache[0];
list(, , , $TorrentTitle) = array_shift($TorrentDetails);


//Convenience variables
$NowTime = time();
$timeExpires = strtotime($timeAdded) + (3600*24*90); // 90 days from start
$IsFilled = !empty($torrentID);
$CanVote = (empty($torrentID) && check_perms('site_vote') && $timeExpires > $NowTime);

if (empty($newCategories[$CategoryID]['image'] ?? null)) {
    trigger_error("Cannot decode category for request: ".$RequestID);
}

if (empty($CategoryID)) {
    $CategoryName = 'unknown';
} else {
    $CategoryName = $newCategories[$CategoryID]['name'];
}

$FullName = $Title;
$DisplayLink = $Title;

//Votes time
$RequestVotes = get_votes_array($RequestID);
$VoteCount = count($RequestVotes['Voters']);
$UserCanEdit = (!$IsFilled && $activeUser['ID'] == $RequestorID && $VoteCount < 2);
$CanEdit = ($UserCanEdit || check_perms('site_moderate_requests'));

show_header('View request: '.$FullName, 'comments,requests,bbcode,jquery,jquery.cookie');

?>
<div class="details thin">
    <h2>
        <span class="arrow" style="float:left"><a href="/requests.php?id=<?=$RequestID?>&action=prev" title="goto previous request"><img src="/static/styles/<?=$activeUser['StyleName']?>/images/arrow_left.png" alt="prev" title="goto previous request" /></a></span>
        <a href="/requests.php">Requests</a> &gt; <?=$CategoryName?> &gt; <?=$DisplayLink?>
        <span class="arrow" style="float:right"><a href="/requests.php?id=<?=$RequestID?>&action=next" title="goto next request"><img src="/static/styles/<?=$activeUser['StyleName']?>/images/arrow_right.png" alt="next" title="goto next request" /></a></span>
    </h2>
    <a id="messages" ></a>
    <div class="linkbox">
<?php
    if ($CanEdit) { ?>
        <a href="/requests.php?action=edit&amp;id=<?=$RequestID?>">[Edit]</a>
<?php   }
    if (check_perms('site_moderate_requests')) { ?>
        <a href="/requests.php?action=delete&amp;id=<?=$RequestID?>">[Delete]</a>
<?php   }
    if (has_bookmarked('request', $RequestID)) { ?>
        <a href="#" id="bookmarklink_request_<?=$RequestID?>" onclick="Unbookmark('request', <?=$RequestID?>, '[Bookmark]');return false;">[Remove bookmark]</a>
<?php 	} else { ?>
        <a href="#" id="bookmarklink_request_<?=$RequestID?>" onclick="Bookmark('request', <?=$RequestID?>, '[Remove bookmark]');return false;">[Bookmark]</a>
<?php 	} ?>
        <?php if (!$master->repos->restrictions->isRestricted($activeUser['ID'], \Luminance\Entities\Restriction::REPORT)): ?>
        <a href="/reports.php?action=report&amp;type=request&amp;id=<?=$RequestID?>">[Report Request]</a>
        <?php endif;
        if (check_perms('site_upload')) { ?>
        <a href="/upload.php?requestid=<?=$RequestID?><?=($GroupID?"&groupid=$GroupID":'')?>">[Upload Request]</a>
        <?php } ?>
        <a href="/log.php?search=request+<?=$RequestID?>">[View logs]</a>
    </div>

    <div class="sidebar">
<?php  if (!empty($Image)) {
          $PreviewImage = fapping_preview($Image, 250);
?>
        <div class="head">
            <strong>Cover</strong>
            <span style="float:right;"><a href="#" id="covertoggle" onclick="Cover_Toggle(); return false;">(Hide)</a></span>
        </div>
        <div id="coverimage" class="box box_albumart center">

            <img style="max-width: 220px;" src="<?=$PreviewImage?>" alt="<?=$FullName?>" onclick="lightbox.init(this,220, '<?=$Image?>');" />

        </div><br/>
<?php  } ?>

        <div class="head">
            <strong>Tags</strong>
            <span style="float:right;margin-left:5px;"><a href="#" id="tagtoggle" onclick="TagBox_Toggle(); return false;">(Hide)</a></span>
        </div>
        <div id="tag_container" class="box box_tags">
            <ul id="torrent_tags" class="stats nobullet">
<?php 	foreach ((array)($Request['Tags'] ?? []) as $TagID => $TagName) { ?>
                <li>
                    <a href="?taglist=<?=$TagName?>"><?=display_str($TagName)?></a>
                    <br style="clear:both" />
                </li>
<?php 	} ?>
            </ul>
        </div><br/>
        <div class="head"><strong>Top Contributors</strong></div>
        <span id="request_votes">
        <!--<table class="box box_votes" id="request_votes">-->
<?php
    echo get_votes_html($RequestVotes, $RequestID);
    $FillerBounty = $RequestVotes['TotalBounty']*($master->options->BountySplit/100);
    $UploaderBounty =  $RequestVotes['TotalBounty'] - $FillerBounty;
?>
        </span><br/>
    </div>
      <div class="middle_column">

            <div class="head">Request</div>
        <table>
            <tr>
                <td class="label">
                    <img style="float:right" src="<?=( '/static/common/caticons/' . $newCategories[$CategoryID]['image'])?>" />
                </td>
                <td style="font-size: 1.2em;text-align:center;font-weight:bold;">
                    <?=$DisplayLink?>
                </td>
            </tr>
            <tr id="bounty">
                <td class="label">Total Bounty</td>
                <td id="formatted_bounty" style="font-size: 1.8em;"><?=get_size($RequestVotes['TotalBounty'])?></td>
            </tr>
            <tr id="fillerbounty">
                <td class="label">Filler's Bounty</td>
                <td id="formatted_fillerbounty" style="font-size: 1.4em;"><?=get_size($FillerBounty)?></td>
            </tr>
            <tr id="uploaderbounty">
                <td class="label">Uploader's Bounty</td>
                <td id="formatted_uploaderbounty" style="font-size: 1.4em;"><?=get_size($UploaderBounty)?></td>
            </tr>
            <tr>
                <td class="label"></td>
                <td title="If you fill this request with another's torrent you will receive only a share of the bounty (<?=get_size($FillerBounty)?>) and the uploader will receive the rest.">If you fill this request with your own torrent you will receive the full bounty of <strong><?=get_size($RequestVotes['TotalBounty'])?></strong></td>
            </tr>
            <tr>
                <td class="label">Created</td>
                <td>
                    <?=time_diff($timeAdded)?>	by  <strong><?=format_username($RequestorID)?></strong>
                </td>
            </tr>
            <tr>
                <td class="label">Expiry Date</td>
                <td <?php
                if ($timeExpires < $NowTime) {
                    echo ' class="greybar"';
                } elseif (($timeExpires - $NowTime) <= (3600*24*7)) {
                    echo ' class="redbar"';
                }
                ?> title="On the expiry date if this request is not filled all bounties will be returned to the requestors and the request removed automatically">
                    <?=time_diff($timeExpires, 2, false, false, 1)." &nbsp; (".time_diff($timeExpires, 2, false, false, 0).')';
                    if (!$IsFilled && $timeExpires < $NowTime) echo "<br/>this request will be deleted and the bounties returned within 24 hours";
                ?>
                </td>
            </tr>

<?php 	if ($GroupID) { ?>
            <tr>
                <td class="label">Torrent Group</td>
                <td><a href="/torrents.php?id=<?=$GroupID?>">torrents.php?id=<?=$GroupID?></td>
            </tr>
<?php 	} ?>
            <tr>
                <td class="label">Votes</td>
                <td>
                    <span id="votecount"><?=$VoteCount?></span>
                </td>
            </tr>
<?php 	if ($CanVote) { ?>
            <tr id="voting">
                <td class="label">Custom Vote</td>
                <td>
                    <input type="text" id="amount_box" size="8" onchange="Calculate();" onkeyup="Calculate();" />
                    <select id="unit" name="unit" onchange="Calculate();">
                        <option value="mb">MiB</option>
                        <option value="gb">GiB</option>
                        <option value="tb">TiB</option>
                    </select>
                    <input type="button" value="Preview" onclick="Calculate();"/><br/>
                    <strong id="inform"></strong>
                </td>
            </tr>
            <tr>
                <td class="label">Post vote information</td>
                <td>
                    <form action="requests.php" method="get" id="request_form">
                        <input type="hidden" name="action" value="vote" />
                        <input type="hidden" id="requestid" name="id" value="<?=$RequestID?>" />
                        <input type="hidden" id="auth" name="auth" value="<?=$activeUser['AuthKey']?>" />
                        <input type="hidden" id="amount" name="amount" value="0" />
                        <input type="hidden" id="readable" name="readable" value="" />
                        <input type="hidden" id="current_uploaded" value="<?=$activeUser['BytesUploaded']?>" />
                        <input type="hidden" id="current_downloaded" value="<?=$activeUser['BytesDownloaded']?>" />
                        <input type="hidden" id="total_bounty" value="<?=$RequestVotes['TotalBounty']?>" />
                        If you add the entered <strong><span id="new_bounty">0.00 MiB</span></strong> of bounty, your new stats will be: <br/>
                        Uploaded: <span id="new_uploaded"><?=get_size($activeUser['BytesUploaded'])?></span><br/>
                        Ratio: <span id="new_ratio"><?=ratio($activeUser['BytesUploaded'], $activeUser['BytesDownloaded'])?></span>
                        <input type="button" id="button_vote" value="Vote!" disabled="disabled" onclick="Vote();"/>
                    </form>
                </td>
            </tr>
<?php  }?>
<?php
    if ($IsFilled) {
?>
            <tr>
                <td class="label">Filled</td>
                <td>
                    <strong><a href="/torrents.php?id=<?=$torrentID?>"><?php echo ($TorrentTitle == '') ? "(torrent deleted)" : $TorrentTitle; ?></a></strong>
<?php       if (($timeExpires>$NowTime &&  ($activeUser['ID'] == $RequestorID || $activeUser['ID'] == $FillerID)) || check_perms('site_moderate_requests')) { ?>
                        - <span title="Unfilling a request without a valid, nontrivial reason will result in a warning."><a href="/requests.php?action=unfill&amp;id=<?=$RequestID?>">[Unfill]</a></span>
<?php       } ?>
                   <br/>Filled by <?=torrent_username($FillerID, $FillerID==$UploaderID?$IsAnon:false)?>
<?php       if ($UploaderID != 0 && $TorrentTitle != '') {
                    echo ", uploaded by ".torrent_username($UploaderID, $IsAnon);
            } ?>
                </td>
            </tr>
<?php 	} elseif ($timeExpires > $NowTime) { ?>
            <tr>
                <td class="label" valign="top">Fill request</td>
                <td>
                    <form action="" method="post">
                        <div>
                            <input type="hidden" name="action" value="takefill" />
                            <input type="hidden" name="auth" value="<?=$activeUser['AuthKey']?>" />
                            <input type="hidden" name="requestid" value="<?=$RequestID?>" />
                            <strong class="warning">Please make sure the torrent you are filling this request with matches the required parameters.</strong>
                            <br/><input type="text" size="50" name="link" <?=(!empty($Link) ? "value='$Link' " : '')?>/>
                            <br/>Should be the permalink (PL) to the torrent
                            <br/>e.g. http://<?=SITE_URL?>/torrents.php?id=xxxx
                            <br/><br/>
                            <?php  if (check_perms('site_moderate_requests')) { ?>
                            <span title="Fill this request on behalf of user:">
                            Fill for user: <input type="text" size="50" name="user" title="the username of the user you are filling this for (they will be recorded as filling this request)" <?=(!empty($FillerUsername) ? "value='$FillerUsername' " : '')?>/>
                            </span><br/><br/>
                            <?php  } ?>
                            <input type="submit" value="Fill request" />
                            <br/>
                        </div>
                    </form>

                </td>
            </tr>
<?php 	} ?>
        </table>

    </div>
    <div style="clear:both"></div>
    <div class="main_column">
        <div class="head">
            <strong>Description</strong>
            <span style="float:right;"><a href="#" id="desctoggle" onclick="Desc_Toggle(); return false;">(Hide)</a></span>
        </div>
        <div id="descbox" class="box pad">
            <?=$bbCode->full_format($Description, get_permissions_advtags($RequestorID))?>
        </div>
        <br/>
<?php

$Results = $master->cache->getValue('request_comments_'.$RequestID);
if ($Results === false) {
    $Results = $master->db->rawQuery(
        "SELECT COUNT(c.ID)
           FROM requests_comments as c
          WHERE c.RequestID = ?",
        [$RequestID]
    )->fetchColumn();
    $master->cache->cacheValue("request_comments_{$RequestID}", $Results, 0);
}

list($Page, $Limit) = page_limit(TORRENT_COMMENTS_PER_PAGE, $Results);

//Get the cache catalogue
$CatalogueID = floor((TORRENT_COMMENTS_PER_PAGE*$Page-TORRENT_COMMENTS_PER_PAGE)/THREAD_CATALOGUE);
$CatalogueLimit=$CatalogueID*THREAD_CATALOGUE . ', ' . THREAD_CATALOGUE;

//---------- Get some data to start processing

// Cache catalogue from which the page is selected, allows block caches and future ability to specify posts per page
$Catalogue = $master->cache->getValue('request_comments_'.$RequestID.'_catalogue_'.$CatalogueID);
if ($Catalogue === false) {
    $Catalogue = $master->db->rawQuery(
        "SELECT c.ID,
                c.AuthorID,
                c.AddedTime,
                c.Body,
                c.EditedUserID,
                c.EditedTime,
                u.Username
           FROM requests_comments as c
      LEFT JOIN users AS u ON u.ID = c.EditedUserID
          WHERE c.RequestID = ?
       ORDER BY c.ID
          LIMIT {$CatalogueLimit}",
        [$RequestID]
    )->fetchAll(\PDO::FETCH_ASSOC);
    $master->cache->cacheValue('request_comments_'.$RequestID.'_catalogue_'.$CatalogueID, $Catalogue, 0);
}

//This is a hybrid to reduce the catalogue down to the page elements: We use the page limit % catalogue
$Thread = array_slice($Catalogue,((TORRENT_COMMENTS_PER_PAGE*$Page-TORRENT_COMMENTS_PER_PAGE)%THREAD_CATALOGUE),TORRENT_COMMENTS_PER_PAGE,true);
?>
    <div class="linkbox"><a name="comments"></a>
<?php
$Pages = get_pages($Page, $Results, TORRENT_COMMENTS_PER_PAGE, 9, '#comments');
echo $Pages;
?>
    </div>

<?php if (!$master->repos->restrictions->isRestricted($activeUser['ID'], Luminance\Entities\Restriction::COMMENT)) { ?>
      <div class="head">Comments</div>
<?php

//---------- Begin printing
foreach ($Thread as $Key => $Post) {
    list($PostID, $AuthorID, $AddedTime, $Body, $EditedUserID, $EditedTime, $EditedUsername) = array_values($Post);
    list($AuthorID, $Username, $PermissionID, $paranoia, $Donor, $Avatar, $enabled, $UserTitle, , , $Signature, , $GroupPermissionID) = array_values(user_info($AuthorID));
    $AuthorPermissions = get_permissions($PermissionID);
    list($classLevel, $PermissionValues, $MaxSigLength, $MaxAvatarWidth, $MaxAvatarHeight)=array_values($AuthorPermissions);
?>
<table class="forum_post vertical_margin<?=$heavyInfo['DisableAvatars'] ? ' noavatar' : ''?>" id="post<?=$PostID?>">
    <tr class="smallhead">
        <td colspan="2">
            <span style="float:left;"><a href="/requests.php?action=view&amp;id=<?=$RequestID?>&amp;postid=<?=$PostID?>#post<?=$PostID?>">#<?=$PostID?></a>
                <?=format_username($AuthorID, $Donor, true, $enabled, $PermissionID, $UserTitle, true, $GroupPermissionID, true)?> <?=time_diff($AddedTime)?>
                - <a href="#quickpost" onclick="Quote('<?=$PostID?>', 'r<?=$RequestID?>', '<?=$Username?>');">[Quote]</a>
<?php if (can_edit_comment($AuthorID, $EditedUserID, $AddedTime, $EditedTime)) { ?>
                - <a href="#post<?=$PostID?>" onclick="Edit_Form('<?=$PostID?>');">[Edit]</a>
<?php }
      if (check_perms('torrent_post_delete')) { ?>
                - <a href="#post<?=$PostID?>" onclick="DeletePost('<?=$PostID?>');">[Delete]</a>
<?php } ?>
            </span>
            <span id="bar<?=$PostID?>" style="float:right;">
                <?php if (!$master->repos->restrictions->isRestricted($activeUser['ID'], \Luminance\Entities\Restriction::REPORT)): ?>
                <a href="/reports.php?action=report&amp;type=requests_comment&amp;id=<?=$PostID?>">[Report]</a>
                <?php endif; ?>
                &nbsp;
                <a href="#">&uarr;</a>
            </span>
        </td>
    </tr>
    <tr>
<?php if (empty($heavyInfo['DisableAvatars'])) { ?>
        <td class="avatar" valign="top">
    <?php if ($Avatar) { ?>
            <img src="<?=$Avatar?>" class="avatar" style="<?=get_avatar_css($MaxAvatarWidth, $MaxAvatarHeight)?>" alt="<?=$Username ?>'s avatar" />
    <?php } else { ?>
            <img src="<?=STATIC_SERVER?>common/avatars/default.png" class="avatar" style="<?=get_avatar_css(100, 120)?>" alt="Default avatar" />
    <?php }
          $UserBadges = get_user_badges($AuthorID);
          if (!empty($UserBadges)) { ?>
               <div class="badges">
<?php              print_badges_array($UserBadges, $AuthorID); ?>
               </div>
<?php     } ?>
        </td>
<?php }
      $AllowTags= get_permissions_advtags($AuthorID, false, $AuthorPermissions);
?>
        <td class="body" valign="top">
            <div id="content<?=$PostID?>">
                <div class="post_content"><?=$bbCode->full_format($Body, $AllowTags) ?> </div>
<?php if ($EditedUserID) { ?>
                        <div class="post_footer">
<?php     if (check_perms('forum_moderate')) { ?>
                <a href="#content<?=$PostID?>" onclick="LoadEdit(<?=$PostID?>, 1); return false;">&laquo;</a>
<?php  	  } ?>
                        <span class="editedby">Last edited by
                            <?=format_username($EditedUserID) ?> <?=time_diff($EditedTime,2,true,true)?>
                        </span>
                        </div>
<?php } ?>
            </div>
        </td>
    </tr>
</table>
<?php
} ?>

        <div class="linkbox">
        <?=$Pages?>
        </div>
<?php
if (!$master->repos->restrictions->isRestricted($activeUser['ID'], Luminance\Entities\Restriction::POST)) { ?>
            <br />
            <div class="messagecontainer" id="container"><div id="message" class="hidden center messagebar"></div></div>
                <table id="quickreplypreview" class="hidden forum_post box vertical_margin" id="preview">
                    <tr class="smallhead">
                        <td colspan="2">
                            <span style="float:left;"><a href='#quickreplypreview'>#XXXXXX</a>
                                <?=format_username($activeUser['ID'], $activeUser['Donor'], true, $activeUser['Enabled'], $activeUser['PermissionID'], $activeUser['Title'],true)?>
                                Just now
                            </span>
                            <span id="barpreview" style="float:right;">
                                <a href="#quickreplypreview">[Report]</a>
                                <a href="#">&uarr;</a>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td class="avatar" valign="top">
                <?php  if (!empty($activeUser['Avatar'])) { ?>
                            <img src="<?=$activeUser['Avatar']?>" class="avatar" style="<?=get_avatar_css($activeUser['MaxAvatarWidth'], $activeUser['MaxAvatarHeight'])?>" alt="<?=$activeUser['Username']?>'s avatar" />
                <?php  } else { ?>
                            <img src="<?=STATIC_SERVER?>common/avatars/default.png" class="avatar" style="<?=get_avatar_css(100, 120)?>" alt="Default avatar" />
                <?php  } ?>
                        </td>
                        <td class="body" valign="top">
                            <div id="contentpreview" class="preview_content" style="text-align:left;"></div>
                        </td>
                    </tr>
                </table>
                  <div class="head">Post reply</div>
            <div class="box pad shadow">
                <form id="quickpostform" action="" method="post" onsubmit="return Validate_Form('message', 'quickpost')" style="display: block; text-align: center;">
                    <div id="quickreplytext">
                        <input type="hidden" name="action" value="reply" />
                        <input type="hidden" name="auth" value="<?=$activeUser['AuthKey']?>" />
                        <input type="hidden" name="requestid" value="<?=$RequestID?>" />
                                    <?php  $bbCode->display_bbcode_assistant("quickpost", get_permissions_advtags($activeUser['ID'], $activeUser['CustomPermissions'])); ?>
                                    <textarea id="quickpost" name="body" class="long" rows="8"></textarea> <br />
                    </div>
                    <input id="post_preview" type="button" value="Preview" onclick="if (this.preview) {Quick_Edit();} else {Quick_Preview();}" />
                    <input type="submit" value="Post comment" />
                </form>
            </div>
<?php  } ?>
    </div>
<?php  } ?>
</div>
<?php
show_footer();
