<?php
show_header('Synonyms');
?>
<div class="thin">
    <h2>Synonyms</h2>

    <div class="linkbox">
        <a href="/tags.php">[Tags & Search]</a>
        <a style="font-weight: bold" href="/tags.php?action=synonyms">[Synonyms]</a>
    </div>

    <div class="tagtable">

<?php
    $Synonyms = $master->cache->getValue('all_synonyms');
    if (!$Synonyms) {
        $Synonyms = $master->db->rawQuery(
            "SELECT ts.ID,
                    ts.Synonym,
                    ts.TagID,
                    t.Name,
                    t.Uses
               FROM tags_synonyms AS ts
          LEFT JOIN tags AS t ON ts.TagID = t.ID
           ORDER BY Name ASC"
        )->fetchAll(\PDO::FETCH_BOTH);
        $master->cache->cacheValue('all_synonyms', $Synonyms);
    }
    $LastParentTagName = '';
    $Row = 'a';

    foreach ($Synonyms as $Synonym) {
        list($SnID, $SnName, $ParentTagID, $ParentTagName, $Uses) = $Synonym;

        if ($LastParentTagName != $ParentTagName) {
            if ($LastParentTagName != '') {  ?>

            </table></div>
<?php
            }   ?>
            <div style="display:inline-block;vertical-align: top;">
            <table  class="syntable shadow">
                <tr>
                    <td class="colhead" style="width:200px"><a href="/torrents.php?taglist=<?=$ParentTagName?>" ><?=$ParentTagName?></a>&nbsp;(<?=$Uses?>)</td>
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
