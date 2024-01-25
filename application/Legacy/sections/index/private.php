<?php

require_once(SERVER_ROOT.'/Legacy/sections/blog/functions.php');
$bbCode = new \Luminance\Legacy\Text;

list($Page, $Limit) = page_limit(5);

if (!$numResults = $master->cache->getValue("news_totalnum")) {
    $numResults = $master->db->rawQuery(
        "SELECT Count(*)
           FROM news"
    )->fetchColumn();
    $master->cache->cacheValue("news_totalnum", $numResults);
}

if ($Page!=1 || !$news = $master->cache->getValue("news")) {

    $news = $master->db->rawQuery(
        "SELECT ID,
                Title,
                Body,
                Time
           FROM news
       ORDER BY Time DESC
          LIMIT {$Limit}"
    )->fetchAll(\PDO::FETCH_OBJ);
    if ($Page==1) {
        $master->cache->cacheValue("news", $news);
        $master->cache->cacheValue('news_latest_id', $news[0]->ID, 0);
    }
}

$Pages = get_pages($Page, $numResults, 5, 9);

if ($Page==1 && $activeUser['LastReadNews'] != $news[0]->ID) {
    $master->db->rawQuery(
        "UPDATE users_info
            SET LastReadNews = ?
          WHERE UserID = ?",
        [$news[0]->ID, $userID]
    );
    $master->repos->users->uncache($userID);
    $activeUser['LastReadNews'] = $news[0]->ID;
}

show_header('News', 'bbcode');
?>
<div class="thin">
    <h2>
        <a style="float:left;margin-top:4px" href="/feeds.php?feed=feed_news&amp;user=<?=$activeUser['ID']?>&amp;auth=<?=$activeUser['RSS_Auth']?>&amp;passkey=<?=$activeUser['torrent_pass']?>&amp;authkey=<?=$activeUser['AuthKey']?>" title="<?=SITE_NAME?> : News" ><img src="<?=STATIC_SERVER?>/common/symbols/rss.png" alt="RSS feed" /></a>
    <?=SITE_NAME?> <?=((strtolower(substr( SITE_NAME,0,1)) === substr( SITE_NAME,0,1))?'news':'News'); ?></h2>

<?php  print_latest_forum_topics(); ?>

    <div class="sidebar">
<?php
    $featuredAlbum = $master->cache->getValue('featured_album');
    if ($featuredAlbum === false) {
        $featuredAlbum = $master->db->rawQuery(
            "SELECT fa.GroupID,
                    tg.Name,
                    tg.Image,
                    fa.ThreadID,
                    fa.Title
               FROM featured_albums AS fa
               JOIN torrents_group AS tg ON tg.ID=fa.GroupID
              WHERE Ended = 0"
        )->fetch(\PDO::FETCH_BOTH);
        $master->cache->cacheValue('featured_album', $featuredAlbum, 0);
    }
    if (is_integer_string($featuredAlbum['GroupID'] ?? null)) {
?>
        <div class="box">
            <div class="head colhead_dark"><strong>Featured Torrent</strong></div>
            <div class="box">
                <div class="center pad"><a href="/torrents.php?id=<?=$featuredAlbum['GroupID']?>"><?=$featuredAlbum['Name']?></a></div>
                <div class="center"><a href="/torrents.php?id=<?=$featuredAlbum['GroupID']?>" title="<?=$featuredAlbum['Name']?>"><img src="<?=$featuredAlbum['Image']?>" alt="<?=$featuredAlbum['Name']?>" width="100%" /></a></div>
                <div class="center pad"><a href="/forum/thread/<?=$featuredAlbum['ThreadID']?>"><em>Read the interview with the band, discuss here</em></a></div>
            </div>
        </div>
<?php
    }
    if (check_perms('users_mod')) {
?>

        <div class="head colhead_dark"><a href="/staff/blog">Latest staff blog posts</a></div>
        <div class="box">

<?php
        if (($blog = $master->cache->getValue('staff_blog')) === false) {
            $blog = $master->db->rawQuery(
                "SELECT b.ID,
                        u.Username,
                        b.Title,
                        b.Body,
                        b.Time
                   FROM staff_blog AS b LEFT JOIN users AS u ON b.UserID=u.ID
               ORDER BY Time DESC
                  LIMIT 20"
            )->fetchAll(\PDO::FETCH_BOTH);
            $master->cache->cacheValue('staff_blog', $blog, 1209600);
        }

        if (($readTime = $master->cache->getValue('staff_blog_read_'.$activeUser['ID'])) === false) {
            $readTime = $master->db->rawQuery(
                "SELECT Time
                   FROM staff_blog_visits
                  WHERE UserID = ?",
                [$activeUser['ID']]
            )->fetchColumn();
            if (!($readTime === false)) {
                $readTime = strtotime($readTime);
            } else {
                $readTime = 0;
            }
            $master->cache->cacheValue("staff_blog_read_{$activeUser['ID']}", $readTime, 1209600);
        }
?>
            <ul class="stats nobullet">
<?php
        if (count($blog) < 5) {
            $Limit = count($blog);
        } else {
            $Limit = 5;
        }
        for ($i = 0; $i < $Limit; $i++) {
            list($BlogID, $Author, $Title, $Body, $BlogTime) = $blog[$i];
?>
                <li>
                    <?=($readTime < strtotime($BlogTime))?'<strong>':''?><?=($i + 1)?>. <a href="/staffblog.php#blog<?=$BlogID?>"><?=$Title?></a><?= ($readTime < strtotime($BlogTime)) ? '</strong>' : '' ?>
                </li>
<?php
}
?>
            </ul>
        </div>
<?php
    }

        printBlogSidebar('Blog');
        printBlogSidebar('Contests');

?>
                <div class="head colhead_dark">Stats</div>
                <div class="box">
            <ul class="stats nobullet">

<?php       if (check_perms('site_view_stats')) { ?>
                <li class="center">
<?php           if (check_perms('site_stats_advanced')) { ?>
                    [<a href="/stats/users">Users</a>] &nbsp;
                    [<a href="/stats/forum">Forum</a>] &nbsp;
<?php           }   ?>
                    [<a href="/stats/site">Site</a>]
<?php           if (check_perms('site_stats_advanced')) { ?>
                    &nbsp;[<a href="/stats/torrents">Torrents</a>]
<?php           }   ?>
                </li>
<?php       }

        if ($master->options->UsersLimit > 0) { ?>
                <li>Maximum Users: <?=number_format($master->options->UsersLimit) ?></li>
<?php       }

if (($userCount = $master->cache->getValue('stats_user_count')) === false) {
    $userCount = $master->db->rawQuery(
        "SELECT COUNT(ID)
           FROM users_main
          WHERE Enabled = '1'"
    )->fetchColumn();
    $master->cache->cacheValue('stats_user_count', $userCount, 0); //inf cache
}
$userCount = (int) $userCount;
?>
                <li>Enabled Users: <?= number_format($userCount) ?></li>
<?php

$query = "SELECT COUNT(ID) FROM users_main WHERE Enabled = '1' AND LastAccess > ?";
if (($userStats = $master->cache->getValue('stats_users')) === false) {
    $userStats['Day'] = $master->db->rawQuery(
        $query,
        [time_minus(3600 * 24)]
    )->fetchColumn();

    $userStats['Week'] = $master->db->rawQuery(
        $query,
        [time_minus(3600 * 24 * 7)]
    )->fetchColumn();

    $userStats['Month'] = $master->db->rawQuery(
        $query,
        [time_minus(3600 * 24 * 30)]
    )->fetchColumn();

    $master->cache->cacheValue('stats_users', $userStats, 0);
}
?>
                <li>Users active today: <?=number_format($userStats['Day'])?> (<?=number_format($userStats['Day']/$userCount*100,2)?>%)</li>
                <li>Users active this week: <?=number_format($userStats['Week'])?> (<?=number_format($userStats['Week']/$userCount*100,2)?>%)</li>
                <li>Users active this month: <?=number_format($userStats['Month'])?> (<?=number_format($userStats['Month']/$userCount*100,2)?>%)</li>
<?php

// overall data stats
if (check_perms('site_stats_advanced')) {

    if (($dataStats = $master->cache->getValue('stats_data')) === false) {

        $dataStats['TotalSize'] = $master->db->rawQuery(
            "SELECT Sum(Size)
               FROM torrents"
        )->fetchColumn();
        $master->cache->cacheValue('stats_data', $dataStats,3600*24);
    }
?>
                <li>Total Data: <?= get_size($dataStats['TotalSize']) ?></li>
<?php
}

// torrent stats
if (($torrentCountLastDay = $master->cache->getValue('stats_torrent_count_daily')) === false) {
      $torrentCountLastDay = $master->db->rawQuery(
          "SELECT COUNT(ID)
             FROM torrents
            WHERE Time > ?",
          [time_minus(3600 * 24, true)]
      )->fetchColumn();
      $master->cache->cacheValue('stats_torrent_count_daily', $torrentCountLastDay, 0); //inf cache
}

?>
                <li>New Torrents last day: <?= number_format($torrentCountLastDay) ?></li>
<?php
if (($torrentCount = $master->cache->getValue('stats_torrent_count')) === false) {
    $torrentCount = $master->db->rawQuery(
        "SELECT COUNT(ID)
           FROM torrents"
    )->fetchColumn();
    $master->cache->cacheValue('stats_torrent_count', $torrentCount, 0); //inf cache
}

?>
                <li>Torrents: <?= number_format($torrentCount) ?></li>
<?php
//End Torrent Stats

if (($requestStats = $master->cache->getValue('stats_requests')) === false) {
    $requestCount = $master->db->rawQuery(
        "SELECT COUNT(ID)
           FROM requests"
    )->fetchColumn();
    $filledCount = $master->db->rawQuery(
        "SELECT COUNT(ID)
           FROM requests
          WHERE FillerID > 0"
    )->fetchColumn();
    $master->cache->cacheValue('stats_requests', [$requestCount, $filledCount], 11280);
} else {
    list($requestCount, $filledCount) = $requestStats;
}

?>
                <li>Requests: <?= number_format($requestCount) ?> (<?= ($requestCount > 0 ? number_format($filledCount / $requestCount * 100, 2) : '0') ?>% filled)</li>
<?php

if ($SnatchStats = $master->cache->getValue('stats_snatches')) {
?>
                <li>Snatches: <?=number_format($SnatchStats)?></li>
<?php
}

if (($PeerStats = $master->cache->getValue('stats_peers')) === false) {
    //Cache lock!
    $Lock = $master->cache->getValue('stats_peers_lock');
    if ($Lock) {
        ?><script type="script/javascript">setTimeout('window.location="//<?=SITE_URL?><?=$_SERVER['REQUEST_URI']?>"', 5)</script><?php
    } else {
        $master->cache->cacheValue('stats_peers_lock', '1', 10);
        $peerCount = $master->db->rawQuery(
            "SELECT IF (remaining = 0, 'Seeding', 'Leeching') AS Type,
                    COUNT(uid)
               FROM xbt_files_users
              WHERE active = 1
           GROUP BY Type"
        )->fetchAll(\PDO::FETCH_KEY_PAIR);
        $SeederCount = $peerCount['Seeding'] ?? 0;
        $LeecherCount = $peerCount['Leeching'] ?? 0;
        $master->cache->cacheValue('stats_peers', [$LeecherCount, $SeederCount], 0);
    }
} else {
    list($LeecherCount, $SeederCount) = $PeerStats;
}

$SeederCount = $SeederCount ?? 0;
$LeecherCount = $LeecherCount ?? 0;

$Ratio = ratio($SeederCount, $LeecherCount);
$PeerCount = $SeederCount + $LeecherCount;
?>
                <li>Peers: <?=number_format($PeerCount) ?></li>
                <li>Seeders: <?=number_format($SeederCount) ?></li>
                <li>Leechers: <?=number_format($LeecherCount ) ?></li>
                <li>Seeder/Leecher Ratio: <?=$Ratio?></li>
            </ul>
                </div>
<?php
if (($threadID = $master->cache->getValue('polls_featured')) === false) {
    $threadID = $master->db->rawQuery(
        "SELECT ThreadID
           FROM forums_polls
          WHERE Featured IS NOT NULL
       ORDER BY Featured DESC
          LIMIT 1"
    )->fetchColumn();
    $master->cache->cacheValue('polls_featured', $threadID, 0);
}
if ($threadID) {
    if (($Poll = $master->cache->getValue("polls_{$threadID}")) === false) {
        $nextRecord = $master->db->rawQuery(
            "SELECT Question,
                    Answers,
                    Featured,
                    Closed
               FROM forums_polls
              WHERE ThreadID = ?",
            [$threadID]
        )->fetch(\PDO::FETCH_NUM);
        list($Question, $Answers, $Featured, $Closed) = $nextRecord;
        $Answers = unserialize($Answers);
        $voteArray = $master->db->rawQuery(
            "SELECT Vote,
                    COUNT(UserID)
               FROM forums_polls_votes
              WHERE ThreadID = ?
                AND Vote <> '0'
           GROUP BY Vote",
            [$threadID]
        )->fetch(\PDO::FETCH_NUM);

        $Votes = [];
        foreach ($voteArray as $VoteSet) {
            list($Key, $Value) = $VoteSet;
            $Votes[$Key] = $Value;
        }

        foreach (array_keys($Answers) as $i) {
            if (!isset($Votes[$i])) {
                $Votes[$i] = 0;
            }
        }
        $master->cache->cacheValue("polls_{$threadID}", [$Question, $Answers, $Votes, $Featured, $Closed], 0);
    } else {
        list($Question, $Answers, $Votes, $Featured, $Closed) = $Poll;
    }

    $thread = $master->repos->forumthreads->load($threadID);
    if ($thread->poll instanceof \Luminance\Entities\ForumPoll) {
        $Votes = $thread->poll->votes;
    } else {
        $Votes = [];
    }

    if (!empty($Votes)) {
        $TotalVotes = array_sum(array_column($Votes, 'total'));
        $MaxVotes = max(array_column($Votes, 'total'));
    } else {
        $TotalVotes = 0;
        $MaxVotes = 0;
    }

    $userResponse = $master->db->rawQuery(
        "SELECT Vote
           FROM forums_polls_votes
          WHERE UserID = ?
            AND ThreadID = ?",
        [$activeUser['ID'], $threadID]
    )->fetchColumn();
    if (!($userResponse === false)) {
        if (array_key_exists($userResponse, $Answers)) {
            $Answers[$userResponse] = '&raquo; '.$Answers[$userResponse];
        }
    }

?>
                <div class="head colhead_dark"><strong>Poll<?php  if ($Closed) { echo ' ['.'Closed'.']'; } ?></strong></div>
        <div class="box">
            <div class="pad">
                <p><strong><?=display_str($Question)?></strong></p>
<?php 	if ($userResponse !== null || $Closed) { ?>
                <ul class="poll nobullet">
<?php 		foreach ($Answers as $i => $Answer) {
            if (!empty($Votes[$i]) && $TotalVotes > 0) {
                $Ratio = $Votes[$i]['total']/$MaxVotes;
                $Percent = $Votes[$i]['total']/$TotalVotes;
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
                    <input type="hidden" name="token" value="<?=$master->secretary->getToken("thread.poll.vote")?>" />
<?php  		foreach ($Answers as $i => $Answer) { ?>
                    <input type="radio" name="vote" id="answer_<?=$i?>" value="<?=$i?>" />
                    <label for="answer_<?=$i?>"><?=display_str($Answers[$i])?></label><br />
<?php  		} ?>
                    <br /><input type="radio" name="vote" id="answer_0" value="0" /> <label for="answer_0">Blank - Show the results!</label><br /><br />
                    <input type="button" onclick="ajax.post('/forum/thread/<?= $threadID ?>/poll/vote', 'polls', function(response) {location.reload();});" value="Vote" />
                </form>
                </div>
<?php  	} ?>
                <br /><strong>Topic:</strong> <a href="/forum/thread/<?= $threadID ?>">Visit</a>
            </div>
        </div>
<?php
}
?>
    </div>
    <div class="main_column">
<?php

$Count = 0;
foreach ($news as $newsItem) {
    if (strtotime($newsItem->Time) > time()) {
        continue;
    }
?>
        <div class="head">
            <?=$bbCode->full_format($newsItem->Title)?> <span class="small"><?=time_diff($newsItem->Time);?></span>
<?php  if (check_perms('admin_manage_news')) {?>
            - <a href="/tools.php?action=editnews&amp;id=<?=$newsItem->ID?>">[Edit]</a>
<?php  } ?>
            </div>
                 <div id="news<?=$newsItem->ID?>" class="box">
                    <div class="pad"><?=$bbCode->full_format($newsItem->Body, true)?></div>
        </div>
<?php
    if (++$Count > 4) {
        break;
    }
}
?>
    <div class="linkbox pager"><?= $Pages ?></div>
    </div>
    <div class="clear"></div>
</div>
<?php
show_footer(['disclaimer'=>true]);

function contest()
{
    global $master, $activeUser;

    list($contest, $totalPoints) = $master->cache->getValue('contest');
    if (!$contest) {
        $contest = $master->db->rawQuery(
            "SELECT UserID,
                    SUM(Points),
                    Username
               FROM users_points AS up
               JOIN users AS u ON u.ID=up.UserID
           GROUP BY UserID
           ORDER BY SUM(Points) DESC
              LIMIT 20"
        )->fetchAll(\PDO::FETCH_BOTH);
        $totalPoints = $master->db->rawQuery(
            "SELECT SUM(Points)
               FROM users_points"
        )->fetchColumn();
        $master->cache->cacheValue('contest', [$contest, $totalPoints], 600);
    }

?>
<!-- Contest Section -->
        <div class="head colhead_dark"><strong>Quality time scoreboard</strong></div>
        <div class="box">
            <div class="pad">
                <ol style="padding-left:5px;">
<?php
    foreach ($contest as $User) {
        list($userID, $Points, $Username) = $User;
?>
                    <li><?=format_username($userID)?> (<?=number_format($Points)?>)</li>
<?php
    }
?>
                </ol>
                Total uploads: <?=$totalPoints?><br />
                <a href="/index.php?action=scoreboard">Full scoreboard</a>
            </div>
        </div>
    <!-- END contest Section -->
<?php  } // contest()
