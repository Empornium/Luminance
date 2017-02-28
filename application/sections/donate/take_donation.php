<?php
authorize();

if (!is_numeric($_REQUEST['userid']) || !is_numeric($_REQUEST['donateid'])) error(0);

$UserID = (int) $_REQUEST['userid'];
if ($UserID != $LoggedUser['ID'] && !check_perms('admin_donor_log'))  error(403);

$DonateID = (int) $_REQUEST['donateid'];

$DB->query("SELECT public FROM bitcoin_donations
                         WHERE state='unused' AND ID='$DonateID' AND userid='$UserID'");
if ($DB->record_count() == 0) error("Could not find address with ID='$DonateID', please contact an admin.");
list($public) = $DB->next_record();

$balance = check_bitcoin_balance($public, 6);
if ($balance == 0)  error("Balance==0 - we cannot detect any balance at that address, if you think this is in error please contact an admin.");
// for the moment we will just use rate right now...
// but we could lookup the rate when it was donated if this becomes an issue
// $activetime = check_bitcoin_activation($public); // time that address first appeared on BC network
// and we would have to record daily rates as we advertise them and then look them up

$eur_rate = get_current_btc_rate();
if ($eur_rate == 0)  error("There was an error getting the bitcoin exchange rate, please contact an admin.");

$amount = round($balance * $eur_rate, 2);
$time = sqltime();
$comment = "donated for ";

if ($_REQUEST['donategb']) {
    $deduct_bytes = get_donate_deduction($amount);
    $comment .= "ratio: - " . get_size($deduct_bytes);
} else {
    $comment .= "love";
    $DB->query("SELECT ID, Title, Badge, Rank, Image, Description FROM badges WHERE Type='Donor' AND Cost<='" . (int) round($amount) . "' ORDER BY Cost DESC LIMIT 1");
    if ($DB->record_count() > 0) {
        list($badgeid, $title, $badge, $rank, $image, $description) = $DB->next_record();
        $comment .= " (received badge: $title)";
    }
}

$comment = db_string($comment);

$DB->query("UPDATE bitcoin_donations SET state='submitted',
                                                     bitcoin_rate='$eur_rate',
                                                     received='$time',
                                                     amount_bitcoin='$balance',
                                                     amount_euro='$amount',
                                                     comment='$comment'
                                                 WHERE ID='$DonateID' ");

if ($_REQUEST['donategb']) {

    $DB->query("SELECT Downloaded FROM users_main WHERE ID='$UserID'");
    list($downloaded_bytes) = $DB->next_record();

    $Summary = sqltime() . ' - ' . "[url=/donate.php?action=my_donations&amp;userid=$UserID]Donated: &euro;$amount.[/url] Download removed: " . get_size($deduct_bytes);
    if ($downloaded_bytes < $deduct_bytes)
        $Summary .= " | NOTE: Could only remove " . get_size($downloaded_bytes);
    $summary .= ", by donation system";

    $DB->query("UPDATE users_info as i JOIN users_main as m ON i.UserID=m.ID
                               SET i.AdminComment=CONCAT_WS( '\n', '".db_string($Summary)."', i.AdminComment),
                                   m.Downloaded=(m.Downloaded-'$deduct_bytes')
                             WHERE m.ID='$UserID'");

    $Summary = get_size($deduct_bytes) . " has been deducted from your download.";
    if ($downloaded_bytes < $deduct_bytes)
        $Summary .= " | NOTE: Could only remove " . get_size($downloaded_bytes);

    send_pm($UserID, 0, db_string("Thank-you for your donation"), db_string("[br]We have received your donation of &euro;$amount [br][br]:thankyou:[br][br]$Summary"));

} else {

    send_pm($UserID, 0, db_string("Thank-you for your donation"), db_string("[br]We have received your donation of &euro;$amount [br][br]:thankyou:[br][br]It's thanks to members like you that this site can carry on :gjob:"));

    $Summary = "[url=/donate.php?action=my_donations&amp;userid=$UserID]Donated: &euro;$amount.[/url]";

    if ($badgeid) {
        $DB->query("SELECT BadgeID FROM users_badges
                                 WHERE UserID='$UserID' AND BadgeID='$badgeid' ");
        if ($DB->record_count() == 0) {
            $description = db_string($description);
            $DB->query("INSERT INTO users_badges (UserID, BadgeID, Description) VALUES
                                                              ($UserID, $badgeid, '$description')");
            // remove lower ranked donor badges
            $DB->query("DELETE ub FROM users_badges AS ub
                                               JOIN badges AS b ON ub.BadgeID=b.ID
                                                   AND b.Badge='$badge' AND b.Rank<$rank
                                                 WHERE ub.UserID='$UserID'");

            $Cache->delete_value('user_badges_ids_' . $UserID);
            $Cache->delete_value('user_badges_' . $UserID);
            $Cache->delete_value('user_badges_' . $UserID . '_limit');
        }
        $Summary .= " Badge added: $title, by donation system";

        send_pm($UserID, 0, db_string("Congratulations you have been awarded the $title"), db_string("[center][br][br][img]http://" . SITE_URL . '/' . STATIC_SERVER . "common/badges/{$image}[/img][br][br][size=5][color=white][bg=#0261a3][br]{$description}[br][br][/bg][/color][/size][/center]"));
    }
    $Summary = db_string(sqltime() . " - $Summary");

    $DB->query("UPDATE users_info
                               SET Donor='1', AdminComment=CONCAT_WS( '\n', '$Summary', AdminComment)
                             WHERE UserID='$UserID'");
}
$master->repos->users->uncache($UserID);

header("Location: donate.php?action=my_donations&userid=$UserID");
