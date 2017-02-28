<?php
// trim whitespace before setting/evaluating these fields
$Title =  db_string(trim($_POST['title']));
$Image =  db_string(trim($_POST['image']));
$Body =  db_string(trim($_POST['body']));
$Category = (int) $_POST['category'];
$TagList = db_string(trim($_POST['tags']));

$TemplateID = (int) $_POST['templateID'];

//TODO: add max number of templates?

if ($Title=='' && $Image=='' && $Body=='' && $TagList=='') {

    $Result = array(0, "Cannot save a template with no content!");

} elseif (is_number($TemplateID)&& $TemplateID>0) {
        //get existing template to write over
        $DB->query("SELECT Name, UserID, Public FROM upload_templates WHERE ID='$TemplateID'");
        if ($DB->record_count()==0) {
            //error
            $Result = array(0, "Could not find template #$TemplateID to overwrite!");
        } else {
            // overwrite this
            list($Name, $UserID, $Public) = $DB->next_record();
            if ($UserID!=$LoggedUser['ID'] && !check_perms('site_edit_public_templates')) {
                $Result = array(0, "Cannot overwrite someone else's template!");
            } else {
                $DB->query("UPDATE upload_templates SET UserID='$UserID',
                                                     TimeAdded='".sqltime()."',
                                                         Title='$Title',
                                                         Image='$Image',
                                                          Body='$Body',
                                                    CategoryID='$Category',
                                                       Taglist='$TagList'
                                       WHERE ID='$TemplateID'");
                                                       // Public='$Public',
                if ($Public) $Cache->delete_value('templates_public');
                else $Cache->delete_value('templates_ids_' . $LoggedUser['ID']);

                $Cache->delete_value('template_' . $TemplateID);
                $Result = array(1, "Saved '$Name' template");
            }
        }

} else {
    $Name = db_string(trim($_POST['name']));
    $Public = $_POST['ispublic']==1?1:0;
    $UserID = (int) $LoggedUser['ID'];

    if ($Name=='') {

        $Result = array(0, "Error: No name set");

    } else {

        if ($Public && !check_perms('site_make_public_templates')) {
            $Result = array(0, "Error: You do not have permissions to save a public template!");

        } elseif (!check_perms('site_make_private_templates')) {
            $Result = array(0, "Error: You do not have permissions to save a private template!");

        } else {
            $DB->query("INSERT INTO upload_templates
                                  (UserID, TimeAdded, Name, Public, Title, Image, Body, CategoryID, Taglist) VALUES
                ('$UserID', '".sqltime()."', '$Name', '$Public', '$Title', '$Image', '$Body', '$Category', '$TagList')  ");

            $TemplateID = $DB->inserted_id();

            if ($Public) $Cache->delete_value('templates_public');
            else $Cache->delete_value('templates_ids_' . $LoggedUser['ID']);

            $Result = array(1, "Added '$Name' template");
        }
    }

}

$Result[] = get_templatelist_html($LoggedUser['ID'], $TemplateID);

echo json_encode($Result);
