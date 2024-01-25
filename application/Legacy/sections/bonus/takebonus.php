<?php
enforce_login();
authorize();

include_once(SERVER_ROOT.'/Legacy/sections/bonus/functions.php');
$wallet = $master->repos->userWallets->get('UserID = ?', [$activeUser['ID']]);
$P=[];
$P=$_POST;

$ItemID = empty($P['itemid']) ? '' : $P['itemid'];
if (!is_integer_string($ItemID))  error(0);
$userID = empty($P['userid']) ? '' : $P['userid'];
if (!is_integer_string($userID))  error(0);
if ($userID != $activeUser['ID'])  error(0);

$ShopItem = get_shop_item($ItemID);

if (!empty($ShopItem) && is_array($ShopItem)) {

    list($ItemID, $Title, $Description, $Action, $Value, $Cost) = $ShopItem;

    $OtherID = true;

    // if we need to have otherID get it from passed username
    $forother = strpos($Action, 'give');
    if ($forother!==false) {
        $Othername = empty($P['othername']) ? '' : $P['othername'];
        if ($Othername) {

            $OtherID = $master->db->rawQuery(
                "SELECT ID
                   FROM users
                  WHERE Username = ?",
                [$Othername]
            )->fetchColumn();
            if (!($OtherID === false)) {
                if (blockedGift($OtherID, $activeUser['ID'])) {
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

    // again lets not trust the check on the previous page as to whether they can afford it
    if ($OtherID && ($Cost <= $wallet->Balance)) {

        $UpdateSet = [];
        $UpdateData = [];
        $UpdateSetOther = [];
        $UpdateDataOther = [];

        $user = $master->repos->users->load($userID);
        $wallet = $user->wallet;

        $otherUser = $master->repos->users->load($OtherID);
        $otherWallet = $otherUser->wallet;

        switch($Action) {  // atm hardcoded in db:  givecredits, givegb, gb, slot, title, badge
            case 'badge' :

                $UserBadgeIDs = get_user_shop_badges_ids($userID);
                if ( in_array($Value, $UserBadgeIDs)) {
                    $ResultMessage='Something bad happened (duplicate badge insertion)';
                    break;
                }

                $BadgeSet = $master->db->rawQuery(
                    "SELECT Badge
                       FROM badges
                      WHERE ID = ?",
                    [$Value]
                )->fetchColumn();

                $UserRank = $master->db->rawQuery(
                    "SELECT MAX(b.Rank)
                       FROM badges AS b
                  LEFT JOIN users_badges AS ub
                         ON ub.BadgeID = b.ID
                      WHERE ub.UserID = ?
                        AND Badge = ?",
                    [$userID, $BadgeSet]
                )->fetchColumn();

                $Badges = $master->db->rawQuery(
                    "SELECT ID,
                            Rank
                       FROM badges
                      WHERE Badge = ?
                   ORDER BY ID",
                    [$BadgeSet]
                )->fetchAll(\PDO::FETCH_BOTH);

                $CurBadgeKey   = array_search($Value, array_column($Badges, 'ID'));
                $PreviousBadge = $Badges[$CurBadgeKey > 0 ? $CurBadgeKey - 1 : 0];

                // If it's not the first badge
                // and if the user doesn't own the previous one, it's bad
                if ($Value != $Badges[0]['ID'] && $UserRank != $PreviousBadge['Rank']) {
                    $ResultMessage = 'You must own the previous badges before getting this one.';
                    break;
                }

                $Summary = " | -$Cost credits | ".ucfirst("you bought a $Title badge.");
                $wallet->adjustBalance(-$Cost);
                $wallet->addLog($Summary);

                $master->db->rawQuery(
                    "INSERT INTO users_badges (UserID, BadgeID, Description)
                          VALUES (?, ?, ?)",
                    [$userID, $Value, $Description]
                );

                list($Badge, $Rank) = $master->db->rawQuery(
                    "SELECT Badge,
                            Rank
                       FROM badges
                      WHERE ID = ?",
                    [$Value]
                )->fetch(\PDO::FETCH_NUM);
                if ($master->db->foundRows() == 0) error(0);

                // remove lower ranked badges of same badge set
                $master->db->rawQuery(
                    "DELETE ub
                       FROM users_badges AS ub
                  LEFT JOIN badges AS b ON b.ID=ub.BadgeID
                      WHERE ub.UserID = ?
                        AND b.Badge = ?
                        AND b.Rank < ?",
                    [$userID, $Badge, $Rank]
                );

                $master->cache->deleteValue('user_badges_ids_'.$userID);
                $master->cache->deleteValue('user_badges_'.$userID);
                $master->cache->deleteValue('user_badges_'.$userID.'_limit');
                $ResultMessage=sqltime().$Summary;

                break;

            case 'givecredits':
                $GiftFrom = isset($_POST['anon_gift']) ? "Anonymous" : $activeUser['Username'];
                $GiftFromTag = isset($_POST['anon_gift']) ? "Anonymous" : "[user]{$activeUser['ID']}[/user]";

                $Summary = " | +".number_format ($Value)." credits | ".ucfirst("you received a gift of ".number_format ($Value)." credits from {$GiftFrom}");
                $otherWallet->adjustBalance($Value);
                $otherWallet->addLog($Summary);

                $Summary = " | -".number_format ($Cost)." credits | ".ucfirst("you gave a gift of ".number_format ($Value)." credits to {$P['othername']}");
                $wallet->adjustBalance(-$Cost);
                $wallet->addLog($Summary);
                $ResultMessage=sqltime().$Summary;

                $AddMessage = isset($P['message']) && $P['message']?"[br][br]Message from sender:[br][br]{$_POST['message']}":'';
                send_pm($OtherID, 0, "Bonus Shop - You received a gift of credits",
                        "[br]You received a gift of ".number_format ($Value)." credits from {$GiftFromTag}{$AddMessage}");

                break;

            case 'gb':
                $ValueBytes = get_bytes($Value.'gb');
                if ($activeUser['BytesDownloaded'] <= 0) {
                    $ResultMessage= "You have no download to deduct from!";
                } else {

                    $Summary = " | -$Cost credits | ".ucfirst("you bought -$Value gb.");
                    if ($user->legacy['Downloaded'] < $ValueBytes) {
                        $Summary .= " | NOTE: Could only remove ". get_size($user->legacy['Downloaded']);
                        $ValueBytes = $user->legacy['Downloaded'];
                    }
                    $wallet->adjustBalance(-$Cost);
                    $wallet->addLog($Summary);

                    $UpdateSet[]="m.Downloaded = (m.Downloaded - ?)";
                    $UpdateData[] = $ValueBytes;
                    $UpdateSet[]="m.Credits = (m.Credits - ?)";
                    $UpdateData[] = $Cost;
                    $ResultMessage=sqltime().$Summary;
                }
                break;

            case 'givegb':  // no test if user had download to remove as this could violate privacy settings
                $ValueBytes = get_bytes($Value.'gb');
                $GiftFrom = isset($_POST['anon_gift']) ? "Anonymous" : $activeUser['Username'];
                $GiftFromTag = isset($_POST['anon_gift']) ? "Anonymous" : "[user]{$activeUser['ID']}[/user]";

                $Summary = " | ".ucfirst("you received a gift of -$Value gb from {$GiftFrom}.");
                $otherWallet->addLog($Summary);

                $Summary = " | -$Cost credits | ".ucfirst("you gave a gift of -$Value gb to {$P['othername']}.");
                $wallet->adjustBalance(-$Cost);
                $wallet->addLog($Summary);

                $AddMessage = isset($P['message']) && $P['message']?"[br][br]Message from sender:[br][br]{$_POST['message']}":'';
                send_pm($OtherID, 0, "Bonus Shop - You received a gift of -gb",
                     "[br]You received a gift of -".number_format ($Value)." gb from {$GiftFromTag}{$AddMessage}");

                if ($otherUser->legacy['Downloaded'] < $ValueBytes) {
                    $ValueBytes = $otherUser->legacy['Downloaded'];
                }

                $UpdateSetOther[]="m.Downloaded = (m.Downloaded - ?)";
                $UpdateDataOther[] = $ValueBytes;
                $ResultMessage=sqltime().$Summary;
                break;

            case 'slot':

                $Summary = " | -$Cost credits | ".ucfirst("you bought $Value slot".($Value>1?'s':'').".");
                $wallet->adjustBalance(-$Cost);
                $wallet->addLog($Summary);
                $UpdateSet[]="m.FLTokens = (m.FLTokens + ?)";
                $UpdateData[] = $Value;
                $ResultMessage=sqltime().$Summary;
                break;

            case 'pfl':

                $Summary = " | -$Cost credits | ".ucfirst("you bought $Value hour".($Value>1?'s':'')." of personal freeleech.");
                $wallet->adjustBalance(-$Cost);
                $wallet->addLog($Summary);

                $personal_freeleech = $activeUser['personal_freeleech'];

                // The user already have personal freeleech time, add to it.
                if ($personal_freeleech >= sqltime()) {
                    $personal_freeleech = date('Y-m-d H:i:s', strtotime($personal_freeleech) + (60 * 60 * $Value));
                // No current freeleech time.
                } else {
                    $personal_freeleech = time_plus(60 * 60 * $Value);
                }

                $UpdateSet[]="personal_freeleech = ?";
                $UpdateData[] = $personal_freeleech;
                $master->tracker->setPersonalFreeleech($activeUser['torrent_pass'], strtotime($personal_freeleech));

                $ResultMessage=sqltime().$Summary;
                break;

            case 'invite':
                if (check_perms('site_purchase_invites')) {
                    $Invites = $master->db->rawQuery(
                        "SELECT Invites
                          FROM users_main
                         WHERE ID = ?",
                        [$userID]
                    )->fetchColumn();
                    if ($Invites < 4) {
                        $master->db->rawQuery(
                            "UPDATE users_main
                                SET Invites = Invites + 1
                              WHERE ID = ?",
                            [$userID]
                        );
                        $Summary = " | -$Cost credits | ".ucfirst("you bought an invite to share the love");
                        $wallet->adjustBalance(-$Cost);
                        $wallet->addLog($Summary);
                        $ResultMessage=$Summary;
                        $Invites = $master->db->rawQuery(
                            "SELECT Invites
                               FROM users_main
                              WHERE ID = ?",
                            [$userID]
                        )->fetchColumn();
                        $comment = sqltime()." - Number of invites changed to {$Invites} - Invite purchased from the bonus shop for {$Cost} points";
                        $StaffNote = $master->db->rawQuery(
                            "UPDATE users_info
                                SET AdminComment = CONCAT_WS(CHAR(10 using utf8), ?, AdminComment)
                              WHERE UserID = ?",
                            [$comment, $userID]
                        );
                        $master->irker->announceDebug('Invite purchased from bonus shop by ' . $activeUser['Username'] . ". Current Invites: " . $Invites);
                        //Send a staff PM too in case irker does not want to comply
                        $Notice = "Please be sure to use your invites wisely while following the rules.";
                        $subject = ("A user has purchased an invite");
                        $MsgStaff = ("/user.php?id=" . $activeUser['ID'] . " has purchased an invite via the bonus shop.");
                        $MsgStaff .= ("\nUser now has: " . $Invites . " invites.");
                        $staffClass = $this->permissions->getMinClassPermission('users_edit_invites');
                        send_staff_pm($subject, $MsgStaff, $staffClass->Level);
                    } else {
                        $master->flasher->notice("You have too many invites to make a purchase!");
                    }
                } else {
                    $master->flasher->notice("Sorry you have not yet attained a user class that is able to purchase invites");
                }
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
                        $NewTitle = display_str($NewTitle);
                        $Summary = " | -$Cost credits | ".ucfirst("you bought a new custom title ''$NewTitle''.");
                        $wallet->adjustBalance(-$Cost);
                        $wallet->addLog($Summary);
                        $UpdateSet[] = "m.Title = ?";
                        $UpdateData[] = $NewTitle;
                        $ResultMessage=sqltime().$Summary;
                    }
                }
                break;

            case 'ufl':

                $GroupID = empty($_POST['torrentid']) ? '' : (int) $_POST['torrentid'];

                if (!$GroupID) {
                    $ResultMessage = "TorrentID was not set";
                } else {

                    $nextRecord = $master->db->rawQuery(
                        "SELECT tg.UserID,
                                Name,
                                FreeTorrent,
                                Size
                           FROM torrents AS t
                           JOIN torrents_group AS tg ON t.GroupID = tg.ID
                          WHERE GroupID = ?",
                        [$GroupID]
                    )->fetch(\PDO::FETCH_NUM);
                    if ($master->db->foundRows() == 0)
                        $ResultMessage = "Could not find any torrent with ID=$GroupID";
                    else {
                        list($OwnerID, $TName, $FreeTorrent, $Sizebytes) = $nextRecord;
                        if ($OwnerID != $activeUser['ID']) {

                            $ResultMessage = "You are not the owner of torrent with ID=$GroupID - only the uploader can buy Universal Freeleech for their torrent";

                        } elseif ($FreeTorrent == '1') {

                            $ResultMessage = "Torrent $TName is already freeleech!";

                        } elseif ($Sizebytes < get_bytes($Value.'gb')) {

                            $ResultMessage = "Torrent $TName (" . get_size($Sizebytes, 2). ") is too small for a > $Value gb freeleech!";

                        } else {

                            // make torrent FL
                            freeleech_groups($GroupID, 1, true, null);

                            $Summary = " | -$Cost credits | ".ucfirst("you bought a universal freeleech ($Value gb+) for torrent [torrent]{$GroupID}[/torrent]");
                            $wallet->adjustBalance(-$Cost);
                            $wallet->addLog($Summary);
                            $ResultMessage= sqltime()." | -$Cost credits | ".ucfirst("you bought a universal freeleech ($Value gb+) for torrent $TName"); ;
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
            $UpdateDataOther[] = $OtherID;
            $master->db->rawQuery(
                "UPDATE users_main AS m
                   JOIN users_info AS i ON m.ID = i.UserID
                    SET {$SET}
                  WHERE m.ID = ?",
                $UpdateDataOther
            );
            $master->repos->users->uncache($OtherID);
        }

        if ($UpdateSet) {
            $SET = implode(', ', $UpdateSet);
            $UpdateData[] = $userID;
            $master->db->rawQuery(
                "UPDATE users_main AS m
                   JOIN users_info AS i ON m.ID = i.UserID
                    SET {$SET}
                  WHERE m.ID = ?",
                $UpdateData
            );
            $master->repos->users->uncache($userID);
        }
    }
}

// Go back
$Msg ='';
if (isset($_REQUEST['retu']) && is_integer_string($_REQUEST['retu']))
    $Msg .= "&retu=".$_REQUEST['retu'];
elseif (isset($_REQUEST['rett']) && is_integer_string($_REQUEST['rett']))
    $Msg .= "&rett=".$_REQUEST['rett'];
header("Location: bonus.php?action=msg&". (!empty($ResultMessage) ? "result=" .urlencode($ResultMessage):"")."%0A".urlencode($Notice ?? '').$Msg);
