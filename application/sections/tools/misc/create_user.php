<?php
if (!check_perms('admin_create_users')) {
    error(403);
}

if (!empty($_POST['submit'])) {
    $Val->SetFields('username', true, 'regex', 'You did not enter a valid username.', array('regex' => '/^[a-z0-9_?]{1,20}$/iD'));
    $Val->SetFields('email', true, 'email', 'You did not enter a valid email address.');
    $Val->SetFields('password', true, 'string', 'You did not enter a valid password (6 - 40 characters).', array('minlength' => 6, 'maxlength' => 40));
    $Val->SetFields('confirm_password', true, 'compare', 'Your passwords do not match.', array('comparefield' => 'password'));

    $Err = $Val->ValidateForm($_POST);
    if ($Err) error($Err);

    //Create variables for all the fields
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    $master->auth->createUser($username, $password, $email);

    //Redirect to users profile
    header("Location: user.php?id=" . $UserID);

//Form wasn't sent -- Show form
} else {

    show_header('Create a User');

    ?>
    <div class="thin">
        <h2>Create a User</h2>

        <form method="post" action="" name="create_user">
            <input type="hidden" name="action" value="create_user" />
            <input type="hidden" name="auth" value="<?= $LoggedUser['AuthKey'] ?>" />
            <table cellpadding="2" cellspacing="1" border="0" align="center">
                <tr valign="top">
                    <td align="right" class="label">Username&nbsp;</td>
                    <td align="left" class="medium"><input type="text" name="username" id="username" class="inputtext"  maxlength="20" pattern="[A-Za-z0-9_\-\.]{1,20}"  /></td>
                </tr>
                <tr valign="top">
                    <td align="right" class="label">Email&nbsp;</td>
                    <td align="left"><input type="text" name="email" id="email" class="inputtext" /></td>
                </tr>
                <tr valign="top">
                    <td align="right" class="label">Password&nbsp;</td>
                    <td align="left"><input type="password" name="password" id="password" class="inputtext" /></td>
                </tr>
                <tr valign="top">
                    <td align="right" class="label">Verify Password&nbsp;</td>
                    <td align="left"><input type="password" name="confirm_password" id="confirm_password" class="inputtext" /></td>
                </tr>
                <tr>
                    <td colspan="2" align="right"><input type="submit" name="submit" value="Create User" class="submit" /></td>
                </tr>
            </table>
        </form>
    </div>
    <?php
    show_footer();
}
