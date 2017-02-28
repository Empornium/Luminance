<?php
enforce_login();

include(SERVER_ROOT.'/sections/articles/functions.php');
include(SERVER_ROOT.'/classes/class_text.php');
$Text = new TEXT;

$StaffClass = 0;
if ($LoggedUser['Class']>=STAFF_LEVEL) { // only interested in staff classes
    $StaffClass = $LoggedUser['Class'];
} elseif ($LoggedUser['SupportFor']) {
    $StaffClass = STAFF_LEVEL;
}

if (isset($_REQUEST['topic'])) {
    include(SERVER_ROOT.'/sections/articles/article.php');

} elseif (isset($_REQUEST['searchtext'])) {
    include(SERVER_ROOT.'/sections/articles/results.php');

} else {
    error(0);
}
