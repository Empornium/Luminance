<?php
enforce_login();

function get_votes_array($RequestID)
{
    global $master;

    $RequestVotes = $master->cache->get_value('request_votes_'.$RequestID);
    if (!is_array($RequestVotes)) {

        $votes = $master->db->raw_query("SELECT rv.UserID,
                                                u.Username,
                                                rv.Bounty
                                           FROM requests_votes as rv
                                      LEFT JOIN users_main AS u ON u.ID=rv.UserID
                                          WHERE rv.RequestID = :requestid
                                       ORDER BY rv.Bounty DESC",
                                               [':requestid' => $RequestID])->fetchAll(\PDO::FETCH_ASSOC);
        $RequestVotes = array();
        if (count($votes)>0) {
            $RequestVotes['TotalBounty'] = array_sum(array_column($votes, 'Bounty'));
            $RequestVotes['Voters'] = $votes;
            $master->cache->cache_value('request_votes_'.$RequestID, $RequestVotes);
        }
    }
    return $RequestVotes;
}

function get_votes_html($RequestVotes, $RequestID)
{
    global $LoggedUser;

    ob_start();
?>
    <table class="box box_votes">
<?php
    $VoteCount = count($RequestVotes['Voters']);

    $VoteMax = ($VoteCount < 10 ? $VoteCount : 10);
    $ViewerVote = false;
    for ($i = 0; $i < $VoteMax; $i++) {
        $User = array_shift($RequestVotes['Voters']);
        $Boldify = false;
        if ($User['UserID'] == $LoggedUser['ID']) {
            $ViewerVote = true;
            $Boldify = true;
        }
?>
                <tr>
                    <td>
                        <a href="user.php?id=<?=$User['UserID']?>"><?=$Boldify?'<strong>':''?><?=display_str($User['Username'])?><?=$Boldify?'</strong>':''?></a>
                    </td>
                    <td>
                        <?=$Boldify?'<strong>':''?><?=get_size($User['Bounty'])?><?=$Boldify?'</strong>':''?>
                    </td>
<?php       if (check_perms("site_moderate_requests")) { ?>
                    <td>
                        <a href="requests.php?action=delete_vote&amp;id=<?=$RequestID?>&amp;auth=<?=$LoggedUser['AuthKey']?>&amp;voterid=<?=$User['UserID']?>">[-]</a>
                    </td>
                </tr>
<?php 	    }
        }
    reset($RequestVotes['Voters']);
    if (!$ViewerVote) {
        foreach ($RequestVotes['Voters'] as $User) {
            if ($User['UserID'] == $LoggedUser['ID']) { ?>
                <tr>
                    <td>
                        <a href="user.php?id=<?=$User['UserID']?>"><strong><?=display_str($User['Username'])?></strong></a>
                    </td>
                    <td>
                        <strong><?=get_size($User['Bounty'])?></strong>
                    </td>
                </tr>
<?php 			}
        }
    }
?>
    </table>
<?php

    $html = ob_get_contents();
    ob_end_clean();

    return $html;
}
