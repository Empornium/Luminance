<?php
// Main feeds page
// The feeds don't use script_start.php, their code resides entirely in feeds.php in the document root
// Bear this in mind when you try to use script_start functions.
require_once(SERVER_ROOT . '/classes/class_feed.php');

//Lets prevent people from clearing feeds
if (isset($_GET['clearcache'])) {
    unset($_GET['clearcache']);
}

$Feed = NEW \FEED;
$Feed->UseSSL = $this->master->request->ssl;
$Cache = $this->master->cache;

header('Cache-Control: no-cache, must-revalidate, post-check=0, pre-check=0');
header('Pragma:');
header('Expires: '.date('D, d M Y H:i:s', time()+(2*60*60)).' GMT');
header('Last-Modified: '.date('D, d M Y H:i:s').' GMT');

if (
    empty($_GET['feed']) ||
    empty($_GET['authkey']) ||
    empty($_GET['auth']) ||
    empty($_GET['passkey']) ||
    empty($_GET['user']) ||
    !is_number($_GET['user']) ||
    strlen($_GET['authkey']) != 32 ||
    strlen($_GET['passkey']) != 32 ||
    strlen($_GET['auth']) != 32
) {
    $Feed->open_feed();
    $Feed->channel('Blocked', 'RSS feed.');
    $Feed->close_feed();
    die();
}

$User = (int) $_GET['user'];

if (!$Enabled = $Cache->get_value('enabled_'.$User)) {
    $DB = $master->olddb;
    $DB->query("SELECT Enabled FROM users_main WHERE ID='$User'");
    list($Enabled) = $DB->next_record();
    $Cache->cache_value('enabled_'.$User, $Enabled, 0);
}

if (md5($User.RSS_HASH.$_GET['passkey']) != $_GET['auth'] || $Enabled != 1) {
    $Feed->open_feed();
    $Feed->channel('Blocked', 'RSS feed.');
    $Feed->close_feed();
    die();
}

$Feed->open_feed();
switch ($_GET['feed']) {
    case 'feed_news':
        include(SERVER_ROOT.'/classes/class_text.php');
        $Text = new TEXT;
        $Feed->channel('News', 'RSS feed for site news.');
        if (!$News = $Cache->get_value('news')) {
            if (!$DB) {
                $DB = $master->olddb;
            }
            $DB->query("SELECT
                ID,
                Title,
                Body,
                Time
                FROM news
                ORDER BY Time DESC
                LIMIT 10");
            $News = $DB->to_array(false,MYSQLI_NUM);
            $Cache->cache_value('news',$News,1209600);
        }
        $Count = 0;
        foreach ($News as $NewsItem) {
            list($NewsID,$Title,$Body,$NewsTime) = $NewsItem;
            if (strtotime($NewsTime) >= time()) {
                continue;
            }
            //echo $Feed->item($Title, "test" , 'index.php#news'.$NewsID, SITE_NAME.' Staff','','',$NewsTime);
            echo $Feed->item($Title, $Text->strip_bbcode($Body), 'index.php#news'.$NewsID, SITE_NAME.' Staff','','',$NewsTime);
            if (++$Count > 4) {
                break;
            }
        }
        break;
    case 'feed_blog':
        include(SERVER_ROOT.'/classes/class_text.php');
        $Text = new TEXT;
        $Feed->channel('Blog', 'RSS feed for site blog.');
        if (!$Blog = $Cache->get_value('blog')) {
            if (!$DB) {
                $DB = $master->olddb;
            }
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
            $Cache->cache_value('Blog',$Blog,1209600);
        }
        foreach ($Blog as $BlogItem) {
            list($BlogID, $Author, $Title, $Body, $BlogTime, $ThreadID) = $BlogItem;
            echo $Feed->item($Title, $Text->strip_bbcode($Body), 'forums.php?action=viewthread&amp;threadid='.$ThreadID, SITE_NAME.' Staff','','',$BlogTime);
        }
        break;
    case 'torrents_all':
        $Feed->channel('All Torrents', 'RSS feed for all new torrent uploads.');
        $Feed->retrieve('torrents_all',$_GET['authkey'],$_GET['passkey']);
        break;

    default:
        // Personalized torrents
        if (empty($_GET['name']) && substr($_GET['feed'], 0, 16) == 'torrents_notify_') {
            // All personalized torrent notifications
            $Feed->channel('Personalized torrent notifications', 'RSS feed for personalized torrent notifications.');
            $Feed->retrieve($_GET['feed'],$_GET['authkey'],$_GET['passkey']);
        } elseif (!empty($_GET['name']) && substr($_GET['feed'], 0, 16) == 'torrents_notify_') {
            // Specific personalized torrent notification channel
            $Feed->channel(display_str($_GET['name']), 'Personal RSS feed: '.display_str($_GET['name']));
            $Feed->retrieve($_GET['feed'],$_GET['authkey'],$_GET['passkey']);
        } elseif (!empty($_GET['name']) && substr($_GET['feed'], 0, 21) == 'torrents_bookmarks_t_') {
            // Bookmarks
            $Feed->channel('Bookmarked torrent notifications', 'RSS feed for bookmarked torrents.');
            $Feed->retrieve($_GET['feed'],$_GET['authkey'],$_GET['passkey']);
        } else {
            $Feed->channel('All Torrents', 'RSS feed for all new torrent uploads.');
            $Feed->retrieve('torrents_all',$_GET['authkey'],$_GET['passkey']);
        }
}
$Feed->close_feed();
