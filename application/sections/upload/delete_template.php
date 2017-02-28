<?php
if (!is_number($_POST['template']) || !check_perms('site_use_templates') ) {
    echo json_encode(array(0, "You do not have permission to use templates", get_templatelist_html($LoggedUser['ID'])));
    die();
}

// delete a template
$TemplateID = (int) $_POST['template'];
$Template = $Cache->get_value('template_' . $TemplateID);

if ($Template === FALSE) { //it should really be cached from upload page
            $DB->query("SELECT
                                        t.ID,
                                        t.UserID,
                                        t.Name,
                                        t.Public
                                   FROM upload_templates as t
                                  WHERE t.ID='$TemplateID'");
            list($Template) = $DB->to_array(false, MYSQLI_BOTH); //dont cache as we have only pulled a subset for this rare edge case (impossible?)
}

$candelete=true;

if (!check_perms('site_delete_any_templates')) {
        if ($Template['Public'] == 1) {

            $Result = array(0, "You cannot delete public templates");
            $candelete=false;

        } elseif ($Template['UserID'] != $LoggedUser['ID']) {  // naughty
            $Result = array(0, "You do not have permission to delete that template");
            $candelete=false;
        }
}

if ($candelete) {
        $DB->query("DELETE FROM upload_templates WHERE ID='$TemplateID'");
        $Cache->delete_value('template_' . $TemplateID);

        if ($Template['Public']) $Cache->delete_value('templates_public');
        else $Cache->delete_value('templates_ids_' . $LoggedUser['ID']);

        $Result = array(1, "Deleted '$Template[Name]' template");
}

$Result[] = get_templatelist_html($LoggedUser['ID'], $TemplateID);

echo json_encode($Result);
