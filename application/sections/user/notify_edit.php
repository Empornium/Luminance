<?php
if (!check_perms('site_torrents_notify')) { error(403); }
show_header('Manage notifications');
?>
<div class="thin">
    <h2>
        <a style="float:left;margin-top:4px" title="RSS Feed - All your torrent notification filters" href="feeds.php?feed=torrents_notify_<?=$LoggedUser['torrent_pass']?>&amp;user=<?=$LoggedUser['ID']?>&amp;auth=<?=$LoggedUser['RSS_Auth']?>&amp;passkey=<?=$LoggedUser['torrent_pass']?>&amp;authkey=<?=$LoggedUser['AuthKey']?>"><img src="<?=STATIC_SERVER?>/common/symbols/rss.png" alt="RSS feed" /></a>
         Notification filters
    </h2>
    <div  class="linkbox">
            [<a href="torrents.php?action=notify" title="View your current pending notifications">notifications</a>]
    </div>
<?php
$DB->query("SELECT ID, Label, Tags, NotTags, Categories FROM users_notify_filters WHERE UserID='$LoggedUser[ID]' UNION ALL SELECT NULL, NULL, NULL, 1, NULL");
$i = 0;
$NumFilters = $DB->record_count()-1;

$Notifications = $DB->to_array();

foreach ($Notifications as $N) { //$N stands for Notifications
    $N['Tags']		= implode(' ', explode('|', substr($N['Tags'],1,-1)));
    $N['NotTags']		= implode(' ', explode('|', substr($N['NotTags'],1,-1)));
    $N['Categories'] 	= explode('|', substr($N['Categories'],1,-1));
    $i++;

    if ($i>$NumFilters) { ?>
            <div class="head">Create a new notification filter</div>
<?php 	} elseif ($NumFilters>0) { ?>
            <div class="head">
                <a title="RSS Feed - <?=$N['Label']?>" href="feeds.php?feed=torrents_notify_<?=$N['ID']?>_<?=$LoggedUser['torrent_pass']?>&amp;user=<?=$LoggedUser['ID']?>&amp;auth=<?=$LoggedUser['RSS_Auth']?>&amp;passkey=<?=$LoggedUser['torrent_pass']?>&amp;authkey=<?=$LoggedUser['AuthKey']?>&amp;name=<?=urlencode($N['Label'])?>"><img src="<?=STATIC_SERVER?>/common/symbols/rss.png" alt="RSS feed" /></a>
                <?=display_str($N['Label'])?>
                <a href="user.php?action=notify_delete&amp;id=<?=$N['ID']?>&amp;auth=<?=$LoggedUser['AuthKey']?>">(Delete)</a>
        </div>
<?php 	} ?>
    <form action="user.php" method="post">
        <input type="hidden" name="action" value="notify_handle" />
        <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
        <table>
<?php 	if ($i>$NumFilters) { ?>
            <tr>
                <td class="label"><strong>Label</strong></td>
                <td>
                    <input type="text" name="label" class="long" />
                    <p class="min_padding">A label for the filter set, to tell different filters apart.</p>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="center">
                    <strong>All fields below here are optional</strong>
                </td>
            </tr>
<?php 	} else { ?>
            <input type="hidden" name="id" value="<?=$N['ID']?>" />
<?php 	} ?>

            <tr>
                <td class="label"><strong>At least one of these tags</strong></td>
                <td>
                    <textarea name="tags" class="long" rows="2"><?=display_str($N['Tags'])?></textarea>
                    <p class="min_padding">Space-separated list - eg. <em>hardcore big.tits anal</em></p>
                </td>
            </tr>
            <tr>
                <td class="label"><strong>None of these tags</strong></td>
                <td>
                    <textarea name="nottags" class="long" rows="2"><?=display_str($N['NotTags'])?></textarea>
                    <p class="min_padding">Space-separated list - eg. <em>hardcore big.tits anal</em></p>
                </td>
            </tr>
            <tr>
                <td colspan="2">
            <table class="cat_list noborder" style="text-align:left;padding:0px">
                <tr>
                    <td colspan="7">
                        <strong>select categories to match</strong>
                    </td>
                </tr>
                <?php
                $row = 'a';
                $x = 0;
                reset($NewCategories);
                foreach ($NewCategories as $Category) {
                    if ($x % 7 == 0) {
                        if ($x > 0) {
                            ?>
                            </tr>
                        <?php  } ?>
                        <tr class="row<?=$row?>">
                            <?php
                            $row = $row == 'a' ? 'b' : 'a';
                        }
                        $x++;
                        ?>
                        <td>
                    <input type="checkbox" name="categories[]" id="<?=$Category['name']?>_<?=$N['ID']?>" value="<?=$Category['name']?>"<?php  if (in_array($Category['name'], $N['Categories'])) { echo ' checked="checked"';} ?> />
                    <label for="<?=$Category['name']?>_<?=$N['ID']?>"><?=$Category['name']?></label>
                        </td>
<?php               } ?>
                    <td colspan="<?= 7 - ($x % 7) ?>"></td>
                </tr>
            </table>
                        </td>
            </tr>
            <tr>
                <td colspan="2" class="center">
                    <input type="submit" value="<?=($i>$NumFilters)?'Create filter':'Update filter'?>" />
                </td>
            </tr>
        </table>
    </form>
    <br /><br />
<?php  } ?>
</div>
<?php
show_footer();
