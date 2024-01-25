<?php

$bbCode = new \Luminance\Legacy\Text;


if (!in_array($blogSection,['Blog', 'Contests'])) $blogSection = 'Blog';

$blog = getBlogPosts($blogSection);

if ($blog) {
    // catch up LastRead vars
    if ($blog[0]['ID']) {
        if ($activeUser['LastRead'.$blogSection] != $blog[0]['ID']) {
            // this check is done at top of page but we put it here again to stress how sure
            // we have to be before inserting this var directly into the sql
            if (!in_array($blogSection,['Blog', 'Contests'])) $blogSection = 'Blog';
            $master->db->rawQuery(
                "UPDATE users_info
                    SET LastRead$blogSection = ?
                  WHERE UserID = ?",
                [$blog[0]['ID'], $activeUser['ID']]
            );
            $activeUser['LastRead'.$blogSection] = $blog[0]['ID'];
        }
        $master->repos->users->uncache($activeUser['ID']);
    }
}

show_header($blogSection, 'bbcode');

?>
<div class="thin">
    <h2>
        <a style="float:left;margin-top:4px" href="/feeds.php?feed=feed_blog&amp;user=<?=$activeUser['ID']?>&amp;auth=<?=$activeUser['RSS_Auth']?>&amp;passkey=<?=$activeUser['torrent_pass']?>&amp;authkey=<?=$activeUser['AuthKey']?>" title="<?=SITE_NAME?> - Blog" ><img src="<?=STATIC_SERVER?>/common/symbols/rss.png" alt="RSS feed" /></a>
        <?=$master->settings->main->site_name." $blogSection"?>
    </h2>
</div>
<?php

if (check_perms('admin_manage_blog')) {
?>
    <div class="linkbox">
        [<a href="/<?=$thispage?>?action=newpost">Add new <?=lcfirst($blogSection)?> post</a>]
    </div>
<?php
}

if ($blog) {

?>
    <div class="thin">
<?php
        foreach ($blog as $item) {
?>
            <div id="blog<?=$item['ID']?>" class="head">
                <strong><?=$item['Title']?></strong> - posted <?=time_diff($item['Time']);?> by <?=$item['Username']?>
<?php       if (check_perms('admin_manage_blog')) { ?>
                - <a href="/<?=$thispage?>?action=editpost&amp;id=<?=$item['ID']?>">[Edit]</a>
                <a href="/<?=$thispage?>?action=deletepost&amp;id=<?=$item['ID']?>&amp;auth=<?=$activeUser['AuthKey']?>">[Delete]</a>
<?php       }       ?>
            </div>
            <div class="box blog">
<?php
            if ($item['Image']) { ?>
                <div class="pad center">
                    <img style="max-width: 100%;max-height:1000px;" src="<?=$item['Image']?>" />
                </div>
<?php       }  ?>
                <div class="pad">
                    <?=$bbCode->full_format($item['Body'], true)?>
<?php       if ($item['ThreadID']) { ?>
                    <br /><br />
                    <em><a href="/forum/thread/<?=$item['ThreadID']?>">Discuss this post here</a></em>
<?php           if (check_perms('admin_manage_blog')) { ?>
                    &nbsp;<a href="/<?=$thispage?>?action=removelink&amp;id=<?=$item['ID']?>&amp;auth=<?=$activeUser['AuthKey']?>">[Remove link]</a>
<?php           }
            } ?>
                </div>
            </div>
            <br />
<?php
        }
?>
    </div>
<?php
}
show_footer();
