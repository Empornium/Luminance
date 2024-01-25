<?php
header('Content-Type: application/json; charset=utf-8');

$master->repos->restrictions->checkRestricted($activeUser['ID'], Luminance\Entities\Restriction::TAGGING);

$TagID = (int) $_POST['tagid'];
$GroupID = (int) $_POST['groupid'];
$Way = $_POST['way'];

if (!is_integer_string($TagID) || !is_integer_string($GroupID)) {
    error(0, true);
}
if (!in_array($Way, ['up', 'down'])) {
    error(0, true);
}

switch ($Way) {
    case 'up':
        echo json_encode($master->tagManager->voteUpTag($TagID, $GroupID));
        break;

    case 'down':
        echo json_encode($master->tagManager->voteDownTag($TagID, $GroupID));
        break;

    default:
        error(0, true);
        break;
}
