<?php
include(SERVER_ROOT.'/Legacy/sections/staffpm/functions.php');

if ($ConvIDs = $_POST['id']) {
	$Queries = array();
	foreach ($ConvIDs as &$ConvID) {
		$ConvID = (int) $ConvID;
		check_access($ConvID);

    	if (isset($_POST['StealthResolve']) && check_perms('admin_stealth_resolve')) {
			$Queries[] = "UPDATE staff_pm_conversations SET StealthResolved=1 WHERE ID=$ConvID";

        	// Add a log message to the StaffPM
        	$Message = sqltime()." - Stealth Resolved by ".$LoggedUser['Username'];
			make_staffpm_note($Message, $ConvID);
    	} else {
			// Conversation belongs to user or user is staff, queue query
			$Queries[] = "UPDATE staff_pm_conversations SET Status='Resolved', ResolverID=".$LoggedUser['ID']." WHERE ID=$ConvID";

	      	// Add a log message to the StaffPM
	      	$Message = sqltime()." - Resolved by ".$LoggedUser['Username'];
		  	make_staffpm_note($Message, $ConvID);
		}
	}

	// Run queries
	foreach ($Queries as $Query) {
		$DB->query($Query);
	}

	// Clear cache for user
	$Cache->delete_value('staff_pm_new_'.$LoggedUser['ID']);

	// Done! Return to inbox
	header("Location: staffpm.php");
} else {
	// No id
	header("Location: staffpm.php");
}
