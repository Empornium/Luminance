<?php
if (!is_integer_string($_POST['template']) || !check_perms('site_use_templates')) {
    echo json_encode([0, "You do not have permission to use templates", get_templatelist_html($activeUser['ID'])]);
    return;
}

// delete a template
$TemplateID = (int) $_POST['template'];
$Template = $master->cache->getValue('template_' . $TemplateID);

if ($Template === FALSE) { //it should really be cached from upload page
    $Template = $master->db->rawQuery(
        "SELECT t.ID,
                t.UserID,
                t.Name,
                t.Public
           FROM upload_templates as t
          WHERE t.ID = ?",
        [$TemplateID]
    )->fetch(\PDO::FETCH_BOTH); //dont cache as we have only pulled a subset for this rare edge case (impossible?)
}

$candelete=true;

if (!check_perms('site_delete_any_templates')) {
        if ($Template['Public'] == 1) {

            $Result = [0, "You cannot delete public templates"];
            $candelete = false;

        } elseif ($Template['UserID'] != $activeUser['ID']) {  // naughty
            $Result = [0, "You do not have permission to delete that template"];
            $candelete = false;
        }
}

if ($candelete) {
        $master->db->rawQuery(
            "DELETE
               FROM upload_templates
              WHERE ID = ?",
            [$TemplateID]
        );
        $master->cache->deleteValue('template_' . $TemplateID);

        if ($Template['Public']) $master->cache->deleteValue('templates_public');
        else $master->cache->deleteValue('templates_ids_' . $activeUser['ID']);

        $Result = [1, "Deleted '{$Template['Name']}' template"];
}

$Result[] = get_templatelist_html($activeUser['ID'], $TemplateID);

echo json_encode($Result);
