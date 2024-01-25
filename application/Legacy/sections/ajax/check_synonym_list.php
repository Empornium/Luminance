<?php
if (!check_perms('admin_manage_tags')) error(403,true);

function get_rejected_message2($Tag, $Item, $Reason)
{
    $Result = "<span class=\"red\">[rejected]</span> '$Tag'";
    if ($Item!== $Tag) $Result .= "&nbsp; <--&nbsp; '$Item'";
    $Result .= "&nbsp; $Reason<br />";

    return $Result ;
}

function get_rejected_message($Tag, $Item, $NumUses, $Reason)
{
    $Result = "<span class=\"red\">[rejected]</span> '$Tag'";
    if ($Item!== $Tag) $Result .= "&nbsp; <--&nbsp; '$Item'";
    $Result .= "&nbsp; ($NumUses) $Reason<br />";

    return $Result ;
}

function process_taglist($ParentTag, $TagInfos, &$Result)
{
    if (is_array($ParentTag)) {
        // process current taglist

        $Result .= "<div class=\"box pad\"><span style=\"font-weight:bold\">[PARENT TAG] <span class=\"green\">$ParentTag[0] ($ParentTag[2])</span></span>";
        if ($ParentTag[1]!== $ParentTag[0]) $Result .= "  <--&nbsp; <span class=\"redd\">($ParentTag[1])</span>";
        $Result .= "<br />";

        foreach ($TagInfos as $tagitem) {
            $Result .= "[synonym] <span class=\"green\">$tagitem[0] ($tagitem[2])</span> ";
            if ($tagitem[1]!==$tagitem[0]) $Result .= "  <--&nbsp; ($tagitem[1])";
            if ($tagitem[2]>$ParentTag[2]) $Result .= " &nbsp; <span class=\"red\">[warning: uses > parent uses]</span>";
            $Result .= "<br />";
        }
        $Result .= "</div>";
    }
}

if (!$_POST['taglist']) error("Nothing in list", true);

$ListInput = $_POST['taglist'];

$ListInput = str_replace(["\r\n", "\n\n\n". "\n\n", "\n"], ' ', $ListInput);
$ListInput = explode(' ', $ListInput);

$Result = '';
$AllTags = [];
$TagInfos = [];
$ParentTag = '';
$numparents = 0;
$numtags=0;

foreach ($ListInput as $item) {

    $StartingNewList = false;
    $Tag = trim($item);
    if (!$Tag) continue;

    if (substr($Tag, 0, 1) == '+') {
        $Tag = substr($Tag, 1);
        $StartingNewList = true;

        process_taglist($ParentTag, $TagInfos, $Result);

        $TagInfos = [];
        $ParentTag = '';

    }

    $Tag = sanitize_tag($Tag);
    if (!$Tag) {
        $Result .= get_rejected_message2($Tag, $item, "(parses to nothing)");   // "<span class=\"red\">[rejected]</span> '$Tag'  <--  '$item' (parses to nothing)<br />";
        continue;
    }

    // check this tag is not in syn table
    $ExistingParent = $master->db->rawQuery("SELECT t.Name FROM tags_synonyms AS ts JOIN tags AS t ON ts.TagID=t.ID WHERE ts.Synonym = ?", [$Tag])->fetchColumn();
    if ($ExistingParent) {
        $Result .= get_rejected_message2($Tag, $item, "(already exists as a synonym for '$ExistingParent')");   // "<span class=\"red\">[rejected]</span> '$Tag'  <--  '$item' (already exists as a synonym for '$ExistingParent')<br />";
        continue;
    }

    $nextRecord = $master->db->rawQuery("SELECT t.Uses, t.TagType, Count(ts.ID)
                      FROM tags AS t LEFT JOIN tags_synonyms AS ts ON ts.TagID=t.ID
                     WHERE t.Name = ?
                  GROUP BY t.ID",
        [$Tag]
    )->fetch(\PDO::FETCH_NUM);
    list($NumUses, $TagType, $NumSyns) = $nextRecord;
    if (!$NumUses) $NumUses = '0';

    if (in_array($Tag, $AllTags)) {   // array_key_exists($Tag, $Tags)) {
        $Result .= get_rejected_message($Tag, $item, $NumUses, "DUPLICATE IN LIST!"); // "<span class=\"red\">[rejected]</span> '$Tag'  <--  '$item' ($NumUses) DUPLICATE IN LIST!<br />";
        continue;
    }

    if ($StartingNewList) {

        // set new parent tag
        $AllTags[] = $Tag;
        $ParentTag = [$Tag, substr($item, 1), $NumUses];
        $numparents++;
        //$Tags = [];

    } else {

        // check this synonym to be is not an official tag
        if ($TagType=='genre' || $NumSyns > 0) {
            $Result .= get_rejected_message($Tag, $item, $NumUses, "is an official tag - $NumSyns synonyms"); // "<span class=\"red\">[rejected]</span> '$Tag'  <--  '$item' ($NumUses) is an official tag - $NumSyns synonyms<br />";
            continue;
        }

        if (!is_array($ParentTag)) {
            $Result .= get_rejected_message($Tag, $item, $NumUses, "NO PARENT TAG!"); // "<span class=\"red\">[rejected]</span> '$Tag'  <--  '$item' ($NumUses) NO PARENT TAG!<br />";
            continue;
        }

        $numtags++;
        $AllTags[] = $Tag;
        $TagInfos[] = [$Tag, $item, $NumUses];
    }
}

process_taglist($ParentTag, $TagInfos, $Result);

$Result = "<div class=\"box pad\"><span style=\"font-weight:bold\">valid: $numparents parents, $numtags synonyms</span></div>$Result";
echo json_encode([$numtags, $Result]);
