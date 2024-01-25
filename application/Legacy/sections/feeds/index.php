<?php
// Main feeds page

//Lets prevent people from clearing feeds
if (isset($_GET['clearcache'])) {
    unset($_GET['clearcache']);
}

$feed = new Luminance\Legacy\Feed;
$feed->UseSSL = $this->master->request->ssl;

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
    !is_integer_string($_GET['user']) ||
    strlen($_GET['authkey']) != 32 ||
    strlen($_GET['passkey']) != 32 ||
    strlen($_GET['auth']) != 32
) {
    $feed->open_feed();
    $feed->channel('Blocked', 'RSS feed.');
    $feed->close_feed();
    die();
}

$User = (int) $_GET['user'];

$enabled = getUserEnabled($User);

if (md5($User.RSS_HASH.$_GET['passkey']) != $_GET['auth'] || $enabled != 1) {
    $feed->open_feed();
    $feed->channel('Blocked', 'RSS feed.');
    $feed->close_feed();
    die();
}

$feed->open_feed();
switch ($_GET['feed']) {
    case 'feed_news':
        $bbCode = new \Luminance\Legacy\Text;
        $feed->channel('News', 'RSS feed for site news.');
        if (!$news = $master->cache->getValue('news')) {
            $news = $master->db->rawQuery(
                "SELECT ID,
                        Title,
                        Body,
                        Time
                   FROM news
               ORDER BY Time DESC
                  LIMIT 10"
            )->fetchAll(\PDO::FETCH_OBJ);
            $master->cache->cacheValue('news', $news, 1209600);
        }
        $Count = 0;
        foreach ($news as $newsItem) {
            if (strtotime($newsItem->Time) >= time()) {
                continue;
            }
            echo $feed->item($newsItem->Title, $bbCode->strip_bbcode($newsItem->Body), 'index.php#news'.$newsItem->ID, SITE_NAME.' Staff', '', '', $newsItem->Time);
            if (++$Count > 4) {
                break;
            }
        }
        break;
    case 'feed_blog':
        $bbCode = new \Luminance\Legacy\Text;
        $feed->channel('Blog', 'RSS feed for site blog.');
        if (!$blog = $master->cache->getValue('feed_blog')) {
            $blog = $master->db->rawQuery(
                "SELECT b.ID,
                        b.Title,
                        b.Body,
                        b.Time,
                        b.ThreadID,
                        b.Image
                   FROM blog AS b
               ORDER BY Time DESC
                  LIMIT 20",
            )->fetchAll(\PDO::FETCH_OBJ);
            $master->cache->cacheValue('feed_blog', $blog, 1209600);
        }
        foreach ($blog as $blogItem) {
            echo $feed->item($blogItem->Title, $bbCode->strip_bbcode($blogItem->Body), 'forum/thread/'.$blogItem->ThreadID, SITE_NAME.' Staff', '', '', $blogItem->Time);
        }
        break;
    case 'torrents_all':
        $feed->channel('All Torrents', 'RSS feed for all new Torrent uploads.');
        $feed->retrieve('torrents_all', $_GET['authkey'], $_GET['passkey']);
        break;

    default:
        // Personalized torrents
        if (empty($_GET['name']) && substr($_GET['feed'], 0, 16) == 'torrents_notify_') {
            // All personalized torrent notifications
            $feed->channel('Personalized torrent notifications', 'RSS feed for personalized torrent notifications.');
            $feed->retrieve($_GET['feed'], $_GET['authkey'], $_GET['passkey']);
        } elseif (!empty($_GET['name']) && substr($_GET['feed'], 0, 16) == 'torrents_notify_') {
            // Specific personalized torrent notification channel
            $feed->channel(display_str($_GET['name']), 'Personal RSS feed: '.display_str($_GET['name']));
            $feed->retrieve($_GET['feed'], $_GET['authkey'], $_GET['passkey']);
        } elseif (!empty($_GET['name']) && substr($_GET['feed'], 0, 21) == 'torrents_bookmarks_t_') {
            // Bookmarks
            $feed->channel('Bookmarked torrent notifications', 'RSS feed for bookmarked torrents.');
            $feed->retrieve($_GET['feed'], $_GET['authkey'], $_GET['passkey']);
        } else {
            $feed->channel('All Torrents', 'RSS feed for all new Torrent uploads.');
            $feed->retrieve('torrents_all', $_GET['authkey'], $_GET['passkey']);
        }
}
$feed->close_feed();
