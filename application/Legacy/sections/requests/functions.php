<?php

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
                        <a href="/user.php?id=<?=$User['UserID']?>"><?=$Boldify?'<strong>':''?><?=display_str($User['Username'])?><?=$Boldify?'</strong>':''?></a>
                    </td>
                    <td>
                        <?=$Boldify?'<strong>':''?><?=get_size($User['Bounty'])?><?=$Boldify?'</strong>':''?>
                    </td>
<?php       if (check_perms("site_moderate_requests")) { ?>
                    <td>
                        <a href="/requests.php?action=delete_vote&amp;id=<?=$RequestID?>&amp;auth=<?=$LoggedUser['AuthKey']?>&amp;voterid=<?=$User['UserID']?>">[-]</a>
                    </td>
                </tr>
<?php 	    }
        }
    if (!$ViewerVote && !empty($RequestVotes['Voters'])) {
        reset($RequestVotes['Voters']);
        foreach ($RequestVotes['Voters'] as $User) {
            if ($User['UserID'] == $LoggedUser['ID']) { ?>
                <tr>
                    <td>
                        <a href="/user.php?id=<?=$User['UserID']?>"><strong><?=display_str($User['Username'])?></strong></a>
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

function expired_pm($BountyInfo, $debug = false)
{
    global $master;

    list($RequestID, $Title, $UserID, $Bounty, $OwnerID, $Description, $CategoryID, $Image) = $BountyInfo;

    $Tags = $master->db->raw_query(
            "SELECT DISTINCT t.Name FROM tags AS t LEFT JOIN requests_tags AS rt ON t.ID = rt.TagID WHERE rt.RequestID = ?",
            [$RequestID])->fetchAll(\PDO::FETCH_COLUMN);

    if (!empty($Tags)) {
        $Tags = implode(" ", $Tags);
    } else {
        $Tags = '';
    }

    // Default PM texts
    $Subject = "Bounty returned from expired request";
    $Body    = "Your bounty of ".get_size($Bounty)." has been returned from the expired request [b]{$Title}[/b].";

    $Extra = [];

    // If user is the owner of the request,
    // we create a special body for them
    if ((int) $UserID === (int) $OwnerID) {
        $QueryData = [
            'action' => 'new',
            'title'  => $Title,
            'image'  => $Image,
            'bounty' => $Bounty,
            'category_id' => $CategoryID
        ];

        // If the description is too long, we can't use it in a HTTP GET request,
        // we just provide it to the user
        if (strlen($Description) <= 2048) {
            $QueryData['description'] = $Description;
        } else {
            $Extra[] = "[*]Your description being too large, you will have to paste it yourself:[br][code]{$Description}[/code]";
        }

        // If the tags are too long, we can't use them in a HTTP GET request,
        // we just provide them to the user
        if (strlen($Tags) <= 2048) {
            $QueryData['tags'] = $Tags;
        } else {
            $Extra[] = "[*]Your tags being too large, you will have to paste them yourself:[br][code]{$Tags}[/code]";
        }

        $Url  = "/requests.php?";
        $Url .= http_build_query($QueryData);

        $Body .= "[br][br]";
        $Body .= "You can re-create it using the following link: [url={$Url}]Create the request again[/url]";

        if (!empty($Extra)) {
            $Body .= "[br][br]";
            $Body .= implode("[br][br]", $Extra);
        }
    }


    if ($debug) {
        return compact('Subject', 'Body');
    }

    return send_pm($UserID, 0, db_string($Subject), db_string($Body));
}
