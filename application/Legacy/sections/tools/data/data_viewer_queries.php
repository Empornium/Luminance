<?php
$data_viewer_queries = [
    'torrents_awards_pending' => [
        'title' => 'Torrents: Ducky Awards Pending',
        'description' => 'This query shows pending Ducky awards',
        'sql' => "
SELECT SQL_CALC_FOUND_ROWS
    CONCAT('<a href=\"/user.php?id=', um.ID, '\">', um.Username, '</a>') AS User,
    CONCAT('<a href=\"/torrents.php?id=', tg.ID, '\">', tg.Name, '</a>') AS Torrent,
    CONCAT('<span class=\"nobr\">', tg.Time, '</span>') AS Date_Uploaded
FROM
    torrents_awards AS ta
    LEFT JOIN torrents_group AS tg ON ta.TorrentID=tg.ID
    LEFT JOIN users_main AS um ON ta.UserID=um.ID
WHERE
    ta.Ducky='0'
ORDER BY
    tg.Time DESC"
    ],
    'torrents_awards_awarded' => [
        'title' => 'Torrents: Ducky Awards Awarded',
        'description' => 'This query shows awarded Ducky awards',
        'sql' => "
SELECT SQL_CALC_FOUND_ROWS
    CONCAT('<a href=\"/user.php?id=', um.ID, '\">', um.Username, '</a>') AS User,
    CONCAT('<a href=\"/torrents.php?id=', tg.ID, '\">', tg.Name, '</a>') AS Torrent,
    CONCAT('<span class=\"nobr\">', tg.Time, '</span>') AS Date_Uploaded
FROM
    torrents_awards AS ta
    LEFT JOIN torrents_group AS tg ON ta.TorrentID=tg.ID
    LEFT JOIN users_main AS um ON ta.UserID=um.ID
WHERE
    ta.Ducky='1'
ORDER BY
    tg.Time DESC"
    ],
    'torrents_awards_all' => [
        'title' => 'Torrents: Ducky Awards',
        'description' => 'This query shows all Ducky awards pending or awarded',
        'sql' => "
SELECT SQL_CALC_FOUND_ROWS
    CONCAT('<a href=\"/user.php?id=', um.ID, '\">', um.Username, '</a>') AS User,
    CONCAT('<a href=\"/torrents.php?id=', tg.ID, '\">', tg.Name, '</a>') AS Torrent,
    CONCAT('<span class=\"nobr\">', tg.Time, '</span>') AS Date_Uploaded,
    IF(ta.Ducky='1','yes', 'pending') AS Ducky
FROM
    torrents_awards AS ta
    LEFT JOIN torrents_group AS tg ON ta.TorrentID=tg.ID
    LEFT JOIN users_main AS um ON ta.UserID=um.ID
ORDER BY
    ta.Ducky ASC,
    tg.Time DESC"
    ],
    'torrents_monthly' => [
        'title' => 'Torrents: Monthly stats',
        'description' => 'This query shows various torrent-related statistics grouped by month.<br /><i>Average current seeders</i> shows the number of active seeders right now for torrents uploaded during that period.',
        'sql' => "
SELECT SQL_CALC_FOUND_ROWS
    YEAR(t.Time) AS Year,
    MONTH(t.Time) AS Month,
    COUNT(DISTINCT t.ID) AS Torrents,
    MIN(t.ID) AS Lowest_ID,
    COUNT(DISTINCT t.UserID) AS Distinct_uploaders,
    ROUND(SUM(t.Size) / 1073741824) AS Total_size_in_GB,
    ROUND(AVG(t.Size) / 1048576) AS Average_size_in_MB,
    ROUND(AVG(t.Seeders), 2) AS Average_current_seeders
FROM
    torrents AS t
GROUP BY
    year,
    month
ORDER BY
    year DESC,
    month DESC"
    ],
    'forums_monthly' => [
        'title' => 'Forums: &nbsp;&nbsp;Monthly stats',
        'description' => 'This query shows various forum-related statistics grouped by month.',
        'sql' => "
SELECT SQL_CALC_FOUND_ROWS
    YEAR(AddedTime) AS Year,
    MONTH(AddedTime) AS Month,
    COUNT(DISTINCT fp.AuthorID) AS Unique_forum_posters,
    COUNT(DISTINCT fp.TopicID) AS Active_forum_topics,
    COUNT(fp.ID) AS Forum_posts
FROM
    forums_posts AS fp
GROUP BY
    year,
    month
ORDER BY
    year DESC,
    month DESC"
    ],
    'unlocked_posts' => [
        'title' => 'Forums: &nbsp;&nbsp;Timelock Exempt Posts',
        'description' => 'This query shows a list of all posts exempt from timelocking.',
        'sql' => "
SELECT SQL_CALC_FOUND_ROWS
    CONCAT('<a href=\"/user.php?id=', um.ID, '\">', um.Username, '</a>') AS User,
    CONCAT('<span style=\"float:left;\">',
           '<a href=\"/forums.php?action=viewthread&threadid=', fp.TopicID, '\">', ft.Title, '</a></span>',
           '<span class=\"last_read\" title=\"Jump to post\" style=\"float:left;\">',
           '<a href=\"/forums.php?action=viewthread&threadid=', fp.TopicID, '&postid=', fp.ID, '#post', fp.ID, '\"></a>',
           '</span>') AS Post
FROM
    forums_posts AS fp
JOIN
    users_main AS um ON um.ID=fp.AuthorID
JOIN
    forums_topics AS ft ON fp.TopicID=ft.ID
WHERE
    fp.TimeLock=0
ORDER BY
    fp.AddedTime DESC"
    ],
    'users_ipcount' => [
        'title' => 'Users: &nbsp;&nbsp;&nbsp;Active IPs',
        'description' => 'This query shows active IP count per user. Particularly high numbers are suspicious.',
        'sql' => "
SELECT SQL_CALC_FOUND_ROWS
    CONCAT('<a href=\"/user.php?id=', xfu.uid, '\">', um.username, '</a>') AS User,
    COUNT(DISTINCT xfu.ipv4) AS IP_count,
    COUNT(DISTINCT xfu.useragent) AS Useragent_count,
    GROUP_CONCAT(DISTINCT xfu.useragent SEPARATOR '<br/>') AS Useragents
FROM
    xbt_files_users AS xfu,
    users_main AS um
WHERE
    um.ID = xfu.uid
GROUP BY
    xfu.uid
ORDER BY
    IP_count DESC"
    ],
    'users_useragentcount' => [
        'title' => 'Users: &nbsp;&nbsp;&nbsp;Active Useragents',
        'description' => 'This query shows active IP count per user. Particularly high numbers are suspicious.',
        'sql' => "
SELECT SQL_CALC_FOUND_ROWS
    CONCAT('<a href=\"/user.php?id=', xfu.uid, '\">', um.username, '</a>') AS User,
    COUNT(DISTINCT xfu.ipv4) AS IP_count,
    COUNT(DISTINCT xfu.useragent) AS Useragent_count,
    GROUP_CONCAT(DISTINCT xfu.useragent SEPARATOR '<br/>') AS Useragents
FROM
    xbt_files_users AS xfu,
    users_main AS um
WHERE
    um.ID = xfu.uid
GROUP BY
    xfu.uid
ORDER BY
    Useragent_count DESC, IP_count DESC, um.username ASC"
    ],
    'users_single_announces' => [
        'title' => 'Users: &nbsp;&nbsp;&nbsp;Single Client Announces',
        'description' => 'This query shows users With a single 0GB down client announce',
        'sql' => "
SELECT SQL_CALC_FOUND_ROWS
    CONCAT('<a href=\"/user.php?id=', xfu.uid, '\">', um.username, '</a>') AS User,
    COUNT(DISTINCT xfu.fid) AS Torrents
FROM
    xbt_files_users AS xfu,
    users_main AS um
WHERE
    um.ID = xfu.uid
    AND um.Enabled='1'
    AND xfu.announced=1
    AND xfu.downloaded=0
GROUP BY
    xfu.uid
ORDER BY
    Torrents DESC"
    ],
    'users_utorrent_1800' => [
        'title' => 'Users: &nbsp;&nbsp;&nbsp;uTorrent 1800',
        'description' => 'This query shows users using uTorrent 1.8.0',
        'sql' => "
SELECT SQL_CALC_FOUND_ROWS
    CONCAT('<a href=\"/user.php?id=', xfu.uid, '\">', um.username, '</a>') AS User,
    INET6_NTOA(xfu.ipv4) AS IP
FROM
    xbt_files_users AS xfu,
    users_main AS um
WHERE
    um.ID = xfu.uid
    AND xfu.useragent LIKE 'uTorrent/1800'
GROUP BY
    xfu.uid
ORDER BY
    um.username ASC"
    ],
    'users_utorrent_3130' => [
        'title' => 'Users: &nbsp;&nbsp;&nbsp;uTorrent 3130 (26837)',
        'description' => 'This query shows users using uTorrent 3.1.3 build 26837',
        'sql' => "
SELECT SQL_CALC_FOUND_ROWS
    CONCAT('<a href=\"/user.php?id=', xfu.uid, '\">', um.username, '</a>') AS User,
    INET6_NTOA(xfu.ipv4) AS IP
FROM
    xbt_files_users AS xfu,
    users_main AS um
WHERE
    um.ID = xfu.uid
    AND xfu.useragent = 'uTorrent/3130(26837)'
GROUP BY
    xfu.uid
ORDER BY
    um.username ASC"
    ],
    'users_azures_3050' => [
        'title' => 'Users: &nbsp;&nbsp;&nbsp;Azures 3.0.5.0',
        'description' => 'This query shows users using Azures 3.0.5.0',
        'sql' => "
SELECT SQL_CALC_FOUND_ROWS
    CONCAT('<a href=\"/user.php?id=', xfu.uid, '\">', um.username, '</a>') AS User,
    INET6_NTOA(xfu.ipv4) AS IP
FROM
    xbt_files_users AS xfu,
    users_main AS um
WHERE
    um.ID = xfu.uid
    AND xfu.useragent LIKE 'Azureus 3.0.5.0;Windows XP;Java 1.6.0_05'
GROUP BY
    xfu.uid
ORDER BY
    um.username ASC"
    ],
    'users_blank' => [
        'title' => 'Users: &nbsp;&nbsp;&nbsp;Blank UserAgent',
        'description' => 'This query shows users with a Blank UserAgent',
        'sql' => "
SELECT SQL_CALC_FOUND_ROWS
    CONCAT('<a href=\"/user.php?id=', xfu.uid, '\">', um.username, '</a>') AS User,
    INET6_NTOA(xfu.ipv4) AS IP,
    LEFT(xfu.peer_id, 8) as ClientID
FROM
    xbt_files_users AS xfu,
    users_main AS um
WHERE
    um.ID = xfu.uid
    AND xfu.useragent = ''
GROUP BY
    xfu.uid
ORDER BY
    um.username ASC"
    ],
    'users_put_io' => [
        'title' => 'Users: &nbsp;&nbsp;&nbsp;put.io',
        'description' => 'This query shows users using put.io',
        'sql' => "
SELECT SQL_CALC_FOUND_ROWS
    CONCAT('<a href=\"/user.php?id=', xfu.uid, '\">', um.username, '</a>') AS User,
    INET6_NTOA(xfu.ipv4) AS IP,
    COUNT(DISTINCT xfu.ipv4) AS Clients
FROM
    xbt_files_users AS xfu,
    users_main AS um
WHERE
    um.ID = xfu.uid
    AND xfu.useragent RLIKE 'Transmission/2.7[2-3]'
GROUP BY
    xfu.uid
HAVING
    COUNT(DISTINCT xfu.ipv4) >= 5
ORDER BY
    um.username ASC"
    ],
    'users_email_changes' => [
        'title' => 'Users: &nbsp;&nbsp;&nbsp;Email Changes',
        'description' => 'This query shows users whose email changed recently',
        'sql' => "
SELECT SQL_CALC_FOUND_ROWS
    CONCAT('<a href=\"/user.php?id=', uhe.UserID, '\">', um.username, '</a>') AS User,
    uhe.Time
FROM
    users_history_emails AS uhe,
    users_main AS um
WHERE
    um.ID = uhe.UserID
    AND uhe.Time != '0000-00-00 00:00:00'
ORDER BY
    uhe.Time DESC"
    ],
    'users_password_changes' => [
        'title' => 'Users: &nbsp;&nbsp;&nbsp;Password Changes',
        'description' => 'This query shows users whose password changed recently',
        'sql' => "
SELECT SQL_CALC_FOUND_ROWS
    CONCAT('<a href=\"/user.php?id=', uhp.UserID, '\">', um.username, '</a>') AS User,
    uhp.ChangeTime AS Time
FROM
    users_history_passwords AS uhp,
    users_main AS um
WHERE
    um.ID = uhp.UserID
ORDER BY
    uhp.ChangeTime DESC"
    ],
    'users_classes' => [
        'title' => 'Users: &nbsp;&nbsp;&nbsp;Class stats',
        'description' => "This query shows various total and average stats per user class.",
        'sql' => "
SELECT SQL_CALC_FOUND_ROWS
    CONCAT('<strong><span style=\"color: #', p.Color, ';\">', p.Name, '</span></strong>') AS Class,
    COUNT(um.ID) AS Number, ROUND(AVG(um.Uploaded)/1073741824) as Uploaded_average_in_GB,
    ROUND(AVG(um.Downloaded)/1073741824) as Downloaded_average_in_GB,
    IFNULL(ROUND(AVG(um.Uploaded)/AVG(um.Downloaded), 2), 'NaN') AS Ratio
FROM
    permissions AS p,
    users_main AS um,
    users_info AS ui
WHERE
    um.PermissionID = p.ID
    AND um.Enabled = '1'
    AND ui.UserID = um.ID
    AND p.IsUserClass = '1'
GROUP BY
    p.ID
ORDER BY
    p.Level"
    ],
    'users_dupe_email' => [
        'title' => 'Users: &nbsp;&nbsp;&nbsp;Possible duplicate emails (SLOW)',
        'description' => '<strong>This query is slow, please use it sparingly.</strong><br />Tries to detect e-mail addresses that are effectively duplicates by processing it in various ways.<br />Results where every single account is already disabled are excluded.',
        'sql' => "
SELECT SQL_CALC_FOUND_ROWS
        CONCAT(REPLACE(SUBSTRING_INDEX(SUBSTRING_INDEX(um.Email, '@', 1), '+', 1), '.', ''), '@', REPLACE(REPLACE(REPLACE(SUBSTRING_INDEX(SUBSTRING_INDEX(um.Email, '@', -1), '.', 1), 'googlemail', 'gmail'), 'live', 'hotmail'), 'outlook', 'hotmail')) AS Cleaned_up_address,
        COUNT(*) AS Number,
        GROUP_CONCAT(IF(um.Enabled='1','','<del>'),'<a href=\"/user.php?id=', um.ID, '\">', um.username, '</a>', IF(um.Enabled='1','','</del>'), ' ', um.Email, ' [', um.IP, '] ' ORDER BY um.ID SEPARATOR '<br />') AS Accounts
FROM
        users_main AS um
GROUP BY
        Cleaned_up_address
HAVING
        Number >= 2
        AND MAX(IF(um.Enabled='1',1,0)) = 1
ORDER BY
        Number DESC"
    ],
    'users_leechers' => [
        'title' => 'Users: &nbsp;&nbsp;&nbsp;Possible multi-account leechers (SLOW)',
        'description' => '<strong>This query is slow, please use it sparingly.</strong><br />Tries to detect leechers with multiple accounts.<br />Results where every single account is already disabled are excluded.',
        'sql' => "
SELECT SQL_CALC_FOUND_ROWS
        GROUP_CONCAT(IF(um.Enabled='1','','<del>'),'<a href=\"/user.php?id=', um.ID, '\">', um.username, '</a>', IF(um.Enabled='1','','</del>'), ' ', IFNULL(ROUND(um.Uploaded/um.Downloaded, 2), '∞') ORDER BY um.ID SEPARATOR '<br />') AS Accounts,
        COUNT(*) AS Number,
        (SELECT COUNT(DISTINCT uh.IP) FROM users_history_ips AS uh WHERE uh.IP=um.IP) AS IPs
FROM
        users_main AS um
GROUP BY
        um.IP
HAVING
        Number > 1
        AND MAX(IF(um.Enabled='1',1,0)) = 1
        AND IPs <= 5
        AND IFNULL(ROUND(AVG(um.Uploaded)/AVG(um.Downloaded), 2), 'NaN') <=0.2
ORDER BY
        Number DESC"
    ],
    'users_monthly' => [
        'title' => 'Users: &nbsp;&nbsp;&nbsp;Monthly stats',
        'description' => "This query shows  the number of users joined as well as disabled each month.",
        'sql' => "
SELECT SQL_CALC_FOUND_ROWS
    joined.year AS Year,
    joined.month AS Month,
    joined.count AS Joined,
    IFNULL(banned.count, 0) AS Disabled,
    joined.count - IFNULL(banned.count, 0) AS Growth
FROM
    (
        SELECT
            YEAR(ui.JoinDate) AS year,
            MONTH(ui.JoinDate) AS month,
            COUNT(DISTINCT ui.UserID) AS count
        FROM
            users_info AS ui
        GROUP BY
            year,
            month
    ) AS joined
LEFT OUTER JOIN
    (
        SELECT
            YEAR(ui.BanDate) AS year,
            MONTH(ui.BanDate) AS month,
            COUNT(DISTINCT ui.UserID) AS count
        FROM
            users_info AS ui
        GROUP BY
            year,
            month
    ) AS banned
ON
    joined.year = banned.year
    AND joined.month = banned.month"
    ],
    'users_special_gifts' => [
        'title' => 'Users: &nbsp;&nbsp;&nbsp;Special Gifts - Donors & Recipients',
        'description' => 'This query shows Special Gifts given by users.',
        'sql' => "
SELECT SQL_CALC_FOUND_ROWS
    CONCAT('<a href=\"/user.php?id=', usg.UserID, '\">', (SELECT username FROM users_main WHERE ID = usg.UserID), '</a>') AS User,
    SUM(CreditsSpent) AS CreditsSpent,
    SUM(CreditsGiven) AS CreditsGiven,
    SUM(GBsGiven) AS GBsGiven,
    CONCAT('<a href=\"/user.php?id=', usg.Recipient, '\">', (SELECT username FROM users_main WHERE ID = usg.Recipient), '</a>') AS Recipient
FROM
    users_special_gifts AS usg
GROUP BY
    usg.UserID,
    usg.Recipient
ORDER BY
    CreditsGiven DESC"
    ],
'users_special_gifts_donors' => [
        'title' => 'Users: &nbsp;&nbsp;&nbsp;Special Gifts - Donors',
        'description' => 'This query shows Special Gifts given by users.',
        'sql' => "
SELECT SQL_CALC_FOUND_ROWS
    CONCAT('<a href=\"/user.php?id=', usg.UserID, '\">', (SELECT username FROM users_main WHERE ID = usg.UserID), '</a>') AS User,
    SUM(CreditsSpent) AS CreditsSpent,
    SUM(CreditsGiven) AS CreditsGiven,
    SUM(GBsGiven) AS GBsGiven
FROM
    users_special_gifts AS usg
GROUP BY
    usg.UserID
ORDER BY
    CreditsSpent DESC"
    ],
'users_special_gifts_recipients' => [
        'title' => 'Users: &nbsp;&nbsp;&nbsp;Special Gifts - Recipients',
        'description' => 'This query shows Special Gifts received by users.',
        'sql' => "
SELECT SQL_CALC_FOUND_ROWS
    CONCAT('<a href=\"/user.php?id=', usg.Recipient, '\">', (SELECT username FROM users_main WHERE ID = usg.Recipient), '</a>') AS Recipient,
    SUM(CreditsGiven) AS CreditsReceived,
    SUM(GBsGiven) AS GBsReceived
FROM
    users_special_gifts AS usg
GROUP BY
    usg.Recipient
ORDER BY
    CreditsReceived DESC, GBsReceived DESC"
    ],
'/24 abusive IPv4 subnets' => [
        'title' => 'Network: &nbsp;Abusive /24 IP subnets',
        'description' => 'This query shows IP address ranges with high numbers of failed login attempts',
        'sql' => "
SELECT SQL_CALC_FOUND_ROWS
    INET_NTOA(INET_ATON(INET6_NTOA(StartAddress)) & 0xFFFFFF00) as Subnet,
    count(*) as IPCount
FROM request_flood AS rf
JOIN ips ON rf.IPID=ips.ID
WHERE
    LastRequest >= DATE_SUB(NOW(), INTERVAL 1 DAY)
    AND OCTET_LENGTH(StartAddress) = 4
GROUP BY
    Subnet
HAVING
    IPCount > 5
ORDER BY
    IPCount DESC"
    ],
'/16 abusive IPv4 subnets' => [
        'title' => 'Network: &nbsp;Abusive /16 IP subnets',
        'description' => 'This query shows IP address ranges with high numbers of failed login attempts',
        'sql' => "
SELECT SQL_CALC_FOUND_ROWS
    INET_NTOA(INET_ATON(INET6_NTOA(StartAddress)) & 0xFFFF0000) as Subnet,
    count(*) as IPCount
FROM request_flood AS rf
JOIN ips ON rf.IPID=ips.ID
WHERE
    LastRequest >= DATE_SUB(NOW(), INTERVAL 1 DAY)
    AND OCTET_LENGTH(StartAddress) = 4
GROUP BY
    Subnet
HAVING
    IPCount > 5
ORDER BY
    IPCount DESC"
    ],
'/8 abusive IPv4 subnets' => [
        'title' => 'Network: &nbsp;Abusive /8 IP subnets',
        'description' => 'This query shows IP address ranges with high numbers of failed login attempts',
        'sql' => "
SELECT SQL_CALC_FOUND_ROWS
    INET_NTOA(INET_ATON(INET6_NTOA(StartAddress)) & 0xFF000000) as Subnet,
    count(*) as IPCount
FROM request_flood AS rf
JOIN ips ON rf.IPID=ips.ID
WHERE
    LastRequest >= DATE_SUB(NOW(), INTERVAL 1 DAY)
    AND OCTET_LENGTH(StartAddress) = 4
GROUP BY
    Subnet
HAVING
    IPCount > 5
ORDER BY
    IPCount DESC"
    ],
];
