<?php
enforce_login();

if(!check_perms('users_groups')) error(403);

if (!empty($_REQUEST['groupid'])) {
    $GroupID = (int) $_REQUEST['groupid'];
}

if (empty($_POST['action'])) {

    if ($GroupID > 0)
        include(SERVER_ROOT . '/sections/groups/group.php');
    else
        include(SERVER_ROOT . '/sections/groups/groups.php');

} else {

    $ApplyTo = isset($_POST['applyto'])?$_POST['applyto']:'';
    if (!in_array($ApplyTo, array('user','group'))) error(0);

    if ($ApplyTo == 'user') {
        if (!empty($_REQUEST['userid'])) {
            $UserID = (int) $_REQUEST['userid'];
        }
    }
    $P = db_array($_POST);

    switch ($_POST['action']) {
        case 'add': // new group or user to group
            authorize();

            if ($ApplyTo == 'user') {  // add user to group
                if (!$GroupID || !$UserID) error(0);
                $DB->query("INSERT IGNORE INTO users_groups (GroupID, UserID, AddedTime, AddedBy)
                                 VALUES ('$GroupID', '$UserID','" . sqltime() . "','" . $LoggedUser['ID'] . "')");
                $DB->query("SELECT Username FROM users_main WHERE ID=$UserID");
                list($Username) = $DB->next_record();
                $Log = sqltime() . " - User [user]{$Username}[/user] [color=blue]added[/color] by [user]{$LoggedUser['Username']}[/user]";
                $DB->query("UPDATE groups SET Log=CONCAT_WS( '\n', '$Log', Log) WHERE ID='$GroupID'");

                header('Location: groups.php?groupid=' . $GroupID);

            } else {    // add group

                if (!$P[name] || $P[name] == '') error("Name of new group cannot be empty");
                $Log = sqltime() . " - Usergroup [color=green]$P[name][/color] [color=blue]created[/color] by [user]{$LoggedUser['Username']}[/user]";
                $DB->query("INSERT IGNORE INTO groups (Name, Log) VALUES ('$P[name]', '$Log')");
                $GroupID = (int) $DB->inserted_id();
                if (!$GroupID) error("Error - Failed to create new group!");
                header('Location: groups.php' . ($GroupID ? '?groupid=' . $GroupID : ''));
            }

            break;

        case 'update': // comment
            authorize();
            if (!$GroupID) error(0);

            if ($ApplyTo == 'user') { // update user comment in group
                if (!$UserID) error(0);
                $DB->query("UPDATE users_groups SET Comment='$P[comment]' WHERE GroupID='$GroupID' AND UserID='$UserID'");
                header('Location: groups.php?groupid=' . $GroupID . '&userid=' . $UserID);
            } else { // update group comment
                $DB->query("UPDATE groups SET Comment='$P[comment]' WHERE ID='$GroupID'");
                header('Location: groups.php?groupid=' . $GroupID);
            }
            break;

        case 'remove':  // user
            authorize();

            if (!$UserID || !$GroupID) error(0);

            $DB->query("DELETE FROM users_groups WHERE GroupID='$GroupID' AND UserID='$UserID'");
            $DB->query("SELECT Username FROM users_main WHERE ID=$UserID");
            list($Username) = $DB->next_record();
            $Log = sqltime() . " - User [user]{$Username}[/user] [color=red]removed[/color] by [user]{$LoggedUser['Username']}[/user]";
            $DB->query("UPDATE groups SET Log=CONCAT_WS( '\n', '$Log', Log) WHERE ID='$GroupID'");

            header('Location: groups.php?groupid=' . $GroupID . '&userid=' . $UserID);
            break;

        case 'pm user':
            if (!$UserID) error(0);
            header('Location: inbox.php?action=compose&to=' . $UserID);
            break;

        case 'change name': // group
            authorize();

            if (!$GroupID) error(0);
            if (!$P['name'] || $P['name'] == '') error("Name of group cannot be empty");
            $Log = sqltime() . " - Name [color=blue]changed[/color] to [color=green]$P[name][/color] by [user]{$LoggedUser['Username']}[/user]";
            $DB->query("UPDATE groups SET Name='$P[name]', Log=CONCAT_WS( '\n', '$Log', Log) WHERE ID='$GroupID'");
            header('Location: groups.php?groupid=' . $GroupID);

            break;

        case 'delete':  // group
            authorize();

            if (!$GroupID) error(0);
            $DB->query("DELETE FROM groups WHERE ID='$GroupID'");
            $DB->query("DELETE FROM users_groups WHERE GroupID='$GroupID'");
            header('Location: groups.php');
            break;

        case 'checkusers':
            authorize();    // ajax call

            if (!$P['userlist']) error("No users in list", true);

            $Items = array();
            // split on both whitespace and commas
            $Preitems = str_replace('\n', ' ',$P['userlist']);
            $Preitems = explode(",", $Preitems);
            foreach ($Preitems as $pitem) {
                $Items = array_merge($Items, explode(" ", $pitem));
            }
            $IDs = array();
            foreach ($Items as $item) {
                $item = trim($item);
                if ($item == '') continue;
                if (is_number($item)) {
                    $UserID = (int) $item;
                    if(in_array($UserID, $IDs)) continue;
                    $DB->query("SELECT Username FROM users_main WHERE ID=$UserID");
                    if ($DB->record_count()==0)
                        $result = '<img src="'. STATIC_SERVER .'common/symbols/warned.png" alt="No result" title="Could not find user with id='.$UserID.'" /> Could not find user with id='.$UserID.'<br/>';
                    else  {
                        list($Username) = $DB->next_record();
                        $result = '<img src="'. STATIC_SERVER .'common/symbols/tick.png" alt="found" title="Found '.$Username.'" /> <a href="user.php?id='.$UserID.'">'.$Username.'</a><br/>';
                        $IDs[] = $UserID;
                    }
                } else {
                    $Username = $item;
                    $DB->query("SELECT ID FROM users_main WHERE Username = '" . db_string($Username). "'");
                    if ($DB->record_count()==0)
                        $result = '<img src="'. STATIC_SERVER .'common/symbols/warned.png" alt="No result" title="Could not find user '.$Username.'" /> Could not find user \''.$Username.'\'<br/>';
                    else  {
                        list($UserID) = $DB->next_record();
                        if(in_array($UserID, $IDs)) continue;
                        $result = '<img src="'. STATIC_SERVER .'common/symbols/tick.png" alt="found" title="Found '.$Username.'" /> <a href="user.php?id='.$UserID.'">'.$Username.'</a><br/>';
                        $IDs[] = $UserID;
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

            $IDs = explode(",", $P['userids']);
            foreach ($IDs as &$id) {
                if (!is_number($id)) error(0);
                $id=(int) $id;
            }

            $Values = "('".$GroupID."', '".implode("', '". sqltime()."', '".$LoggedUser['ID']."'), ('".$GroupID."', '", $IDs)."', '".sqltime()."', '".$LoggedUser['ID']."')";
            $DB->query("INSERT IGNORE INTO users_groups (GroupID, UserID, AddedTime, AddedBy) VALUES $Values");

            $IDs = implode(',', $IDs);
            $DB->query("SELECT ID, Username FROM users_main WHERE ID IN ($IDs)");
            $Log = ''; $Div ='';
            while (list($Uid, $Username) = $DB->next_record()) {
                $Log .= "{$Div}[url=/user.php?id=$Uid]{$Username}[/url]";
                $Div =', ';
            }

            $Log = sqltime() . " - User(s) $Log [color=blue]added[/color] by [user]{$LoggedUser['Username']}[/user]";
            $DB->query("UPDATE groups SET Log=CONCAT_WS( '\n', '$Log', Log) WHERE ID='$GroupID'");

            header('Location: groups.php?groupid=' . $GroupID);

            break;
        case 'remove all':
            authorize();

            if (!$GroupID) error(0);

            $DB->query("SELECT ug.UserID, Username
                          FROM users_main AS um
                          JOIN users_groups AS ug ON ug.UserID=um.ID
                         WHERE ug.GroupID = $GroupID");

            if ($DB->record_count()>0) {

                $Log = ''; $Div ='';
                while (list($Uid, $Username) = $DB->next_record()) {
                    $Log .= "{$Div}[url=/user.php?id=$Uid]{$Username}[/url]";
                    $Div =', ';
                }

                $DB->query("DELETE FROM users_groups WHERE GroupID='$GroupID'");
                $Log = sqltime() . " - [color=red]All Users[/color] ($Log) [color=red]removed[/color] by [user]{$LoggedUser['Username']}[/user]";
                $DB->query("UPDATE groups SET Log=CONCAT_WS( '\n', '$Log', Log) WHERE ID='$GroupID'");
            }
            header('Location: groups.php?groupid=' . $GroupID );

            break;

        case 'mass pm': // users in group
            if (!$GroupID) error(0);
            include(SERVER_ROOT . '/sections/groups/masspm.php');
            break;

        case 'takemasspm':
            if (!$GroupID) error(0);
            include(SERVER_ROOT . '/sections/groups/takemasspm.php');
            break;

        case 'group award':
            if (!$GroupID) error(0);
            include(SERVER_ROOT . '/sections/groups/massaward.php');
            break;

        case 'takemassaward':
            if (!$GroupID) error(0);
            include(SERVER_ROOT . '/sections/groups/takemassaward.php');
            break;

        case 'give credits':
            if (!check_perms('users_edit_credits')) error(403);
            if (!$GroupID) error(0);
            $DB->query('SELECT UserID FROM users_groups WHERE GroupID='.$GroupID);

            if ($DB->record_count()>0) {
                $AdjustCredits = $_POST['credits'];
                if ( $AdjustCredits[0]=='+') $AdjustCredits = substr($AdjustCredits, 1);
                if ( !is_number($AdjustCredits)) error(0);
                $AdjustCredits = (int) $AdjustCredits;
                if ($AdjustCredits>0) $AdjustCredits = "+$AdjustCredits";

                $Users = $DB->collect('UserID');
                $UserIDs = implode(',', $Users);

                $BonusSummary = sqltime()." | $AdjustCredits | ".ucfirst("credits given by $LoggedUser[Username]");
                $Summary = sqltime()." - Bonus Credits adjusted by $AdjustCredits (mass credit award) by $LoggedUser[Username]";

                $DB->query("UPDATE users_main AS m JOIN users_info AS i ON m.ID=i.UserID
                                   SET Credits=Credits$AdjustCredits,
                                       AdminComment=CONCAT_WS( '\n', '$Summary', AdminComment),
                                       BonusLog=CONCAT_WS( '\n', '$BonusSummary', BonusLog)
                                 WHERE m.ID IN ($UserIDs)");

                foreach ($Users as $UserID) {
                    $master->repos->users->uncache($UserID);
                }
            }

            $Log = db_string( sqltime()." - [color=purple]Credits awarded[/color] by $LoggedUser[Username] - amount: $AdjustCredits" );
            $DB->query("UPDATE groups SET Log=CONCAT_WS( '\n', '$Log', Log) WHERE ID='$GroupID'");

            header('Location: groups.php?groupid=' . $GroupID );

            break;

        case 'adjust download':
            if (!check_perms('users_edit_ratio')) error(403);
            if (!$GroupID) error(0);
            $DB->query('SELECT UserID FROM users_groups WHERE GroupID='.$GroupID);

            if ($DB->record_count()>0) {
                $AdjustDownload = $_POST['download'];
                if ( $AdjustDownload[0]=='+') $AdjustDownload = substr($AdjustDownload, 1);
                if ( !is_number($AdjustDownload)) error(0);
                $AdjustDownload = (int) $AdjustDownload;
                $AdjustDownload = get_bytes("{$AdjustDownload}gb");
                $StrDownload = get_size($AdjustDownload);
                if ($AdjustDownload>0) $AdjustDownload = "+$AdjustDownload";

                $Users = $DB->collect('UserID');
                $UserIDs = implode(',', $Users);

                $Summary = sqltime()." - Download amount adjusted by $StrDownload (mass download adjustment) by $LoggedUser[Username]";

                $DB->query("UPDATE users_main AS m JOIN users_info AS i ON m.ID=i.UserID
                                   SET Downloaded=IF(Downloaded<=".abs($AdjustDownload).",0,Downloaded$AdjustDownload),
                                       AdminComment=CONCAT_WS( '\n', '$Summary', AdminComment)
                                 WHERE m.ID IN ($UserIDs)");

                foreach ($Users as $UserID) {
                    $master->repos->users->uncache($UserID);
                }
            }

            $Log = db_string( sqltime()." - [color=purple]Download adjusted[/color] by $LoggedUser[Username] - amount: $StrDownload" );
            $DB->query("UPDATE groups SET Log=CONCAT_WS( '\n', '$Log', Log) WHERE ID='$GroupID'");

            header('Location: groups.php?groupid=' . $GroupID );

            break;

        default :
            error(0);
            break;
    }
}
