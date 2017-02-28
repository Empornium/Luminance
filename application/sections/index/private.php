<?php
include(SERVER_ROOT.'/classes/class_text.php');
$Text = new TEXT;

list($Page,$Limit) = page_limit(5);

if (!$NumResults = $Cache->get_value("news_totalnum") ) {
    $DB->query("SELECT Count(*) FROM news");
    list($NumResults) = $DB->next_record();
    $Cache->cache_value("news_totalnum",$NumResults);
}

if ($Page!=1 || !$News = $Cache->get_value("news")) {

    $DB->query("SELECT ID,
                       Title,
                       Body,
                       Time
                  FROM news
              ORDER BY Time DESC
                 LIMIT $Limit");
    $News = $DB->to_array(false,MYSQLI_NUM,false);
    if ($Page==1) {
        $Cache->cache_value("news",$News);
        $Cache->cache_value('news_latest_id', $News[0][0], 0);
    }
}

$Pages=get_pages($Page,$NumResults,5,9);

if ($Page==1 && $LoggedUser['LastReadNews'] != $News[0][0]) {
    $DB->query("UPDATE users_info SET LastReadNews = '".$News[0][0]."' WHERE UserID = ".$UserID);
    $master->repos->users->uncache($UserID);
    $LoggedUser['LastReadNews'] = $News[0][0];
}

show_header('News','bbcode');
?>
<div class="thin">
    <h2>
        <a style="float:left;margin-top:4px" href="feeds.php?feed=feed_news&amp;user=<?=$LoggedUser['ID']?>&amp;auth=<?=$LoggedUser['RSS_Auth']?>&amp;passkey=<?=$LoggedUser['torrent_pass']?>&amp;authkey=<?=$LoggedUser['AuthKey']?>" title="<?=SITE_NAME?> : News" ><img src="<?=STATIC_SERVER?>/common/symbols/rss.png" alt="RSS feed" /></a>
    <?=SITE_NAME?> <?=((strtolower(substr( SITE_NAME,0,1))===substr( SITE_NAME,0,1))?'news':'News'); ?></h2>

<?php  print_latest_forum_topics(); ?>

    <div class="sidebar">
<?php
    $FeaturedAlbum = $Cache->get_value('featured_album');
    if ($FeaturedAlbum === false) {
        $DB->query("SELECT fa.GroupID, tg.Name, tg.Image, fa.ThreadID, fa.Title FROM featured_albums AS fa JOIN torrents_group AS tg ON tg.ID=fa.GroupID WHERE Ended = 0");
        $FeaturedAlbum = $DB->next_record();

        $Cache->cache_value('featured_album', $FeaturedAlbum, 0);
    }
    if (is_number($FeaturedAlbum['GroupID'])) {
?>
                <div class="head colhead_dark"><strong>Featured Torrent</strong></div>
        <div class="box">
            <div class="center pad"><a href="torrents.php?id=<?=$FeaturedAlbum['GroupID']?>"><?=$FeaturedAlbum['Name']?></a></div>
            <div class="center"><a href="torrents.php?id=<?=$FeaturedAlbum['GroupID']?>" title="<?=$FeaturedAlbum['Name']?>"><img src="<?=$FeaturedAlbum['Image']?>" alt="<?=$FeaturedAlbum['Name']?>" width="100%" /></a></div>
            <div class="center pad"><a href="forums.php?action=viewthread&amp;threadid=<?=$FeaturedAlbum['ThreadID']?>"><em>Read the interview with the band, discuss here</em></a></div>
        </div>
<?php
    }
    if (check_perms('users_mod')) {
?>

            <div class="head colhead_dark"><a href="staffblog.php">Latest staff blog posts</a></div>
        <div class="box">

<?php
if (($Blog = $Cache->get_value('staff_blog')) === false) {
    $DB->query("SELECT
        b.ID,
        um.Username,
        b.Title,
        b.Body,
        b.Time
        FROM staff_blog AS b LEFT JOIN users_main AS um ON b.UserID=um.ID
        ORDER BY Time DESC
        LIMIT 20");
    $Blog = $DB->to_array();
    $Cache->cache_value('staff_blog',$Blog,1209600);
}
if (($ReadTime = $Cache->get_value('staff_blog_read_'.$LoggedUser['ID'])) === false) {
    $DB->query("SELECT Time FROM staff_blog_visits WHERE UserID = ".$LoggedUser['ID']);
    if (list($ReadTime) = $DB->next_record()) {
        $ReadTime = strtotime($ReadTime);
    } else {
        $ReadTime = 0;
    }
    $Cache->cache_value('staff_blog_read_'.$LoggedUser['ID'],$ReadTime,1209600);
}
?>
            <ul class="stats nobullet">
<?php
if (count($Blog) < 5) {
    $Limit = count($Blog);
} else {
    $Limit = 5;
}
for ($i = 0; $i < $Limit; $i++) {
    list($BlogID, $Author, $Title, $Body, $BlogTime, $ThreadID) = $Blog[$i];
?>
                <li>
                    <?=($ReadTime < strtotime($BlogTime))?'<strong>':''?><?=($i + 1)?>. <a href="staffblog.php#blog<?=$BlogID?>"><?=$Title?></a><?=($ReadTime < strtotime($BlogTime))?'</strong>':''?>
                </li>
<?php
}
?>
            </ul>
        </div>
<?php 	}  ?>
        <div class="head colhead_dark">
            <a href="blog.php">Latest blog posts</a>
            <a style="float:right;margin-top:4px" href="feeds.php?feed=feed_blog&amp;user=<?=$LoggedUser['ID']?>&amp;auth=<?=$LoggedUser['RSS_Auth']?>&amp;passkey=<?=$LoggedUser['torrent_pass']?>&amp;authkey=<?=$LoggedUser['AuthKey']?>" title="<?=SITE_NAME?> : Blog" ><img src="<?=STATIC_SERVER?>/common/symbols/rss.png" alt="RSS feed" /></a>
        </div>
        <div class="box">

<?php
if (($Blog = $Cache->get_value('blog')) === false) {
    $DB->query("SELECT
        b.ID,
        um.Username,
        b.Title,
        b.Body,
        b.Time,
        b.ThreadID
        FROM blog AS b LEFT JOIN users_main AS um ON b.UserID=um.ID
        ORDER BY Time DESC
        LIMIT 20");
    $Blog = $DB->to_array();
    $Cache->cache_value('blog',$Blog,1209600);
}
?>
            <ul class="stats nobullet">
<?php
if (count($Blog) < 5) {
    $Limit = count($Blog);
} else {
    $Limit = 5;
}
for ($i = 0; $i < $Limit; $i++) {
    list($BlogID, $Author, $Title, $Body, $BlogTime, $ThreadID) = $Blog[$i];
?>
                <li>
                    <?=($i + 1)?>. <a href="blog.php#blog<?=$BlogID?>"><?=$Title?></a>
                </li>
<?php
}
?>
            </ul>
        </div>
                <div class="head colhead_dark">Stats</div>
                <div class="box">
            <ul class="stats nobullet">

<?php       if (check_perms('site_view_stats')) { ?>
                <li class="center">
<?php           if (check_perms('site_stats_advanced')) { ?>
                    [<a href="stats.php?action=users">Users</a>] &nbsp;
<?php           }   ?>
                    [<a href="stats.php?action=site">Site History</a>]
<?php           if (check_perms('site_stats_advanced')) { ?>
                    &nbsp;[<a href="stats.php?action=torrents">Torrents</a>]
<?php           }   ?>
                </li>
<?php       }

        if (USER_LIMIT>0) { ?>
                <li>Maximum Users: <?=number_format(USER_LIMIT) ?></li>
<?php       }

if (($UserCount = $Cache->get_value('stats_user_count')) === false) {
    $DB->query("SELECT COUNT(ID) FROM users_main WHERE Enabled='1'");
    list($UserCount) = $DB->next_record();
    $Cache->cache_value('stats_user_count', $UserCount, 0); //inf cache
}
$UserCount = (int) $UserCount;
?>
                <li>Enabled Users: <?=number_format($UserCount)?></li>
<?php

if (($UserStats = $Cache->get_value('stats_users')) === false) {
    $DB->query("SELECT COUNT(ID) FROM users_main WHERE Enabled='1' AND LastAccess>'".time_minus(3600*24)."'");
    list($UserStats['Day']) = $DB->next_record();

    $DB->query("SELECT COUNT(ID) FROM users_main WHERE Enabled='1' AND LastAccess>'".time_minus(3600*24*7)."'");
    list($UserStats['Week']) = $DB->next_record();

    $DB->query("SELECT COUNT(ID) FROM users_main WHERE Enabled='1' AND LastAccess>'".time_minus(3600*24*30)."'");
    list($UserStats['Month']) = $DB->next_record();

    $Cache->cache_value('stats_users',$UserStats,0);
}
?>
                <li>Users active today: <?=number_format($UserStats['Day'])?> (<?=number_format($UserStats['Day']/$UserCount*100,2)?>%)</li>
                <li>Users active this week: <?=number_format($UserStats['Week'])?> (<?=number_format($UserStats['Week']/$UserCount*100,2)?>%)</li>
                <li>Users active this month: <?=number_format($UserStats['Month'])?> (<?=number_format($UserStats['Month']/$UserCount*100,2)?>%)</li>
<?php

// overall data stats
if (check_perms('site_stats_advanced')) {

    if (($DataStats = $Cache->get_value('stats_data')) === false) {

        $DB->query("SELECT Sum(Size) FROM torrents ");
        list($DataStats['TotalSize']) = $DB->next_record();
        $Cache->cache_value('stats_data',$DataStats,3600*24);
    }
?>
                <li>Total Data: <?=get_size($DataStats['TotalSize'])?></li>
<?php
}

// torrent stats
if (($TorrentCountLastDay = $Cache->get_value('stats_torrent_count_daily')) === false) {
      $DB->query("SELECT COUNT(ID) FROM torrents WHERE Time > '".time_minus(3600*24,true)."'");
      list($TorrentCountLastDay) = $DB->next_record();
      $Cache->cache_value('stats_torrent_count_daily', $TorrentCountLastDay, 0); //inf cache
}

?>
                <li>New Torrents last day: <?=number_format($TorrentCountLastDay)?></li>
<?php
if (($TorrentCount = $Cache->get_value('stats_torrent_count')) === false) {
    $DB->query("SELECT COUNT(ID) FROM torrents");
    list($TorrentCount) = $DB->next_record();
    $Cache->cache_value('stats_torrent_count', $TorrentCount, 0); //inf cache
}

?>
                <li>Torrents: <?=number_format($TorrentCount)?></li>
<?php
//End Torrent Stats

if (($RequestStats = $Cache->get_value('stats_requests')) === false) {
    $DB->query("SELECT COUNT(ID) FROM requests");
    list($RequestCount) = $DB->next_record();
    $DB->query("SELECT COUNT(ID) FROM requests WHERE FillerID > 0");
    list($FilledCount) = $DB->next_record();
    $Cache->cache_value('stats_requests',array($RequestCount,$FilledCount),11280);
} else {
    list($RequestCount,$FilledCount) = $RequestStats;
}

?>
                <li>Requests: <?=number_format($RequestCount)?> (<?=number_format($FilledCount/$RequestCount*100, 2)?>% filled)</li>
<?php

if ($SnatchStats = $Cache->get_value('stats_snatches')) {
?>
                <li>Snatches: <?=number_format($SnatchStats)?></li>
<?php
}

if (($PeerStats = $Cache->get_value('stats_peers')) === false) {
    //Cache lock!
    $Lock = $Cache->get_value('stats_peers_lock');
    if ($Lock) {
        ?><script type="script/javascript">setTimeout('window.location="//<?=SITE_URL?><?=$_SERVER['REQUEST_URI']?>"', 5)</script><?php
    } else {
        $Cache->cache_value('stats_peers_lock', '1', 10);
        $DB->query("SELECT IF(remaining=0,'Seeding','Leeching') AS Type, COUNT(uid) FROM xbt_files_users WHERE active=1 GROUP BY Type");
        $PeerCount = $DB->to_array(0, MYSQLI_NUM, false);
        $SeederCount = isset($PeerCount['Seeding'][1]) ? $PeerCount['Seeding'][1] : 0;
        $LeecherCount = isset($PeerCount['Leeching'][1]) ? $PeerCount['Leeching'][1] : 0;
        $Cache->cache_value('stats_peers',array($LeecherCount,$SeederCount),0);
    }
} else {
    list($LeecherCount,$SeederCount) = $PeerStats;
}

$Ratio = ratio($SeederCount, $LeecherCount);
$PeerCount = $SeederCount + $LeecherCount;
?>
                <li>Peers: <?=number_format($PeerCount) ?></li>
                <li>Seeders: <?=number_format($SeederCount) ?></li>
                <li>Leechers: <?=number_format($LeecherCount) ?></li>
                <li>Seeder/Leecher Ratio: <?=$Ratio?></li>
            </ul>
                </div>
<?php
if (($TopicID = $Cache->get_value('polls_featured')) === false) {
    $DB->query("SELECT TopicID FROM forums_polls ORDER BY Featured DESC LIMIT 1");
    list($TopicID) = $DB->next_record();
    $Cache->cache_value('polls_featured',$TopicID,0);
}
if ($TopicID) {
    if (($Poll = $Cache->get_value('polls_'.$TopicID)) === false) {
        $DB->query("SELECT Question, Answers, Featured, Closed FROM forums_polls WHERE TopicID='".$TopicID."'");
        list($Question, $Answers, $Featured, $Closed) = $DB->next_record(MYSQLI_NUM, array(1));
        $Answers = unserialize($Answers);
        $DB->query("SELECT Vote, COUNT(UserID) FROM forums_polls_votes WHERE TopicID='$TopicID' AND Vote <> '0' GROUP BY Vote");
        $VoteArray = $DB->to_array(false, MYSQLI_NUM);

        $Votes = array();
        foreach ($VoteArray as $VoteSet) {
            list($Key,$Value) = $VoteSet;
            $Votes[$Key] = $Value;
        }

        foreach (array_keys($Answers) as $i) {
            if (!isset($Votes[$i])) {
                $Votes[$i] = 0;
            }
        }
        $Cache->cache_value('polls_'.$TopicID, array($Question,$Answers,$Votes,$Featured,$Closed), 0);
    } else {
        list($Question,$Answers,$Votes,$Featured,$Closed) = $Poll;
    }

    if (!empty($Votes)) {
        $TotalVotes = array_sum($Votes);
        $MaxVotes = max($Votes);
    } else {
        $TotalVotes = 0;
        $MaxVotes = 0;
    }

    $DB->query("SELECT Vote FROM forums_polls_votes WHERE UserID='".$LoggedUser['ID']."' AND TopicID='$TopicID'");
    list($UserResponse) = $DB->next_record();
    if (!empty($UserResponse) && $UserResponse != 0) {
        $Answers[$UserResponse] = '&raquo; '.$Answers[$UserResponse];
    }

?>
                <div class="head colhead_dark"><strong>Poll<?php  if ($Closed) { echo ' ['.'Closed'.']'; } ?></strong></div>
        <div class="box">
            <div class="pad">
                <p><strong><?=display_str($Question)?></strong></p>
<?php 	if ($UserResponse !== null || $Closed) { ?>
                <ul class="poll nobullet">
<?php 		foreach ($Answers as $i => $Answer) {
            if (!empty($Votes[$i]) && $TotalVotes > 0) {
                $Ratio = $Votes[$i]/$MaxVotes;
                $Percent = $Votes[$i]/$TotalVotes;
            } else {
                $Ratio=0;
                $Percent=0;
            }
?>					<li><?=display_str($Answers[$i])?> (<?=number_format($Percent*100,2)?>%)</li>
                    <li class="graph">
                        <span class="left_poll"></span>
                        <span class="center_poll" style="width:<?=round($Ratio*140)?>px;"></span>
                        <span class="right_poll"></span>
                        <br />
                    </li>
<?php 		} ?>
                </ul>
                <strong>Votes:</strong> <?=number_format($TotalVotes)?><br />
<?php  	} else { ?>
                <div id="poll_results">
                <form id="polls" action="">
                    <input type="hidden" name="action" value="poll_vote"/>
                    <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>"/>
                    <input type="hidden" name="topicid" value="<?=$TopicID?>" />
<?php  		foreach ($Answers as $i => $Answer) { ?>
                    <input type="radio" name="vote" id="answer_<?=$i?>" value="<?=$i?>" />
                    <label for="answer_<?=$i?>"><?=display_str($Answers[$i])?></label><br />
<?php  		} ?>
                    <br /><input type="radio" name="vote" id="answer_0" value="0" /> <label for="answer_0">Blank - Show the results!</label><br /><br />
                    <input type="button" onclick="ajax.post('forums.php','polls',function (response) {$('#poll_results').raw().innerHTML = response});" value="Vote" />
                </form>
                </div>
<?php  	} ?>
                <br /><strong>Topic:</strong> <a href="forums.php?action=viewthread&amp;threadid=<?=$TopicID?>">Visit</a>
            </div>
        </div>
<?php
}
?>
    </div>
    <div class="main_column">
<?php

$Count = 0;
foreach ($News as $NewsItem) {
    list($NewsID,$Title,$Body,$NewsTime) = $NewsItem;
    if (strtotime($NewsTime) > time()) {
        continue;
    }
?>
        <div class="head">
            <?=$Text->full_format($Title)?> <span class="small"><?=time_diff($NewsTime);?></span>
<?php  if (check_perms('admin_manage_news')) {?>
            - <a href="tools.php?action=editnews&amp;id=<?=$NewsID?>">[Edit]</a>
<?php  } ?>
            </div>
                 <div id="news<?=$NewsID?>" class="box">
                    <div class="pad"><?=$Text->full_format($Body, true)?></div>
        </div>
<?php
    if (++$Count > 4) {
        break;
    }
}
?>
    <div class="linkbox"><?=$Pages?></div>
    </div>
    <div class="clear"></div>
</div>
<?php
show_footer(array('disclaimer'=>true));

function contest()
{
    global $DB, $Cache, $LoggedUser;

    list($Contest, $TotalPoints) = $Cache->get_value('contest');
    if (!$Contest) {
        $DB->query("SELECT
            UserID,
            SUM(Points),
            Username
            FROM users_points AS up
            JOIN users_main AS um ON um.ID=up.UserID
            GROUP BY UserID
            ORDER BY SUM(Points) DESC
            LIMIT 20");
        $Contest = $DB->to_array();

        $DB->query("SELECT SUM(Points) FROM users_points");
        list($TotalPoints) = $DB->next_record();

        $Cache->cache_value('contest', array($Contest,$TotalPoints), 600);
    }

?>
<!-- Contest Section -->
        <div class="head colhead_dark"><strong>Quality time scoreboard</strong></div>
        <div class="box">
            <div class="pad">
                <ol style="padding-left:5px;">
<?php
    foreach ($Contest as $User) {
        list($UserID, $Points, $Username) = $User;
?>
                    <li><?=format_username($UserID, $Username)?> (<?=number_format($Points)?>)</li>
<?php
    }
?>
                </ol>
                Total uploads: <?=$TotalPoints?><br />
                <a href="index.php?action=scoreboard">Full scoreboard</a>
            </div>
        </div>
    <!-- END contest Section -->
<?php  } // contest()
