<?php
include(SERVER_ROOT.'/sections/bookmarks/functions.php');

$Bookmarks = all_bookmarks('torrent');

$UniqueResults = $DupeResults['UniqueMatches'];
$NumChecked = $DupeResults['NumChecked'];
$DupeTitle = $DupeResults['Title'];
$SearchTags = $DupeResults['SearchTags'];

$TotalSize = $DupeResults['TotalSize'];
$SizeUniqueMatches = $DupeResults['SizeUniqueMatches'];
$Percent = round((($SizeUniqueMatches/$TotalSize)*100),2);

$DupeResults = $DupeResults['DupeResults'];

if (!$DupeResults) $NumDupes =0;
else $NumDupes=count($DupeResults);

if (!$INLINE) {
    show_header("Dupe check for $DupeTitle");
    ?>
    <div class="thin">
        <h2>Dupe check for <?=$DupeTitle?></h2>
    <?php
}

?>
    <div class="head"><?php if($NumDupes>0)echo $NumDupes?> Possible dupe<?php if($NumDupes>1)echo 's'?><?php if($NumDupes>=500)echo " (only displaying first 500 matches)";?></div>
<?php
if (!$DupeResults || $NumDupes<1) {
    ?>
    <div class="box pad">No files with the same bytesize were found in the torrents database</div>
    <?php
} else {
    ?>
    <div class="box pad">
    <table class="torrent_table grouping" id="torrent_table">
        <tr class="colhead">
            <td width="22%">Your file</td>
            <td width="22%">File matched</td>
            <td width="8%">File Size</td>
            <td class="small cats_col"></td>
            <td width="40%">Torrent</td>
            <td>Files</td>
            <td>Time</td>
            <td>Size</td>
            <td class="sign"><img src="static/styles/<?= $LoggedUser['StyleName'] ?>/images/snatched.png" alt="Snatches" title="Snatches" /></td>
            <td class="sign"><img src="static/styles/<?= $LoggedUser['StyleName'] ?>/images/seeders.png" alt="Seeders" title="Seeders" /></td>
            <td class="sign"><img src="static/styles/<?= $LoggedUser['StyleName'] ?>/images/leechers.png" alt="Leechers" title="Leechers" /></td>
            <td>Uploader</td>
        </tr>
        <?php
        // Start printing torrent list
        $row='a';
        $lastday = 0;

        foreach ($DupeResults as $GroupID2 => $GData) {

            if (isset($GData['excluded'])) { // this file was excluded from the dupe check, we will tell the user why
?>
                <tr class="">
                    <td colspan="2">
                        This could match many files because it is <?=$GData['excluded']?>
                        <br />Please make sure you have searched carefully to ensure this is not a dupe.
                        <br/>Try <a href="torrents.php?action=advanced&taglist=<?=$SearchTags?>" title="Torrent Search: Tags" target="_blank">searching tags</a>
                         and <a href="torrents.php?action=advanced&searchtext=<?=$DupeTitle?>" title="Torrent Search: Title" target="_blank">searching title</a>
                         and <a href="torrents.php?action=advanced&filelist=<?=$GData['dupedfile']?>" title="Torrent Search: Filelist" target="_blank">searching filelist</a>
                    </td>
                    <td class="torrent" colspan="3" title="File with exact match (bytesize)"><?=$GData['dupedfile']?></td>
					<td colspan="7" ><?=get_size($GData['dupedfilesize'])?></td>
                </tr>
<?php
            } else {

                list($GroupID, $GroupName, $TagList, $Torrents, $FreeTorrent, $Image, $TotalLeechers, $NewCategoryID, $SearchText,
                         $TotalSeeders, $MaxSize, $TotalSnatched, $GroupTime, $DupedFile, $DupedFileSize, $OrigFile) = array_values($GData);

                list($TorrentID, $Data) = each($Torrents);

                $Review = get_last_review($GroupID);

                $TagList = explode(' ', str_replace('_', '.', $TagList));

                $TorrentTags = array();
                $numtags=0;
                foreach ($TagList as $Tag) {
                    if ($numtags++>=$LoggedUser['MaxTags'])  break;
                    $TorrentTags[] = '<a href="torrents.php?' . $Action . '&amp;taglist=' . $Tag . '">' . $Tag . '</a>';
                }
                $TorrentTags = implode(' ', $TorrentTags);

                $AddExtra = torrent_icons($Data, $TorrentID,$Review, $Bookmarks);

                $row = ($row == 'a'? 'b' : 'a');
                $IsMarkedForDeletion = $Review['Status'] == 'Warned' || $Review['Status'] == 'Pending';
 ?>
                <tr class="torrent <?=($IsMarkedForDeletion?'redbar':"row$row")?>">

                    <td class="torrent" title="File with exact match (bytesize)"><?=$DupedFile?></td>
					<td><?=$OrigFile?></td>
					<td><?=get_size($DupedFileSize)?></td>

                    <td class="center cats_col">
                        <?php  $CatImg = 'static/common/caticons/' . $NewCategories[$NewCategoryID]['image']; ?>
                        <div title="<?= $NewCategories[$NewCategoryID]['tag'] ?>"><a href="torrents.php?filter_cat[<?=$NewCategoryID?>]=1"><img src="<?= $CatImg ?>" /></a></div>
                    </td>
                    <td>

        <?php
                        if ($Data['ReportCount'] > 0) {
                            $Title = "This torrent has ".$Data['ReportCount']." active ".($Data['ReportCount'] > 1 ?'reports' : 'report');
                            $GroupName .= ' /<span class="reported" title="'.$Title.'"> Reported</span>';
                        }

                        ?>
                        <?=$AddExtra?>
                        <a href="torrents.php?id=<?=$GroupID?>"><?=$GroupName?></a>

                        <br />
                        <?php  if ($LoggedUser['HideTagsInLists'] !== 1) { ?>
                        <div class="tags">
                           <?= $TorrentTags ?>
                        </div>
                        <?php  } ?>
                    </td>

                    <td class="center"><?=number_format($Data['FileCount'])?></td>
                    <td class="nobr"><?=time_diff($Data['Time'], 1) ?></td>
                    <td class="nobr"><?= get_size($Data['Size']) ?></td>
                    <td><?= number_format($Data['Snatched']) ?></td>
                    <td<?= ($Data['Seeders'] == 0) ? ' class="r00"' : '' ?>><?= number_format($Data['Seeders']) ?></td>
                    <td><?= number_format($Data['Leechers']) ?></td>
                    <td class="user"><?=  torrent_username($Data['UserID'], $Data['Username'], $Data['Anonymous']) ?></td>
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

if (!$INLINE) {
    ?>
    </div>
    <?php
    show_footer();
}
