<?php
enforce_login();

if (!defined('LOG_ENTRIES_PER_PAGE')) {
    define('LOG_ENTRIES_PER_PAGE', 25);
}
list($Page, $Limit) = page_limit(LOG_ENTRIES_PER_PAGE);

$_GET['search'] = trim($_GET['search'] ?? '');

if (!empty($_GET['search'])) {
    $Search = ($_GET['search']);
} else {
    $Search = false;
}

$sql = "SELECT
    SQL_CALC_FOUND_ROWS
    Message,
    Time
    FROM log ";
$params = [];
if ($Search) {
    $Search = preg_replace('/\(/', '\(', $Search);
    $Search = preg_replace('/\)/', '\)', $Search);
    // Break search string down into individual words
    $Words = explode(' ',  $Search);
    foreach ($Words as $Key => &$Word) {
        $Word = trim($Word);

        // Should we do this for log search?
        $slen = strlen($Word);
        if ($slen <= 2 && !in_array($Word, ['!', '.*'])) {
            unset($Words[$Key]);
        }
    }
    $sql .= "WHERE Message REGEXP ? ";
    $params[] = implode(" ", $Words);
}
if (!check_perms('site_view_full_log')) {
    if ($Search) {
        $sql.=" AND ";
    } else {
        $sql.=" WHERE ";
    }
    $sql .= " Time>'".time_minus(3600*24*28)."' ";
}

$sql .= "ORDER BY Time DESC LIMIT {$Limit}";

show_header("Site log");
$logs = $master->db->rawQuery($sql, $params)->fetchAll(\PDO::FETCH_BOTH);
$Results = $master->db->foundRows();

?>
<div class="thin">
    <h2>Site log</h2>
        <form action="" method="get">
            <table cellpadding="6" cellspacing="1" border="0" class="border" width="100%">
                <tr>
                    <td class="label"><strong>Search for:</strong></td>
                    <td>
                        <input type="text" name="search" size="60"<?php  if (!empty($_GET['search'])) { echo ' value="'.display_str($_GET['search']).'"'; } ?> />
                        &nbsp;
                        <input type="submit" value="Search log" />
                    </td>
                </tr>
            </table>
        </form>

    <div class="linkbox pager">
<?php
$Pages = get_pages($Page, $Results, LOG_ENTRIES_PER_PAGE, 9);
echo $Pages;
?>
    </div>

    <table cellpadding="6" cellspacing="1" border="0" class="border" width="100%">
        <tr class="colhead">
            <td style="width: 180px;"><strong>Time</strong></td>
            <td><strong>Message</strong></td>
        </tr>

<?php
if ($Results === 0) {
    echo '<tr class="nobr"><td colspan="2">Nothing found!</td></tr>';
}
$Row = 'a';
$Usernames = [];
$HideName = !check_perms('site_view_uploaders');

foreach ($logs as $log) {
    $log['Message'] = display_str($log['Message']);
    $MessageParts = explode(" ", $log['Message']);
    $log['Message'] = "";
    $Color = $Colon = false;
    //$HideName=true;
    for ($i = 0, $PartCount = sizeof($MessageParts); $i < $PartCount; $i++) {
        if ((strpos($MessageParts[$i], 'https://'.SSL_SITE_URL) === 0 && $Offset = strlen('https://'.SSL_SITE_URL.'/')) ||
            (strpos($MessageParts[$i], 'http://'.NONSSL_SITE_URL) === 0 && $Offset = strlen('http://'.NONSSL_SITE_URL.'/'))) {
            // Avoid the creation of external links
            $link = preg_replace('%^/+%', '', substr($MessageParts[$i], $Offset));
            $MessageParts[$i] = '<a href="/'.$link.'">'.$link.'</a>';
        }
        switch ($MessageParts[$i]) {
            case "article":
                $articleID = $MessageParts[$i + 1];
                if (is_numeric($articleID)) {
                    $log['Message'] = $log['Message'].' '.$MessageParts[$i].' <a href="/wiki.php?action=article&id='.$articleID.'"> '.$articleID.'</a>';
                    $i++;
                } else {
                    $log['Message'] = $log['Message'].' '.$MessageParts[$i];
                }
                break;
            case "list:":
            case "Tag": // are Tag/tag used?
            case "tag":
            case "Synonym":
            case "synonym":
                $Tag = $MessageParts[$i + 1];
                if (is_string($Tag)) {
                    $log['Message'] = $log['Message'].' '.$MessageParts[$i].' <a href="/torrents.php?taglist='.str_replace(',', '', $Tag).'"> '.$Tag.'</a>';
                    $i++;
                } else {
                    $log['Message'] = $log['Message'].' '.$MessageParts[$i];
                }
                break;
            case "Torrent":
            case "torrent":
                $HideName = true;
                $torrentID = $MessageParts[$i + 1];
                //$torrentID = trim($torrentID, ',');
                if (is_numeric($torrentID)) {
                    $HideThisName = $master->db->rawQuery(
                        "SELECT Anonymous
                           FROM torrents
                          WHERE id = ?",
                        [$torrentID]
                    )->fetchColumn();

                    $HideName = $HideThisName || $HideName;
                    $log['Message'] = $log['Message'].' '.$MessageParts[$i].' <a href="/torrents.php?torrentid='.$torrentID.'"> '.$torrentID.'</a>';
                    $i++;
                } else {
                    $log['Message'] = $log['Message'].' '.$MessageParts[$i];
                }
                break;
            case "Torrents":
            case "torrents": // actually groups but call it torrents to not overely confuse user
                        $torrentIDs = explode(',', $MessageParts[$i + 1]);
                        $Links='';
                        $Div='';
                        foreach ($torrentIDs as $torrentID) {
                            if (is_numeric($torrentID)) {
                                    $Links .= $Div .'<a href="/torrents.php?torrentid='.$torrentID.'">'.$torrentID.'</a>';
                                    $Div=', ';
                            }
                        }
                $log['Message'] = "{$log['Message']} {$MessageParts[$i]} {$Links}";
                        if ($Links != '') $i++;
                break;
            case "Request":
                //$HideName = true;
                $RequestID = $MessageParts[$i + 1];
                if (is_numeric($RequestID)) {
                    $log['Message'] = $log['Message'].' '.$MessageParts[$i].' <a href="/requests.php?action=view&id='.$RequestID.'"> '.$RequestID.'</a>';
                    $i++;
                } else {
                    $log['Message'] = $log['Message'].' '.$MessageParts[$i];
                }
                break;
            case "group":
            case "Group":
                $GroupID = $MessageParts[$i + 1];
                if (is_numeric($GroupID)) {
                    $log['Message'] = $log['Message'].' '.$MessageParts[$i].' <a href="/torrents.php?id='.$GroupID.'"> '.$GroupID.'</a>';
                } else {
                    $log['Message'] = $log['Message'].' '.$MessageParts[$i];
                }
                $i++;
                break;
            case "by":
                $userID = 0;
                $User = "";
                $URL = "";
                $HideThisName = (($HideName && !check_perms('users_view_anon_uploaders')) && $MessageParts[$i-1] == 'uploaded');

                if (!$HideThisName) {

                    if ($MessageParts[$i + 1] == "user") {
                        $i++;
                        if (is_numeric($MessageParts[$i + 1])) {
                            $userID = $MessageParts[++$i];
                        }
                        $Username = $master->db->rawQuery(
                            "SELECT Username
                               FROM users
                              WHERE ID = ?",
                            [$userID]
                        )->fetchColumn();
                        $URL = "<a href=\"/user.php?id={$userID}\">{$Username}</a>";
                    } else {
                        $User = implode(' ', array_slice($MessageParts, $i+1));
                        preg_match('/^(.*?)( was| for| with|$)/', $User, $matches);
                        $User = trim($matches[1]);
                        $UserWords = count(explode(' ', $User));
                        $i = $i + $UserWords;
                        if (substr($User,-1) == ':') {
                            $User = substr($User, 0, -1);
                            $Colon = true;
                        }
                        if (!isset($Usernames[$User])) {
                            $userID = $master->db->rawQuery(
                                "SELECT ID
                                   FROM users
                                  WHERE Username = ?",
                                [$User]
                            )->fetchColumn();
                            $Usernames[$User] = $userID ? $userID : '';
                        } else {
                            $userID = $Usernames[$User];
                        }
                        $URL = $Usernames[$User] ? '<a href="/user.php?id='.$userID.'">'.$User."</a>".($Colon?':':'') : $User;
                    }

                } else {
                    $URL = "User";
                    $MessageParts[$i + 1] = '';
                }
                $log['Message'] = $log['Message']." by ".$URL;
                break;
            case "converted":
                if ($Color === false) {
                    $Color = 'purple';
                }
                $log['Message'] = $log['Message']." ".$MessageParts[$i];
                break;
            case "Awarding":
                if ($Color === false) {
                    $Color = 'purple';
                }
                $log['Message'] = $log['Message']." ".$MessageParts[$i];
                break;
            case "uploaded":
            case "created":
                if ($Color === false) {
                    $Color = 'blue';
                }
                $log['Message'] = $log['Message']." ".$MessageParts[$i];
                break;
            case "Okay":
                $HideName = false;
                if ($Color === false) {
                    $Color = 'green';
                }
                $log['Message'] = $log['Message']." ".$MessageParts[$i];
                break;
            case "Warned":
                $HideName = false;
                $Color = '#a07100';
                $log['Message'] = $log['Message']." ".$MessageParts[$i];
                break;
            case "deleted":
            case "auto-deleted":
                $Color = 'red';
                $log['Message'] = $log['Message']." ".$MessageParts[$i];
                break;
            case "edited":
                if ($Color === false) {
                    $Color = '#1E90FF';
                }
                $log['Message'] = $log['Message']." ".$MessageParts[$i];
                break;
            case "un-filled":
                if ($Color === false) {
                    $Color = '';
                }
                $log['Message'] = $log['Message']." ".$MessageParts[$i];
                break;
            case "marked":
                if ($i == 1) {
                    $User = $MessageParts[$i - 1];
                    if (!isset($Usernames[$User])) {
                        $userID = $master->db->rawQuery(
                            "SELECT ID
                               FROM users
                              WHERE Username = ?",
                            [$User]
                        )->fetchColumn();
                        $Usernames[$User] = $userID ? $userID : '';
                    } else {
                        $userID = $Usernames[$User];
                    }
                    $URL = $Usernames[$User] ? '<a href="/user.php?id='.$userID.'">'.$User."</a>" : $User;
                    $log['Message'] = $URL." ".$MessageParts[$i];
                } else {
                    $log['Message'] = $log['Message'].' '.$MessageParts[$i];
                }
                break;
            case "Collage":
                $CollageID = $MessageParts[$i + 1];
                if (is_numeric($CollageID)) {
                    $log['Message'] = $log['Message'].' '.$MessageParts[$i].' <a href="/collage/'.$CollageID.'"> '.$CollageID.'</a>';
                    $i++;
                } else {
                    $log['Message'] = $log['Message'].' '.$MessageParts[$i];
                }
                break;
            default:
                $log['Message'] = $log['Message']." ".$MessageParts[$i];
        }
    }
    $Row = ($Row == 'a') ? 'b' : 'a';
?>
        <tr class="row<?=$Row?>">
            <td class="nobr">
                <?=time_diff($log['Time'])?>
            </td>
            <td>
                <span<?php  if ($Color) { ?> style="color: <?=$Color ?>;"<?php  } ?>><?=$log['Message']?></span>
            </td>
        </tr>
<?php
}
?>
    </table>
    <div class="linkbox pager">
        <?=$Pages?>
    </div>
</div>
<?php
show_footer();
