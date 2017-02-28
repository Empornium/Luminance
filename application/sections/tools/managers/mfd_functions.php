<?php
/*
 * Possibly this should not be hardcoded... and should be changeable in the toolbox somewhere
 * while it is hardcoded it can go here
 */

// how long they get to fix their upload before autodeletion is set here
function get_warning_time()
{
    global $DB;
    //return time() + (12 * 60 * 60); // 12 hours...   ?
    $DB->query("SELECT ReviewHours FROM site_options");
    list($Time) = $DB->next_record();

    return time() + ((int) $Time * 3600);
}

// message is in two parts so we can grab the bits around the reason for display etc.
function get_warning_message($FirstPart = true, $LastPart = false, $GroupID=0, $TorrentName='', $Reason = null, $KillTime = '', $Rejected = false, $ExtraMsg = '')
{
    $Message = '';
    if ($FirstPart) {
        if ($Rejected) $Message .= "[br]Unfortunately the fix you made for your upload is not good enough.[br]The following message still applies:[br]";
        $Message .= "[br]Your upload [url=/torrents.php?id=$GroupID]{$TorrentName}[/url] does not meet our standards for uploading and has been marked for deletion.";
        $Message .= "[br][br][size=3][b]It will be automatically deleted if you do not fix your upload in the next ". time_diff($KillTime, 1, false,false,0)." &nbsp;(".date('M d Y, H:i', ($KillTime)).").[/b][/size]";
        $Message .= '[br][br][b]Once you have fixed your upload be sure to click the green button at the top of the torrent page to send the staff a message telling them you have done so.[/b]&nbsp;';
        $Message .= '[br][br][b]Reason: [/b]&nbsp;';
    }
    if ($Reason) $Message.= "[color=red][b]{$Reason}[/b][/color]";
    if ($ExtraMsg) $Message.= "[br][br]{$ExtraMsg}";
    if ($LastPart) {
        //$Message .= "[br][b]Time left: [/b]&nbsp; ". time_diff($KillTime, 2, false)." &nbsp;(".date('M d Y, H:i', strtotime($KillTime)).")";
        $Message .= '[br][br]Please make sure you read the [url=/articles.php?topic=upload]Upload Rules[/url]';
        $Message .= '[br]You will find useful guides in the [url=/articles.php?topic=presentation]Upload Guides[/url] and [url=/articles.php?topic=tutorials]Tutorials section[/url]';
        $Message .= '[br]If you need further help please post in the [url=/forums.php?action=viewforum&amp;forumid=6]Help & Support Forum[/url]';
    }

    return $Message;
}

// a reply to an existing conversation
function send_message_reply($ConvID, $ToID, $FromID, $Message, $SetStatus = false, $FromStaff = true, $NewSubject = null, $SetUnread = true)
{
    global $LoggedUser, $DB, $Cache;

    $DB->query("INSERT INTO staff_pm_messages (UserID, SentDate, Message, ConvID)
                       VALUES ($FromID, '".sqltime()."', '".db_string($Message)."', $ConvID)");

    if ($SetStatus !== FALSE) {
        // Update conversation (if from user
        if ($SetStatus == 'Resolved') $SQL_insert = "Status='Resolved', ResolverID=$FromID";
        elseif ($SetStatus == 'Open') $SQL_insert = "Status='Open'";
        else $SQL_insert = "Status='Unanswered'";
        if ($NewSubject) $SQL_insert .= ", Subject='".db_string($NewSubject)."' ";
        $DB->query("UPDATE staff_pm_conversations SET Date='".sqltime()."', Unread=$SetUnread, $SQL_insert WHERE ID=$ConvID");
    }

    if ($FromStaff) {
        if ($FromID>0) {
            $Cache->delete_value('num_staff_pms_'.$FromID);
            $Cache->delete_value('num_staff_pms_open_'.$FromID);
            $Cache->delete_value('num_staff_pms_my_'.$FromID);
        }
    } elseif($ToID>0)  // Clear cache for user
        $Cache->delete_value('staff_pm_new_'.$ToID);

}

// the message the user sends to staff to tell them its fixed
function get_user_okay_message($GroupID, $TorrentName, $KillTime, $Reason)
{
    $Message = "[br]I have fixed my upload [url=/torrents.php?id=$GroupID]{$TorrentName}[/url].";
    $Message .= "[br][br]note for staff: deal with this by going to the torrent detail page and using the review tools to mark as 'Accept Fix' or 'Reject Fix'.";
    $Message .= "[br][br][b]Reason it needed fixing:[/b]&nbsp;$Reason";
    $Message .= "[br][b]Time left: [/b]&nbsp; ". time_diff($KillTime, 2, false, false,0)." &nbsp;(".date('M d Y, H:i', strtotime($KillTime)).")";

    return $Message;
}

// from staff to user when fixed
function get_fixed_message($GroupID, $TorrentName)
{
    $Message = "[br]Thank-you for fixing your upload [url=/torrents.php?id=$GroupID]{$TorrentName}[/url].";

    return $Message;
}

// message is in two parts so we can grab the bits around the reason for display etc.
function get_deleted_message($GroupID, $TorrentName, $Reason)
{
    $Message = "[b][br]Your upload [/b][url=/torrents.php?id=$GroupID]{$TorrentName}[/url] [b]has been auto-deleted.";
    $Message .= "[br][br]Reason: &nbsp;[color=red]{$Reason}[/color][/b]";
    $Message .= '[br][br]Before you upload something again please make sure you read the [url=/articles.php?topic=upload]Upload Rules[/url]';
    $Message .= '[br]You will find useful guides in the [url=/articles.php?topic=tutorials]Tutorials section[/url]';
    $Message .= '[br]If you need further help please post in the [url=/forums.php?action=viewforum&amp;forumid=6]Help & Support Forum[/url]';

    return $Message;
}

function get_num_overdue_torrents($WhereStatus = 'warned')
{
    global $DB;

    switch ($WhereStatus) {
        case 'unmarked':
            return 0;
            break;
        case 'pending':
            $WHERE= "tr.Status = 'Pending' ";
            break;
        case 'both':
            $WHERE= "(tr.Status = 'Warned' OR tr.Status = 'Pending') ";
            break;
        case 'warned':
        default:
            $WHERE= "tr.Status = 'Warned' ";
            break;
    }

    $DB->query("SELECT Count(*)
              FROM torrents AS t
                    JOIN torrents_reviews AS tr ON tr.GroupID=t.GroupID
                   WHERE $WHERE
                     AND tr.Time=(SELECT MAX(torrents_reviews.Time)
                                         FROM torrents_reviews
                                         WHERE torrents_reviews.GroupID=t.GroupID)
                     AND tr.KillTime < '".sqltime()."'");

    list($Num) = $DB->next_record();

    return $Num;
}

 // passing an array of ints as last param makes the where clause ignores the first 2 params
function get_torrents_under_review($ViewStatus = 'warned', $ReturnOverdueOnly = true, $InGroupIDs = false)
{
    global $DB;

    if ($InGroupIDs !== false && is_array($InGroupIDs)) {
        $WHERE = 't.GroupID IN (';
        $Sep = '';
        foreach ($InGroupIDs as &$ID) {
            $ID = (int) $ID;
            $WHERE .= "$Sep $ID";
            $Sep = ',';
        }
        $WHERE .= ' ) ';
    } else if ($ViewStatus == 'unmarked') {
        $DB->query("SELECT t.ID,
                           t.GroupID,
                           tg.Name,
                           'Unmarked',
                           0,
                           t.Time,
                           'Awaiting review' AS Reason,
                           t.UserID,
                           um.Username
              FROM torrents AS t
                    JOIN torrents_group AS tg ON tg.ID = t.GroupID
               LEFT JOIN users_main AS um ON um.ID=t.UserID
               LEFT JOIN torrents_reviews AS tr ON tr.GroupID=t.GroupID
                   WHERE tr.GroupID IS NULL AND t.Time > '2013-01-01 00:00:00'");

    $Torrents = $DB->to_array();

      return $Torrents;
    } else {
          switch ($ViewStatus) {
              case 'pending':
                  $WHERE= "tr.Status = 'Pending' ";
                  break;
              case 'both':
                  $WHERE= "(tr.Status = 'Warned' OR tr.Status = 'Pending') ";
                  break;
              case 'warned':
              default:
                  $WHERE= "tr.Status = 'Warned' ";
                  break;
          }
          if ($ReturnOverdueOnly) $WHERE .=  "AND tr.KillTime < '".sqltime()."' ";


      $DB->query("SELECT t.ID,
                         t.GroupID,
                         tg.Name,
                         tr.Status,
                         tr.ConvID,
                         tr.KillTime,
                         IF(tr.ReasonID = 0, tr.Reason, rr.Description) AS Reason,
                         t.UserID,
                         um.Username
              FROM torrents AS t
                    JOIN torrents_group AS tg ON tg.ID = t.GroupID
                    JOIN torrents_reviews AS tr ON tr.GroupID=t.GroupID
               LEFT JOIN review_reasons AS rr ON rr.ID=tr.ReasonID
               LEFT JOIN users_main AS um ON um.ID=t.UserID
                   WHERE $WHERE
                     AND tr.Time=(SELECT MAX(torrents_reviews.Time)
                                         FROM torrents_reviews
                                         WHERE torrents_reviews.GroupID=t.GroupID)
                ORDER BY KillTime");

    $Torrents = $DB->to_array();
    }
      return $Torrents;
}

function delete_torrents_list($Torrents)
{
    global $DB;

    $LogEntries = array();
    $i=0;
    foreach ($Torrents as $TorrentID) {
        list($ID, $GroupID, $Name, $Status, $ConvID, $KillTime, $Reason, $UserID, $Username) = $TorrentID;

        delete_torrent($ID, $GroupID, $UserID);
        $LogEntries[] = "Torrent ".$ID." (".$Name.") was auto-deleted for $Reason";

        $Msg = get_deleted_message($GroupID, $Name, $Reason);

        if ($ConvID) { //
                send_message_reply($ConvID, $UserID, 0, $Msg, 'Resolved');
        } else {
                send_pm($UserID, 0, db_string("Your upload has been auto deleted."), $Msg);
        }
        ++$i;
    }

    $sqltime=sqltime();
    if (count($LogEntries) > 0) {
        $Values = "('".implode("', '".$sqltime."'), ('",$LogEntries)."', '".$sqltime."')";
        $DB->query('INSERT INTO log (Message, Time) VALUES '.$Values);
    }

  return $i;
}
