<?php
/*
 * This is the page that displays the request to the end user after being created.
 */

include(SERVER_ROOT.'/sections/bookmarks/functions.php'); // has_bookmarked()
include(SERVER_ROOT.'/classes/class_text.php');
$Text = new TEXT;

if (empty($_GET['id']) || !is_number($_GET['id'])) {
    error(0);
}

$RequestID = $_GET['id'];

//First things first, lets get the data for the request.

$Request = get_requests(array($RequestID));
$Request = $Request['matches'][$RequestID];
if (empty($Request)) {
    error(404);
}

list($RequestID, $RequestorID, $RequestorName, $TimeAdded, $LastVote, $CategoryID, $Title, $Image, $Description,
     $FillerID, $FillerName, $TorrentID, $TimeFilled, $GroupID, $UploaderID, $UploaderName) = $Request;

include(SERVER_ROOT.'/sections/torrents/functions.php');
$TorrentCache = get_group_info($TorrentID, true, false);

$TorrentDetails = $TorrentCache[0];
$TorrentList = $TorrentCache[1];

list(,,, $TorrentTitle) = array_shift($TorrentDetails);

list(,,,,,,,,,,,,,,,,,,,, $IsAnon) = $TorrentList[0];

//Convenience variables
$NowTime = time();
$TimeExpires = strtotime($TimeAdded) + (3600*24*90); // 90 days from start
$IsFilled = !empty($TorrentID);
$CanVote = (empty($TorrentID) && check_perms('site_vote') && $TimeExpires > $NowTime);

if ($CategoryID == 0) {
    $CategoryName = 'unknown';
} else {
    $CategoryName = $NewCategories[$CategoryID]['name'];
}

$FullName = $Title;
$DisplayLink = $Title;

//Votes time
$RequestVotes = get_votes_array($RequestID);
$VoteCount = count($RequestVotes['Voters']);
$UserCanEdit = (!$IsFilled && $LoggedUser['ID'] == $RequestorID && $VoteCount < 2);
$CanEdit = ($UserCanEdit || check_perms('site_moderate_requests'));

show_header('View request: '.$FullName, 'comments,requests,bbcode,jquery,jquery.cookie');

?>
<div class="thin">
    <h2><a href="requests.php">Requests</a> &gt; <?=$CategoryName?> &gt; <?=$DisplayLink?></h2>
    <a id="messages" ></a>
    <div class="linkbox">
<?php
    if ($CanEdit) { ?>
        <a href="requests.php?action=edit&amp;id=<?=$RequestID?>">[Edit]</a>
<?php   }
    if (check_perms('site_moderate_requests') ) { ?>
        <a href="requests.php?action=delete&amp;id=<?=$RequestID?>">[Delete]</a>
<?php   }
    if (has_bookmarked('request', $RequestID)) { ?>
        <a href="#" id="bookmarklink_request_<?=$RequestID?>" onclick="Unbookmark('request', <?=$RequestID?>,'[Bookmark]');return false;">[Remove bookmark]</a>
<?php 	} else { ?>
        <a href="#" id="bookmarklink_request_<?=$RequestID?>" onclick="Bookmark('request', <?=$RequestID?>,'[Remove bookmark]');return false;">[Bookmark]</a>
<?php 	} ?>
        <a href="reports.php?action=report&amp;type=request&amp;id=<?=$RequestID?>">[Report Request]</a>
        <a href="upload.php?requestid=<?=$RequestID?><?=($GroupID?"&groupid=$GroupID":'')?>">[Upload Request]</a>
        <a href="log.php?search=request+<?=$RequestID?>">[View logs]</a>
    </div>

    <div class="sidebar">
<?php  if (!empty($Image)) { ?>
        <div class="head">
            <strong>Cover</strong>
            <span style="float:right;"><a href="#" id="covertoggle" onclick="Cover_Toggle(); return false;">(Hide)</a></span>
        </div>
        <div id="coverimage" class="box box_albumart center">

            <img style="max-width: 220px;" src="<?=$Image?>" alt="<?=$FullName?>" onclick="lightbox.init(this,220);" />

        </div><br/>
<?php  } ?>

        <div class="head">
            <strong>Tags</strong>
            <span style="float:right;margin-left:5px;"><a href="#" id="tagtoggle" onclick="TagBox_Toggle(); return false;">(Hide)</a></span>
        </div>
        <div id="tag_container" class="box box_tags">
            <ul id="torrent_tags" class="stats nobullet">
<?php 	foreach ($Request['Tags'] as $TagID => $TagName) { ?>
                <li>
                    <a href="torrents.php?taglist=<?=$TagName?>"><?=display_str($TagName)?></a>
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
?>
        </span><br/>
    </div>
      <div class="middle_column">

            <div class="head">Request</div>
        <table>
            <tr>
                <td class="label">
                    <img style="float:right" src="<?=( 'static/common/caticons/' . $NewCategories[$CategoryID]['image'])?>" />
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
                <td id="formatted_fillerbounty" style="font-size: 1.4em;"><?=get_size($RequestVotes['TotalBounty']/2)?></td>
            </tr>
            <tr id="uploaderbounty">
                <td class="label">Uploader's Bounty</td>
                <td id="formatted_uploaderbounty" style="font-size: 1.4em;"><?=get_size($RequestVotes['TotalBounty']/2)?></td>
            </tr>
            <tr>
                <td class="label"></td>
                <td title="If you fill this request with another's torrent you will receive half of the bounty (<?=get_size($RequestVotes['TotalBounty']/2)?>) and the uploader will recieve the other half.">If you fill this request with your own torrent you will receive the full bounty of <strong><?=get_size($RequestVotes['TotalBounty'])?></strong></td>
            </tr>
            <tr>
                <td class="label">Created</td>
                <td>
                    <?=time_diff($TimeAdded)?>	by  <strong><?=format_username($RequestorID, $RequestorName)?></strong>
                </td>
            </tr>
            <tr>
                <td class="label">Expiry Date</td>
                <td <?php
                if(  $TimeExpires < $NowTime ) echo ' class="greybar"';
                elseif( ( $TimeExpires - $NowTime ) <= (3600*24*7) ) echo ' class="redbar"';
                ?> title="On the expiry date if this request is not filled all bounties will be returned to the requestors and the request removed automatically">
                    <?=time_diff($TimeExpires,2,false,false,1)." &nbsp; (".time_diff($TimeExpires,2,false,false,0).')';
                    if (!$IsFilled && $TimeExpires < $NowTime) echo "<br/>this request will be deleted and the bounties returned within 24 hours";
                ?>
                </td>
            </tr>

<?php 	if ($GroupID) { ?>
            <tr>
                <td class="label">Torrent Group</td>
                <td><a href="torrents.php?id=<?=$GroupID?>">torrents.php?id=<?=$GroupID?></td>
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
                        <option value="mb">MB</option>
                        <option value="gb">GB</option>
                                                <option value="tb">TB</option>
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
                        <input type="hidden" id="auth" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                        <input type="hidden" id="amount" name="amount" value="0" />
                        <input type="hidden" id="readable" name="readable" value="" />
                        <input type="hidden" id="current_uploaded" value="<?=$LoggedUser['BytesUploaded']?>" />
                        <input type="hidden" id="current_downloaded" value="<?=$LoggedUser['BytesDownloaded']?>" />
                        <input type="hidden" id="total_bounty" value="<?=$RequestVotes['TotalBounty']?>" />
                        If you add the entered <strong><span id="new_bounty">0.00 MB</span></strong> of bounty, your new stats will be: <br/>
                        Uploaded: <span id="new_uploaded"><?=get_size($LoggedUser['BytesUploaded'])?></span><br/>
                        Ratio: <span id="new_ratio"><?=ratio($LoggedUser['BytesUploaded'],$LoggedUser['BytesDownloaded'])?></span>
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
                    <strong><a href="torrents.php?id=<?=$TorrentID?>"><?php echo ($TorrentTitle == '') ? "(torrent deleted)" : $TorrentTitle; ?></a></strong>
<?php 		if( ( $TimeExpires>$NowTime &&  ($LoggedUser['ID'] == $RequestorID || $LoggedUser['ID'] == $FillerID) )
                || check_perms('site_moderate_requests')) { ?>
                        - <span title="Unfilling a request without a valid, nontrivial reason will result in a warning."><a href="requests.php?action=unfill&amp;id=<?=$RequestID?>">[Unfill]</a></span>
<?php 		} ?>
                   <br/>Filled by <?=format_username($FillerID, $FillerName)?>
<?php           if ( $UploaderID != 0 && $TorrentTitle != '' ) {
                    echo ", uploaded by ".torrent_username($UploaderID, $UploaderName, $IsAnon);
                } ?>
                </td>
            </tr>
<?php 	} elseif ($TimeExpires > $NowTime) { ?>
            <tr>
                <td class="label" valign="top">Fill request</td>
                <td>
                    <form action="" method="post">
                        <div>
                            <input type="hidden" name="action" value="takefill" />
                            <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
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
            <?=$Text->full_format($Description, get_permissions_advtags($RequestorID))?>
        </div>
        <br/>
<?php

$Results = $Cache->get_value('request_comments_'.$RequestID);
if ($Results === false) {
    $DB->query("SELECT
            COUNT(c.ID)
            FROM requests_comments as c
            WHERE c.RequestID = '$RequestID'");
    list($Results) = $DB->next_record();
    $Cache->cache_value('request_comments_'.$RequestID, $Results, 0);
}

list($Page,$Limit) = page_limit(TORRENT_COMMENTS_PER_PAGE,$Results);

//Get the cache catalogue
$CatalogueID = floor((TORRENT_COMMENTS_PER_PAGE*$Page-TORRENT_COMMENTS_PER_PAGE)/THREAD_CATALOGUE);
$CatalogueLimit=$CatalogueID*THREAD_CATALOGUE . ', ' . THREAD_CATALOGUE;

//---------- Get some data to start processing

// Cache catalogue from which the page is selected, allows block caches and future ability to specify posts per page
$Catalogue = $Cache->get_value('request_comments_'.$RequestID.'_catalogue_'.$CatalogueID);
if ($Catalogue === false) {
    $DB->query("SELECT
            c.ID,
            c.AuthorID,
            c.AddedTime,
            c.Body,
            c.EditedUserID,
            c.EditedTime,
            u.Username
            FROM requests_comments as c
            LEFT JOIN users_main AS u ON u.ID=c.EditedUserID
            LEFT JOIN users_main AS a ON a.ID = c.AuthorID
            WHERE c.RequestID = '$RequestID'
            ORDER BY c.ID
            LIMIT $CatalogueLimit");
    $Catalogue = $DB->to_array(false,MYSQLI_ASSOC);
    $Cache->cache_value('request_comments_'.$RequestID.'_catalogue_'.$CatalogueID, $Catalogue, 0);
}

//This is a hybrid to reduce the catalogue down to the page elements: We use the page limit % catalogue
$Thread = array_slice($Catalogue,((TORRENT_COMMENTS_PER_PAGE*$Page-TORRENT_COMMENTS_PER_PAGE)%THREAD_CATALOGUE),TORRENT_COMMENTS_PER_PAGE,true);
?>
    <div class="linkbox"><a name="comments"></a>
<?php
$Pages=get_pages($Page,$Results,TORRENT_COMMENTS_PER_PAGE,9,'#comments');
echo $Pages;
?>
    </div>
      <div class="head">Comments</div>
<?php

//---------- Begin printing
foreach ($Thread as $Key => $Post) {
    list($PostID, $AuthorID, $AddedTime, $Body, $EditedUserID, $EditedTime, $EditedUsername) = array_values($Post);
    list($AuthorID, $Username, $PermissionID, $Paranoia, $Donor, $Warned, $Avatar, $Enabled, $UserTitle,,,$Signature,,$GroupPermissionID) = array_values(user_info($AuthorID));
    $AuthorPermissions = get_permissions($PermissionID);
    list($ClassLevel,$PermissionValues,$MaxSigLength,$MaxAvatarWidth,$MaxAvatarHeight)=array_values($AuthorPermissions);
?>
<table class="forum_post vertical_margin<?=$HeavyInfo['DisableAvatars'] ? ' noavatar' : ''?>" id="post<?=$PostID?>">
    <tr class="smallhead">
        <td colspan="2">
            <span style="float:left;"><a href='#post<?=$PostID?>'>#<?=$PostID?></a>
                <?=format_username($AuthorID, $Username, $Donor, $Warned, $Enabled, $PermissionID,$UserTitle,true,$GroupPermissionID,true)?> <?=time_diff($AddedTime)?>
                - <a href="#quickpost" onclick="Quote('requests','<?=$PostID?>','r<?=$RequestID?>','<?=$Username?>');">[Quote]</a>
<?php if (can_edit_comment($AuthorID, $EditedUserID, $AddedTime, $EditedTime)) { ?>
                - <a href="#post<?=$PostID?>" onclick="Edit_Form('requests','<?=$PostID?>','<?=$Key?>');">[Edit]</a>
<?php }
      if (check_perms('site_admin_forums')) { ?>
                - <a href="#post<?=$PostID?>" onclick="Delete('<?=$PostID?>');">[Delete]</a>
<?php } ?>
            </span>
            <span id="bar<?=$PostID?>" style="float:right;">
                <a href="reports.php?action=report&amp;type=requests_comment&amp;id=<?=$PostID?>">[Report]</a>
                &nbsp;
                <a href="#">&uarr;</a>
            </span>
        </td>
    </tr>
    <tr>
<?php if (empty($HeavyInfo['DisableAvatars'])) { ?>
        <td class="avatar" valign="top">
    <?php if ($Avatar) { ?>
            <img src="<?=$Avatar?>" class="avatar" style="<?=get_avatar_css($MaxAvatarWidth, $MaxAvatarHeight)?>" alt="<?=$Username ?>'s avatar" />
    <?php } else { ?>
            <img src="<?=STATIC_SERVER?>common/avatars/default.png" class="avatar" style="<?=get_avatar_css(100, 120)?>" alt="Default avatar" />
    <?php }
          $UserBadges = get_user_badges($AuthorID);
          if ( !empty($UserBadges) ) { ?>
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
                <div class="post_content"><?=$Text->full_format($Body, $AllowTags) ?> </div>
<?php if ($EditedUserID) { ?>
                        <div class="post_footer">
<?php     if (check_perms('site_moderate_forums')) { ?>
                <a href="#content<?=$PostID?>" onclick="LoadEdit('requests', <?=$PostID?>, 1); return false;">&laquo;</a>
<?php  	  } ?>
                        <span class="editedby">Last edited by
                            <?=format_username($EditedUserID, $EditedUsername) ?> <?=time_diff($EditedTime,2,true,true)?>
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
if (!$LoggedUser['DisablePosting']) { ?>
            <br />
            <div class="messagecontainer" id="container"><div id="message" class="hidden center messagebar"></div></div>
                <table id="quickreplypreview" class="hidden forum_post box vertical_margin" id="preview">
                    <tr class="smallhead">
                        <td colspan="2">
                            <span style="float:left;"><a href='#quickreplypreview'>#XXXXXX</a>
                                <?=format_username($LoggedUser['ID'], $LoggedUser['Username'], $LoggedUser['Donor'], $LoggedUser['Warned'], $LoggedUser['Enabled'], $LoggedUser['PermissionID'],$LoggedUser['Title'],true)?>
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
                <?php  if (!empty($LoggedUser['Avatar'])) { ?>
                            <img src="<?=$LoggedUser['Avatar']?>" class="avatar" style="<?=get_avatar_css($LoggedUser['MaxAvatarWidth'], $LoggedUser['MaxAvatarHeight'])?>" alt="<?=$LoggedUser['Username']?>'s avatar" />
                <?php  } else { ?>
                            <img src="<?=STATIC_SERVER?>common/avatars/default.png" class="avatar" style="<?=get_avatar_css(100, 120)?>" alt="Default avatar" />
                <?php  } ?>
                        </td>
                        <td class="body" valign="top">
                            <div id="contentpreview" style="text-align:left;"></div>
                        </td>
                    </tr>
                </table>
                  <div class="head">Post reply</div>
            <div class="box pad shadow">
                <form id="quickpostform" action="" method="post" onsubmit="return Validate_Form('message', 'quickpost')" style="display: block; text-align: center;">
                    <div id="quickreplytext">
                        <input type="hidden" name="action" value="reply" />
                        <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                        <input type="hidden" name="requestid" value="<?=$RequestID?>" />
                                    <?php  $Text->display_bbcode_assistant("quickpost", get_permissions_advtags($LoggedUser['ID'], $LoggedUser['CustomPermissions'])); ?>
                                    <textarea id="quickpost" name="body" class="long" rows="8"></textarea> <br />
                    </div>
                    <input id="post_preview" type="button" value="Preview" onclick="if (this.preview) {Quick_Edit();} else {Quick_Preview();}" />
                    <input type="submit" value="Post comment" />
                </form>
            </div>
<?php  } ?>
    </div>
</div>
<?php
show_footer();
