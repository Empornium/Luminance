<?php
    enforce_login();
    show_header('IRC');

    include(SERVER_ROOT.'/classes/class_text.php');
    $Text = new TEXT;
    if (!$_POST["connect"] || (!isset($_POST["emp"]) && !isset($_POST["help"]) && !isset($_POST["staff"]))) {
?>
<div class="thin">
    <div class="head">IRC Rules - Please read these carefully!</div>
    <div class="box pad" style="padding:10px 10px 10px 20px;">
<?php
        $Body=get_article('chatrules');
        if (!$Body) $Body = "could not find article 'chatrules'";
        echo $Text->full_format($Body, true);
?>
    </div>
    <form method="post" action="chat.php" onsubmit="return ($('#channel1').raw().checked || $('#channel2').raw().checked || $('#channel3').raw().checked);">
        <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
        <br/>
        <table>
            <tr>
                <td class="noborder right" width="60%">
                     connect to the <strong>#empornium</strong> general chat channel
                    <input type="checkbox" id="channel1" name="emp" value="1" checked="checked" /><br/>
                     connect to the <strong>#empornium-help</strong> channel*
                    <input type="checkbox" id="channel2" name="help" value="1" />
<?php               if ($LoggedUser['SupportFor'] !="" || $LoggedUser['DisplayStaff'] == 1) { ?>
                    <br/> connect to the <strong>#empornium-staff</strong> channel*
                    <input type="checkbox" id="channel3" name="staff" value="1" />
<?php               } ?>
                </td>
                <td class="noborder">
                    <input type="submit" id="connect" name="connect" style="width:160px" value="I agree to the rules" />
                </td>
            </tr>
            <tr>
                <td class="noborder right" colspan="2">
                    *note: Please be patient we are not around 24/7. If you want help idle in the help channel (or if you want to help) &nbsp;&nbsp;
                </td>
            </tr>
        </table>
    </form>
</div>

<?php
    } else {
        $nick = $LoggedUser["Username"];
        $nick = preg_replace('/[^a-zA-Z0-9\[\]\\`\^\{\}\|_]/', '', $nick);
        if (strlen($nick) == 0) {
            $nick = "EmpGuest????";
        } else {
            if (is_numeric(substr($nick, 0, 1))) {
                $nick = "_" . $nick;
            }
        }
            $channels='';
            $div='';
            if (isset($_POST["emp"])) {
                $channels='#empornium';
                $div=',';
            }
            if (isset($_POST["help"])) {
                $channels .= "{$div}#empornium-help";
                $div=',';
            }
            if(isset($_POST["staff"]))
                if ( $LoggedUser['SupportFor'] !="" || $LoggedUser['DisplayStaff'] == 1 )
                    $channels .= "{$div}#empornium-staff";
?>
<div class="thin">
    <div class="head">IRC</div>
    <div class="box pad center">
                <iframe src="<?=CHAT_URL?>nick=<?=$nick?><?=$channels?>" width="98%" height="600"></iframe>
    </div>
</div>
<?php
    }

show_footer();
