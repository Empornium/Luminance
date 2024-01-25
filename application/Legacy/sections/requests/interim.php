<?php
if (!isset($_GET['id']) || !is_integer_string($_GET['id'])) { error(404); }
$RequestID = $_GET['id'];

$Action = $_GET['action'];
if ($Action != "unfill" && $Action != "delete" && $Action != "delete_vote") {
    error(404);
}
$Title = ucwords(str_replace('_', ' ', $Action));

list($RequestorID, $FillerID) = $master->db->rawQuery(
    "SELECT UserID,
            FillerID
       FROM requests
      WHERE ID = ?",
    [$_GET['id']]
)->fetch(\PDO::FETCH_NUM);

if ($Action == 'unfill') {
    if ($activeUser['ID'] != $RequestorID && $activeUser['ID'] != $FillerID && !check_perms('site_moderate_requests')) {
        error(403);
    }
} elseif ($Action == "delete" || $Action == "delete_vote") {
    if (!check_perms('site_moderate_requests')) {
        error(403);
    }
}

$Request = get_requests([$RequestID]);
$Request = $Request['matches'][$RequestID];
if (empty($Request)) {
    error(404);
}

show_header(ucwords($Title)." Request");
?>
<div class="thin middle_column">
    <div style="width:800px;margin:20px auto;">
        <div class="head">
            <?=ucwords($Title)?> Request
        </div>
        <form action="requests.php" method="post">
            <table class="box pad">
                <tr>
                    <td class="label">
                        <img style="float:right" src="<?=( 'static/common/caticons/' . $newCategories[$Request['CategoryID']]['image'])?>" />
                    </td>
                    <td style="font-size: 1.2em;font-weight:bold;">
                        <?=$Request['Title']?>
                    </td>
                </tr>
                <tr>
                    <td class="label">Votes</td>
                    <td>
                        <input type="hidden" name="action" value="take<?=$Action?>" />
                        <input type="hidden" name="auth" value="<?=$activeUser['AuthKey']?>" />
                        <input type="hidden" name="id" value="<?=$RequestID?>" />
        <?php  if ($Action == 'delete') { ?>
                        <div class="warning">To return all bounties to users make sure the 'Return Bounties' option is checked.</div>
        <?php
                        echo get_votes_html(get_votes_array($RequestID), $RequestID);
        ?>
                        <input type="checkbox" name="returnvotes" checked="checked" value="1" /> Return all Bounties to voters.<br \>
                        (When bounties are returned all voters will get a 'returned bounty' system PM, the request uploader always receives a 'deleted request' system PM)<br />
        <?php  } elseif ($Action == 'unfill') { ?>
                        <div class="warning">Unfilling a request without a valid, nontrivial reason will result in a warning.<br/>If in doubt please message the staff and ask for advice first.</div>
        <?php  } elseif ($Action == 'delete_vote') { ?>
                        <input type="hidden" name="voterid" value="<?=$_GET['voterid']?>" />
                        <div class="warning">This will return the user's bounty and, if this is the last vote, it will delete the request.</div>
                        (This user will get a 'returned bounty' system PM, if this deletes the request the uploader will also receive a 'deleted request' system PM)<br />
        <?php  } ?>
                    </td>
                </tr>
                <tr>
                    <td class="label"><strong>Reason: (required)</strong></td>
                    <td>
                        <textarea name="reason" class="long" rows="8"/></textarea>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" class=center>
                        <input value="<?=$Title?>" type="submit" />
                    </td>
                </tr>
            </table>
        </form>
        <div class="box pad">
        </div>
    </div>
</div>
<?php
show_footer();
