<?php
authorize();

if (!is_numeric($_REQUEST['userid']) || !is_numeric($_REQUEST['donateid'])) error(0);

$userID = (int) $_REQUEST['userid'];
if ($userID != $activeUser['ID'] && !check_perms('admin_donor_log'))  error(403);

$DonateID = (int) $_REQUEST['donateid'];

$public = $master->db->rawQuery(
    "SELECT public
       FROM bitcoin_donations
      WHERE state = 'unused'
        AND ID = ?
        AND userid = ?",
    [$DonateID, $userID]
)->fetchColumn();
if ($master->db->foundRows() == 0) error("Could not find address with ID='$DonateID', please contact an admin.");

$balance = check_bitcoin_balance($public, 6);
if ($balance == 0)  error("Balance==0 - we cannot detect any balance at that address, if you think this is in error please contact an admin.");
// for the moment we will just use rate right now...
// but we could lookup the rate when it was donated if this becomes an issue
// $activetime = check_bitcoin_activation($public); // time that address first appeared on BC network
// and we would have to record daily rates as we advertise them and then look them up

$eur_rate = get_current_btc_rate();
if ($eur_rate == 0)  error("There was an error getting the bitcoin exchange rate, please contact an admin.");

$amount = round($balance * $eur_rate, 2);
$sqltime = sqltime();
$comment = "donated for ";

if ($_REQUEST['donategb'] ?? false) {
    $deduct_bytes = get_donate_deduction($amount);
    $comment .= "ratio: - " . get_size($deduct_bytes);
} else {
    $comment .= "love";
    $nextRecord = $master->db->rawQuery(
        "SELECT ID,
                Title,
                Badge,
                Rank,
                Image,
                Description
           FROM badges
          WHERE Type='Donor'
            AND Cost <= ?
       ORDER BY Cost DESC
          LIMIT 1",
        [(int) round($amount)]
    )->fetch(\PDO::FETCH_NUM);
    if ($master->db->foundRows() > 0) {
        list($badgeid, $title, $badge, $rank, $image, $description) = $nextRecord;
        $comment .= " (received badge: $title)";
    }
}

$master->db->rawQuery(
    "UPDATE bitcoin_donations
        SET state = 'submitted',
            bitcoin_rate = ?,
            received = ?,
            amount_bitcoin = ?,
            amount_euro = ?,
            comment = ?
      WHERE ID = ?",
    [$eur_rate, $sqltime, $balance, $amount, $comment, $DonateID]
);

if ($_REQUEST['donategb'] ?? false) {
    $downloaded_bytes = $master->db->rawQuery(
        "SELECT Downloaded
           FROM users_main
          WHERE ID = ?",
        [$userID]
    )->fetchColumn();

    $summary = sqltime() . ' - ' . "[url=/donate.php?action=my_donations&amp;userid=$userID]Donated: &euro;$amount.[/url] Download removed: " . get_size($deduct_bytes);
    if ($downloaded_bytes < $deduct_bytes)
        $summary .= " | NOTE: Could only remove " . get_size($downloaded_bytes);
    $summary .= ", by donation system";

    $master->db->rawQuery(
        "UPDATE users_info as i
           JOIN users_main as m
             ON i.UserID = m.ID
            SET i.AdminComment = CONCAT_WS(CHAR(10 using utf8), ?, i.AdminComment),
                m.Downloaded = (m.Downloaded - ?)
          WHERE m.ID = ?",
        [$summary, $deduct_bytes, $userID]
    );

    $summary = get_size($deduct_bytes) . " has been deducted from your download.";
    if ($downloaded_bytes < $deduct_bytes)
        $summary .= " | NOTE: Could only remove " . get_size($downloaded_bytes);

    send_pm($userID, 0, "Thank you for your donation", "[br]We have received your donation of &euro;{$amount} [br][br]:thankyou:[br][br]{$summary}");

} else {

    send_pm($userID, 0, "Thank you for your donation", "[br]We have received your donation of &euro;{$amount} [br][br]:thankyou:[br][br]It's thanks to members like you that this site can carry on :gjob:");

    $summary = "[url=/donate.php?action=my_donations&amp;userid=$userID]Donated: &euro;$amount.[/url]";

    if ($badgeid) {
        $recordCount = $master->db->rawQuery(
            "SELECT COUNT(BadgeID)
               FROM users_badges
              WHERE UserID = ?
                AND BadgeID = ?",
            [$userID, $badgeid]
        )->fetchColumn();
        if ($recordCount === '0') {
            $master->db->rawQuery(
                "INSERT INTO users_badges (UserID, BadgeID, Description)
                      VALUES (?, ?, ?)",
                [$userID, $badgeid, $description]
            );
            // remove lower ranked donor badges
            $master->db->rawQuery(
                "DELETE ub
                   FROM users_badges AS ub
                   JOIN badges AS b ON ub.BadgeID=b.ID
                    AND b.Badge = ?
                    AND b.Rank < ?
                  WHERE ub.UserID = ?",
                [$badge, $rank, $userID]
            );

            $master->cache->deleteValue('user_badges_ids_' . $userID);
            $master->cache->deleteValue('user_badges_' . $userID);
            $master->cache->deleteValue('user_badges_' . $userID . '_limit');
        }
        $summary .= " Badge added: $title, by donation system";

        send_pm($userID, 0, "Congratulations you have been awarded the $title", "[center][br][br][img]/static/common/badges/{$image}[/img][br][br][size=5][color=white][bg=#0261a3][br]{$description}[br][br][/bg][/color][/size][/center]");
    }
    $summary = sqltime() . " - $summary";

    $master->db->rawQuery(
        "UPDATE users_info
            SET Donor = '1',
                AdminComment = CONCAT_WS(CHAR(10 using utf8), ?, AdminComment)
          WHERE UserID = ?",
        [$summary, $userID]
    );
}
$master->repos->users->uncache($userID);

header("Location: donate.php?action=my_donations&userid=$userID");
