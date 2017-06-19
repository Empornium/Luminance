<?php
if (!check_perms('admin_convert_tags')) {
    error(403);
}

$goodtags = getGoodTags();
$badtags = getBadTags();

show_header('Good & Bad Tags Manager','tagmanager');

printRstMessage();
?>
<div class="thin">
    <h2>Good &amp; bad tag list Manager</h2>
<?php
    printTagLinks();

    printTagTable($goodtags, 'good', "These are tags (or synonyms) that are allowed despite being under the minimum tag size (".$master->options->MinTagLength." characters)");

    printTagTable($badtags, 'bad', "These are tags that are not allowed to be added to any torrent.");
?>

</div>
<?php

show_footer();


function printTagTable($taglist, $tagtype, $desc)
{
    global $LoggedUser;

    $tagtype = strtolower($tagtype);
    if (!in_array($tagtype, ['bad','good'])) $tagtype = 'bad';
?>
    <br/>
    <h2><?=ucfirst($tagtype)?> Tags</h2>

    <div class="tagtable center">
        <form action="tools.php" method="post">
            <input type="hidden" name="action" value="tags_goodbad_alter" />
            <input type="hidden" name="tagtype" value="<?=$tagtype?>" />
            <input type="hidden" name="auth" value="<?= $LoggedUser['AuthKey'] ?>" />

            <div class="box shadow" style="text-align:left">
                <div class="pad">
                    <h3><?=ucfirst($tagtype)?> Tags</h3>
                    <?=$desc?><br/>
                </div>
                <table class="tagtable">
                    <tr class="colhead" style="font-weight: bold">
                        <td>Remove</td><td>Tag</td><td>&nbsp;</td>
                        <td>Remove</td><td>Tag</td><td>&nbsp;</td>
                        <td>Remove</td><td>Tag</td><td>&nbsp;</td>
                        <td>Remove</td><td>Tag</td>
                    </tr>
<?php
                $x = 0; $y =0;
                foreach($taglist as $tag) {

                    if ($x==0) { ?>
                        <tr class="<?= (($y % 2) ? 'rowa' : 'rowb') ?>">
<?php               }            ?>
                    <td><input type="checkbox" name="old<?=$tagtype?>tags[]" value="<?= $tag['ID'] ?>" /></td>
                    <td><?= $tag['Tag'] ?></td>
<?php               if ($x<3) { ?>
                        <td>&nbsp;</td>
<?php               }
                    $x++;
                    if ($x==4) {
                        $x=0;
                        $y++;
                    }
                    if ($x==0) { ?>
                        </tr>
<?php               }
                }
                if ($x>0) {
                    for ( ;$x<4;$x++ ) {   ?>
                        <td>&nbsp;</td><td>&nbsp;</td>
<?php                   if ($x<3) {  ?>
                        <td>&nbsp;</td>
<?php                   }
                    }
                    $y++;
?>
                    </tr>
<?php           }
?>
                    <tr class="<?= (($y % 2) ? 'rowa' : 'rowb') ?>">
                        <td colspan="11"><label for="new<?=$tagtype?>tag">Add <?=ucfirst($tagtype)?> tag(s): </label>
                            <input type="text" class="medium" name="new<?=$tagtype?>tag" />
                        </td>
                    </tr>
                    <tr style="border-top: thin solid #98AAB1">
                        <td colspan="11" style="text-align: center">
                            <input type="submit" value="Submit Changes" />
                        </td>
                    </tr>
                </table>
            </div>
        </form>
    </div>
<?php
}
