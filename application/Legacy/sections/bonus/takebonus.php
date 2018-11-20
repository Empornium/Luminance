<?php
enforce_login();
authorize();

include_once(SERVER_ROOT.'/Legacy/sections/bonus/functions.php');

$P=array();
$P=db_array($_POST);

$ItemID = empty($P['itemid']) ? '' : $P['itemid'];
if (!is_number($ItemID)) {
    error(0);
}
$UserID = empty($P['userid']) ? '' : $P['userid'];
if (!is_number($UserID)) {
    error(0);
}
if ($UserID != $LoggedUser['ID']) {
    error(0);
}

$ShopItem = get_shop_item($ItemID);

if (!empty($ShopItem) && is_array($ShopItem)) {
    list($ItemID, $Title, $Description, $Action, $Value, $Cost) = $ShopItem;

    $OtherID = true;

    // if we need to have otherID get it from passed username
    $forother = strpos($Action, 'give');
    if ($forother!==false) {
        $Othername = empty($P['othername']) ? '' : $P['othername'];
        if ($Othername) {
            $DB->query("SELECT ID From users_main WHERE Username='$Othername'");
            if (($DB->record_count()) > 0) {
                list($OtherID) = $DB->next_record();
                if (blockedGift($OtherID, $LoggedUser['ID'])) {
                    $ResultMessage = "You cannot donate to this user";
                    header("Location: bonus.php?action=msg&result=" .urlencode($ResultMessage));
                    die();
                }
                //$OtherUserStats = get_user_stats($OtherID);
            } else {
                $OtherID=false;
                $ResultMessage = "Could not find user $Othername";
            }
        } else {
            $OtherID = false; // user cancelled js prompt so othername is not set
        }
    }

    $DB->query("SELECT Credits From users_main WHERE ID='$UserID'");
    list($Credits) = $DB->next_record();

    // again lets not trust the check on the previous page as to whether they can afford it
    if ($OtherID && ($Cost <= $Credits)) {
        $UpdateSet = array();
        $UpdateSetOther = array();

        switch ($Action) {  // atm hardcoded in db:  givecredits, givegb, gb, slot, title, badge
            case 'badge':
                $UserBadgeIDs = get_user_shop_badges_ids($UserID);
                if (in_array($Value, $UserBadgeIDs)) {
                    $ResultMessage='Something bad happened (duplicate badge insertion)';
                    break;
                }

                $DB->query("SELECT Badge FROM badges WHERE ID = ".intval($Value));
                list($BadgeSet) = $DB->next_record();

                $DB->query("SELECT MAX(b.Rank) FROM badges AS b LEFT JOIN users_badges AS ub ON ub.BadgeID = b.ID WHERE ub.UserID = $UserID AND Badge = '".db_string($BadgeSet)."'");
                list($UserRank) = $DB->next_record();

                $DB->query("SELECT ID, Rank FROM badges WHERE Badge = '".db_string($BadgeSet)."' ORDER BY ID");
                $Badges = $DB->to_array(false, MYSQLI_ASSOC);

                $CurBadgeKey   = array_search($Value, array_column($Badges, 'ID'));
                $PreviousBadge = $Badges[$CurBadgeKey > 0 ? $CurBadgeKey - 1 : 0];

                // If it's not the first badge
                // and if the user doesn't own the previous one, it's bad
                if ($Value != $Badges[0]['ID'] && $UserRank != $PreviousBadge['Rank']) {
                    $ResultMessage = 'You must own the previous badges before getting this one.';
                    break;
                }

                $Summary = sqltime()." | -$Cost credits | ".ucfirst("you bought a $Title badge.");
                $UpdateSet[]="i.BonusLog=CONCAT_WS( '\n', '$Summary', i.BonusLog)";

                $DB->query("INSERT INTO users_badges (UserID, BadgeID, Description)
                                  VALUES ( '$UserID', '$Value', '$Description')");

                $DB->query("SELECT Badge, Rank FROM badges WHERE ID='$Value'");
                if ($DB->record_count() == 0) {
                    error(0);
                }
                list($Badge, $Rank) = $DB->next_record();

                // remove lower ranked badges of same badge set
                $DB->query("DELETE ub
                          FROM users_badges AS ub
                     LEFT JOIN badges AS b ON b.ID=ub.BadgeID
                         WHERE ub.UserID = '$UserID'
                           AND b.Badge='$Badge' AND b.Rank<$Rank");

                $Cache->delete_value('user_badges_ids_'.$UserID);
                $Cache->delete_value('user_badges_'.$UserID);
                $Cache->delete_value('user_badges_'.$UserID.'_limit');
                $UpdateSet[]="m.Credits=(m.Credits-'$Cost')";
                $ResultMessage=$Summary;

                break;

            case 'givecredits':
                $Summary = sqltime()." | +".number_format($Value)." credits | ".ucfirst("you received a gift of ".number_format($Value)." credits from {$LoggedUser['Username']}");
                $UpdateSetOther[]="i.BonusLog=CONCAT_WS( '\n', '$Summary', i.BonusLog)";
                $UpdateSetOther[]="m.Credits=(m.Credits+'$Value')";

                $Summary = sqltime()." | -".number_format($Cost)." credits | ".ucfirst("you gave a gift of ".number_format($Value)." credits to {$P['othername']}");
                $UpdateSet[]="i.BonusLog=CONCAT_WS( '\n', '$Summary', i.BonusLog)";
                $UpdateSet[]="m.Credits=(m.Credits-'$Cost')";
                $ResultMessage=$Summary;

                $AddMessage = isset($P['message']) && $P['message']?"[br][br]Message from sender:[br][br]{$_POST['message']}":'';
                send_pm(
                    $OtherID,
                    0,
                    db_string("Bonus Shop - You received a gift of credits"),
                    db_string("[br]You received a gift of ".number_format($Value)." credits from [user]{$LoggedUser['ID']}[/user]{$AddMessage}")
                );

                break;

            case 'gb':
                $ValueBytes = get_bytes($Value.'gb');
                if ($LoggedUser['BytesDownloaded'] <= 0) {
                    $ResultMessage= "You have no download to deduct from!";
                } else {
                    $Summary = sqltime()." | -$Cost credits | ".ucfirst("you bought -$Value gb.");
                    if ($LoggedUser['BytesDownloaded'] < $ValueBytes) {
                        $Summary .= " | NOTE: Could only remove ". get_size($LoggedUser['BytesDownloaded']);
                    }
                    $UpdateSet[]="i.BonusLog=CONCAT_WS( '\n', '$Summary', i.BonusLog)";

                    $UpdateSet[]="m.Downloaded=(m.Downloaded-'$ValueBytes')";
                    $UpdateSet[]="m.Credits=(m.Credits-'$Cost')";
                    $ResultMessage=$Summary;
                }
                break;

            case 'givegb':  // no test if user had download to remove as this could violate privacy settings
                $ValueBytes = get_bytes($Value.'gb');

                $Summary = sqltime()." | ".ucfirst("you received a gift of -$Value gb from {$LoggedUser['Username']}.");
                $UpdateSetOther[]="i.BonusLog=CONCAT_WS( '\n', '$Summary', i.BonusLog)";

                $Summary = sqltime()." | -$Cost credits | ".ucfirst("you gave a gift of -$Value gb to {$P['othername']}.");
                $UpdateSet[]="i.BonusLog=CONCAT_WS( '\n', '$Summary', i.BonusLog)";
                $UpdateSet[]="m.Credits=(m.Credits-'$Cost')";

                $AddMessage = isset($P['message']) && $P['message']?"[br][br]Message from sender:[br][br]{$_POST['message']}":'';
                send_pm(
                    $OtherID,
                    0,
                    db_string("Bonus Shop - You received a gift of -gb"),
                    db_string("[br]You received a gift of -".number_format($Value)." gb from [user]{$LoggedUser['ID']}[/user]{$AddMessage}")
                );

                $UpdateSetOther[]="m.Downloaded=(m.Downloaded-'$ValueBytes')";
                $ResultMessage=$Summary;

                break;

            case 'slot':
                $Summary = sqltime()." | -$Cost credits | ".ucfirst("you bought $Value slot".($Value>1?'s':'').".");
                $UpdateSet[]="i.BonusLog=CONCAT_WS( '\n', '$Summary', i.BonusLog)";
                $UpdateSet[]="m.FLTokens=(m.FLTokens+'$Value')";
                $UpdateSet[]="m.Credits=(m.Credits-'$Cost')";
                $ResultMessage=$Summary;
                break;

            case 'pfl':
                $Summary = sqltime()." | -$Cost credits | ".ucfirst("you bought $Value hour".($Value>1?'s':'')." of personal freeleech.");
                $UpdateSet[]="i.BonusLog=CONCAT_WS( '\n', '$Summary', i.BonusLog)";
                $UpdateSet[]="m.Credits=(m.Credits-'$Cost')";

                $personal_freeleech = $LoggedUser['personal_freeleech'];

                // The user already have personal freeleech time, add to it.
                if ($personal_freeleech >= sqltime()) {
                    $personal_freeleech = date('Y-m-d H:i:s', strtotime($personal_freeleech) + (60 * 60 * $Value));
                // No current freeleech time.
                } else {
                    $personal_freeleech = time_plus(60 * 60 * $Value);
                }

                $UpdateSet[]="personal_freeleech='$personal_freeleech'";
                $master->tracker->setPersonalFreeleech($LoggedUser['torrent_pass'], strtotime($personal_freeleech));

                $ResultMessage=$Summary;
                break;

            case 'title':
                //get the unescaped title for len test
                $NewTitle = empty($_POST['title']) ? '' : $_POST['title'];
                if (!$NewTitle) {
                    $ResultMessage = "Title was not set";
                } else {
                    $tlen = mb_strlen($NewTitle, "UTF-8");
                    if ($tlen > 32) {
                        $ResultMessage = "Title was too long ($tlen characters, max=32)";
                    } else {
                        $NewTitle = db_string(display_str($NewTitle));
                        $Summary = sqltime()." | -$Cost credits | ".ucfirst("you bought a new custom title ''$NewTitle''.");
                        $UpdateSet[]="i.BonusLog=CONCAT_WS( '\n', '$Summary', i.BonusLog)";
                        $UpdateSet[]="m.Title='$NewTitle'";
                        $UpdateSet[]="m.Credits=(m.Credits-'$Cost')";
                        $ResultMessage=$Summary;
                    }
                }
                break;

            case 'ufl':
                $GroupID = empty($_POST['torrentid']) ? '' : (int) $_POST['torrentid'];

                if (!$GroupID) {
                    $ResultMessage = "TorrentID was not set";
                } else {
                    $DB->query("SELECT UserID, Name, FreeTorrent, Size FROM torrents AS t JOIN torrents_group AS tg ON t.GroupID=tg.ID WHERE GroupID='$GroupID'");
                    if ($DB->record_count()==0) {
                        $ResultMessage = "Could not find any torrent with ID=$GroupID";
                    } else {
                        list($OwnerID, $TName, $FreeTorrent, $Sizebytes) = $DB->next_record();
                        if ($OwnerID != $LoggedUser['ID']) {
                            $ResultMessage = "You are not the owner of torrent with ID=$GroupID - only the uploader can buy Universal Freeleech for their torrent";
                        } elseif ($FreeTorrent == '1') {
                            $ResultMessage = "Torrent $TName is already freeleech!";
                        } elseif ($Sizebytes < get_bytes($Value.'gb')) {
                            $ResultMessage = "Torrent $TName (" . get_size($Sizebytes, 2). ") is too small for a > $Value gb freeleech!";
                        } else {
                            // make torrent FL
                            freeleech_groups($GroupID, 1, true);

                            $Summary = sqltime()." | -$Cost credits | ".ucfirst("you bought a universal freeleech ($Value gb+) for torrent [torrent]{$GroupID}[/torrent]");
                            $UpdateSet[]="i.BonusLog=CONCAT_WS( '\n', '$Summary', i.BonusLog)";
                            $UpdateSet[]="m.Credits=(m.Credits-'$Cost')";
                            $ResultMessage= sqltime()." | -$Cost credits | ".ucfirst("you bought a universal freeleech ($Value gb+) for torrent $TName");
                            ;
                        }
                    }
                }
                break;

            default:
                $Cost = 0;
                $ResultMessage ='No valid action!';
                break;
        }

        if ($UpdateSetOther) {
            $SET = implode(', ', $UpdateSetOther);
            $sql = "UPDATE users_main AS m JOIN users_info AS i ON m.ID=i.UserID SET $SET WHERE m.ID='$OtherID'";
            $DB->query($sql);
            $master->repos->users->uncache($OtherID);
        }

        if ($UpdateSet) {
            $SET = implode(', ', $UpdateSet);
            $sql = "UPDATE users_main AS m JOIN users_info AS i ON m.ID=i.UserID SET $SET WHERE m.ID='$UserID'";
            $DB->query($sql);
            $master->repos->users->uncache($UserID);
        }
    }
}

// Go back
$Msg ='';
if (isset($_REQUEST['retu']) && is_number($_REQUEST['retu'])) {
    $Msg .= "&retu=".$_REQUEST['retu'];
} elseif (isset($_REQUEST['rett']) && is_number($_REQUEST['rett'])) {
    $Msg .= "&rett=".$_REQUEST['rett'];
}
header("Location: bonus.php?action=msg&". (!empty($ResultMessage) ? "result=" .urlencode($ResultMessage):"").$Msg);
