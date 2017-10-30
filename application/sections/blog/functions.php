<?php

function getBlogPosts($section)
{
    global $master;
    if (!in_array($section, ['Blog','Contests'])) return false;
    if (!$blog = $master->cache->get_value(strtolower($section))) {
        $blog = $master->db->raw_query("SELECT
                                    b.ID,
                                    um.Username,
                                    b.Title,
                                    b.Body,
                                    b.Time,
                                    b.ThreadID,
                                    b.Image
                                FROM blog AS b LEFT JOIN users_main AS um ON b.UserID=um.ID
                                WHERE b.Section = :section
                                ORDER BY Time DESC
                                LIMIT 20",
                                    [':section' => $section])->fetchAll(\PDO::FETCH_ASSOC);
        $master->cache->cache_value(strtolower($section), $blog, 1209600);
    }
    return $blog;
}


function printBlogSidebar($section, $numposts = 5)
{
    global $LoggedUser;

    if (!in_array($section, ['Blog','Contests'])) return false;
    if (!is_number($numposts)) return false;
?>
    <div class="head colhead_dark">
        <a href="/<?=lcfirst($section)?>.php">Latest <?=lcfirst($section)?> posts</a>
        <a style="float:right;margin-top:4px" href="/feeds.php?feed=feed_blog&amp;user=<?=$LoggedUser['ID']?>&amp;auth=<?=$LoggedUser['RSS_Auth']?>&amp;passkey=<?=$LoggedUser['torrent_pass']?>&amp;authkey=<?=$LoggedUser['AuthKey']?>" title="<?=SITE_NAME?> : Blog" ><img src="<?=STATIC_SERVER?>/common/symbols/rss.png" alt="RSS feed" /></a>
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
