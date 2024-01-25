<?php
if (isset($_SERVER['http_if_modified_since'])) {
    header("Status: 304 Not Modified");
    die();
}

header('Expires: '.date('D, d-M-Y H:i:s \U\T\C',time()+3600*24*120)); //120 days
header('Last-Modified: '.date('D, d-M-Y H:i:s \U\T\C',time()));

if (!check_perms('users_view_ips')) { die('Access denied.'); }

$ip = $_GET['ip'] ?? null;

if (!validate_ip($ip)) {
    die('Invalid IP.');
}

$Host = lookup_ip($_GET['ip']);

if ($Host === '') {
    trigger_error("get_host() command failed with no output, ensure that the host command exists on your system and accepts the argument -W");
} elseif ($Host === false) {
    print 'Could not retrieve host.';
} else {
    print $Host;
}
