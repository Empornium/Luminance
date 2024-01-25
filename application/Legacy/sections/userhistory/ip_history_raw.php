<?php

if (!is_integer_string($_GET['userid'])) {
    error(404);
}

$userID = (int) $_GET['userid'];
$UsersOnly = (int) $_GET['usersonly'];

// Get selected user
$user = $master->repos->users->load($userID);
if (!($user instanceof Luminance\Entities\User)) {
    error(404);
}

if (!check_perms('users_view_ips', $user->class->Level)) {
    error(403);
}

//Let's see if the mod changed their default IPs Per Page option, if not use default
if (isset($activeUser['IpsPerPage'])) {
    $perPage = $activeUser['IpsPerPage'];
} else {
    $perPage = 25;
}
list($page, $limit) = page_limit($perPage);

list($ips, $results) = $master->repos->userhistoryips->findCount('UserID = ?', [$user->ID], 'StartTime DESC', $limit);

show_header('IP history for '.display_str($user->Username));
$params = [
    'user'          => $user,
    'ips'           => $ips,
    'page'          => $page,
    'pageSize'      => $perPage,
    'results'       => $results,
];

echo $master->render->template('@Legacy/userhistory/ip_history_raw.html.twig', $params);
show_footer();
