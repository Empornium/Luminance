<?php

include_once(SERVER_ROOT.'/Legacy/sections/torrents/functions.php');

$Queries = [];

$orderWays = ['votes', 'bounty', 'created', 'lastvote', 'filled'];
list($Page, $Limit) = page_limit(REQUESTS_PER_PAGE);
$Submitted = !empty($_GET['submitted']);

$type = $_GET['type'] ?? null;
$userID = $_GET['userid'] ?? null;
$BookmarkView = false;

if (empty($type)) {
    $Title = 'Search Requests';
    if (!check_perms('site_see_old_requests') || empty($_GET['showall'])) {
        $search->setFilter('visible', [1]);
    }
} else {
    switch ($type) {
        case 'created':
            $Title = 'My requests';
            $search->setFilter('userid', [$activeUser['ID']]);
            break;
        case 'voted':
            if (!empty($userID)) {
                if (is_integer_string($userID)) {
                    //Paranoia
                    $UserInfo = user_info((int) $userID);
                    $Perms = get_permissions($UserInfo['PermissionID']);

                    if (!check_force_anon($userID) || !check_paranoia('requestsvoted_list', $UserInfo['Paranoia'], $Perms['Class'], $userID)) { error(PARANOIA_MSG); }
                    $Title = "Requests voted for by ".$UserInfo['Username'];
                    $search->setFilter('voter', [$userID]);
                } else {
                    error(404);
                }
            } else {
                $Title = "Requests I've voted on";
                $search->setFilter('voter', [$activeUser['ID']]);
            }
            break;
        case 'filled':
            if (empty($userID) || !is_integer_string($userID)) {
                error(404);
            } else {
                //Paranoia
                $UserInfo = user_info((int) $userID);
                $Perms = get_permissions($UserInfo['PermissionID']);

                if (!check_force_anon($userID) || !check_paranoia('requestsfilled_list', $UserInfo['Paranoia'], $Perms['Class'], $userID)) { error(PARANOIA_MSG); }
                $Title = "Requests filled by ".$UserInfo['Username'];
                $search->setFilter('fillerid', [$userID]);
            }
            break;
        case 'bookmarks':
            $Title = 'Your bookmarked requests';
            $BookmarkView = true;
            $search->setFilter('bookmarker', [$activeUser['ID']]);
            break;
        default:
            error(404);
    }
}

if ($Submitted && empty($_GET['show_filled'])) {
    $search->setFilter('torrentid', [0]);
}

if (!empty($_GET['search'])) {
    $Words = explode(' ', $_GET['search']);
    foreach ($Words as $Key => &$Word) {
        $Word = trim($Word);
        if (empty($Word)) {
            continue;
        }
        if ($Word[0] == '!' && strlen($Word) > 2) {
            if (strpos($Word, '!', 1) === false) {
                $Word = '!'.$search->escapeString(substr($Word, 1));
            } else {
                $Word = $search->escapeString($Word);
            }
        } elseif (strlen($Word) >= 2) {
            $Word = $search->escapeString($Word);
        } else {
            unset($Words[$Key]);
        }
    }
    if (!empty($Words)) {
        $Queries[] = "@* ".implode(' ', $Words);
    }
}

if (!empty($_GET['taglist'])) {
        $Tags = cleanup_tags($_GET['taglist']);
    $Tags = array_unique(explode(' ', $Tags));
    $TagNames = [];
    foreach ($Tags as $Tag) {
        $Tag = sanitize_tag($Tag);
        if (!empty($Tag)) {
            $TagNames[] = $Tag;
        }
    }
    $Tags = get_tags($TagNames);
}

if (empty($_GET['tags_type']) && !empty($Tags)) {
    $_GET['tags_type'] = '0';
    $search->setFilter('tagid', array_keys($Tags));
} elseif (!empty($Tags)) {
    foreach (array_keys($Tags) as $Tag) {
        $search->setFilter('tagid', [$Tag]);
    }
} elseif (!empty($_GET['taglist']) && empty($Tags)) {
    // We're searching for tags but couldn't find any of them -> we should return an empty result
    $search->setFilter('tagid', [0]);
} else {
    $_GET['tags_type'] = '1';
}

if (!empty($_GET['filter_cat'])) {
    $Keys = array_keys($_GET['filter_cat']);
    $search->setFilter('categoryid', $Keys);
}

if (!empty($_GET['requestor']) && check_perms('site_see_old_requests')) {
    if (is_integer_string($_GET['requestor'])) {
        $search->setFilter('userid', [$_GET['requestor']]);
    } else {
        error(404);
    }
}

if (!empty($_GET['page']) && is_integer_string($_GET['page'])) {
    $Page = min($_GET['page'], 50000/REQUESTS_PER_PAGE);
    $search->limit(($Page - 1) * REQUESTS_PER_PAGE, REQUESTS_PER_PAGE, 50000);
} else {
    $Page = 1;
    $search->limit(0, REQUESTS_PER_PAGE, 50000);
}

if (empty($_GET['order'])) {
    $CurrentOrder = 'created';
    $CurrentSort = 'desc';
    $Way = SPH_SORT_ATTR_DESC;
    $NewSort = 'asc';
} else {
    if (in_array($_GET['order'], $orderWays)) {
        $CurrentOrder = $_GET['order'];
        if ($_GET['sort'] == 'asc' || $_GET['sort'] == 'desc') {
            $CurrentSort = $_GET['sort'];
            $Way = ($CurrentSort == 'asc' ? SPH_SORT_ATTR_ASC : SPH_SORT_ATTR_DESC);
            $NewSort = ($_GET['sort'] == 'asc' ? 'desc' : 'asc');
        } else {
            error(404);
        }
    } else {
        error(404);
    }
}

switch ($CurrentOrder) {
    case 'votes' :
        $orderBy = "Votes";
        break;
    case 'bounty' :
        $orderBy = "Bounty";
        break;
    case 'created' :
        $orderBy = "TimeAdded";
        break;
    case 'lastvote' :
        $orderBy = "LastVote";
        break;
    case 'filled' :
        $orderBy = "TimeFilled";
        break;
    default :
        $orderBy = "TimeAdded";
        break;
}

$search->setSortMode($Way, $orderBy);

if (count($Queries) > 0) {
    $Query = implode(' ', $Queries);
} else {
    $Query='';
}

$search->setIndex('requests requests_delta');
$searchResults = $search->search($Query, '', 0, [], '', '');
$NumResults = $search->totalResults;
//We don't use sphinxapi's default cache searcher, we use our own functions

if (!empty($searchResults['notfound'])) {
    $SQLResults = get_requests($searchResults['notfound']);
    if (is_array($SQLResults['notfound'])) {
        //Something wasn't found in the db, remove it from results
        reset($SQLResults['notfound']);
        foreach ($SQLResults['notfound'] as $ID) {
            unset($SQLResults['matches'][$ID]);
            unset($searchResults['matches'][$ID]);
        }
    }

    // Merge SQL results with memcached results
    foreach ($SQLResults['matches'] as $ID => $SQLResult) {
        $searchResults['matches'][$ID] = $SQLResult;
    }
}

$Pages = get_pages($Page, $NumResults, REQUESTS_PER_PAGE);

$Requests = $searchResults['matches'] ?? [];

$CurrentURL = get_url(['order', 'sort']);

show_header($Title, 'requests,jquery,jquery.cookie,overlib');

?>
<div class="thin">
    <h2>Requests</h2>

    <div class="linkbox">
<?php 	if (!$BookmarkView) { ?>
        <a href="/requests.php">[Search requests]</a>
<?php 		if (check_perms('site_submit_requests')) { ?>
            <a href="/requests.php?action=new">[New request]</a>
            <a href="/requests.php?type=created">[My requests]</a>
<?php 		}
        if (check_perms('site_vote')) {?>
            <a href="/requests.php?type=voted">[Requests I've voted on]</a>
<?php 		}
        if (!check_perms('site_submit_requests')) {
            $class = $master->repos->permissions->getMinClassPermission('site_submit_requests');
            if ($class !== false) {
?>
            <br/><em> <a href="/articles/view/requests">You must be a <?=$class->Name?> with a ratio of at least 1.05 to be able to make a Request.</a></em>
<?php       }
        }
    } else { ?>
        <a href="/bookmarks.php?type=torrents">[Torrents]</a>
        <a href="/collage/bookmarks">[Collages]</a>
        <a href="/bookmarks.php?type=requests">[Requests]</a>
<?php 	} ?>
    </div>

        <form id="search_form" action="" method="get">
    <div class="head"><?=$Title?></div>
    <div class="box pad">
<?php 	if ($BookmarkView) { ?>
            <input type="hidden" name="action" value="view" />
            <input type="hidden" name="type" value="requests" />
<?php 	} else { ?>
            <input type="hidden" name="type" value="<?=$type?>" />
<?php 	} ?>
            <input type="hidden" name="submitted" value="true" />
<?php 	if (!empty($userID) && is_integer_string($userID)) { ?>
            <input type="hidden" name="userid" value="<?=$userID?>" />
<?php 	} ?>
            <table cellpadding="6" cellspacing="1" border="0" class="" width="100%">
                <tr>
                    <td class="label">Search terms:</td>
                    <td>
                        <input type="text" name="search" size="75" value="<?php if (isset($_GET['search'])) { echo display_str($_GET['search']); } ?>" />
                    </td>
                </tr>
                <tr>
                    <td class="label">Tags:</td>
                    <td>
                        <textarea id="taginput" name="taglist" class="medium" title="Search Tags"><?=(!empty($TagNames) ? display_str(implode(' ', $TagNames)) : '')?></textarea>
                        <br/>
                        <label class="checkbox_label" title="Toggle autocomplete mode on or off.&#10;When turned off, you can access your browser's form history.">
                            <input id="autocomplete_toggle" type="checkbox" name="autocomplete_toggle" checked="checked" />
                            Autocomplete tags
                        </label>
                    </td>
                </tr>
                <tr>
                    <td class="label">Include filled:</td>
                    <td>
                        <input type="checkbox" name="show_filled" <?php  if (!$Submitted || !empty($_GET['show_filled']) || (!$Submitted && !empty($type) && $type == "filled")) { ?>checked="checked"<?php  } ?> />
                    </td>
                </tr>
<?php 	if (check_perms('site_see_old_requests')) { ?>
                <tr>
                    <td class="label">Include old:</td>
                    <td>
                        <input type="checkbox" name="showall" <?php  if (!empty($_GET['showall'])) {?>checked="checked"<?php ; } ?> />
                    </td>
                </tr>
<?php 	} ?>
            </table><br/>
            <table class="cat_list">
<?php
$x=0;
reset($newCategories);
$row = 'a';
foreach ($newCategories as $Cat) {
    if ($x%7==0) {
        if ($x > 0) {
?>
            </tr>
<?php 		} ?>
            <tr class="row<?=$row?>">
<?php
            $row = ($row == 'a' ? 'b' :'a');
    }
    $x++;
?>
                <td>
                    <input type="checkbox" name="filter_cat[<?=($Cat['id'])?>]" id="cat_<?=($Cat['id'])?>" value="1" <?php  if (isset($_GET['filter_cat'][$Cat['id']])) { ?>checked="checked"<?php  } ?>/>
                    <label for="cat_<?=($Cat['id'])?>"><a href="/requests.php?filter_cat[<?=$Cat['id']?>]=1"><?= $Cat['name'] ?></a></label>
                </td>
<?php }?>
                                <td colspan="<?=7-($x%7)?>"></td>
                        </tr>
                </table>

            <table>
                <tr>
                    <td colspan="2" class="center">
                        <input type="submit" value="Search requests" />
                    </td>
                </tr>
            </table>
    </div>
        </form>

    <div class="linkbox">
        <?=$Pages?>
    </div>
    <div class="head">Requests</div>
    <table id="request_table" cellpadding="6" cellspacing="1" border="0" class="shadow" width="100%">
        <tr class="colhead">
            <td class="small cats_col"></td>
            <td width="40%" class="nobr">
                Request Name
            </td>
            <td class="nobr">
                <a href="?order=votes&amp;sort=<?=(($CurrentOrder == 'votes') ? $NewSort : 'desc')?>&amp;<?=$CurrentURL ?>">Votes</a>
            </td>
            <td class="nobr">
                <a href="?order=bounty&amp;sort=<?=(($CurrentOrder == 'bounty') ? $NewSort : 'desc')?>&amp;<?=$CurrentURL ?>">Bounty</a>
            </td>
            <td class="nobr">
                <a href="?order=filled&amp;sort=<?=(($CurrentOrder == 'filled') ? $NewSort : 'desc')?>&amp;<?=$CurrentURL ?>">Filled</a>
            </td>
            <td class="nobr">
                Filled by
            </td>
            <td class="nobr">
                Requested by
            </td>
            <td class="nobr">
                <a href="?order=created&amp;sort=<?=(($CurrentOrder == 'created') ? $NewSort : 'desc')?>&amp;<?=$CurrentURL ?>">Created</a>
            </td>
            <td class="nobr">
                <a href="?order=lastvote&amp;sort=<?=(($CurrentOrder == 'lastvote') ? $NewSort : 'desc')?>&amp;<?=$CurrentURL ?>">Last Vote</a>
            </td>
        </tr>
<?php 	if ($NumResults == 0) { ?>
        <tr class="rowb">
            <td colspan="9" style="text-align:center">
                No requests!
            </td>
        </tr>
<?php 	} else {
        $Row = 'a';
            foreach ($Requests as $RequestID => $Request) {

            list(
                'ID'          => $RequestID,
                'UserID'      => $RequestorID,
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

            $usernameOptions = [
                'drawInBox' => false,
                'colorname' => false,
                'dropDown'  => false,
                'useSpan'   => false,
                'noIcons'   => true,
                'noGroup'   => true,
                'noClass'   => true,
                'noTitle'   => true,
                'noLink'    => true,
            ];
            $RequestorName = $master->render->username($RequestorID, $usernameOptions);

            $RequestVotes = get_votes_array($RequestID);

            $VoteCount = count($RequestVotes['Voters']);

            $IsFilled = ($torrentID != 0);

            $FullName ='<a href="/requests.php?action=view&amp;id='.$RequestID.'" onmouseover="return overlib(overlay'.$RequestID.', FULLHTML);" onmouseout="return nd();">'.$Title.'</a>';
            $Overlay = get_request_overlay_html(
                    $Title,
                    $RequestorName,
                    $Image,
                    $RequestVotes['TotalBounty'],
                    $VoteCount,
                    $IsFilled
            );

            $Row = ($Row == 'a') ? 'b' : 'a';

            $Tags = $Request['Tags'];
?>
        <tr class="row<?=$Row?>">
            <td class="center ">
                <?php  $CatImg = '/static/common/caticons/' . $newCategories[$CategoryID]['image']; ?>
                <div title="<?= $newCategories[$CategoryID]['tag'] ?>">
                    <a href="/requests.php?filter_cat[<?=$CategoryID?>]=1"><img src="<?= $CatImg ?>" /></a>
                </div>
            </td>
            <td>
                <script>
                    var overlay<?=$RequestID?> = <?=json_encode($Overlay)?>
                </script>
                <?=$FullName?>
                                <?php  if ($activeUser['HideTagsInLists'] !== 1) { ?>
                <div class="tags">
<?php
            $TagList = [];
            foreach ($Tags as $TagID => $TagName) {
                $TagList[] = "<a href='?taglist=".$TagName.($BookmarkView ? "&amp;type=requests" : "")."'>".display_str($TagName)."</a>";
            }
            $TagList = implode(' ', $TagList);
?>
                    <?=$TagList?>
                </div>
                                <?php  } ?>
            </td>
            <td class="nobr">
                <form id="form_<?=$RequestID?>">
                    <span id="vote_count_<?=$RequestID?>"><?=$VoteCount?></span>
<?php   	 	if (!$IsFilled && check_perms('site_vote')) { ?>
                    <input type="hidden" id="requestid_<?=$RequestID?>" name="requestid" value="<?=$RequestID?>" />
                    <input type="hidden" id="auth" name="auth" value="<?=$activeUser['AuthKey']?>" />
                    &nbsp;&nbsp; <a href="javascript:VotePromptMB(<?=$RequestID?>)"><strong>(+)</strong></a>
<?php   		} ?>
                </form>
            </td>
            <td class="nobr">
                <span id="bounty_<?=$RequestID?>"><?=get_size($RequestVotes['TotalBounty'])?></span>
            </td>
            <td>
<?php    		if ($IsFilled) { ?>
                <a href="/torrents.php?id=<?=$torrentID?>"><strong><?=time_diff($timeFilled)?></strong></a>
<?php    		} else { ?>
                <strong>No</strong>
<?php    		} ?>
            </td>
            <td>
<?php           if ($IsFilled) {
                    echo torrent_username($FillerID, $FillerID==$UploaderID?$IsAnon:false);
                } else {
                    echo "--";
                } ?>
            </td>
            <td>
                <a href="/user.php?id=<?=$RequestorID?>"><?=$RequestorName?></a>
            </td>
            <td>
                <?=time_diff($timeAdded)?>
            </td>
            <td>
                <?=time_diff($LastVote)?>
            </td>
        </tr>
<?php
        } // while
    } // else
?>
    </table>
    <div class="linkbox">
        <?=$Pages?>
    </div>
</div>
<?php
show_footer();
