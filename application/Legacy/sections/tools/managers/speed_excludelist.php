<?php
include(SERVER_ROOT . '/Legacy/sections/tools/managers/speed_functions.php');

$Action = 'speed_excludelist';

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

show_header('Exclude list', 'watchlist');

?>
<div class="thin">
    <h2>Exclude list</h2>

    <div class="linkbox">
        <a href="/tools.php?action=speed_watchlist">[Watch-list]</a>
        <a href="/tools.php?action=speed_excludelist">[Exclude-list]</a>
        <a href="/tools.php?action=speed_records">[Speed Records]</a>
        <a href="/tools.php?action=speed_cheats">[Speed Cheats]</a>
        <a href="/tools.php?action=speed_zerocheats">[Zero Cheats]</a>
    </div>
<?php
    list($Page, $Limit) = page_limit(50);

    $Userlist = $master->db->rawQuery("SELECT SQL_CALC_FOUND_ROWS
                        wl.UserID, u.Username as Username, StaffID, u2.Username AS Staffname, Time, Count(xbt.uid) as Count, wl.Comment,
                                 ui.Donor, um.Enabled, um.PermissionID
                  FROM users_not_cheats AS wl
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

    print_user_list($Userlist, 'excludelist', "Exclude users list ($NumResults users)", 'watchedgreen',
            'Users in this list will be excluded from the multiban function and will not be shown on the cheats page');

?>
    <div class="linkbox pager"><?= $Pages ?></div>

</div>
<?php
show_footer();
