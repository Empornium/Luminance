<?php
if (!check_perms('site_torrents_notify')) { error(403); }
authorize();

$TagList = '';
$NotTagList = '';
$CategoryList = '';
$HasFilter = false;

if ($_POST['tags'] ?? false) {
    $TagList = '|';
    $Tags = explode(' ', strtolower($_POST['tags']));
    foreach ($Tags as $Tag) {
        $TagList .= trim($Tag) . '|';
    }
    $TagList = str_replace('||', '|', $TagList);
    $HasFilter = true;
}

if ($_POST['nottags'] ?? false) {
    $NotTagList = '|';
    $Tags = explode(' ', strtolower($_POST['nottags']));
    foreach ($Tags as $Tag) {
        $NotTagList .= trim($Tag) . '|';
    }
    $NotTagList = str_replace('||', '|', $NotTagList);
    $HasFilter = true;
}

if ($_POST['categories'] ?? false) {
    $CategoryList = '|';
    foreach ($_POST['categories'] as $Category) {
        $CategoryList .= trim($Category) . '|';
    }
    $HasFilter = true;
}

if (!$HasFilter) {
    $Err = 'You must add at least one criterion to filter by';
} elseif (!($_POST['label'] ?? false) && !($_POST['id'] ?? false)) {
    $Err = 'You must add a label for the filter set';
}

if ($Err ?? false) {
    error($Err);
    header('Location: user.php?action=notify');
    die();
}

$Freeleech = ($_POST['freeleech'] ?? 0) ? 1 : 0;

if (is_integer_string($_POST['id'] ?? null)) {
    $master->db->rawQuery(
        "UPDATE users_notify_filters
            SET Tags = ?,
                NotTags = ?,
                Categories = ?,
                Freeleech = ?
          WHERE ID = ?
            AND UserID = ?",
        [$TagList, $NotTagList, $CategoryList, $Freeleech, $_POST['id'], $activeUser['ID']]
);
} else {
    $master->db->rawQuery(
        "INSERT INTO users_notify_filters (UserID, Label, Tags, NotTags, Categories, Freeleech)
              VALUES (?, ?, ?, ?, ?, ?)",
        [$activeUser['ID'], $_POST['label'], $TagList, $NotTagList, $CategoryList, $Freeleech]
    );
}

$master->cache->deleteValue('notify_filters_'.$activeUser['ID']);

header('Location: user.php?action=notify');
