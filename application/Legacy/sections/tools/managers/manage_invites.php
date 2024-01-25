<?php

/**
 * TODO: Improve permission?
 * TODO: Merge with the invite_pool page?
 * TODO: Add more user options (e.g. exclude disabled, etc.)
 */

if (!check_perms('users_edit_invites')) {
    error(403);
}

// Get all user classes
$Query = $master->db->rawQuery("SELECT Name, Level FROM permissions WHERE IsUserClass = '1' ORDER BY Level ASC");
$classes = $Query->fetchAll(\PDO::FETCH_OBJ);

if (isset($_POST['submit'])) {
    authorize();

    $Simulation = isset($_POST['simulate']);

    if (!isset($_POST['do']) || !in_array($_POST['do'], ['give', 'remove'], true)) {
        error('Invalid `do` parameter.');
    }

    $do = $_POST['do'];

    if (!isset($_POST['min-level']) || !is_integer_string($_POST['min-level'])) {
        error('Invalid `min-level` parameter.');
    }

    $MinLevel = (int) $_POST['min-level'];

    if (!isset($_POST['max-level']) || !is_integer_string($_POST['max-level'])) {
        error('Invalid `max-level` parameter.');
    }

    $MaxLevel = (int) $_POST['max-level'];

    if (!isset($_POST['quantity']) || !is_integer_string($_POST['quantity'])) {
        error('Invalid `quantity` parameter.');
    }

    $Quantity = (int) $_POST['quantity'];

    if (!isset($_POST['threshold']) || !is_integer_string($_POST['threshold'])) {
        error('Invalid `threshold` parameter.');
    }

    $Threshold = (int) $_POST['threshold'];

    if ($do === 'give' && $Quantity > $Threshold) {
        error("You wanted to send {$Quantity} invites, with a maximum of {$Threshold}. That makes no sense!");
    }

    if ($MinLevel > $MaxLevel) {
        error("You got the max/min classes backwards.");
    }

    if (!$Simulation && empty($_POST['reason'])) {
        error("Please write a reason to {$do} those invites.");
    }

    $Reason = $_POST['reason'];

    $params = [$Threshold, $Quantity];
    if ($do === 'give') {
        $SetQuery = "LEAST(?, Invites + ?)";
    } else {
        $SetQuery = "GREATEST(?, Invites - ?)";
    }

    $conditionParams = [$MinLevel];
    if ($MinLevel === $MaxLevel) {
        $LevelCond = "p.Level = ?";
    } else {
        $conditionParams[] = $MaxLevel;
        $LevelCond = "p.Level >= ? AND p.Level <= ?";
    }

    $UsersCount = $master->db->rawQuery("SELECT COUNT(*) FROM users_main AS um
                                      LEFT JOIN permissions AS p ON um.PermissionID = p.ID
                                      WHERE um.Enabled = '1' AND {$LevelCond}",
        $conditionParams
    )->fetchColumn();

    $InvitesCount = $UsersCount * $Quantity;
    $Results = "{$Quantity} invites sent to {$UsersCount} users, {$InvitesCount} invites are now in the wild. ";

    if (!$Simulation) {
        // Staff note
        $Action = $do === 'give' ? 'given' : 'removed';
        $ThresholdStr = $do === 'give' ? "(Max: {$Threshold})" : "(Min: {$Threshold})";
        $Comment = sqltime()." - {$Quantity} invites {$Action} {$ThresholdStr} for {$Reason} by {$activeUser['Username']}\n";
        $params[] = $Comment;
        $params = array_merge($params, $conditionParams);

        $master->db->rawQuery("UPDATE users_main AS um
                                LEFT JOIN permissions AS p ON um.PermissionID = p.ID
                                LEFT JOIN users_info AS ui ON um.ID = ui.UserID
                                SET Invites = {$SetQuery}, AdminComment = CONCAT(?, AdminComment)
                                WHERE Enabled = '1' AND {$LevelCond}",
            $params
        );
        $logMsg = $Results . ' Reason: ' . $Reason;
        if ($Action === 'given') {
            $this->inviteLog->massInviteGrant($logMsg, $MinLevel, $MaxLevel, $Quantity);
        } else {
            $this->inviteLog->massInviteRemoval($logMsg, $MinLevel, $MaxLevel, $Quantity);
        }
    } else {
        $Results .= '[Simulation mode, nothing has been sent!]';
    }
}

show_header('Manage invites');
?>
    <div class="thin">
        <h2>Manage invites</h2>

        <?php if (!empty($Results)): ?>
            <div class="head">Results</div>
            <div class="box pad">
                <?= display_str($Results) ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="action" value="manage_invites" />
            <input type="hidden" name="auth" value="<?= $activeUser['AuthKey'] ?>" />

            <div class="head">Manage invites</div>
            <div class="box pad">
                <div id="quickreplytext">
                    <div style="display:inline-block;margin-right:20px;vertical-align: top;">
                        <h3>Do</h3>
                        <select name="do">
                            <option value="give">Give invites</option>
                            <option value="remove">Remove invites</option>
                        </select>
                    </div>
                    <div style="display:inline-block;margin-right:20px;vertical-align: top;">
                        <h3>Min class</h3>
                        <select name="min-level">
                            <?php foreach ($classes as $class): ?>
                            <option value="<?= $class->Level ?>"><?= $class->Name ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="display:inline-block;margin-right:20px;vertical-align: top;">
                        <h3>Max class</h3>
                        <select name="max-level">
                            <?php foreach ($classes as $class): ?>
                                <option value="<?= $class->Level ?>"><?= $class->Name ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="display:inline-block;margin-right:20px;vertical-align: top;">
                        <h3>Quantity</h3>
                        <input type="number" name="quantity" value="3">
                    </div>
                    <div style="display:inline-block;margin-right:20px;vertical-align: top;">
                        <h3>Min/max</h3>
                        <input type="number" name="threshold" value="4">
                    </div>
                    <div style="display:inline-block;margin-right:20px;vertical-align: top;">
                        <h3>&nbsp;</h3>
                        <input type="checkbox" id="simulate" name="simulate" checked> <label for="simulate">Simulation only</label>
                        <input type="submit" name="submit" value="Run" class="submit">
                    </div>

                    <h3>Reason</h3>
                    <input type="text" name="reason" class="long" placeholder="Your reason to send/remove those invites">
                </div>
            </div>

            <div class="head">History</div>
                <div class="box pad">
                    <?php $logs = $this->inviteLog->massLog(); ?>
                    <table>
                    <tr><th>Date</th><th>Event</th><th>Reason</th><th>User</th><th>Quantity</th><th>Action</th></tr>
                    <?php foreach ($logs as $row) {
                        list($event, $reason, $authorID, $quantity, $action, $date) = $row; ?>
                        <tr><td><?=trimDate($date)?></td><td><?=$event?></td><td><?=$reason?></td><td><?=format_username($authorID)?></td><td><?=$quantity?></td><td><?=$action?></td></tr>
                    <?php } ?>
                    </table>
                </div>
            </div>
        </form>
    </div>
<?php
show_footer();
