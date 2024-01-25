<?php

function getBlogPosts($section)
{
    global $master;
    if (!in_array($section, ['Blog', 'Contests'])) return false;
    if (!$blog = $master->cache->getValue(strtolower($section))) {
        $blog = $master->db->rawQuery(
            "SELECT b.ID,
                    u.Username,
                    b.Title,
                    b.Body,
                    b.Time,
                    b.ThreadID,
                    b.Image
               FROM blog AS b
          LEFT JOIN users AS u ON b.UserID=u.ID
              WHERE b.Section = :section
           ORDER BY Time DESC
              LIMIT 20",
            [':section' => $section]
        )->fetchAll(\PDO::FETCH_ASSOC);
        $master->cache->cacheValue(strtolower($section), $blog, 1209600);
    }
    return $blog;
}


function printBlogSidebar($section, $numposts = 5)
{
    global $activeUser;

    if (!in_array($section, ['Blog', 'Contests'])) return false;
    if (!is_integer_string($numposts)) return false;
?>
    <div class="head colhead_dark">
        <a href="/<?=lcfirst($section)?>.php">Latest <?=lcfirst($section)?> posts</a>
        <a style="float:right;margin-top:4px" href="/feeds.php?feed=feed_blog&amp;user=<?=$activeUser['ID']?>&amp;auth=<?=$activeUser['RSS_Auth']?>&amp;passkey=<?=$activeUser['torrent_pass']?>&amp;authkey=<?=$activeUser['AuthKey']?>" title="<?=SITE_NAME?> : Blog" ><img src="<?=STATIC_SERVER?>/common/symbols/rss.png" alt="RSS feed" /></a>
<?php
        if (check_perms('admin_manage_blog')) {     ?>
            <a href="/<?=lcfirst($section)?>.php?action=newpost" style="float:right;margin-right:11px" title="Add new <?=lcfirst($section)?> post">Add new</a>
<?php   }    ?>
    </div>
    <div class="box pad">
<?php
        $blog = getBlogPosts($section);
        $limit = min(count($blog), $numposts);

        for ($i = 0; $i < $limit; $i++) {
            $item = $blog[$i];
?>
            <div class="center blog">
                <a href="/<?=lcfirst($section)?>.php#blog<?=$item['ID']?>">
<?php
            if ($item['Image']) {    ?>
                    <div class="pad center">
                            <img style="max-width: 100%;max-height:200px;" src="<?=$item['Image']?>" />
                    </div>
<?php       }     ?>
                    <?=$item['Title']?></a>
            </div>
<?php
        }
?>
    </div>
<?php
    return true;
}
