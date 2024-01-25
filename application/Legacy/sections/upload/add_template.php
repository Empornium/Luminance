<?php
// trim whitespace before setting/evaluating these fields
$Title = trim($_POST['title']);
$Image = trim($_POST['image']);
$Body = trim($_POST['body']);
$Category = (int) $_POST['category'];
$TagList = trim($_POST['tags']);

$TemplateID = (int) $_POST['templateID'];

//TODO: add max number of templates?

if ($Title=='' && $Image=='' && $Body=='' && $TagList=='') {

    $Result = [0, "Cannot save a template with no content!"];

} elseif (is_integer_string($TemplateID)&& $TemplateID>0) {
        //get existing template to write over
        list($Name, $userID, $Public) = $master->db->rawQuery(
            "SELECT Name,
                    UserID,
                    Public
               FROM upload_templates
              WHERE ID = ?",
            [$TemplateID]
        )->fetch(\PDO::FETCH_NUM);
        if ($master->db->foundRows() == 0) {
            //error
            $Result = [0, "Could not find template #$TemplateID to overwrite!"];
        } else {
            if ($userID!=$activeUser['ID'] && !check_perms('site_edit_public_templates')) {
                $Result = [0, "Cannot overwrite someone else's template!"];
            } else {
                $master->db->rawQuery(
                    "UPDATE upload_templates
                        SET UserID = ?,
                            TimeAdded = ?,
                            Title = ?,
                            Image = ?,
                            Body = ?,
                            CategoryID = ?,
                            Taglist = ?
                      WHERE ID = ?",
                    [$userID, sqltime(), $Title, $Image, $Body, $Category, $TagList, $TemplateID]
                );
                // Public='$Public',
                if ($Public) $master->cache->deleteValue('templates_public');
                else $master->cache->deleteValue('templates_ids_' . $activeUser['ID']);

                $master->cache->deleteValue('template_' . $TemplateID);
                $Result = [1, "Saved '$Name' template"];
            }
        }

} else {
    $Name = trim($_POST['name']);
    $Public = $_POST['ispublic']==1?1:0;
    $userID = (int) $activeUser['ID'];

    if ($Name=='') {

        $Result = [0, "Error: No name set"];

    } else {

        if ($Public && !check_perms('site_make_public_templates')) {
            $Result = [0, "Error: You do not have permissions to save a public template!"];

        } elseif (!check_perms('site_make_private_templates')) {
            $Result = [0, "Error: You do not have permissions to save a private template!"];

        } else {
            $master->db->rawQuery(
                "INSERT INTO upload_templates (UserID, TimeAdded, Name, Public, Title, Image, Body, CategoryID, Taglist)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$userID, sqltime(), $Name, $Public, $Title, $Image, $Body, $Category, $TagList]
            );

            $TemplateID = $master->db->lastInsertID();

            if ($Public) $master->cache->deleteValue('templates_public');
            else $master->cache->deleteValue('templates_ids_' . $activeUser['ID']);

            $Result = [1, "Added '$Name' template"];
        }
    }

}

$Result[] = get_templatelist_html($activeUser['ID'], $TemplateID);

echo json_encode($Result);
