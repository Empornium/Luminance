<?php

authorize();

if (!check_perms('admin_whitelist')) {
    error(403);
}

if ($_POST['submit'] == 'Delete') {
    if (!is_number($_POST['id']) || $_POST['id'] == '') {
        error(0);
    }

    $DB->query("SELECT peer_id FROM xbt_client_blacklist WHERE id = " . $_POST['id']);
    list($PeerID) = $DB->next_record();
    $DB->query('DELETE FROM xbt_client_blacklist WHERE id=' . $_POST['id']);
    update_tracker('remove_blacklist', array('peer_id' => $PeerID));

} else { //Edit & Create, Shared Validation

    if ($_POST['submit'] == 'Edit') { //Edit
        if (empty($_POST['client']) || empty($_POST['peer_id'])) {
            //error(print_r($_POST, true));
            error("One or more of the fields is blank");
        } elseif (empty($_POST['id']) || !is_number($_POST['id'])) {
            error(0);
        } else {
            $Client = db_string($_POST['client']);
            $PeerID = db_string($_POST['peer_id']);

            $DB->query("SELECT peer_id FROM xbt_client_blacklist WHERE id = " . $_POST['id']);
            list($OldPeerID) = $DB->next_record();
            $DB->query("UPDATE xbt_client_blacklist SET
                vstring='" . $Client . "',
                peer_id='" . $PeerID . "'
                WHERE ID=" . $_POST['id']);
            update_tracker('edit_blacklist', array('old_peer_id' => $OldPeerID, 'new_peer_id' => $PeerID));
        }
    } else { //Create
        $Values = array();
        $PeerIDs = array();

        if ($_POST['submit'] == 'Create') {
            if (empty($_POST['client']) || empty($_POST['peer_id'])) {
                error("One or more of the fields is blank");
            }
            $PeerID = db_string($_POST['peer_id']);
            $Client = db_string($_POST['client']);
        } else {

            if (empty($_POST['clients'])) error("Clients field is blank");
            $Clients = str_replace( array("\r\n", "\r"), "\n", $_POST['clients']);
            $Clients = explode("\n", $Clients);

            $clientinfo = trim($Clients[0]);
            if (empty($clientinfo)) error("Error parsing input: $clientinfo");
            $first_space = mb_strpos($clientinfo, ' ');
            if ($first_space === false || $first_space >= mb_strlen($clientinfo)-1) {
                error("Incorrectly formatted line: $clientinfo");
            }
            $PeerID = db_string(trim(substr($clientinfo, 0, $first_space)));
            $Client = db_string(trim(substr($clientinfo, $first_space)));
            unset($Clients[0]);
            $Clients = implode("\n", $Clients);
        }

        $DB->query("SELECT id FROM xbt_client_blacklist WHERE peer_id = '$PeerID'" );
        if ($DB->record_count()>0) error("There is already an entry in the blacklist with peer_id=$PeerID");

        $DB->query("INSERT INTO xbt_client_blacklist
                            (vstring, peer_id)
                     VALUES ('" . $Client . "','" . $PeerID . "')");

        update_tracker('add_blacklist', array('peer_id' => $PeerID));
    }
}

$Cache->delete('blacklisted_clients');

include(SERVER_ROOT.'/sections/tools/managers/blacklist_list.php');
