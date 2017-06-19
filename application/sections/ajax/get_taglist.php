<?php

if (!check_perms('admin_manage_tags'))
    error(403, true);

if (empty($_GET['char']))
    error(0, true);

$Char    = $_GET['char'];
$MinUses = (int) $_GET['minuses'];
$Params  = [];

switch ($Char) {
    case 'all':
        $WHERE = '';
        break;
    case 'other':
        $WHERE = " AND t.Name REGEXP '^[^a-z]' ";
        break;
    default:
        $WHERE = " AND LEFT(t.Name, 1) = :char ";
        $Params[':char'] = $Char;
}

if ($MinUses > 0) {
    $WHERE .= " AND t.Uses >= :minuses ";
    $Params[':minuses'] = $MinUses;
}

$TagList = $master->db->raw_query("SELECT t.ID, t.Name, t.Uses, COUNT(ts.ID)
                                   FROM tags AS t
                                   LEFT JOIN tag_synomyns AS ts ON ts.TagID = t.ID
                                   WHERE t.TagType = 'other' $WHERE
                                   GROUP BY t.ID HAVING COUNT(ts.ID) = 0
                                   ORDER BY Name ASC",
                                   $Params)
                      ->fetchAll(\PDO::FETCH_NUM);

$taglistHTML = '<option value="0" selected="selected">none &nbsp;</option>';

foreach ($TagList as $Tag) {
    list($TagID, $TagName, $TagUses) = $Tag;
    $taglistHTML .= "<option value=\"$TagID\">$TagName ($TagUses) &nbsp;</option>";
}

echo json_encode([$taglistHTML]);