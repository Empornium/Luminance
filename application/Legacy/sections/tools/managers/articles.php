<?php
enforce_login();
if (!check_perms('admin_edit_articles')) { error(403); }

$Text = new Luminance\Legacy\Text;

$StaffClass = 0;
if ($LoggedUser['Class']>=STAFF_LEVEL) { // only interested in staff classes
                    // should there be a way for FLS to see these... perm setting maybe?
    $StaffClass = $LoggedUser['Class'];
} elseif ($LoggedUser['SupportFor']) {
    $StaffClass = STAFF_LEVEL;
}

if (!check_perms('admin_edit_articles')) error(403);

switch ($_REQUEST['action']) {
    case 'takeeditarticle':
        if (is_number($_POST['articleid'])) {
                authorize();
                $TopicID = strtolower($_POST['topicid']);
                if(!$TopicID) error("You must enter a topicid for this article");
                if (!preg_match('/^[a-z0-9\-\_.()\@&]+$/', $TopicID)) error("Invalid characters in topicID ($TopicID); allowed: a-z 0-9 -_.()@&");
                $Count = $master->db->raw_query(
                    "SELECT Count(*) as c FROM articles WHERE TopicID=? AND ID<>?",
                    [$TopicID, $_POST['articleid']]
                )->fetchColumn();
                if ($Count > 0) {
                    error('The topic ID must be unique for the article');
                }
                $OldBody = $master->db->raw_query("SELECT Body FROM articles WHERE TopicID=?", [$TopicID])->fetchColumn();

                $master->db->raw_query(
                    "INSERT INTO comments_edits (Page, PostID, EditUser, EditTime, Body)
                          VALUES ('articles', :postid, :userid, :sqltime, :body)",
                    [':postid'  => $_POST['articleid'],
                     ':userid'  => $LoggedUser['ID'],
                     ':sqltime' => sqltime(),
                     ':body'    => $OldBody]
                );

                $master->db->raw_query(
                    "UPDATE articles SET Category=?, SubCat=?, TopicID=?, Title=?, Description=?, Body=?, MinClass=?, Time=? WHERE ID=?",
                    [
                        $_POST['category'], $_POST['subcat'], $TopicID, $_POST['title'],
                        $_POST['description'], $_POST['body'], $_POST['level'], sqltime(), $_POST['articleid']
                    ]
                 );

                $Cache->delete_value("article_$TopicID");
                $Cache->delete_value("articles_$_POST[category]");
                $Cache->delete_value("articles_sub_".(int) $_POST['category']."_".(int) $_POST['subcat']);
        }
        header('Location: tools.php?action=articles');
        die();
        break;

    case 'editarticle':
        $ArticleID = db_string($_REQUEST['id']);

        $edit = $master->db->raw_query("SELECT ID, Category, SubCat, TopicID, Title, Description, Body, MinClass FROM articles WHERE ID=?", [$ArticleID])->fetch();

        if ($edit['MinClass']>0) { // check permissions
            if ( $StaffClass < $edit['MinClass'] ) error(403);
        }
        break;

    case 'takearticle':
        $TopicID = strtolower($_POST['topicid']);
        if(!$TopicID) error("You must enter a topicid for this article");
        if (!preg_match('/^[a-z0-9\-\_.()\@&]+$/', $TopicID)) error("Invalid characters in topicID ($TopicID); allowed: a-z 0-9 -_.()@&");

        $Count = $master->db->raw_query(
            "SELECT Count(*) as c FROM articles WHERE TopicID=?", [$TopicID]
        )->fetchColumn();
        if ($Count > 0) error('The topic ID must be unique for the article');
        $master->db->raw_query(
            "INSERT INTO articles (Category, SubCat, TopicID, Title, Description, Body, Time, MinClass)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $_POST['category'], $_POST['subcat'], $TopicID, $_POST['title'],
                $_POST['description'], $_POST['body'], sqltime(), $_POST['level']
            ]
        );
        $NewID = $master->db->last_insert_id();
        $Cache->delete_value("articles_$_POST[category]");
        $Cache->delete_value("articles_sub_".(int) $_POST['category']."_".(int) $_POST['subcat']);
        //header("Location: tools.php?action=editarticle&amp;id=$NewID");
        header('Location: tools.php?action=articles');
        die();
        break;

    case 'deletearticle':
        if (!check_perms('admin_delete_articles')) error(403);
        if (is_number($_GET['id'])) {
            authorize();
            $del = $master->db->raw_query("SELECT TopicID, Category, SubCat FROM articles WHERE ID=?", [$_GET['id']])->fetchAll(PDO::FETCH_ASSOC);
            $master->db->raw_query("DELETE FROM articles WHERE ID=?", [$_GET['id']]);
            $Cache->delete_value("article_{$del['TopicID']}");
            $Cache->delete_value("articles_{$del['Category']}");
            $Cache->delete_value("articles_sub_{$del['Category']}_{$del['SubCat']}");
        }

        header('Location: tools.php?action=articles');
        die();
        break;
}
$records = $master->db->raw_query(
    "SELECT ID, Category, SubCat, TopicID, Title, Body, Time, Description, MinClass
       FROM articles
   ORDER BY Category, SubCat, Title"
)->fetchAll(PDO::FETCH_ASSOC);

show_header('Manage articles','bbcode');
echo $master->render->render('legacy/tools/articles_manager.html.twig', ['edit' => $edit, 'records' => $records, 'articleCategories' => $ArticleCats, 'articleSubCategories' => $ArticleSubCats, 'classLevels' => $ClassLevels, 'staffClass' => $StaffClass]);
show_footer();
