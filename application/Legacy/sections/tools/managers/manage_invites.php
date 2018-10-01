<?php

/**
 * TODO: Improve permission?
 * TODO: Create a log table to know when invites were sent and by who?
 * TODO: Merge with the invite_pool page?
 * TODO: Add more user options (e.g. exclude disabled, etc.)
 */

if (!check_perms('users_edit_invites')) {
    error(403);
}

// Get all user classes
$Query = $master->db->raw_query("SELECT Name, Level FROM permissions WHERE IsUserClass = '1' ORDER BY Level ASC");
$Classes = $Query->fetchAll(\PDO::FETCH_OBJ);

if (isset($_POST['submit'])) {
    authorize();

    $Simulation = isset($_POST['simulate']);

    if (!isset($_POST['do']) || !in_array($_POST['do'], ['give', 'remove'], true)) {
        error('Invalid `do` parameter.');
    }

    $do = $_POST['do'];

    if (!isset($_POST['min-level']) || !is_number($_POST['min-level'])) {
        error('Invalid `min-level` parameter.');
    }

    $MinLevel = (int) $_POST['min-level'];

    if (!isset($_POST['max-level']) || !is_number($_POST['max-level'])) {
        error('Invalid `max-level` parameter.');
    }

    $MaxLevel = (int) $_POST['max-level'];

    if (!isset($_POST['quantity']) || !is_numeric($_POST['quantity'])) {
        error('Invalid `quantity` parameter.');
    }

    $Quantity = (int) $_POST['quantity'];

    if (!isset($_POST['threshold']) || !is_numeric($_POST['threshold'])) {
        error('Invalid `threshold` parameter.');
    }

    $Threshold = (int) $_POST['threshold'];

    if ($do === 'give' && $Quantity > $Threshold) {
        error("You wanted to send {$Quantity} invites, with a maximum of {$Threshold}. That makes no sense!");
    }

    if ($MinLevel > $MaxLevel) {
        error("You got the max/min classes backwards.");
    }

    # Uncomment when/if the history table is set
//    if (!$Simulation && empty($_POST['reason'])) {
//        error("Please write a reason to {$do} those invites.");
//    }

    $Reason = $_POST['reason'];

    if ($do === 'give') {
        $SetQuery = "LEAST({$Threshold}, Invites + {$Quantity})";
    } else {
        $SetQuery = "GREATEST({$Threshold}, Invites - {$Quantity})";
    }

    if ($MinLevel === $MaxLevel) {
        $LevelCond = "p.Level = {$MinLevel}";
    } else {
        $LevelCond = "p.Level >= {$MinLevel} AND p.Level <= {$MaxLevel}";
    }

    $UsersCount = $master->db->raw_query("SELECT COUNT(*) FROM users_main AS u 
                                      LEFT JOIN permissions AS p ON u.PermissionID = p.ID 
                                      WHERE {$LevelCond}")->fetchColumn();

    $InvitesCount = $UsersCount * $Quantity;
    $Results = "{$Quantity} invites sent to {$UsersCount} users, {$InvitesCount} invites are now in the wild. ";

    if (!$Simulation) {
        // Staff note
        $Action = $do === 'give' ? 'given' : 'removed';
        $ThresholdStr = $do === 'give' ? "(Max: {$Threshold})" : "(Min: {$Threshold})";
        $Comment = sqltime()." - {$Quantity} invites {$Action} {$ThresholdStr} for {$Reason} by {$LoggedUser['Username']}\n";
        $Comment = db_string($Comment);

        $master->db->raw_query("UPDATE users_main AS u
                                LEFT JOIN permissions AS p ON u.PermissionID = p.ID
                                LEFT JOIN users_info AS ui ON u.ID = ui.UserID
                                SET Invites = {$SetQuery}, AdminComment = CONCAT('{$Comment}', AdminComment)
                                WHERE {$LevelCond}");
    } else {
        $Results .= '[Simulation mode, nothing has been sent!]';
    }
}

show_header('Manage invites');
?>
    <div class="thin">
        <h2>Manage invites</h2>

        <?php if(!empty($Results)): ?>
            <div class="head">Results</div>
            <div class="box pad">
                <?= display_str($Results) ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="action" value="manage_invites" />
            <input type="hidden" name="auth" value="<?= $LoggedUser['AuthKey'] ?>" />

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
                            <?php foreach ($Classes as $Class): ?>
                            <option value="<?= $Class->Level ?>"><?= $Class->Name ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="display:inline-block;margin-right:20px;vertical-align: top;">
                        <h3>Max class</h3>
                        <select name="max-level">
                            <?php foreach ($Classes as $Class): ?>
                                <option value="<?= $Class->Level ?>"><?= $Class->Name ?></option>
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

            <div class="head">History (TODO?)</div>
                <div class="box pad">
                    <p><em>22/05/2018</em> - 7th birthday by Admin</p>
                    <p><em>15/01/2018</em> - Oktoberfest by Starbuck</p>
                </div>
            </div>
        </form>
    </div>
<?php
show_footer();