<?php
if (!$UserCount = $Cache->get_value('stats_user_count')) {
    $UserCount = $master->db->raw_query("SELECT COUNT(ID) FROM users_main WHERE Enabled='1'")->fetchColumn();
    $Cache->cache_value('stats_user_count', $UserCount, 0);
}

//This is where we handle things passed to us
authorize();

$CanLeech = $master->db->raw_query("SELECT can_leech FROM users_main WHERE ID = ?", [$LoggedUser['ID']])->fetchColumn();

if($LoggedUser['RatioWatch'] ||
    !$CanLeech ||
    $LoggedUser['DisableInvites'] == '1'||
    $LoggedUser['Invites']==0 && !check_perms('site_send_unlimited_invites') ||
    ($UserCount >= USER_LIMIT && USER_LIMIT != 0 && !check_perms('site_can_invite_always'))) {

        error(403);
}

$Email = $_POST['email'];
$Anon = isset($_POST['anon']) ? true : false;
$Username = $LoggedUser['Username'];
$SiteName = SITE_NAME;
$SiteURL = SITE_URL;
$Scheme = $master->request->ssl ? 'https' : 'http';
$InviteExpires = time_plus(60*60*24*3); // 3 days

//MultiInvite
if (strpos($Email, '|') && check_perms('site_send_unlimited_invites')) {
    $Emails = explode('|', $Email);
} else {
    $Emails = array($Email);
}

foreach ($Emails as $CurEmail) {
    if (!preg_match("/^".EMAIL_REGEX."$/i", $CurEmail)) {
        if (count($Emails) > 1) {
            continue;
        } else {
            error('Invalid email.');
            header('Location: user.php?action=invite');
            die();
        }
    }
    $dupeInvite = $master->db->raw_query(
        "SELECT COUNT(*) FROM invites WHERE InviterID = :userID and Email LIKE :email",
        [':userID' => $LoggedUser['ID'], ':email' => $CurEmail])->fetchColumn();
    if ($dupeInvite > 0) {
        error("You already have a pending invite to that address!");
        header('Location: user.php?action=invite');
        die();
    }

    $token = $master->secretary->getExternalToken($CurEmail, 'users.register');

    $master->db->raw_query("INSERT INTO invites (InviterID, InviteKey, Email, Expires)
                            VALUES (:userID, :token, :email, :expires)",
                           [':userID'  => $LoggedUser['ID'],
                            ':token'   => $token,
                            ':email'   => $CurEmail,
                            ':expires' => $InviteExpires]);
    $inviteID = $master->db->last_insert_id();

    if (!check_perms('site_send_unlimited_invites')) {
        $master->db->raw_query("UPDATE users_main SET Invites=GREATEST(Invites,1)-1 WHERE ID=?", [$LoggedUser['ID']]);
        $master->repos->users->uncache($LoggedUser['ID']);
    }

    $token = $master->crypto->encrypt(['email' => $CurEmail, 'inviteID' => $inviteID, 'token' => $token], 'default', true);

    $subject = 'You have been invited to '.$master->settings->main->site_name;

    $email_body = [];
    $email_body['username'] = $Username;
    $email_body['email']    = $CurEmail;
    $email_body['token']    = $token;
    $email_body['anon']     = $Anon;
    $email_body['settings'] = $master->settings;
    $email_body['scheme']   = $Scheme;
    if (DEBUG_MODE) {
        $body = $master->tpl->render('invite_email.flash', $email_body);
        $master->flasher->notice($body);
        error($body);
    } else {
        $body = $master->tpl->render('invite_email.email', $email_body);
        $master->secretary->send_email($CurEmail, $subject, $body);
    }
}

header('Location: user.php?action=invite');
