<?php
show_header('Synonyms');
?>
<div class="thin">
    <h2>Synonyms</h2>

    <div class="linkbox">
        <a href="tags.php">[Tags & Search]</a>
        <a style="font-weight: bold" href="tags.php?action=synonyms">[Synonyms]</a>
    </div>

    <div class="tagtable">

<?php
    $Synomyns = $Cache->get_value('all_synomyns');
    if (!$Synomyns) {
        $DB->query("SELECT ts.ID, Synomyn, TagID, t.Name, Uses
                    FROM tag_synomyns AS ts LEFT JOIN tags AS t ON ts.TagID=t.ID
                    ORDER BY Name ASC");
        $Synomyns = $DB->to_array(false, MYSQLI_BOTH);
        $Cache->cache_value('all_synomyns', $Synomyns);
    }
    $LastParentTagName ='';
    $Row = 'a';

    foreach ($Synomyns as $Synomyn) {
        list($SnID, $SnName, $ParentTagID, $ParentTagName, $Uses) = $Synomyn;

        if ($LastParentTagName != $ParentTagName) {
            if ($LastParentTagName != '') {  ?>

            </table></div>
<?php
            }   ?>
            <div style="display:inline-block;vertical-align: top;">
            <table  class="syntable shadow">
                <tr>
                    <td class="colhead" style="width:200px"><a href="torrents.php?taglist=<?=$ParentTagName?>" ><?=$ParentTagName?></a>&nbsp;(<?=$Uses?>)</td>
                </tr>
<?php
            $LastParentTagName = $ParentTagName;
       }
                $Row = $Row == 'b'?'a':'b';
?>
                <tr class="row<?=$Row?>">
                    <td ><?=$SnName?></td>
                </tr>
<?php   }
    if ($SnID) { // only finish if something was in list ?>
            </table></div>
<?php   }    ?>
    </div>
</div>
<?php
show_footer();
