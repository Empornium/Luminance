<?php
if (!check_perms('admin_whitelist')) {
    error(403);
}

show_header('Client Blacklist Management');
$DB->query('SELECT id, vstring, peer_id FROM xbt_client_blacklist ORDER BY peer_id ASC');
?>
<div class="thin">
    <h2>Blacklisted Clients</h2>
    <table class="wid740">
        <tr class="head">
            <td colspan="3">Add a client</td>
        </tr>
        <tr class="colhead">
            <td width="40%">Peer ID</td>
            <td width="40%">Client</td>
            <td width="20%">Submit</td>
        </tr>
        <tr class="rowa">
        <form action="" method="post">
            <td>
                <input type="hidden" name="action" value="client_blacklist_alter" />
                <input type="hidden" name="auth" value="<?= $LoggedUser['AuthKey'] ?>" />
                <input class="long" type="text" size="10" name="peer_id" />
            </td>
            <td>
                <input class="long" type="text" name="client" />
            </td>
            <td>
                <input type="submit" name="submit" value="Create" />
            </td>
        </form>
        </tr>
    </table>
    <br />
    <table class="wid740">
        <tr class="head">
            <td>Add clients</td>
        </tr>
        <tr class="colhead" class="multiadd">
            <td>PeerID &nbsp; Client</td>
        </tr>
        <tr class="rowa" class="multiadd">
        <form action="" method="post">
            <td>
                <input type="hidden" name="action" value="client_blacklist_alter" />
                <input type="hidden" name="auth" value="<?= $LoggedUser['AuthKey'] ?>" />
                <textarea name="clients" class="long" title="On each line enter the peerID and then the text description. NOTE: The line will be split on the first space."><?=$Clients?></textarea><br/>
                <input type="submit" name="submit"  value="Add client" />
                You can only add one client at a time but this interface makes it slightly less painful to add many (it adds the first line and returns with that line removed)
            </td>
        </form>
        </tr>
    </table>
    <br />
    <table class="wid740">
        <tr class="head">
            <td colspan="3">Mange client blacklist</td>
        </tr>
        <tr class="colhead">
            <td width="40%">Peer ID</td>
            <td width="40%">Client</td>
            <td width="20%">Submit</td>
        </tr>
        <?php
        $Row = 'b';
        while (list($ID, $Client, $Peer_ID) = $DB->next_record()) {
            $Row = ($Row === 'a' ? 'b' : 'a');
            ?>
            <tr class="row<?= $Row ?>">
            <form action="" method="post">
                <td>
                    <input type="hidden" name="action" value="client_blacklist_alter" />
                    <input type="hidden" name="auth" value="<?= $LoggedUser['AuthKey'] ?>" />
                    <input type="hidden" name="id" value="<?= $ID ?>" />
                    <input class="long" type="text" size="10" name="peer_id" value="<?= $Peer_ID ?>" />
                </td>
                <td>
                    <input class="long" type="text" name="client" value="<?= $Client ?>" />
                </td>
                <td>
                    <input type="submit" name="submit" value="Edit" />
                    <input type="submit" name="submit" value="Delete" />
                </td>
            </form>
            </tr>
<?php  } ?>
    </table>
</div>
<?php
show_footer();
