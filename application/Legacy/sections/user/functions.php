<?php

function delete_user($userID) {
    global $master;

    $user = $master->repos->users->load($userID);

    # Luminance Tables
    $master->db->rawQuery("DELETE FROM users    WHERE     ID=?", [$userID]);
    $master->db->rawQuery("DELETE FROM emails   WHERE UserID=?", [$userID]);
    $master->db->rawQuery("DELETE FROM sessions WHERE UserID=?", [$userID]);

    # Main User Tables
    $master->db->rawQuery("DELETE FROM users_main WHERE     ID=?", [$userID]);
    $master->db->rawQuery("DELETE FROM users_info WHERE UserID=?", [$userID]);

    # Other User Tables
    $master->db->rawQuery("DELETE FROM users_badges                 WHERE UserID=?", [$userID]);
    $master->db->rawQuery("DELETE FROM collages_subscriptions       WHERE UserID=?", [$userID]);
    $master->db->rawQuery("DELETE FROM users_connectable_status     WHERE UserID=?", [$userID]);
    $master->db->rawQuery("DELETE FROM users_downloads              WHERE UserID=?", [$userID]);
    $master->db->rawQuery("DELETE FROM users_dupes                  WHERE UserID=?", [$userID]);
    $master->db->rawQuery("DELETE FROM users_freeleeches            WHERE UserID=?", [$userID]);
    $master->db->rawQuery("DELETE FROM users_groups                 WHERE UserID=?", [$userID]);
    $master->db->rawQuery("DELETE FROM users_history_ips            WHERE UserID=?", [$userID]);
    $master->db->rawQuery("DELETE FROM users_history_passkeys       WHERE UserID=?", [$userID]);
    $master->db->rawQuery("DELETE FROM users_history_passwords      WHERE UserID=?", [$userID]);
    $master->db->rawQuery("DELETE FROM users_info                   WHERE UserID=?", [$userID]);
    $master->db->rawQuery("DELETE FROM users_languages              WHERE UserID=?", [$userID]);
    $master->db->rawQuery("DELETE FROM users_not_cheats             WHERE UserID=?", [$userID]);
    $master->db->rawQuery("DELETE FROM users_notify_filters         WHERE UserID=?", [$userID]);
    $master->db->rawQuery("DELETE FROM users_notify_torrents        WHERE UserID=?", [$userID]);
    $master->db->rawQuery("DELETE FROM users_seedhours_history      WHERE UserID=?", [$userID]);
    $master->db->rawQuery("DELETE FROM users_slots                  WHERE UserID=?", [$userID]);
    $master->db->rawQuery("DELETE FROM forums_subscriptions         WHERE UserID=?", [$userID]);
    $master->db->rawQuery("DELETE FROM users_torrent_history        WHERE UserID=?", [$userID]);
    $master->db->rawQuery("DELETE FROM users_torrent_history_snatch WHERE UserID=?", [$userID]);
    $master->db->rawQuery("DELETE FROM users_torrent_history_temp   WHERE UserID=?", [$userID]);
    $master->db->rawQuery("DELETE FROM users_watch_list             WHERE UserID=?", [$userID]);

    # Torrent Awards - only delete if it is a pending record
    $master->db->rawQuery("DELETE FROM torrents_awards              WHERE UserID=? AND Ducky='0'", [$userID]);

    # Tracker Tables
    $master->db->rawQuery("DELETE FROM xbt_snatched                 WHERE uid=?", [$userID]);
    $master->db->rawQuery("DELETE FROM xbt_files_users              WHERE uid=?", [$userID]);

    # Cache keys
    $master->cache->deleteValue('enabled_'                .$userID);
    $master->cache->deleteValue('user_stats_'             .$userID);
    $master->cache->deleteValue('user_info_heavy_'        .$userID);
    $master->cache->deleteValue('notify_filters_'         .$userID);
    $master->cache->deleteValue('torrent_user_status_'    .$userID);
    $master->cache->deleteValue('staff_pm_new_'           .$userID);
    $master->cache->deleteValue('ibox_new_'               .$userID);
    $master->cache->deleteValue('notifications_new_'      .$userID);
    $master->cache->deleteValue('collage_subs_user_new_'  .$userID);
    $master->cache->deleteValue('user_peers_'             .$userID);
    $master->cache->deleteValue('user_langs_'             .$userID);
    $master->cache->deleteValue('user_tokens_'            .$userID);
    $master->cache->deleteValue('user_torrents_snatched_' .$userID);
    $master->cache->deleteValue('user_torrents_grabbed_'  .$userID);
    $master->repos->users->uncache($userID);
    $master->tracker->removeUser($user->legacy['torrent_pass']);

}

function status($name, $enabled) {
    $status = ($enabled === true)? 'enabled':'disabled';
    return "{$name} status {$status}";
}
