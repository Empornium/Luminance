<?php
if (!check_perms('site_manage_tags')) error(403,true);

$Char = $_GET['char'];
$MinUses = (int) $_GET['minuses'];

if ($Char == 'all') $WHERE= "";
elseif ($Char == 'other') $WHERE= " AND t.Name REGEXP '^[^a-z]' ";
else  $WHERE= " AND LEFT( t.Name, 1)='$Char' ";

if ($MinUses>0) {
    $WHERE .= " AND t.Uses >= '$MinUses' ";
}

$DB->query("SELECT t.ID, t.Name, t.Uses, Count(ts.ID)
                                  FROM tags AS t
                             LEFT JOIN tag_synomyns AS ts ON ts.TagID=t.ID
                                 WHERE t.TagType='other' $WHERE
                              GROUP BY t.ID
                              HAVING Count(ts.ID)=0
                              ORDER BY Name ASC");
$TagList = $DB->to_array();
$taglistHTML='<option value="0" selected="selected">none &nbsp;</option>';

foreach ($TagList as $Tag) {
    list($TagID, $TagName, $TagUses) = $Tag;
    $taglistHTML .= "<option value=\"$TagID\">$TagName ($TagUses) &nbsp;</option>";
}

echo json_encode(array($taglistHTML));
