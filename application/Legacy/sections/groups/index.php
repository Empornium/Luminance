<?php
enforce_login();

if (!check_perms('users_groups')) error(403);

if (!empty($_REQUEST['groupid'] ?? null)) {
    $GroupID = (int) $_REQUEST['groupid'];
}

if (empty($_POST['action'])) {

    if ($GroupID > 0)
        include(SERVER_ROOT . '/Legacy/sections/groups/group.php');
    else
        include(SERVER_ROOT . '/Legacy/sections/groups/groups.php');

} else {

    $ApplyTo = isset($_POST['applyto'])?$_POST['applyto']:'';
    if (!in_array($ApplyTo, ['user', 'group'])) error(0);

    if ($ApplyTo == 'user') {
        if (!empty($_REQUEST['userid'])) {
            $userID = (int) $_REQUEST['userid'];
        }
    }
    $P = $_POST;

    switch ($_POST['action']) {
        case 'add': // new group or user to group
            authorize();

            if ($ApplyTo == 'user') {  // add user to group
                if (!$GroupID || !$userID) error(0);
                $master->db->rawQuery(
                    "INSERT IGNORE INTO users_groups (GroupID, UserID, AddedTime, AddedBy)
                                 VALUES (?, ?, ?, ?)",
                    [$GroupID, $userID, sqltime(), $activeUser['ID']]
                );
                $Log = sqltime() . " - User [user]{$userID}[/user] [color=blue]added[/color] by [user]{$activeUser['ID']}[/user]";
                $master->db->rawQuery(
                    "UPDATE groups
                        SET Log = CONCAT_WS(CHAR(10 using utf8), ?, Log)
                      WHERE ID = ?",
                    [$Log, $GroupID]
                );

                header('Location: groups.php?groupid=' . $GroupID);

            } else {    // add group

                if (!$P['name'] || $P['name'] == '') error("Name of new group cannot be empty");
                $Log = sqltime() . " - Usergroup [color=green]{$_POST['name']}[/color] [color=blue]created[/color] by [user]{$activeUser['ID']}[/user]";
                $master->db->rawQuery(
                    "INSERT IGNORE INTO groups (Name, Log)
                                 VALUES (?, ?)",
                    [$_POST['name'], $Log]
                );
                $GroupID = (int) $master->db->lastInsertID();
                if (!$GroupID) error("Error - Failed to create new group!");
                header('Location: groups.php' . ($GroupID ? '?groupid=' . $GroupID : ''));
            }

            break;

        case 'update': // comment
            authorize();
            if (!$GroupID) error(0);

            if ($ApplyTo == 'user') { // update user comment in group
                if (!$userID) error(0);
                $master->db->rawQuery(
                    "UPDATE users_groups
                        SET Comment = ?
                      WHERE GroupID = ?
                        AND UserID = ?",
                    [$_POST['comment'], $GroupID, $userID]
                );
                header('Location: groups.php?groupid=' . $GroupID . '&userid=' . $userID);
            } else { // update group comment
                $master->db->rawQuery(
                    "UPDATE groups
                        SET Comment = ?
                      WHERE ID = ?",
                    [$_POST['comment'], $GroupID]
                );
                header("Location: groups.php?groupid={$GroupID}");
            }
            break;

        case 'remove':  // user
            authorize();

            if (!$userID || !$GroupID) error(0);

            $master->db->rawQuery(
                "DELETE
                   FROM users_groups
                  WHERE GroupID = ?
                    AND UserID = ?",
                [$GroupID, $userID]
            );
            $Log = sqltime() . " - User [user]{$userID}[/user] [color=red]removed[/color] by [user]{$activeUser['ID']}[/user]";
            $master->db->rawQuery(
                "UPDATE groups
                    SET Log = CONCAT_WS(CHAR(10 using utf8), ?, Log)
                  WHERE ID = ?",
                [$Log, $GroupID]
            );

            header('Location: groups.php?groupid=' . $GroupID . '&userid=' . $userID);
            break;

        case 'pm user':
            if (!$userID) error(0);
            header('Location: /user/'.$userID.'/inbox/compose');
            break;

        case 'change name': // group
            authorize();

            if (!$GroupID) error(0);
            if (!$P['name'] || $P['name'] == '') error("Name of group cannot be empty");
            $Log = sqltime() . " - Name [color=blue]changed[/color] to [color=green]{$_POST['name']}[/color] by [user]{$activeUser['ID']}[/user]";
            $master->db->rawQuery(
                "UPDATE groups
                    SET Name = ?,
                        Log = CONCAT_WS(CHAR(10 using utf8), ?, Log)
                  WHERE ID = ?",
                [$_POST['name'], $Log, $GroupID]
            );
            header('Location: groups.php?groupid=' . $GroupID);

            break;

        case 'delete':  // group
            authorize();

            if (!$GroupID) error(0);
            $master->db->rawQuery(
                "DELETE
                   FROM groups
                  WHERE ID = ?",
                [$GroupID]
            );
            $master->db->rawQuery(
                "DELETE
                   FROM users_groups
                  WHERE GroupID = ?",
                [$GroupID]
            );
            header('Location: groups.php');
            break;

        case 'checkusers':
            authorize();    // ajax call

            if (!$P['userlist']) error("No users in list", true);

            $Items = [];
            // split on both whitespace and commas
            $Preitems = str_replace('\n', ' ', $P['userlist']);
            $Preitems = explode(',', $Preitems);
            foreach ($Preitems as $pitem) {
                $Items = array_merge($Items, explode(" ", $pitem));
            }
            $IDs = [];
            foreach ($Items as $item) {
                $item = trim($item);
                if ($item == '') continue;
                if (is_integer_string($item)) {
                    $userID = (int) $item;
                    if (in_array($userID, $IDs)) continue;
                    $Username = $master->db->rawQuery(
                        "SELECT Username
                           FROM users
                          WHERE ID = ?",
                        [$userID]
                    );
                    if ($master->db->foundRows() == 0)
                        $result = '<img src="'. STATIC_SERVER .'common/symbols/warned.png" alt="No result" title="Could not find user with id='.$userID.'" /> Could not find user with id='.$userID.'<br/>';
                    else  {
                        $result = '<img src="'. STATIC_SERVER .'common/symbols/tick.png" alt="found" title="Found '.$Username.'" /> <a href="/user.php?id='.$userID.'">'.$Username.'</a><br/>';
                        $IDs[] = $userID;
                    }
                } else {
                    $Username = $item;
                    $userID = $master->db->rawQuery(
                        "SELECT ID
                           FROM users
                          WHERE Username = ?",
                        [$Username]
                    )->fetchColumn();
                    if ($master->db->foundRows() === 0)
                        $result = '<img src="'. STATIC_SERVER .'common/symbols/warned.png" alt="No result" title="Could not find user '.$Username.'" /> Could not find user \''.$Username.'\'<br/>';
                    else  {
                        if (in_array($userID, $IDs)) continue;
                        $result = '<img src="'. STATIC_SERVER .'common/symbols/tick.png" alt="found" title="Found '.$Username.'" /> <a href="/user.php?id='.$userID.'">'.$Username.'</a><br/>';
                        $IDs[] = $userID;
                    }
                }
                echo $result;
            }
            if (count($IDs)>0) $IDs = implode(',', $IDs);
            else $IDs = '';
            echo '<input type="hidden" id="userids" name="userids" value="'.$IDs.'" />';
            break;

        case 'add users':
            authorize();

            if (!$GroupID) error(0);
            if (!$P['userids']) error("No users in list");

            $IDs = explode(',', $P['userids']);
            foreach ($IDs as &$id) {
                if (!is_integer_string($id)) error(0);
                $id=(int) $id;
            }

            $sqltime = sqltime();
            $valuesQuery = implode(',', array_fill(0, count($IDs), "(?, ?, '{$sqltime}', ?)"));
            $params = [];
            foreach ($IDs as $id) {
                $params = array_merge($params, [$GroupID, $id, $activeUser['ID']]);
            }

            $master->db->rawQuery(
                "INSERT IGNORE INTO users_groups (GroupID, UserID, AddedTime, AddedBy)
                             VALUES {$valuesQuery}",
                $params
            );

            $inQuery = implode(',', array_fill(0, count($IDs), '?'));
            $userRecords = $master->db->rawQuery(
                "SELECT ID,
                        Username
                   FROM users
                  WHERE ID IN ({$inQuery})",
                $IDs
            )->fetchAll(\PDO::FETCH_NUM);
            $Log = ''; $Div ='';
            foreach ($userRecords as $userRecord) {
                list($Uid, $Username) = $userRecord;
                $Log .= "{$Div}[url=/user.php?id={$Uid}]{$Username}[/url]";
                $Div =',';
            }

            $Log = sqltime() . " - User(s) $Log [color=blue]added[/color] by [user]{$activeUser['ID']}[/user]";
            $master->db->rawQuery(
                "UPDATE groups
                    SET Log = CONCAT_WS(CHAR(10 using utf8), ?, Log)
                  WHERE ID = ?",
                [$Log, $GroupID]
            );

            header('Location: groups.php?groupid=' . $GroupID);

            break;
        case 'remove all':
            authorize();

            if (!$GroupID) error(0);

            $userRecords = $master->db->rawQuery(
                "SELECT ug.UserID,
                        Username
                   FROM users AS u
                   JOIN users_groups AS ug ON ug.UserID=u.ID
                  WHERE ug.GroupID = ?",
                [$GroupID]
            )->fetchAll(\PDO::FETCH_NUM);

            if ($master->db->foundRows()>0) {

                $Log = ''; $Div ='';
                foreach ($userRecords as $userRecord) {
                    list($Uid, $Username) = $userRecord;
                    $Log .= "{$Div}[url=/user.php?id={$Uid}]{$Username}[/url]";
                    $Div =',';
                }

                $master->db->rawQuery(
                    "DELETE
                       FROM users_groups
                      WHERE GroupID = ?",
                    [$GroupID]
                );
                $Log = sqltime() . " - [color=red]All Users[/color] ($Log) [color=red]removed[/color] by [user]{$activeUser['ID']}[/user]";
                $master->db->rawQuery(
                    "UPDATE groups
                        SET Log = CONCAT_WS(CHAR(10 using utf8), ?, Log)
                      WHERE ID = ?",
                    [$Log, $GroupID]
                );
            }
            header('Location: groups.php?groupid=' . $GroupID);

            break;

        case 'mass pm': // users in group
            if (!$GroupID) error(0);
            include(SERVER_ROOT . '/Legacy/sections/groups/masspm.php');
            break;

        case 'takemasspm':
            if (!$GroupID) error(0);
            include(SERVER_ROOT . '/Legacy/sections/groups/takemasspm.php');
            break;

        case 'group award':
            if (!$GroupID) error(0);
            include(SERVER_ROOT . '/Legacy/sections/groups/massaward.php');
            break;

        case 'takemassaward':
            if (!$GroupID) error(0);
            include(SERVER_ROOT . '/Legacy/sections/groups/takemassaward.php');
            break;

        case 'give credits':
            authorize();
            if (!check_perms('users_edit_credits')) error(403);
            if (!$GroupID) error(0);
            $Users = $master->db->rawQuery(
                'SELECT UserID
                   FROM users_groups
                  WHERE GroupID = ?',
                [$GroupID]
            )->fetchAll(\PDO::FETCH_COLUMN);

            if ($master->db->foundRows()>0) {
                $AdjustCredits = $_POST['credits'];
                if ( $AdjustCredits[0]=='+') $AdjustCredits = substr($AdjustCredits, 1);
                if ( !is_numeric($AdjustCredits)) error(0);
                $AdjustCredits = (int) $AdjustCredits;
                $textCredits = number_format($AdjustCredits);
                $textCredits = "+{$textCredits}";
                $userIDs = $Users;
                $inQuery = implode(',', array_fill(0, count($userIDs), '?'));

                $BonusSummary = " | $textCredits | Credit award given by {$activeUser['Username']}";
                foreach ($userIDs as $userID) {
                    $wallet = $master->repos->userWallets->get('UserID = ?', [$userID]);
                    $wallet->adjustBalance($AdjustCredits);
                    $wallet->addLog($BonusSummary);
                }

                foreach ($Users as $userID) {
                    $master->repos->users->uncache($userID);
                }
            }

            $Log = sqltime() . " - [color=purple]Credits awarded[/color] by {$activeUser['Username']} - amount: {$textCredits}";
            $master->db->rawQuery(
                "UPDATE groups
                    SET Log = CONCAT_WS(CHAR(10 using utf8), ?, Log)
                  WHERE ID = ?",
                [$Log, $GroupID]
            );

            header('Location: groups.php?groupid=' . $GroupID);

            break;

        case 'adjust download':
            authorize();
            if (!check_perms('users_edit_ratio')) error(403);
            if (!$GroupID) error(0);
            $Users = $master->db->rawQuery(
                'SELECT UserID
                   FROM users_groups
                  WHERE GroupID = ?',
                [$GroupID]
            )->fetchAll(\PDO::FETCH_COLUMN);

            if ($master->db->foundRows()>0) {
                $AdjustDownload = $_POST['download'];
                if ( $AdjustDownload[0]=='+') $AdjustDownload = substr($AdjustDownload, 1);
                // TODO this seems like a bug, using is_integer_string means this can never be negative
                if ( !is_integer_string($AdjustDownload)) error(0);
                $AdjustDownload = (int) $AdjustDownload;
                $AdjustDownload = get_bytes("{$AdjustDownload}gb");
                $StrDownload = get_size($AdjustDownload);

                $userIDs = $Users;
                $inQuery = implode(',', array_fill(0, count($userIDs), '?'));

                $Summary = sqltime()." - Download amount adjusted by {$StrDownload} (mass download adjustment) by {$activeUser['Username']}";

                $master->db->rawQuery(
                    "UPDATE users_main AS m
                       JOIN users_info AS i ON m.ID=i.UserID
                        SET Downloaded=IF (Downloaded<=".abs($AdjustDownload).",0,Downloaded + ?),
                            AdminComment=CONCAT_WS(CHAR(10 using utf8), ?, AdminComment)
                      WHERE m.ID IN ({$inQuery})",
                    array_merge([$AdjustDownload, $Summary], $userIDs)
                );

                foreach ($Users as $userID) {
                    $master->repos->users->uncache($userID);
                }
            }

            $Log = sqltime() . " - [color=purple]Download adjusted[/color] by {$activeUser['Username']} - amount: {$StrDownload}";
            $master->db->rawQuery(
                "UPDATE groups
                    SET Log = CONCAT_WS(CHAR(10 using utf8), ?, Log)
                  WHERE ID = ?",
                [$Log, $GroupID]
            );

            header('Location: groups.php?groupid=' . $GroupID);

            break;

        default :
            error(0);
            break;
    }
}
