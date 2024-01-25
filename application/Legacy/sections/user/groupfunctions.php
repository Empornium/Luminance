<?php

function remove_user($userID, $groupID) {
    global $master, $activeUser;
    authorize();

    if (!$userID || !$groupID) error(0);

    $master->db->rawQuery("DELETE FROM users_groups WHERE GroupID = ? AND UserID = ?", [$groupID, $userID]);
    $Log = sqltime() . " - User [user]{$userID}[/user] [color=red]removed[/color] by [user]{$activeUser['ID']}[/user]";
    $master->db->rawQuery(
        "UPDATE groups
            SET Log = CONCAT_WS(CHAR(10 using utf8), ?, Log)
            WHERE ID = ?",
            [$Log, $groupID]
    );
}

function add_user($userID, $groupID) {
    global $master, $activeUser;
    authorize();

    if (!$userID || !$groupID) error(0);

    $master->db->rawQuery(
        "INSERT IGNORE INTO users_groups (GroupID, UserID, AddedTime, AddedBy)
                     VALUES (?, ?, ?, ?)",
        [$groupID, $userID, sqltime(), $activeUser['ID']]
    );
    $Log = sqltime() . " - User [user]{$userID}[/user] [color=blue]added[/color] by [user]{$activeUser['ID']}[/user]";
    $master->db->rawQuery(
        "UPDATE groups
            SET Log = CONCAT_WS(CHAR(10 using utf8), ?, Log)
          WHERE ID = ?",
        [$Log, $groupID]
    );
}

function add_comment($comment, $userID, $groupID) {
    global $master, $activeUser;
    authorize();

    if (!$userID || !$groupID) error(0);

    $master->db->rawQuery(
        "UPDATE users_groups
            SET Comment = ?
            WHERE GroupID = ?
                AND UserID = ?",
        [$comment, $groupID, $userID]
    );
    $Log = sqltime() . " - User [user]{$userID}[/user] [color=yellow]comment modified[/color] by [user]{$activeUser['ID']}[/user]";
    $master->db->rawQuery(
        "UPDATE groups
            SET Log = CONCAT_WS(CHAR(10 using utf8), ?, Log)
          WHERE ID = ?",
        [$Log, $groupID]
    );
}