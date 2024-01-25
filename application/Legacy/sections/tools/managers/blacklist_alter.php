<?php

authorize();

if (!check_perms('admin_whitelist')) {
    error(403);
}

if ($_POST['submit'] == 'Delete') {
    if (!is_integer_string($_POST['id']) || $_POST['id'] == '') {
        error(0);
    }

    $PeerID = $master->db->rawQuery("SELECT peer_id FROM xbt_client_blacklist WHERE id = ?", [$_POST['id']])->fetchColumn();
    $master->db->rawQuery('DELETE FROM xbt_client_blacklist WHERE id= ?', [$_POST['id']]);
    $master->tracker->removeBlacklist($PeerID);

} else { //Edit & Create, Shared Validation

    if ($_POST['submit'] == 'Edit') { //Edit
        if (empty($_POST['client']) || empty($_POST['peer_id'])) {
            //error(print_r($_POST, true));
            error("One or more of the fields is blank");
        } elseif (empty($_POST['id']) || !is_integer_string($_POST['id'])) {
            error(0);
        } else {
            $Client = $_POST['client'];
            $PeerID = $_POST['peer_id'];

            $OldPeerID = $master->db->rawQuery("SELECT peer_id FROM xbt_client_blacklist WHERE id = ?", [$_POST['id']])->fetchColumn();
            $master->db->rawQuery(
                "UPDATE xbt_client_blacklist
                    SET vstring = ?,
                        peer_id = ?
                  WHERE ID=" . $_POST['id'],
                [$Client, $PeerID, $_POST['id']]
            );
            $master->tracker->editBlacklist($OldPeerID, $PeerID);
        }
    } else { //Create
        $values = [];
        $PeerIDs = [];

        if ($_POST['submit'] == 'Create') {
            if (empty($_POST['client']) || empty($_POST['peer_id'])) {
                error("One or more of the fields is blank");
            }
            $PeerID = $_POST['peer_id'];
            $Client = $_POST['client'];
        } else {

            if (empty($_POST['clients'])) error("Clients field is blank");
            $Clients = str_replace(["\r\n", "\r"], "\n", $_POST['clients']);
            $Clients = explode("\n", $Clients);

            $clientinfo = trim($Clients[0]);
            if (empty($clientinfo)) error("Error parsing input: $clientinfo");
            $first_space = mb_strpos($clientinfo, ' ');
            if ($first_space === false || $first_space >= mb_strlen($clientinfo)-1) {
                error("Incorrectly formatted line: $clientinfo");
            }
            $PeerID = trim(substr($clientinfo, 0, $first_space));
            $Client = trim(substr($clientinfo, $first_space));
            unset($Clients[0]);
            $Clients = implode("\n", $Clients);
        }

        $master->db->rawQuery("SELECT id FROM xbt_client_blacklist WHERE peer_id = ?", [$PeerID]);
        if ($master->db->foundRows() > 0) error("There is already an entry in the blacklist with peer_id={$PeerID}");

        $master->db->rawQuery(
            "INSERT INTO xbt_client_blacklist (vstring, peer_id)
                  VALUES (?, ?)",
            [$Client, $PeerID]
        );

        $master->tracker->addBlacklist($PeerID);
    }
}

$master->cache->deleteValue('blacklisted_clients');

include(SERVER_ROOT.'/Legacy/sections/tools/managers/blacklist_list.php');
