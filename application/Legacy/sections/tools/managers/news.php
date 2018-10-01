<?php
enforce_login();
if (!check_perms('admin_manage_news')) error(403);

$Text = new Luminance\Legacy\Text;

switch ($_REQUEST['action']) {
    case 'takeeditnews':
        if (is_number($_POST['newsid'])) {
            authorize();
            $master->db->raw_query(
                "UPDATE news SET Title=:title, Body=:body WHERE ID=:id",
                [':id' => $_POST['newsid'], ':title' => $_POST['title'], ':body' => $_POST['body']]
            );
            $Cache->delete_value('news');
            $Cache->delete_value('feed_news');
        }
        $Page = (int)$_POST['page'];
        header("Location: /tools.php?action=news&page={$Page}");
        break;

    case 'deletenews':
        if (is_number($_GET['id'])) {
            authorize();
            $master->db->raw_query("DELETE FROM news WHERE ID=?", [$_GET['id']]);
            $Cache->delete_value('news');
            $Cache->delete_value('news_totalnum');
            $Cache->delete_value('feed_news');

            // Deleting latest news
            $LatestNews = $Cache->get_value('news_latest_id');
            if ($LatestNews !== FALSE && $LatestNews == $_GET['id']) {
                $Cache->delete_value('news_latest_id');
            }
        }
        $Page = (int)$_GET['page'];
        header("Location: /tools.php?action=editnews&page={$Page}");
        die();
        break;

    case 'takenewnews':
        $master->db->raw_query(
            "INSERT INTO news (UserID, Title, Body, Time) VALUES (?, ?, ?, ?)",
            [$LoggedUser[ID], $_POST['title'], $_POST['body'], sqltime()]
        );
        $Cache->cache_value('news_latest_id', $DB->inserted_id(), 0);

        $Cache->delete_value('news');
        $Cache->delete_value('news_totalnum');
        $Cache->delete_value('feed_news');

        $Page = (int)$_POST['page'];
        header("Location: /tools.php?action=news&page={$Page}");
        die();
        break;

    case 'editnews':
        if (is_number($_GET['id'])) {
            $NewsID = $_GET['id'];
            $Edit = $master->db->raw_query("SELECT ID, Title, Body FROM news WHERE ID=?", [$NewsID])->fetch(\PDO::FETCH_ASSOC);
        }
        break;
}

list($Page, $Limit) = page_limit(5);

$Records = $master->db->raw_query(
    "SELECT SQL_CALC_FOUND_ROWS
            n.ID,
            n.Title,
            n.Body,
            n.Time
       FROM news AS n
   ORDER BY n.Time DESC
      LIMIT {$Limit}"
)->fetchAll(\PDO::FETCH_ASSOC);
$NumResults = $master->db->found_rows();
$Pages=get_pages($Page, $NumResults, 5, 13);

show_header('Manage news','bbcode');
echo $master->render->render('legacy/tools/news_manager.html.twig', ['page' => $Page, 'pages' => $Pages, 'edit' => $Edit, 'records' => $Records]);
show_footer();
