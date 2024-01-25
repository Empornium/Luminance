<?php
if (!check_perms('users_mod')) { error(403); }

if (isset($_REQUEST['addtokens'])) {
    authorize();
    $Tokens = $_REQUEST['numtokens'];

    if (!is_integer_string($Tokens) || ($Tokens < 0)) {	error("Please enter a valid number of tokens."); }
    $sql = "UPDATE users_main SET FLTokens = FLTokens + ? WHERE Enabled = '1'";
    if (!isset($_REQUEST['leechdisabled'])) {
        $sql .= " AND can_leech = 1";
    }
    $master->db->rawQuery($sql, [$Tokens]);
    $sql = "SELECT ID FROM users_main WHERE Enabled = '1'";
    if (!isset($_REQUEST['leechdisabled'])) {
        $sql .= " AND can_leech = 1";
    }
    $userIDs = $master->db->rawQuery($sql)->fetchAll(\PDO::FETCH_COLUMN);
    foreach ($userIDs as $userID) {
        $master->repos->users->uncache($userID);
    }
    $message = "<strong>$Tokens freeleech tokens added to all enabled users" . (!isset($_REQUEST['leechdisabled'])?' with enabled leeching privs':'') . '.</strong><br /><br />';
} elseif (isset($_REQUEST['cleartokens'])) {
    authorize();
    $Tokens = $_REQUEST['numtokens'];

    if (!is_integer_string($Tokens) || ($Tokens < 0)) {	error("Please enter a valid number of tokens."); }

    if (isset($_REQUEST['onlydrop'])) {
        $Where = "FLTokens > ?";
    } elseif (!isset($_REQUEST['leechdisabled'])) {
        $Where = "(Enabled = '1' AND can_leech = 1) OR FLTokens > ?";
    } else {
        $Where = "Enabled = '1' OR FLTokens > ?";
    }
    $Users = $master->db->rawQuery("SELECT ID FROM users_main WHERE {$Where}", [$Tokens])->fetchAll(\PDO::FETCH_COLUMN);
    $master->db->rawQuery("UPDATE users_main SET FLTokens = ? WHERE {$Where}",
        [$Tokens]
    );

    foreach ($Users as $userID) {
        list($userID) = $userID;
        $master->repos->users->uncache($userID);
    }
}

show_header('Add tokens sitewide');

?>
<div class="thin">
<h2>Add freeleech tokens to all enabled users</h2>

<div class="linkbox"><a href="/tools.php?action=tokens&showabusers=1">[Show Abusers]</a></div>
<div class="box pad" style="margin-left: auto; margin-right: auto; text-align:center; max-width: 40%">
    <?=$message ?? ''?>
    <form action="" method="post">
        <input type="hidden" name="action" value="tokens" />
        <input type="hidden" name="auth" value="<?=$activeUser['AuthKey']?>" />
        Tokens to add: <input type="text" name="numtokens" size="5" style="text-align: right" value="0"><br /><br />
        <label for="leechdisabled">Grant tokens to leech disabled users: </label><input type="checkbox" id="leechdisabled" name="leechdisabled" value="1"><br /><br />
        <input type="submit" name="addtokens" value="Add tokens">
    </form>
</div>
<br />
<div class="box pad" style="margin-left: auto; margin-right: auto; text-align:center; max-width: 40%">
    <?=$message ?? ''?>
    <form action="" method="post">
        <input type="hidden" name="action" value="tokens" />
        <input type="hidden" name="auth" value="<?=$activeUser['AuthKey']?>" />
        Tokens to set: <input type="text" name="numtokens" size="5" style="text-align: right" value="0"><br /><br />
        <span id="droptokens" class=""><label for="onlydrop">Only affect users with at least this many tokens: </label><input type="checkbox" id="onlydrop" name="onlydrop" value="1" onChange="$('#disabled').toggle();return true;"></span><br />
        <span id="disabled" class=""><label for="leechdisabled">Also add tokens (as needed) to leech disabled users: </label><input type="checkbox" id="leechdisabled" name="leechdisabled" value="1" onChange="$('#droptokens').toggle();return true;"></span><br /><br />
        <input type="submit" name="cleartokens" value="Set token total">
    </form>
</div>
</div>
<?php
show_footer();
