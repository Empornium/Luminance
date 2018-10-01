<?php

$isFLS = !empty($LoggedUser['SupportFor']);

$time = time();
$cache_reset = 7200;

// Cache keys
$num_search_key  = 'numsearch_user_'.$LoggedUser['ID'];
$last_search_key = 'lastsearch_user_'.$LoggedUser['ID'];

// The following checks are not necessary for staff
if (!$isFLS) {
    if (!isset($_REQUEST['token'])) {
        error('Authorization token expired or invalid.');
    }

    // Uncaught exceptions are ugly in Legacy,
    // so we catch it and display it ourselves
    try {
        $master->secretary->checkToken($_REQUEST['token'], 'users.search', 600);
    } catch (\Exception $e) {
        error($e->getMessage());
    }

    $num  = (int) $Cache->get_value($num_search_key) + 1;
    $lock = (int) $Cache->get_value($last_search_key);

    // Search throttling
    if ($time <= $lock) {
        $diff = $lock - $time;

        // Send a staff PM if someone reaches a worrying limit
        if ($diff >= 900) {
            $Subject  = "{$LoggedUser['Username']} reached user-search limit";
            $Message  = "[user]{$LoggedUser['ID']}[/user] just reached an important rate-limit ({$diff} seconds, after {$num} searches) on user search.[br]This should be investigated.";
            send_staff_pm($Subject, $Message);
        }

        error("You have to wait {$diff} seconds before you can search again.");
    }
}

// Legacy search parameter
if (!empty($_REQUEST['search'])) {
    $_REQUEST['username'] = $_REQUEST['search'];
}

if (!isset($_REQUEST['username'])) {
    error(0);
}

// Searching user
$username = trim($_REQUEST['username']);
$query    = $master->db->raw_query('SELECT ID FROM users WHERE Username = ? LIMIT 1', [$username]);
$userID   = $query->fetchColumn();

// Limit search rate even if we haven't found anything
if (!$isFLS) {
    // Be careful with the values you choose here
    $new_time = ceil($time + (5 * ($num ** 2)));

    if ($lock === 0) {
        $Cache->cache_value($num_search_key, 1, $cache_reset);
        $Cache->cache_value($last_search_key, $new_time, $cache_reset);
    } else {
        $Cache->increment($num_search_key);
        $Cache->replace_value($last_search_key, $new_time, $cache_reset);
    }
}

if ($userID === false) {
    error('No user found with that username.');
}

$master->redirect("/user.php?id={$userID}", null, 302);