<?php

$isFLS = check_perms('users_fls');

$time = time();
$cache_reset = 7200;

// Cache keys
$num_search_key  = 'numsearch_user_'.$activeUser['ID'];
$last_search_key = 'lastsearch_user_'.$activeUser['ID'];

// The following checks are not necessary for staff
if (!$isFLS) {
    if (!isset($_REQUEST['token'])) {
        error('Authorization token expired or invalid.');
    }

    $master->secretary->checkToken($_REQUEST['token'], 'user.search', 600);

    $num  = (int) $master->cache->getValue($num_search_key) + 1;
    $lock = (int) $master->cache->getValue($last_search_key);

    // Search throttling
    if ($time <= $lock) {
        $diff = $lock - $time;

        // Send a staff PM if someone reaches a worrying limit
        if ($diff >= 900) {
            $Subject  = "{$activeUser['Username']} reached user-search limit";
            $Message  = "[user]{$activeUser['ID']}[/user] just reached an important rate-limit ({$diff} seconds, after {$num} searches) on user search.[br]This should be investigated.";
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
    error('Cannot search for a blank username');
}

// Searching user
$username = trim($_REQUEST['username']);
$query    = $master->db->rawQuery(
    'SELECT ID
       FROM users
      WHERE Username = ?
      LIMIT 1',
    [$username]
);
$userID   = $query->fetchColumn();

// Limit search rate even if we haven't found anything
if (!$isFLS) {
    // Be careful with the values you choose here
    $new_time = ceil($time + (5 * ($num ** 2)));

    if ($lock === 0) {
        $master->cache->cacheValue($num_search_key, 1, $cache_reset);
        $master->cache->cacheValue($last_search_key, $new_time, $cache_reset);
    } else {
        $master->cache->incrementValue($num_search_key);
        $master->cache->replaceValue($last_search_key, $new_time, $cache_reset);
    }
}

if ($userID === false) {
    error('No user found with that username.');
}

$master->redirect("/user.php?id={$userID}", null, 302);
