<?php
enforce_login();

$action = $_GET['action'] ?? null;
switch ($action) {
    case 'autocomplete':
        // ajax call for autocomplete js class
        include(SERVER_ROOT . '/Legacy/sections/tags/autocomplete_tags.php');
        break;

    case 'synonyms':
        include(SERVER_ROOT.'/Legacy/sections/tags/tag_synonyms.php');
        break;

    case 'tags':
    default:
        include(SERVER_ROOT . '/Legacy/sections/tags/tags.php');
        break;
}
