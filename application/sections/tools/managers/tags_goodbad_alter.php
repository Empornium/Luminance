<?php
enforce_login();
authorize();

if (!check_perms('admin_convert_tags')) {
    error(403);
}
$tagtype = $_POST['tagtype'];
if (!in_array($tagtype, ['bad','good'])) error(0);

$returnmessage = '';
$result = 0;
if (isset($_POST["old{$tagtype}tags"])) {
    $oldIDs = $_POST["old{$tagtype}tags"];

    if (is_array($oldIDs) && count($oldIDs)>0) {
        // check we really have an array of numbers
        foreach ($oldIDs AS $tagID) {
            if (!is_number($tagID)) error(403);
        }
        // gets named param string in form ':id0,:id1', $params are returned in form [':id0'=>$val0, ':id1'=>$val1]
        $namedparams = $master->db->bindParamArray("id", $oldIDs, $params);
        // get names for results message
        $tags = $master->db->raw_query("SELECT Tag FROM tags_goodbad WHERE ID IN ($namedparams)", $params)->fetchAll(\PDO::FETCH_COLUMN);
        // delete tags
        $master->db->raw_query("DELETE FROM tags_goodbad WHERE ID IN ($namedparams)", $params);
        $message = "Removed ".count($tags)." tag".(count($tags)>1?'s':'')." from $tagtype tag list: ". implode(', ', $tags);
        $returnmessage .= $message;
        write_log("$message, by " . $LoggedUser['Username']);
        $result = 1;
        $master->cache->delete_value("{$tagtype}_tags");
    }
}

if (isset($_POST["new{$tagtype}tag"])) {
    // convert input into array of strings
    if (strrpos($_POST["new{$tagtype}tag"], ",")!==false) {
        $rawtags = explode(",", $_POST["new{$tagtype}tag"]);
    } else if (strrpos($_POST["new{$tagtype}tag"], " ")!==false) {
        $rawtags = explode(" ", $_POST["new{$tagtype}tag"]);
    } else {
        $rawtags = [$_POST["new{$tagtype}tag"]];
    }
    $tags=[];
    // process array of tags
    foreach ($rawtags as $tag) {
        $tag = trim($tag,'.'); // trim dots from the beginning and end
        $tag = strtolower(trim($tag));
        $tag = preg_replace('/[^a-z0-9.-]/', '', $tag);

        if ($tag) {
            $master->db->raw_query("INSERT IGNORE INTO tags_goodbad (Tag, TagType) VALUES (:tag, :tagtype)",
                                                       [':tag'     => $tag,
                                                        ':tagtype' => $tagtype]);
            $tags[] = $tag;
        }
    }
    if (count($tags)>0) {
        $message = "Added ".count($tags)." tag".(count($tags)>1?'s':'')." to $tagtype tags list: ". implode(', ', $tags);
        $returnmessage .= " $message ";
        write_log("$message, by " . $LoggedUser['Username']);
        $result = 1;
        $master->cache->delete_value("{$tagtype}_tags");
    }

}


if ($result>0) {
    header("Location: tools.php?action=tags_goodbad&rst=$result&msg=" . htmlentities($returnmessage) .$anchor);
} else {
    header('Location: tools.php?action=tags_goodbad'.$anchor);
}
