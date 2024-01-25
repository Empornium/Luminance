<?php
authorize();

if (!check_perms('users_give_donor'))  error(403);

if (!is_numeric($_REQUEST['userid'])) error(0); //  || !is_numeric($_REQUEST['donateid'])
if (!is_numeric($_REQUEST['amount'])) error(0);

$userID = (int) $_REQUEST['userid'];
$amount = round($_REQUEST['amount'], 2);

$public = '';
for ($i=0;$i<10;$i++) {
    $try = 'DO_NOT_USE_'. make_secret(30);
    // strictly speaking we should check this 50 char random string is unique...
    $master->db->rawQuery(
        "SELECT ID
           FROM bitcoin_donations
          WHERE public = ?",
        [$try]
    );
    if ($master->db->foundRows()<1) {
        $public = $try;
        break;
    }
}
// either there is a bug or the laws of probability have stopped working if its not unique after 10 tries
if ($public=='') error("Could not create a unique dummy address.... something is fubar! (harass a coder immediately)");

$sqltime = sqltime();
$comment = "(manual payment) donated for ";

if ($_REQUEST['donategb']) {
    //$deduct_bytes = floor($amount) * DEDUCT_GB_PER_EURO * 1024 * 1024 * 1024; // 1 euro per gb
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
    "INSERT INTO bitcoin_donations (state, public, time, userID, staffID, received, amount_euro, comment)
          VALUES ('submitted', ?, ?, ?, ?, ?, ?, ?)",
    [
        $public,
        $sqltime,
        $userID,
        $activeUser['ID'],
        $sqltime,
        $amount,
        $comment,
    ]
);

// why?
$ID = $master->db->lastInsertID();

if ($_REQUEST['donategb']) {
    $downloaded_bytes = $master->db->rawQuery(
        "SELECT Downloaded
           FROM users_main
          WHERE ID = ?",
        [$userID]
    )->fetchColumn();

    $Summary = sqltime() . ' - ' . "[url=/donate.php?action=my_donations&amp;userid={$userID}]Donated: &euro;{$amount}.[/url] Download removed: " . get_size($deduct_bytes);
    if ($downloaded_bytes < $deduct_bytes)
        $Summary .= " | NOTE: Could only remove " . get_size($downloaded_bytes);
    $Summary .= ", by donation system";

    $master->db->rawQuery(
        "UPDATE users_info as i
           JOIN users_main as um ON i.UserID = um.ID
            SET i.AdminComment = CONCAT_WS(CHAR(10 using utf8), ?, i.AdminComment),
                um.Downloaded = (um.Downloaded - ?)
          WHERE um.ID = ?",
        [$Summary, $deduct_bytes, $userID]
    );

    $Summary = get_size($deduct_bytes) . " has been deducted from your download.";
    if ($downloaded_bytes < $deduct_bytes)
        $Summary .= " | NOTE: Could only remove " . get_size($downloaded_bytes);

    send_pm($userID, 0, "Thank you for your donation", "[br]We have received your donation of &euro;{$amount} [br][br]:thankyou:[br][br]{$Summary}");

} else {

    send_pm($userID, 0, "Thank you for your donation", "[br]We have received your donation of &euro;{$amount} [br][br]:thankyou:[br][br]It's thanks to members like you that this site can carry on :gjob:");

    $Summary = "[url=/donate.php?action=my_donations&amp;userid={$userID}]Donated: &euro;{$amount}.[/url]";

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
        $Summary .= " Badge added: $title, by donation system";

        send_pm($userID, 0, "Congratulations you have been awarded the {$title}", "[center][br][br][img]/static/common/badges/{$image}[/img][br][br][size=5][color=white][bg=#0261a3][br]{$description}[br][br][/bg][/color][/size][/center]");
    }
    $Summary = sqltime() . " - {$Summary}";
    //write_user_log($userID, $Summary);
    $master->db->rawQuery(
        "UPDATE users_info
            SET Donor = '1',
                AdminComment = CONCAT_WS(CHAR(10 using utf8), ?, AdminComment)
          WHERE UserID = ?",
        [$Summary, $userID]
    );
}

if (isset($_REQUEST['convid']) && is_integer_string($_REQUEST['convid'])) {
    $ConvID = (int) $_REQUEST['convid'];
    $master->db->rawQuery(
        "UPDATE staff_pm_conversations
            SET Status = 'Resolved',
                ResolverID = ?
          WHERE ID=?",
        [$activeUser['ID'], $ConvID]
    );
    $master->cache->deleteValue('staff_pm_new_'.$activeUser['ID']);
}

$master->repos->users->uncache($userID);

header("Location: donate.php?action=my_donations&userid={$userID}");
