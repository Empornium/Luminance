<?php
enforce_login();
if (!check_perms('torrents_review_manage')) error(403);

if ($ID = (int) $_POST['id']) {
    $DB->query("DELETE FROM review_reasons WHERE ID=$ID");
    echo '1';

} else {
    // No id
    echo '-1';
}
