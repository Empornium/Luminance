<?php
include(SERVER_ROOT . '/Legacy/sections/tools/managers/speed_functions.php');

$Action = 'speed_watchlist';

if (!empty($_GET['order_way']) && $_GET['order_way'] == 'asc') {
    $orderWay = 'asc'; // For header links
} else {
    $_GET['order_way'] = 'desc';
    $orderWay = 'desc';
}

if (empty($_GET['order_by']) || !in_array($_GET['order_by'], ['Username', 'Staffname', 'Time', 'Count', 'Comment'])) {
    $_GET['order_by'] = 'Time';
    $orderBy = 'Time';
} else {
    $orderBy = $_GET['order_by'];
}

show_header('Watchlist', 'watchlist');

?>
<div class="thin">
    <h2>Speed Watchlist</h2>
    <div class="linkbox">
        <a href="/tools.php?action=speed_watchlist">[Watch-list]</a>
        <a href="/tools.php?action=speed_excludelist">[Exclude-list]</a>
        <a href="/tools.php?action=speed_records">[Speed Records]</a>
        <a href="/tools.php?action=speed_cheats">[Speed Cheats]</a>
        <a href="/tools.php?action=speed_zerocheats">[Zero Cheats]</a>
    </div>
<?php
    list($Page, $Limit) = page_limit(50);

    $Watchlist = $master->db->rawQuery("SELECT SQL_CALC_FOUND_ROWS
                        wl.UserID, u.Username as Username, StaffID, u2.Username AS Staffname, Time, Count(xbt.uid) as Count, wl.Comment,
                                 ui.Donor, um.Enabled, um.PermissionID
                  FROM users_watch_list AS wl
             LEFT JOIN users AS u ON u.ID=wl.UserID
             LEFT JOIN users_main AS um ON um.ID=wl.UserID
             LEFT JOIN users_info AS ui ON ui.UserID=wl.UserID
             LEFT JOIN users AS u2 ON u2.ID=wl.StaffID
             LEFT JOIN xbt_peers_history AS xbt ON xbt.uid=wl.UserID
                 GROUP BY wl.UserID
              ORDER BY {$orderBy} {$orderWay}
                 LIMIT {$Limit}"
    )->fetchAll(\PDO::FETCH_NUM);

    $NumResults = $master->db->foundRows();

    $Pages = get_pages($Page, $NumResults, 50, 9);

?>
    <div class="linkbox pager"><?= $Pages ?></div>
<?php

    print_user_list($Watchlist, 'watchlist', "User watch list ($NumResults users)", 'watchedred', 'Users in the watch list will have their records retained until they are manually deleted. You can use this information to help detect ratio cheaters.<br/>
                    note: use the list sparingly - this can quickly fill the database with a huge number of records.');

?>
    <div class="linkbox pager"><?= $Pages ?></div>
<?php

?>

</div>
<?php
show_footer();
