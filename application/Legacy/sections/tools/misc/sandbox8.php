<?php

error('nonono');

$DB->query("DROP TABLE IF EXISTS fixit"); // jsut in case!
$DB->query("CREATE TABLE `fixit` (
  `UserID` int(11) NOT NULL,
  `TorrentID` int(11) NOT NULL,
  `Count` int(11) NOT NULL,
  `Time` datetime NOT NULL,
  PRIMARY KEY ( UserID, TorrentID )
) ENGINE=InnoDB DEFAULT CHARSET=utf8");

$DB->query("INSERT INTO fixit
            SELECT UserID, TorrentID, Count(TorrentID), Max( Time ) from users_downloads
            group by UserID, TorrentID
            having Count(TorrentID) > 1;");

$DB->query("DELETE u FROM users_downloads AS u JOIN fixit AS f ON u.UserID=f.UserID AND u.TorrentID = f.TorrentID
            WHERE f.Time != u.Time;");
