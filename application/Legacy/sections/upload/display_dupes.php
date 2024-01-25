<?php

global $master, $newCategories;

$Bookmarks = all_bookmarks('torrent');

$UniqueResults = $dupeResults['UniqueMatches'];
$NumChecked = $dupeResults['NumChecked'];
$DupeTitle = $dupeResults['Title'];
$SearchTags = $dupeResults['SearchTags'];

$TotalSize = ($dupeResults['TotalSize'] ?? 0);
$SizeUniqueMatches = $dupeResults['SizeUniqueMatches'];
if ($TotalSize > 0) {
    $Percent = round((($SizeUniqueMatches/$TotalSize)*100),2);
} else {
    $Percent = 0;
}

$dupeResults = $dupeResults['DupeResults'];

if (!$dupeResults) $NumDupes =0;
else $NumDupes=count($dupeResults);

if (!($INLINE ?? false)) {
    show_header("Dupe check for $DupeTitle");
    ?>
    <div class="thin">
        <h2>Dupe check for <?=$DupeTitle?></h2>
    <?php
}

?>
    <div class="head"><?php if ($NumDupes>0)echo $NumDupes?> Possible dupe<?php if ($NumDupes>1)echo 's'?><?php if ($NumDupes>=500)echo " (only displaying first 500 matches)";?></div>
<?php
if (!$dupeResults || $NumDupes<1) {
    ?>
    <div class="box pad">No files with the same bytesize were found in the torrents database</div>
    <?php
} else {
    ?>
    <div class="box pad">
    <table class="torrent_table grouping" id="torrent_table">
        <tr class="colhead">
            <td width="70%">Torrent</td>
            <td>Files</td>
            <td>Time</td>
            <td>Size</td>
            <td class="sign"><img src="/static/styles/<?= $activeUser['StyleName'] ?>/images/snatched.png" alt="Snatches" title="Snatches" /></td>
            <td class="sign"><img src="/static/styles/<?= $activeUser['StyleName'] ?>/images/seeders.png" alt="Seeders" title="Seeders" /></td>
            <td class="sign"><img src="/static/styles/<?= $activeUser['StyleName'] ?>/images/leechers.png" alt="Leechers" title="Leechers" /></td>
            <td>Uploader</td>
        </tr>
        <?php
        // Start printing torrent list
        $row='a';
        $lastday = 0;

        foreach ($dupeResults as $group) {

            if (isset($GData['excluded'])) { // this file was excluded from the dupe check, we will tell the user why
?>
                <tr class="">
                    <td colspan="2">
                        This could match many files because it is <?=$GData['excluded']?>
                        <br />Please make sure you have searched carefully to ensure this is not a dupe.
                        <br/>Try <a href="/torrents.php?action=advanced&taglist=<?=$SearchTags?>" title="Torrent Search: Tags" target="_blank">searching tags</a>
                         and <a href="/torrents.php?action=advanced&searchtext=<?=$DupeTitle?>" title="Torrent Search: Title" target="_blank">searching title</a>
                         and <a href="/torrents.php?action=advanced&filelist=<?=$GData['dupedfile']?>" title="Torrent Search: Filelist" target="_blank">searching filelist</a>
                    </td>
                    <td class="torrent" colspan="3" title="File with exact match (bytesize)"><?=$GData['dupedfile']?></td>
                    <td colspan="7" ><?=get_size($GData['dupedfilesize'])?></td>
                </tr>
<?php
            } else {
                # Deleted group?
                if (!array_key_exists('Torrents', $group)) {
                    continue;
                }

                $Data = array_values((array)$group['Torrents'])[0];
                if (empty($Data)) {
                    continue;
                }

                $torrentID = $Data['ID'];

                $Review = get_last_review($group['ID']);

                $group['TagList'] = explode(' ', str_replace('_', '.', $group['TagList']));

                $TorrentTags = [];
                $numtags=0;
                foreach ($group['TagList'] as $Tag) {
                    if ($numtags++>=$activeUser['MaxTags'])  break;
                    $TorrentTags[] = '<a href="/torrents.php?taglist=' . $Tag . '">' . $Tag . '</a>';
                }
                $TorrentTags = implode(' ', $TorrentTags);

                $AddExtra = torrent_icons($Data, $torrentID, $Review, in_array($group['ID'], $Bookmarks));

                $row = ($row == 'a'? 'b' : 'a');
                $IsMarkedForDeletion = $Review['Status'] == 'Warned' || $Review['Status'] == 'Pending';
 ?>
                <tr class="torrent <?=($IsMarkedForDeletion?'redbar':"row$row")?>">
                    <td>
                        <table>
                            <tr class="row<?=$row?>">
                                <td class="center cats_col">
                                    <?php
                                        if (!empty($newCategories[$group['Category']]['image'])) {
                                            $CatImg = 'static/common/caticons/' . $newCategories[$group['Category']]['image'];
                                        } else {
                                            $CatImg = '';
                                        }
                                    ?>
                                    <div title="<?= $newCategories[$group['Category']]['tag'] ?>"><a href="/torrents.php?filter_cat[<?=$group['Category']?>]=1"><img src="<?= $CatImg ?>" /></a></div>
                                </td>
                                <td colspan="2">
                                    <?php if ($Data['ReportCount'] > 0) {
                                        $Title = "This torrent has ".$Data['ReportCount']." active ".($Data['ReportCount'] > 1 ?'reports' : 'report');
                                        $group['Name'] .= ' /<span class="reported" title="'.$Title.'"> Reported</span>';
                                    }

                                    ?>
                                    <?=$AddExtra?>
                                    <a href="/torrents.php?id=<?=$group['ID']?>"><?=$group['Name']?></a>

                                    <br />
                                    <?php  if ($activeUser['HideTagsInLists'] !== 1) { ?>
                                    <div class="tags">
                                       <?= $TorrentTags ?>
                                    </div>
                                    <?php  } ?>
                                </td>
                            </tr>
                            <tr class="colhead">
                                <td>File Size</td>
                                <td colspan="2">Matched Files</td>
                            </tr>
                            <tr class="row<?=$row?>">
                                <td rowspan="2"><?=get_size($group['dupedfilesize'])?></td>
                                <td>Your file</td>
                                <td class="torrent" title="Your file with exact match (bytesize)"><?=$group['dupedfile']?></td>
                            </tr>
                            <tr class="row<?=$row?>">
                                <td>Original file</td>
                                <td class="torrent" title="Existing file with exact match (bytesize)"><?=$group['origfile']?></td>
                            </tr>
                        </table>
                    </td>
                    <td class="center"><?=number_format($Data['FileCount'])?></td>
                    <td class="nobr"><?=time_diff($Data['Time'], 1) ?></td>
                    <td class="nobr"><?= get_size($Data['Size']) ?></td>
                    <td><?= number_format($Data['Snatched']) ?></td>
                    <td<?= ($Data['Seeders'] == 0) ? ' class="r00"' : '' ?>><?= number_format($Data['Seeders']) ?></td>
                    <td><?= number_format($Data['Leechers']) ?></td>
                    <td class="user"><?=  torrent_username($Data['UserID'], $Data['Anonymous']) ?></td>
                </tr>
    <?php
            }
        }
        ?>
    </table>
    <br/><?="$UniqueResults/$NumChecked"?> files with matches, <?=$NumDupes?> possible matches overall
    <br/><?=get_size($SizeUniqueMatches)?> / <?=get_size($TotalSize)?>  (<?=$Percent?>%)
    </div>
    <?php
}

if (!($INLINE ?? false)) {
    ?>
    </div>
    <?php
    show_footer();
}
