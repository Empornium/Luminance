<?php
enforce_login();

switch ($_GET['action']) {
    case 'autocomplete':
        // ajax call for autocomplete js class
        include(SERVER_ROOT . '/Legacy/sections/tags/autocomplete_tags.php');
        break;

    case 'synonyms':
        include(SERVER_ROOT.'/Legacy/sections/tags/tag_synomyns.php');
        break;

    case 'tags':
    default:
        include(SERVER_ROOT . '/Legacy/sections/tags/tags.php');
        break;
}
