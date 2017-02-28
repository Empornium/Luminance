<?php
authorize(true);

require(SERVER_ROOT.'/sections/torrents/functions.php');

include(SERVER_ROOT.'/classes/class_text.php'); // Text formatting class
$Text = new TEXT;

$GroupAllowed = array('Body', 'Image', 'ID', 'Name', 'NewCategoryID', 'Time');
$TorrentAllowed = array('ID', 'FileCount', 'Size', 'Seeders', 'Leechers', 'Snatched', 'FreeTorrent', 'Time', 'FileList', 'FilePath', 'UserID', 'Username');

$GroupID = (int) $_GET['id'];

if ($GroupID == 0) { error('bad id parameter', true); }

$TorrentCache = get_group_info($GroupID, true, 0);

// http://stackoverflow.com/questions/4260086/php-how-to-use-array-filter-to-filter-array-keys
function filter_by_key($input, $keys) { return array_intersect_key($input, array_flip($keys)); }

$TorrentDetails = filter_by_key($TorrentCache[0][0], $GroupAllowed);
$JsonTorrentDetails = array(
    'Body' => $Text->full_format($TorrentDetails['Body']),
    'Image' => $TorrentDetails['Image'],
    'id' => (int) $TorrentDetails['ID'],
    'name' => $TorrentDetails['Name'],
    'categoryId' => (int) $TorrentDetails['NewCategoryID'],
    'time' => $TorrentDetails['Time'],
);
$TorrentList = array();
foreach ($TorrentCache[1] as $Torrent) {
    $TorrentList[] = filter_by_key($Torrent, $TorrentAllowed);
}
$JsonTorrentList = array();
foreach ($TorrentList as $Torrent) {
    $JsonTorrentList[] = array(
        'id' => (int) $Torrent['ID'],
        'fileCount' => (int) $Torrent['FileCount'],
        'size' => (int) $Torrent['Size'],
        'seeders' => (int) $Torrent['Seeders'],
        'leechers' => (int) $Torrent['Leechers'],
        'snatched' => (int) $Torrent['Snatched'],
        'freeTorrent' => $Torrent['FreeTorrent'] == 1,
        'time' => $Torrent['Time'],
        'fileList' => $Torrent['FileList'],
        'filePath' => $Torrent['FilePath'],
        'userId' => (int) $Torrent['UserID'],
        'username' => $Torrent['Username']
    );
}

print json_encode(array('status' => 'success', 'response' => array('group' => $JsonTorrentDetails, 'torrents' => $JsonTorrentList)));
