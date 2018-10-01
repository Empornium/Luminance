<?php
if (isset($_REQUEST['topic'])) {
    $CurrentTopicID = db_string($_REQUEST['topic']);
} else {
    error(0);
}

$DB->query("SELECT ID, Category, Title, Body, Time, MinClass, SubCat FROM articles WHERE TopicID='$CurrentTopicID'");
if (!list($ArticleID, $Category, $Title, $Body, $Time, $MinClass, $SubCat) = $DB->next_record()) {
    error(404);
}
$Body = $Text->full_format($Body, true); // true so regardless of author permissions articles can use adv tags
$Body = replace_special_tags($Body);

if ($MinClass>0) { // check permissions
        // should there be a way for FLS to see these... perm setting maybe?
    if ( $StaffClass < $MinClass ) error(403);
}

$Articles = $Cache->get_value("articles_$Category");
if ($Articles===false) {
        $DB->query("SELECT TopicID, Title, Description, SubCat, MinClass
                  FROM articles
                 WHERE Category='$Category'
              ORDER BY SubCat, Title");
        $Articles = $DB->to_array();
        $Cache->cache_value("articles_$Category", $Articles);
}

$TopArticles = $Cache->get_value("articles_sub_{$Category}_$SubCat");
if ($TopArticles===false) {
        $DB->query("SELECT TopicID, Title, Description, SubCat, MinClass
                    FROM articles
                    WHERE Category='$Category' AND SubCat='$SubCat'
                    ORDER BY SubCat, Title");
        $TopArticles = $DB->to_array();
        $Cache->cache_value("articles_sub_{$Category}_$SubCat", $TopArticles);
}

$PageTitle = empty($LoggedUser['ShortTitles'])?"{$ArticleCats[$Category]} > $Title":$Title ;
$SubTitle = $ArticleCats[$Category] ." Articles";

show_header( $PageTitle, 'browse,overlib,bbcode');
?>

<div class="thin">
    <h2><?=$SubTitle?></h2>

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

<?php
    print_articles($TopArticles, $StaffClass);
?>

    <div class="head">
        <?=$Title?>

        <?php if (check_perms('admin_edit_articles')) { ?>
        <a href="/tools.php?action=editarticle&amp;id=<?=$ArticleID?>">[Edit]</a>
        <?php } ?>
        <?php if (check_perms('admin_delete_articles')) { ?>
        <a href="/tools.php?action=deletearticle&amp;id=<?=$ArticleID?>&amp;auth=<?=$LoggedUser['AuthKey']?>" onClick="return confirm('Are you sure you want to delete this article?');">[Delete]</a>
        <?php } ?>
    </div>
    <div class="box pad" style="padding:10px 10px 10px 20px;">
        <?=$Body?>
    </div>

<?php
    print_articles($Articles, $StaffClass, $SubCat);
?>

</div>

<?php
show_footer();
