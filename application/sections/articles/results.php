<?php
$Searchtext = trim($_REQUEST['searchtext']);

$DB->query("SELECT TopicID, Title, Description, Category, SubCat, MinClass
                  FROM articles
                 WHERE Category!='2'
                   AND MinClass<='$StaffClass'
                   AND MATCH (Title,Description,Body) AGAINST ('".db_string($Searchtext)."' IN BOOLEAN MODE)"); //
$Articles = $DB->to_array();
//$DB->query("SELECT FOUND_ROWS()");
//list($NumResults) = $DB->next_record();
$NumResults=count($Articles);

show_header( "Articles>Search Results", 'browse,overlib,bbcode');
?>

<div class="thin">
    <h2>Search Results</h2>

    <div class="head">Search Articles</div>
    <form method="get" action="articles.php">
        <table>
            <tr class="box">
                <td class="label">Search for:</td>
                <td>
                    <input name="searchtext" type="text" class="long" value="<?=htmlentities($Searchtext)?>" />
                </td>
                <td width="10%">
                        <input type="submit" value="Search" />
                </td>
            </tr>
        </table>
    </form>
    <br/>

    <div class="head"><?=$NumResults?> Search Results</div>
    <table width="100%" class="topic_list">
            <tr class="colhead">
                    <td colspan="2">Searched for: <?=htmlentities($Searchtext)?></td>
                    <td>Found <?=$NumResults?> result<?php if($NumResults!=1) echo"s";?></td>
            </tr>
<?php
    $Row = 'a';

    foreach ($Articles as $Article) {
        list($TopicID, $Title, $Description, $Category, $SubCat, $MinClass) = $Article;

        $Row = ($Row == 'a') ? 'b' : 'a';
?>
            <tr class="row<?=$Row?>">

                    <td class="topic_link">
                        <?="$ArticleCats[$Category] > $ArticleSubCats[$SubCat]"?>
                    </td>
                    <td class="topic_link">
                            <a href="articles.php?topic=<?=$TopicID?>"><?=display_str($Title)?></a>
                    </td>
                    <td>
                            <?=display_str($Description)?>
<?php               if ($MinClass) { ?>
                        <span style="float:right">
                            <?="[{$ClassLevels[$MinClass][Name]}+]"?>
                        </span>
<?php               } ?>
                    </td>
            </tr>
<?php  } ?>
    </table>
</div>

<?php
show_footer();
