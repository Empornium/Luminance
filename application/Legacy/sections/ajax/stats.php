<?php
if (in_array($_GET['stat'], ['inbox', 'uploads', 'bookmarks', 'notifications', 'subscriptions', 'comments', 'friends', 'logs', 'bonus', 'sandbox', 'conncheck'])) {
    $master->cache->deleteValue('stats_links');
}
