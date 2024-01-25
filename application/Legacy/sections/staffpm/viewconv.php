<?php
// FLS+Staff
include_once(SERVER_ROOT.'/Legacy/sections/staffpm/functions.php');
if ($IsFLS) {
    list($NumMy, $NumUnanswered, $NumOpen) = get_num_staff_pms($activeUser['ID'], $activeUser['Class']);
}
$bbCode = new \Luminance\Legacy\Text;

if ($ConvID = (int) $_GET['id']) {
    // Is the user allowed to access this StaffPM
    check_access($ConvID);
    // Get conversation info
    $nextRecord = $master->db->rawQuery(
        "SELECT Subject,
                UserID,
                Level,
                AssignedToUser,
                Unread,
                Status,
                StealthResolved,
                ResolverID,
                Urgent
           FROM staff_pm_conversations
          WHERE ID = ?",
        [$ConvID]
    )->fetch(\PDO::FETCH_NUM);
    list($Subject, $TargetUserID, $Level, $AssignedToUser, $Unread, $Status, $StealthResolved, $ResolverID, $Urgent) = $nextRecord;

    if (empty($Urgent)) $Urgent = 'No';
    $IsResolved = $Status == 'Resolved' || $Status == 'User Resolved';

    // User is trying to view their own unread conversation, set it to read
    if ($TargetUserID == $activeUser['ID'] && $Unread) {
        $ExtraSet = '';
        if ($Urgent == 'Read') {
            $ExtraSet = ", Urgent='No'";
        }
        $master->db->rawQuery(
            "UPDATE staff_pm_conversations
                SET Unread = false
                    {$ExtraSet}
              WHERE ID = ?",
            [$ConvID]
        );
        // Make a note so we know when he read it
        $Message = sqltime()." - ".$activeUser['Username']." read staff message";
        make_staffpm_note($Message, $ConvID);
        // Clear cache for user
        $master->cache->deleteValue('staff_pm_new_'.$activeUser['ID']);
        $master->cache->deleteValue('staff_pm_urgent_'.$activeUser['ID']);
    }

    show_header('Staff PM', 'comments,staffpm,bbcode,jquery,jquery.cookie');

    $OwnerInfo = user_info($TargetUserID);
    //$UserInfo = $OwnerInfo;
    $UserStr = format_username($TargetUserID, $OwnerInfo['Donor'], true, $OwnerInfo['Enabled'], $OwnerInfo['PermissionID'], $OwnerInfo['Title'], true, $OwnerInfo['GroupPermissionID'], $IsFLS);
      //$OwnerID = $userID;
    if ($ResolverID) {
            $ResolverInfo = user_info($ResolverID);
            $ResolverStr = format_username($ResolverID, $ResolverInfo['Donor'], true, $ResolverInfo['Enabled'], $ResolverInfo['PermissionID'], false, true, $ResolverInfo['GroupPermissionID']);
    }
    // Get assigned
    if ($AssignedToUser == '') { // Assigned to class
            $Assigned = ($Level == 0) ? "First Line Support" : $classLevels[$Level]['Name'];
            // No + on Sysops
        if ($Assigned != 'Sysop') { $Assigned .= "+"; }
      } else {  // Assigned to user
            $AssignInfo = user_info($AssignedToUser);
        $Assigned = format_username($AssignedToUser, $AssignInfo['Donor'], true, $AssignInfo['Enabled']); //, $AssignInfo['PermissionID'], false, false, $AssignInfo['GroupPermissionID']);
      }
?>
<div class="thin">
    <h2>Staff PM - <?=display_str($Subject)?></h2>
    <div class="linkbox">
<?php  	if ($IsStaff) { ?>
        [ &nbsp;<a href="/staffpm.php?view=my">My unanswered<?=$NumMy>0?" ($NumMy)":''?></a>&nbsp; ] &nbsp;
<?php  	}
        if ($IsFLS) { ?>
        [ &nbsp;<a href="/staffpm.php?view=unanswered">All unanswered<?=$NumUnanswered>0?" ($NumUnanswered)":''?></a>&nbsp; ] &nbsp;
        [ &nbsp;<a href="/staffpm.php?view=open">Open<?=$NumOpen>0?" ($NumOpen)":''?></a>&nbsp; ] &nbsp;
        [ &nbsp;<a href="/staffpm.php?view=resolved">Resolved</a>&nbsp; ] &nbsp;
<?php       if (check_perms('admin_stealth_resolve')) { ?>
        [ &nbsp;<a href="/staffpm.php?view=stealthresolved">Stealth Resolved</a>&nbsp; ] &nbsp;
<?php       } ?>
        [ &nbsp;<a href="/staffpm.php?action=responses">Common Answers</a>&nbsp; ] &nbsp;
<?php       if (check_perms('admin_staffpm_stats')) { ?>
        [ &nbsp;<a href="/staffpm.php?action=stats">StaffPM Stats</a>&nbsp; ]
<?php       }
        } else { ?>
        [ &nbsp;<a href="/staffpm.php">Back to inbox</a>&nbsp; ]
<?php   } ?>

        <br />
        <br />
    </div>

<?php
    // Fetch last message ID
    $LastMessageID = $master->db->rawQuery(
        "SELECT ID
           FROM staff_pm_messages
          WHERE ConvID = ?
       ORDER BY ID DESC
          LIMIT 1",
        [$ConvID]
    )->fetchColumn();
    // Get messages
    $StaffPMs = $master->db->rawQuery(
        "SELECT ID,
                UserID,
                EditedUserID,
                EditedTime,
                SentDate,
                Message
           FROM staff_pm_messages
          WHERE ConvID=$ConvID
            AND NOT IsNotes
       ORDER BY SentDate"
    )->fetchAll(\PDO::FETCH_NUM);
    $Key = 0;
    foreach ($StaffPMs as $staffPM) {
    list($ID, $userID, $EditedUserID, $EditedTime, $SentDate, $Message) = $staffPM;
        // Set user string
        $UserInfo = user_info($userID);
        if ($userID == $TargetUserID) {
            // User, use prepared string
            $UserString = $UserStr;
        } else {
            // Staff/FLS
            $UserString = format_username($userID, $UserInfo['Donor'], true, $UserInfo['Enabled'], $UserInfo['PermissionID'], $UserInfo['Title'], true, $UserInfo['GroupPermissionID'], $IsFLS);
        }
        $ViewerIsTarget = $TargetUserID == $activeUser['ID'];
            // determine if conversation was started by user or not (checks first record for userID)
        if (!isset($UserInitiated)) {
                $SenderID = $userID;
                $UserInitiated = $SenderID == $TargetUserID;
?>
                <div class="head">
                    Status: <?=$Status; if (($ResolverInfo ?? false) && $IsResolved) echo " by ".$ResolverInfo['Username']; ?>
                    <span style="float:right"><em>Assigned to: <?=$Assigned?></em></span>
<?php                     ?>
                </div>
                <div class="box pad vertical_space colhead">
<?php               $UrgentStr = '';
                    $UserRead  = '';
                    if (!$ViewerIsTarget) {
                        if ($Unread) {
                            $UserRead="<span title='The user has not yet read this message'>(Unread)</span>";
                        } else {
                            $UserRead="<span title='The user has read this message'>(Read)</span>";
                        }
                        if ($Urgent != 'No') {
                            $UrgentStr = '&nbsp;&nbsp;&nbsp;&nbsp;<span title="This user will be forced to do this action" class="red">User must '.$Urgent.'</span>';
                        }
                    } else {
                        if ($Urgent != 'No') {
                            $UrgentStr = '&nbsp;&nbsp;&nbsp;&nbsp;<span class="red">User must '.$Urgent.'</span>';
                        }
                    }
                    $SenderString = format_username($userID, $UserInfo['Donor'], true, $UserInfo['Enabled'], $UserInfo['PermissionID'], false, true, $UserInfo['GroupPermissionID'], $IsFLS);
?>
                    <span style="float:left;">
                        Sent to <?=$UserInitiated?'<strong>Staff</strong>':$UserStr;?>
                    </span>
                    <span style="margin-left:30%;">
                        Status: <?=$Status;?> <?=$UserRead; ?> <?=$UrgentStr?>
                    </span>
                    <span style="float:right;">
                        Sent by <?=$SenderString;?>
                    </span>
                </div>
<?php
                if ($ViewerIsTarget && $Urgent != 'No') {
                    echo '<div class="center pad urgentbar">'.($Urgent=='Read'?'Thank-you for reading':'You must respond to').' this message</div>';
                }
?>
                <br/>
<?php
        }
?>
            <div class="head">
                <span>
                <?=$UserString;?>
<?php
        if ($Status != 'Resolved') {
?>
                - <a href="#quickpost" onclick="Quote('<?=$ID?>', '', '<?=$UserInfo['Username']?>');">[Quote]</a>
<?php
        }

        if ($EditedUserID) {
            $LastUser['Class'] = get_permissions(user_info($EditedUserID)['PermissionID'])['Class'] ?? null;
            $LastUser['ID'] = $EditedUserID;
        } else {
            $LastUser['Class'] = get_permissions(user_info($userID)['PermissionID'])['Class'] ?? null;
            $LastUser['ID'] = $userID;
        }
        if (!$ViewerIsTarget && $IsStaff && (($activeUser['Class'] > $LastUser['Class'])||($activeUser['ID'] == $LastUser['ID']))) {
?>
                - <a href="#content<?=$ID?>" onclick="Edit_Form('<?=$ID?>');">[Edit]</a>
<?php
        }
?>
                </span>
                <span class="small" style="float:right">
                    <span id="bar<?=$ID?>" style="padding-right: 3px"></span>
                    <?=time_diff($SentDate, 2, true);?>
                </span>
            </div>
            <div class="box vertical_space">
                <div id="content<?=$ID?>">
                    <div class="post_content"><?=$bbCode->full_format($Message, !$userID || get_permissions_advtags($userID))?></div>
<?php
        if ($EditedUserID) {
            $EditedUserInfo = user_info($EditedUserID); ?>
                    <div class="post_footer">
<?php
            if ($IsStaff) { ?>
                        <a href="#content<?=$ID?>" onclick="LoadEdit(<?=$ID?>, 1); return false;">&laquo;</a>
<?php
            } ?>
                        <span class="editedby">Last edited by
                            <?=format_username($EditedUserID) ?> <?=time_diff($EditedTime,2,true,true)?>
                        </span>
                    </div>
<?php   } ?>
                </div>
            </div>
<?php
    } ?>

<?php
        $nextRecord = $master->db->rawQuery(
            "SELECT ID,
                    Message
               FROM staff_pm_messages
              WHERE ConvID = ?
                AND IsNotes",
            [$ConvID]
        )->fetch(\PDO::FETCH_NUM);
        if (!($nextRecord === false) && ($IsStaff || $IsFLS) && !$ViewerIsTarget) {
           list($NoteID, $NotesMessage) = $nextRecord;
?>
            <div class="head">StaffPM Notes</div>
                <div class="box vertical_space scrollbox" style="max-height:200px;">
                    <div id="Notes" name="Notes" class="body"><?=$bbCode->full_format($NotesMessage, true)?></div>
                </div>
            <div align="center" style="display: none"></div>
<?php   }

    // Common responses
    if ($SenderID && !$ViewerIsTarget && $IsFLS && $Status != 'Resolved') {
?>
        <div id="common_answers" class="hidden">
            <div class="head"> <strong>Common Answers</strong></div>
            <div class="box pad">
                <div class="center">

                <select id="common_answers_select" onChange="UpdateMessage();" style="min-width: 130px">
                    <option id="first_common_response">Select a message</option>
<?php
            // List common responses
            $responses = $master->db->rawQuery(
                "SELECT ID,
                        Name
                   FROM staff_pm_responses
               ORDER BY Name ASC"
            )->fetchAll(\PDO::FETCH_OBJ);
            foreach ($responses as $response) {
?>
                    <option value="<?= $response->ID ?>"><?= $response->Name ?></option>
<?php       } ?>
                </select>
                <input type="button" value="Set message" onClick="SetMessage();" />
                <input type="button" value="Create new / Edit" onClick="location.href='/staffpm.php?action=responses&amp;convid=<?=$ConvID?>'" />
                <br/><br/>
                </div>
                <div id="common_answers_body" class="body box"><div class="center">Select an answer from the dropdown to view it.</div></div>
            </div>
        </div>
<?php 	}

        // Ajax assign response div
        if ($IsFLS) { ?>
            <div class="messagecontainer"><div id="ajax_message" class="hidden center messagebar"></div></div>
<?php 	}

        if (!$master->repos->restrictions->isRestricted($activeUser['ID'], Luminance\Entities\Restriction::STAFFPM) || !$IsResolved) {
        // Replybox and buttons
?>
        <div class="head">
<?php
        if (!$SenderID) {
                echo 'notes';
        } else {
?>
            <strong>Reply</strong>
<?php
            if ($ViewerIsTarget) {
                if (!$IsResolved) {
                    if ($UserInitiated) echo " &nbsp; <em>(click resolve to close the conversation if you are happy with the answer given)</em>";
                } else {
                    echo " &nbsp; <em>(click unresolve to reopen the conversation)</em>";
                }
            }
        }
?>
        </div>
        <div class="box pad">
            <div id="preview" class="box pad hidden"></div>
            <div id="buttons" class="center">
                <form action="staffpm.php" method="post" class="staffpm" id="messageform">
                    <input type="hidden" name="action" value="takepost" />
                    <input type="hidden" name="resolved" value="<?=$IsResolved?>" id="resolved" />
                    <input type="hidden" name="convid" value="<?=$ConvID?>" id="convid" />
                    <input type="hidden" name="unread" value="<?=$Unread?>" id="unread" />
                    <input type="hidden" name="lastmessageid" value="<?=$LastMessageID?>" id="lastmessageid" />
<?php               if ($Status != 'Resolved' && ($SenderID || $IsStaff)) {
                        $bbCode->display_bbcode_assistant("quickpost", get_permissions_advtags($activeUser['ID'], $activeUser['CustomPermissions'])); ?>
                        <textarea id="quickpost" name="message" class="long" rows="10"></textarea>
                        <br />
                        <input type="button" id="previewbtn" value="Preview" style="margin-right: 10px;" onclick="PreviewMessage();" />
<?php               }
                    if ($SenderID && !$ViewerIsTarget && $IsFLS) { ?>
                        <input type="button" value="Common answers"  style="margin-right: 10px;" onClick="$('#common_answers').toggle();" />
<?php               }
                    if (!$ViewerIsTarget && $IsStaff) { ?>
                        <input type="submit" name="note" value="Save as note"  style="margin-right: 10px;" />
<?php               }
                    if ($SenderID) {  ?>
                        <input type="submit" name="send" value="Send message" />
<?php               }
                    if ($SenderID || $IsStaff) {  ?>
                    <br/><br/>
<?php               }
        // Assign to
        if (!$ViewerIsTarget && $IsStaff) {
        // Staff assign dropdown

            print_staff_assign_select($AssignedToUser, $Level);
?>

<?php
        } elseif (!$ViewerIsTarget && $IsFLS) {
                    // FLS assign button
?>
                    <input type="button" value="Assign to Mods"  style="margin-right: 10px;" onClick="location.href='/staffpm.php?action=assign&amp;to=staff&amp;convid=<?=$ConvID?>';" />
                    <input type="button" value="Assign to Admins"  style="margin-right: 10px;" onClick="location.href='/staffpm.php?action=assign&amp;to=admin&amp;convid=<?=$ConvID?>';" />
<?php
        }

        if ($SenderID && !$ViewerIsTarget && $IsStaff) {
            if ($Status == "Unanswered") {?>
                <input type="button" value="Mark as Read" title="Mark as Read (sets status to Open)" style="margin-right: 10px;" onClick="location.href='/staffpm.php?action=mark_read&amp;id=<?=$ConvID?>&amp;return=1';"/>
<?php       } else if ($Status == "Open") { ?>
                <input type="button" value="Mark as Unread" title="Mark as Unread (sets status to Unanswered)" style="margin-right: 10px;" onClick="location.href='/staffpm.php?action=mark_unread&amp;id=<?=$ConvID?>&amp;return=1';"/>
<?php       }
        }

        if ($Status != 'Resolved') {
            // as staff can now start a staff-user conversation check to see if user should be able to resolve
            if (($Urgent=='No' && $UserInitiated && !$IsResolved)  || (!$ViewerIsTarget && $IsFLS)) {
?>
                <input type="submit" name="resolve" value="Resolve">
<?php       }
        }
        if ($IsResolved) {
?>
                    <input type="button" value="Unresolve" onClick="location.href='/staffpm.php?action=unresolve&amp;id=<?=$ConvID?>&amp;return=1';" />
<?php
        }
        if ($SenderID && check_perms('admin_stealth_resolve')) {
            if ($StealthResolved) {
?>
                    <input type="button" value="Stealth Unresolve" onClick="location.href='/staffpm.php?action=stealthunresolve&amp;id=<?=$ConvID?>';" />
<?php
            } else {
?>
                    <input type="button" value="Stealth Resolve" onClick="location.href='/staffpm.php?action=stealthresolve&amp;id=<?=$ConvID?>';" />
<?php
            }
        }

        if ($SenderID && $IsStaff) {
?>

                    <select id="urgency" name="urgency" style="margin-left: 60px;">
                        <option value="No" selected="selected">No</option>
                        <option value="Read">Read</option>
                        <option value="Respond">Respond</option>
                    </select>
                    <input type="button" onClick="AssignUrgency();" value="Force Response" />
<?php
        }
?>
                </form>
<?php
        if ($SenderID && check_perms('users_give_donor')) {
?>
                <br/>
                <form action="donate.php" method="post">
                    <input type="hidden" name="action" value="submit_donate_manual" />
                    <input type="hidden" name="convid" value="<?=$ConvID?>" />
                    <input type="hidden" name="userid" value="<?=$TargetUserID?>" />
                    <input type="hidden" name="auth" value="<?=$activeUser['AuthKey']?>" />
                    make <?=format_username($TargetUserID)?> a donor:
                    &nbsp; Amount: <strong style="font-size:19px;">&euro; </strong><input type="text" name="amount" value="" /> &nbsp; &nbsp; &nbsp;
                    <input type="submit" name="donategb" value="donate for -GB" />
                    <input type="submit" name="donatelove" value="donate for love" />
                </form>
<?php
        }
?>
            </div>
        </div>
        <?php } ?>
</div>
<?php
    show_footer();
} else {
    // No id
    header('Location: staffpm.php');
}
