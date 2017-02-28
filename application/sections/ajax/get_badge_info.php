<?php
// set the output to be served as xml
header("Content-type: text/xml");

$BadgeID = (int) $_REQUEST['badgeid'];
if ($BadgeID>0) {

    $DB->query("SELECT Title, Description, Image
                  FROM badges WHERE ID=$BadgeID");
    if ($DB->record_count()>0) {
        list($Name,$Desc,$Image) = $DB->next_record();

        $Image = STATIC_SERVER.'common/badges/'.$Image;
        echo "<badgeinfo><image>$Image</image><name>$Name</name><desc>$Desc</desc></badgeinfo>";
    }
}
