<?php
enforce_login();
if (!check_perms('torrent_review_manage')) error(403);

if ($ID = (int) $_POST['id']) {
    $master->db->rawQuery("DELETE FROM review_reasons WHERE ID = ?", [$ID]);
    echo '1';

} else {
    // No id
    echo '-1';
}
