<?php
// FLS+Staff
include(SERVER_ROOT.'/sections/staffpm/functions.php');
include(SERVER_ROOT.'/classes/class_text.php');
if ($IsFLS) {
    list($NumMy, $NumUnanswered, $NumOpen) = get_num_staff_pms($LoggedUser['ID'], $LoggedUser['Class']);
}
$Text = new TEXT;

if ($ConvID = (int) $_GET['id']) {
    // Get conversation info
    $DB->query("SELECT Subject, UserID, Level, AssignedToUser, Unread, Status, StealthResolved, ResolverID FROM staff_pm_conversations WHERE ID=$ConvID");
    list($Subject, $UserID, $Level, $AssignedToUser, $Unread, $Status, $StealthResolved, $ResolverID) = $DB->next_record();

    $TargetUserID = $UserID;

    if (!(($UserID == $LoggedUser['ID']) || ($AssignedToUser == $LoggedUser['ID']) || (($Level > 0 && $Level <= $LoggedUser['Class']) || ($Level == 0 && $IsFLS)))) {
    // User is trying to view someone else's conversation
        error(403);
    }
    // User is trying to view their own unread conversation, set it to read
    if ($UserID == $LoggedUser['ID'] && $Unread) {
        $DB->query("UPDATE staff_pm_conversations SET Unread=false WHERE ID=$ConvID");
        // Make a note so we know when he read it
        $Message = sqltime()." - ".$LoggedUser['Username']." read staff message";
        make_staffpm_note($Message, $ConvID);
        // Clear cache for user
        $Cache->delete_value('staff_pm_new_'.$LoggedUser['ID']);
    }

    show_header('Staff PM', 'comments,staffpm,bbcode,jquery');

    $OwnerInfo = user_info($UserID);
    $UserInfo = $OwnerInfo;
    $UserStr = format_username($UserID, $OwnerInfo['Username'], $OwnerInfo['Donor'], $OwnerInfo['Warned'], $OwnerInfo['Enabled'], $OwnerInfo['PermissionID'], $OwnerInfo['Title'], true, $OwnerInfo['GroupPermissionID'], $IsFLS);
      $OwnerID = $UserID;
      if ($ResolverID) {
              $ResolverInfo = user_info($ResolverID);
            $ResolverStr = format_username($ResolverID, $ResolverInfo['Username'], $ResolverInfo['Donor'], $ResolverInfo['Warned'], $ResolverInfo['Enabled'], $ResolverInfo['PermissionID'], false, true, $ResolverInfo['GroupPermissionID']);
    }
    // Get assigned
    if ($AssignedToUser == '') { // Assigned to class
            $Assigned = ($Level == 0) ? "First Line Support" : $ClassLevels[$Level]['Name'];
            // No + on Sysops
        if ($Assigned != 'Sysop') { $Assigned .= "+"; }
      } else {  // Assigned to user
            $AssignInfo = user_info($AssignedToUser);
        $Assigned = format_username($AssignedToUser, $AssignInfo['Username'], $AssignInfo['Donor'], $AssignInfo['Warned'], $AssignInfo['Enabled']); //, $AssignInfo['PermissionID'], false, false, $AssignInfo['GroupPermissionID']);
      }
?>
<div class="thin">
    <h2>Staff PM - <?=display_str($Subject)?></h2>
    <div class="linkbox">
<?php  	if ($IsStaff) { ?>
        [ &nbsp;<a href="staffpm.php?view=my">My unanswered<?=$NumMy>0?" ($NumMy)":''?></a>&nbsp; ] &nbsp;
<?php  	}
        if ($IsFLS) { ?>
        [ &nbsp;<a href="staffpm.php?view=unanswered">All unanswered<?=$NumUnanswered>0?" ($NumUnanswered)":''?></a>&nbsp; ] &nbsp;
        [ &nbsp;<a href="staffpm.php?view=open">Open<?=$NumOpen>0?" ($NumOpen)":''?></a>&nbsp; ] &nbsp;
        [ &nbsp;<a href="staffpm.php?view=resolved">Resolved</a>&nbsp; ] &nbsp;
<?php       if (check_perms('admin_stealth_resolve')) { ?>
        [ &nbsp;<a href="staffpm.php?view=stealthresolved">Stealth Resolved</a>&nbsp; ] &nbsp;
<?php       } ?>
        [ &nbsp;<a href="staffpm.php?action=responses">Common Answers</a>&nbsp; ] &nbsp;
<?php       if (check_perms('admin_staffpm_stats')) { ?>
        [ &nbsp;<a href="staffpm.php?action=stats">StaffPM Stats</a>&nbsp; ]
<?php       }
        } else { ?>
        [ &nbsp;<a href="staffpm.php">Back to inbox</a>&nbsp; ]
<?php   } ?>

        <br />
        <br />
    </div>

<?php
    // Fetch last message ID
    $DB->query("SELECT ID FROM staff_pm_messages WHERE ConvID=$ConvID ORDER BY ID DESC LIMIT 1");
    list($LastMessageID) = $DB->next_record();
    // Get messages
    $StaffPMs = $DB->query("SELECT ID, UserID, EditedUserID, EditedTime, SentDate, Message FROM staff_pm_messages WHERE ConvID=$ConvID AND NOT IsNotes ORDER BY SentDate");
    $Key = 0;
    while (list($ID, $UserID, $EditedUserID, $EditedTime, $SentDate, $Message) = $DB->next_record()) {
        // Set user string
        $UserInfo = user_info($UserID);
        if ($UserID == $OwnerID) {
            // User, use prepared string
            $UserString = $UserStr;
        } else {
            // Staff/FLS
            $UserString = format_username($UserID, $UserInfo['Username'], $UserInfo['Donor'], $UserInfo['Warned'], $UserInfo['Enabled'], $UserInfo['PermissionID'], $UserInfo['Title'], true, $UserInfo['GroupPermissionID'], $IsFLS);
        }
            // determine if conversation was started by user or not (checks first record for userID)
        if (!isset($UserInitiated)) {
                $UserInitiated = $UserID == $OwnerID;
?>
                <div class="head">
                    Status: <?=$Status; if($ResolverStr && $Status=='Resolved' ) echo " by $ResolverStr"; ?>
<?php                   //if ($UserInitiated) { ?>
                        <span style="float:right"><em>Assigned to: <?=$Assigned?></em></span>
<?php                   //}  ?>
                </div>
                <div class="box pad vertical_space colhead">
                    <span style="float:right">
<?php                       $SenderString = format_username($UserID, $UserInfo['Username'], $UserInfo['Donor'], $UserInfo['Warned'], $UserInfo['Enabled'], $UserInfo['PermissionID'], false, true, $UserInfo['GroupPermissionID'], $IsFLS);
                        echo "sent by $SenderString&nbsp;&nbsp;"; ?>
                    </span>
                    Sent to  <?=$UserInitiated?'<strong>Staff</strong>':$UserStr;?>
<?php               if (($IsStaff || $IsFLS)) {
                        if ($Unread) {
                            $UserRead="<span title='The user has not yet read this message'>(Unread)</span>";
                        } else {
                            $UserRead="<span title='The user has read this message'>(Read)</span>";
                        }
                    }
?>
                    <span style="float:right;margin-right:30%">
                        Status: <?=$Status;?> <?=$UserRead; ?>
                    </span>
                </div>
                <br/>
<?php
        }
?>
            <div class="head">
                <?=$UserString;?>
<?php if($Status != 'Resolved') { ?>
                - <a href="#quickpost" onclick="Quote('staffpm','<?=$ID?>','','<?=$UserInfo['Username']?>');">[Quote]</a>
<?php } ?>
<?php if($EditedUserID) {
          $LastUser['Class'] = get_permissions(user_info($EditedUserID)['PermissionID'])['Class'];
          $LastUser['ID'] = $EditedUserID;
      } else {
          $LastUser['Class'] = get_permissions(user_info($UserID)['PermissionID'])['Class'];
          $LastUser['ID'] = $UserID;
      }
      if ($IsStaff && (($LoggedUser['Class'] > $LastUser['Class'])||($LoggedUser['ID'] == $LastUser['ID']))) { ?>
                - <a href="#content<?=$ID?>" onclick="Edit_Form('staffpm','<?=$ID?>','<?=$Key++?>');">[Edit]</a>
<?php } ?>
                <span class="small" style="float:right">
                    <span id="bar<?=$ID?>" style="padding-right: 3px"></span>
                    <?=time_diff($SentDate, 2, true);?>
                </span>
            </div>
        <div class="box vertical_space">
            <div id="content<?=$ID?>">
                <div class="body"><?=$Text->full_format($Message, get_permissions_advtags($UserID))?></div>
<?php if ($EditedUserID) {
    $EditedUserInfo = user_info($EditedUserID); ?>
                    <div class="post_footer">
<?php     if( $IsStaff) { ?>
                    <a href="#content<?=$ID?>" onclick="LoadEdit('staffpm', <?=$ID?>, 1); return false;">&laquo;</a>
<?php     } ?>
                    <span class="editedby">Last edited by
                            <?=format_username($EditedUserID, $EditedUserInfo['Username']) ?> <?=time_diff($EditedTime,2,true,true)?>
                            </span>
                    </div>
<?php   } ?>
            </div>
        </div>
<?php
        $DB->set_query_id($StaffPMs);
    } ?>

<?php   $DB->query("SELECT ID, Message FROM staff_pm_messages WHERE ConvID=$ConvID AND IsNotes");
        if ((list($NoteID, $NotesMessage) = $DB->next_record()) && ($IsStaff || $IsFLS) && !($TargetUserID == $LoggedUser['ID'])) { ?>
            <div class="head">StaffPM Notes</div>
                <div class="box vertical_space scrollbox" style="max-height:200px;">
                    <div id="Notes" name="Notes" class="body"><?=$Text->full_format($NotesMessage, get_permissions_advtags($UserID))?></div>
                </div>
            <div align="center" style="display: none"></div>
<?php   }

    // Common responses
    if ($IsFLS && $Status != 'Resolved') {
?>
        <div id="common_answers" class="hidden">
            <div class="head"> <strong>Common Answers</strong></div>
            <div class="box pad">
                <div class="center">

                <select id="common_answers_select" onChange="UpdateMessage();" style="min-width: 130px">
                    <option id="first_common_response">Select a message</option>
<?php
        // List common responses
        $DB->query("SELECT ID, Name FROM staff_pm_responses ORDER BY Name ASC");
        while (list($ID, $Name) = $DB->next_record()) {
?>
                    <option value="<?=$ID?>"><?=$Name?></option>
<?php 		} ?>
                </select>
                <input type="button" value="Set message" onClick="SetMessage();" />
                <input type="button" value="Create new / Edit" onClick="location.href='staffpm.php?action=responses&convid=<?=$ConvID?>'" />
                <br/><br/>
                </div>
                <div id="common_answers_body" class="body box"><div class="center">Select an answer from the dropdown to view it.</div></div>
            </div>
        </div>
<?php 	}

    // Ajax assign response div
    if ($IsStaff) { ?>
            <div class="messagecontainer"><div id="ajax_message" class="hidden center messagebar"></div></div>
<?php 	}

    // Replybox and buttons
?>
        <div class="head">
                <strong>Reply</strong> <?php
                if (!$IsFLS) {
                    if ($Status == 'User Resolved') { $Status = 'Resolved'; }
                    if ($Status != 'Resolved') {
                        if ($UserInitiated) echo " &nbsp; <em>(click resolve to close the conversation if you are happy with the answer given)</em>";
                    } else {
                        echo " &nbsp; <em>(click unresolve to reopen the conversation)</em>";
                    }
                }
                ?>
        </div>
        <div class="box pad">
            <div id="preview" class="box pad hidden"></div>
            <div id="buttons" class="center">
                <form action="staffpm.php" method="post" class="staffpm" id="messageform">
                    <input type="hidden" name="action" value="takepost" />
                    <input type="hidden" name="convid" value="<?=$ConvID?>" id="convid" />
                    <input type="hidden" name="lastmessageid" value="<?=$LastMessageID?>" id="lastmessageid" />
<?php               if ($Status != 'Resolved') {    ?>
                    <?php  $Text->display_bbcode_assistant("quickpost", get_permissions_advtags($LoggedUser['ID'], $LoggedUser['CustomPermissions'])); ?>
                    <textarea id="quickpost" name="message" class="long" rows="10"></textarea>
                    <br />
<?php               }   ?>
<?php             if ($Status != 'Resolved') {    ?>
                    <input type="button" id="previewbtn" value="Preview" style="margin-right: 10px;" onclick="PreviewMessage();" />
<?php             }
                  if ($IsFLS) { ?>
                    <input type="button" value="Common answers"  style="margin-right: 10px;" onClick="$('#common_answers').toggle();" />
<?php             }
                  if ($IsStaff && ($TargetUserID != $LoggedUser['ID'])) { ?>
                    <input type="submit" name="note" value="Save as note"  style="margin-right: 10px;" />
<?php             } ?>
                    <input type="submit" value="Send message" />
                    <br><br/>
<?php
    // Assign to
    if ($IsStaff) {
        // Staff assign dropdown ?>
                    <select id="assign_to" name="assign">
                        <optgroup label="User classes">
<?php 		// FLS "class"
        $Selected = (!$AssignedToUser && $Level == 0) ? ' selected="selected"' : '';
?>
                            <option value="class_0"<?=$Selected?>>First Line Support</option>
<?php 		// Staff classes
        foreach ($ClassLevels as $Class) {
            // Create one <option> for each staff user class  >= 650
            if ($Class['Level'] >= 500) {
                $Selected = (!$AssignedToUser && ($Level == $Class['Level'])) ? ' selected="selected"' : '';
?>
                            <option value="class_<?=$Class['Level']?>"<?=$Selected?>><?=$Class['Name']?></option>
<?php 			}
        } ?>
                        </optgroup>
                        <optgroup label="Staff">
<?php 		// Staff members
        $DB->query("
            SELECT
                m.ID,
                m.Username
            FROM permissions as p
            JOIN users_main as m ON m.PermissionID=p.ID
            WHERE p.DisplayStaff='1'
            ORDER BY p.Level DESC, m.Username ASC"
        );
        while (list($ID, $Name) = $DB->next_record()) {
            // Create one <option> for each staff member
            $Selected = ($AssignedToUser == $ID) ? ' selected="selected"' : '';
?>
                            <option value="user_<?=$ID?>"<?=$Selected?>><?=$Name?></option>
<?php 		} ?>
                        </optgroup>
                        <optgroup label="First Line Support">
<?php
        // FLS users
        $DB->query("
            SELECT
                m.ID,
                m.Username
            FROM users_info as i
            JOIN users_main as m ON m.ID=i.UserID
            JOIN permissions as p ON p.ID=m.PermissionID
            WHERE p.DisplayStaff!='1' AND i.SupportFor!=''
            ORDER BY m.Username ASC
        ");
        while (list($ID, $Name) = $DB->next_record()) {
            // Create one <option> for each FLS user
            $Selected = ($AssignedToUser == $ID) ? ' selected="selected"' : '';
?>
                            <option value="user_<?=$ID?>"<?=$Selected?>><?=$Name?></option>
<?php   } ?>
                        </optgroup>
                    </select>
                    <input type="button"  style="margin-right: 10px;" onClick="Assign();" value="Assign" />
<?php 	} elseif ($IsFLS) {	// FLS assign button ?>
                    <input type="button" value="Assign to Mods"  style="margin-right: 10px;" onClick="location.href='staffpm.php?action=assign&to=staff&convid=<?=$ConvID?>';" />
                    <input type="button" value="Assign to Admins"  style="margin-right: 10px;" onClick="location.href='staffpm.php?action=assign&to=admin&convid=<?=$ConvID?>';" />
<?php 	}

    if ($Status != 'Resolved') {
                  if($Status == "Unanswered" && $IsStaff) {?>
                      <input type="button" value="Mark as read"  style="margin-right: 10px;" onClick="location.href='staffpm.php?action=mark_read&id=<?=$ConvID?>&return=1';"/>
<?php             } else if ($Status == "Open" && $IsStaff) { ?>
                      <input type="button" value="Mark as unread"  style="margin-right: 10px;" onClick="location.href='staffpm.php?action=mark_unread&id=<?=$ConvID?>&return=1';"/>
<?php             }
                  if ($UserInitiated || $IsFLS) { // as staff can now start a staff - user conversation check to see if user should be able to resolve ?>
                    <input type="button" value="Resolve" onClick="location.href='staffpm.php?action=resolve&id=<?=$ConvID?>';" />
<?php             }
    } else {
            // if ($UserInitiated || $IsFLS) {  ?>
                    <input type="button" value="Unresolve" onClick="location.href='staffpm.php?action=unresolve&id=<?=$ConvID?>&return=1';" />
<?php 			// }
    }
    if (check_perms('admin_stealth_resolve')) {
        if($StealthResolved) {
    ?>
                    <input type="button" value="Stealth Unresolve" onClick="location.href='staffpm.php?action=stealthunresolve&id=<?=$ConvID?>';" />
<?php
        } else {
    ?>
                    <input type="button" value="Stealth Resolve" onClick="location.href='staffpm.php?action=stealthresolve&id=<?=$ConvID?>';" />
<?php
        }
    }
    ?>
                </form>
                <?php
                if (check_perms('users_give_donor')) { ?>
        <br/>
        <form action="donate.php" method="post">
            <input type="hidden" name="action" value="submit_donate_manual" />
            <input type="hidden" name="convid" value="<?=$ConvID?>" />
            <input type="hidden" name="userid" value="<?=$OwnerID?>" />
            <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
            make <?=format_username($OwnerID, $OwnerInfo['Username'])?> a donor:
            &nbsp; Amount: <strong style="font-size:19px;">&euro; </strong><input type="text" name="amount" value="" /> &nbsp; &nbsp; &nbsp;
            <input type="submit" name="donategb" value="donate for -GB" />
            <input type="submit" name="donatelove" value="donate for love" />
        </form>
<?php 	}


                ?>
            </div>
        </div>

</div>
<?php
    show_footer();
} else {
    // No id
    header('Location: staffpm.php');
}
