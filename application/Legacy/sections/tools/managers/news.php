<?php
enforce_login();
if (!check_perms('admin_manage_news')) error(403);

$bbCode = new \Luminance\Legacy\Text;

$Page = 0;
$Edit = [];
switch ($_REQUEST['action']) {
    case 'takeeditnews':
        if (is_integer_string($_POST['newsid'])) {
            authorize();
            $master->db->rawQuery(
                "UPDATE news SET Title=:title, Body=:body WHERE ID=:id",
                [':id' => $_POST['newsid'], ':title' => $_POST['title'], ':body' => $_POST['body']]
            );
            $master->cache->deleteValue('news');
            $master->cache->deleteValue('feed_news');
        }
        $Page = (int)$_POST['page'];
        header("Location: /tools.php?action=news&page={$Page}");
        break;

    case 'deletenews':
        if (is_integer_string($_GET['id'])) {
            authorize();
            $master->db->rawQuery("DELETE FROM news WHERE ID=?", [$_GET['id']]);
            $master->cache->deleteValue('news');
            $master->cache->deleteValue('news_totalnum');
            $master->cache->deleteValue('feed_news');

            // Deleting latest news
            $LatestNews = $master->cache->getValue('news_latest_id');
            if ($LatestNews !== FALSE && $LatestNews == $_GET['id']) {
                $master->cache->deleteValue('news_latest_id');
            }
        }
        $Page = (int)$_GET['page'];
        header("Location: /tools.php?action=editnews&page={$Page}");
        die();
        break;

    case 'takenewnews':
        $master->db->rawQuery(
            "INSERT INTO news (UserID, Title, Body, Time) VALUES (?, ?, ?, ?)",
            [$activeUser['ID'], $_POST['title'], $_POST['body'], sqltime()]
        );
        $master->cache->deleteValue('news_latest_id');
        $master->cache->deleteValue('news');
        $master->cache->deleteValue('news_totalnum');
        $master->cache->deleteValue('feed_news');

        $Page = (int)$_POST['page'];
        header("Location: /tools.php?action=news&page={$Page}");
        die();
        break;

    case 'editnews':
        if (is_integer_string($_GET['id'])) {
            $NewsID = $_GET['id'];
            $Edit = $master->db->rawQuery("SELECT ID, Title, Body FROM news WHERE ID=?", [$NewsID])->fetch(\PDO::FETCH_ASSOC);
        }
        break;
}

list($Page, $Limit) = page_limit(5);

$Records = $master->db->rawQuery(
    "SELECT SQL_CALC_FOUND_ROWS
            n.ID,
            n.Title,
            n.Body,
            n.Time
       FROM news AS n
   ORDER BY n.Time DESC
      LIMIT {$Limit}"
)->fetchAll(\PDO::FETCH_ASSOC);
$NumResults = $master->db->foundRows();
$Pages = get_pages($Page, $NumResults, 5, 13);

show_header('Manage news', 'bbcode');
echo $master->render->template('@Legacy/tools/news_manager.html.twig', ['page' => $Page, 'pages' => $Pages, 'edit' => $Edit, 'records' => $Records]);
show_footer();
