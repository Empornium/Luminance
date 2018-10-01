<?php

function blockedPM($toID, $fromID, &$Err = false)
{
    global $master;
    $staffIDs = getStaffIDs();
    $Err=false;

    if (!is_number($fromID)) {
        $Err = 0;
    } elseif (!is_number($toID)) {
        $Err = "This recipient does not exist.";
    } else {
         // staff are never blocked from sending
        if (!isset($staffIDs[$fromID])) {
            // check if sender is on recepients blocked list
            $friendType = $master->db->raw_query("SELECT Type FROM friends
                                                   WHERE UserID=:toid AND FriendID=:fromid",
                                                        [':toid'=>$toID, ':fromid'=>$fromID])->fetchColumn();
            if($friendType == 'blocked')
                $Err = "This user cannot receive PM's from you.";
            else {
                // check recepients blockPM setting
                $blockPMs = $master->db->raw_query("SELECT BlockPMs FROM users_info WHERE UserID=:toid", [':toid'=>$toID])->fetchColumn();
                if($blockPMs == 2) {
                    // all users are blocked to this recepient
                    $Err = "This user cannot receive PM's from you.";
                } elseif($blockPMs == 1 && $friendType != 'friends') {
                    // non friends are blocked to this recepient
                    $Err = "This user cannot receive PM's from you.";
                }
            }
        }
    }
    return $Err !== false;
}

function getStaffIDs()
{
    global $master;
    static $staffIDs = null;
    if (!is_array($staffIDs)) $staffIDs = $master->cache->get_value("staff_ids");
    if (!is_array($staffIDs)) {
        $allstaff = $master->db->raw_query("SELECT m.ID, m.Username
                                       FROM users_main AS m JOIN permissions AS p ON p.ID=m.PermissionID
                                      WHERE p.DisplayStaff='1'")->fetchAll(\PDO::FETCH_ASSOC);
        $staffIDs = [];
        foreach($allstaff as $staff) {
            $staffIDs[$staff['ID']] = $staff['Username'];
        }
        uasort($staffIDs, 'strcasecmp');
        $master->cache->cache_value("staff_ids", $staffIDs);
    }
    return $staffIDs;
}
