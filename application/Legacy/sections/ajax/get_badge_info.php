<?php
// set the output to be served as xml
header("Content-type: text/xml");

$BadgeID = (int) $_REQUEST['badgeid'];
if ($BadgeID>0) {

    list($Name, $Desc, $Image) = $master->db->rawQuery(
        "SELECT Title,
                Description,
                Image
           FROM badges
          WHERE ID = ?",
        [$BadgeID]
    )->fetch(\PDO::FETCH_NUM);
    if ($master->db->foundRows() > 0) {
        $Image = STATIC_SERVER.'common/badges/'.$Image;
        echo "<badgeinfo><image>$Image</image><name>$Name</name><desc>$Desc</desc></badgeinfo>";
    }
}
