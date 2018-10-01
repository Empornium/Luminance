<?php

function delete_user($UserID) {
    global $master;

    # Luminance Tables
    $master->db->raw_query("DELETE FROM users    WHERE     ID=?", [$UserID]);
    $master->db->raw_query("DELETE FROM emails   WHERE UserID=?", [$UserID]);
    $master->db->raw_query("DELETE FROM sessions WHERE UserID=?", [$UserID]);

    # Main User Tables
    $master->db->raw_query("DELETE FROM users_main WHERE     ID=?", [$UserID]);
    $master->db->raw_query("DELETE FROM users_info WHERE UserID=?", [$UserID]);

    # Other User Tables
    $master->db->raw_query("DELETE FROM users_badges                 WHERE UserID=?", [$UserID]);
    $master->db->raw_query("DELETE FROM users_collage_subs           WHERE UserID=?", [$UserID]);
    $master->db->raw_query("DELETE FROM users_connectable_status     WHERE UserID=?", [$UserID]);
    $master->db->raw_query("DELETE FROM users_downloads              WHERE UserID=?", [$UserID]);
    $master->db->raw_query("DELETE FROM users_dupes                  WHERE UserID=?", [$UserID]);
    $master->db->raw_query("DELETE FROM users_freeleeches            WHERE UserID=?", [$UserID]);
    $master->db->raw_query("DELETE FROM users_groups                 WHERE UserID=?", [$UserID]);
    $master->db->raw_query("DELETE FROM users_history_ips            WHERE UserID=?", [$UserID]);
    $master->db->raw_query("DELETE FROM users_history_passkeys       WHERE UserID=?", [$UserID]);
    $master->db->raw_query("DELETE FROM users_history_passwords      WHERE UserID=?", [$UserID]);
    $master->db->raw_query("DELETE FROM users_info                   WHERE UserID=?", [$UserID]);
    $master->db->raw_query("DELETE FROM users_languages              WHERE UserID=?", [$UserID]);
    $master->db->raw_query("DELETE FROM users_not_cheats             WHERE UserID=?", [$UserID]);
    $master->db->raw_query("DELETE FROM users_notify_filters         WHERE UserID=?", [$UserID]);
    $master->db->raw_query("DELETE FROM users_notify_torrents        WHERE UserID=?", [$UserID]);
    $master->db->raw_query("DELETE FROM users_seedhours_history      WHERE UserID=?", [$UserID]);
    $master->db->raw_query("DELETE FROM users_slots                  WHERE UserID=?", [$UserID]);
    $master->db->raw_query("DELETE FROM users_subscriptions          WHERE UserID=?", [$UserID]);
    $master->db->raw_query("DELETE FROM users_torrent_history        WHERE UserID=?", [$UserID]);
    $master->db->raw_query("DELETE FROM users_torrent_history_snatch WHERE UserID=?", [$UserID]);
    $master->db->raw_query("DELETE FROM users_torrent_history_temp   WHERE UserID=?", [$UserID]);
    $master->db->raw_query("DELETE FROM users_watch_list             WHERE UserID=?", [$UserID]);

    # Torrent Awards - only delete if it is a pending record
    $master->db->raw_query("DELETE FROM torrents_awards              WHERE UserID=? AND Ducky='0'", [$UserID]);

    # Tracker Tables
    $master->db->raw_query("DELETE FROM xbt_snatched                 WHERE uid=?", [$UserID]);
    $master->db->raw_query("DELETE FROM xbt_files_users              WHERE uid=?", [$UserID]);

    # Cache keys
    $master->cache->delete_value('enabled_'                .$UserID);
    $master->cache->delete_value('user_stats_'             .$UserID);
    $master->cache->delete_value('user_info_heavy_'        .$UserID);
    $master->cache->delete_value('notify_filters_'         .$UserID);
    $master->cache->delete_value('torrent_user_status_'    .$UserID);
    $master->cache->delete_value('staff_pm_new_'           .$UserID);
    $master->cache->delete_value('ibox_new_'               .$UserID);
    $master->cache->delete_value('notifications_new_'      .$UserID);
    $master->cache->delete_value('collage_subs_user_new_'  .$UserID);
    $master->cache->delete_value('user_peers_'             .$UserID);
    $master->cache->delete_value('user_langs_'             .$UserID);
    $master->cache->delete_value('bookmarks_torrent_'      .$UserID);
    $master->cache->delete_value('user_tokens_'            .$UserID);
    $master->cache->delete_value('user_torrents_snatched_' .$UserID);
    $master->cache->delete_value('user_torrents_grabbed_'  .$UserID);

    $master->repos->users->uncache($UserID);

    //update_tracker('remove_user', array('passkey' => $Cur['torrent_pass']));
    $master->tracker->removeUser($Cur['torrent_pass']);

}

function status($name, $enabled) {
    $status = ($enabled === true)? 'enabled':'disabled';
    return "{$name} status {$status}";
}
