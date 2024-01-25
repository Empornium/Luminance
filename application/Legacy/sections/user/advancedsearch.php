<?php
/**********************************************************************
 *>>>>>>>>>>>>>>>>>>>>>>>>>>> User search <<<<<<<<<<<<<<<<<<<<<<<<<<<<*
 * Best viewed with a wide screen monitor							 *
 **********************************************************************/

// Cleaner search URLs
if ($master->server['REQUEST_METHOD'] == 'POST') {
    $query = '';
    foreach ($_POST as $key => $var) {
        if (!empty($var)) {
            $query .= "&{$key}={$var}";
        }
    }
    header('Location: user.php?action=search'.$query);
}

$_GET['search'] = trim($_GET['search'] ?? '');
$_GET['matchtype'] = $_GET['matchtype'] ?? 'fuzzy';

if (!empty($_GET['search'])) {
    if (validate_ip($_GET['search']) && check_perms('users_view_ips')) {
        $_GET['ip'] = $_GET['search'];
        $_GET['ip_history'] = 'on';
    } elseif (preg_match("/^".EMAIL_REGEX."$/i", $_GET['search']) && check_perms('users_view_email')) {
        $_GET['email'] = $_GET['search'];
    } elseif (preg_match('/^[a-z0-9_?\-\.]{1,20}$/iD', $_GET['search'])) {
        $ID = $master->db->rawQuery(
            "SELECT ID
               FROM users
              WHERE Username = ?",
            [$_GET['search']]
        )->fetchColumn();
        if ($ID) {
            header('Location: user.php?id='.$ID);
            die();
        }
        $_GET['username'] = $_GET['search'];
    } else {
        $_GET['comment'] = $_GET['search'];
    }
}

define('USERS_PER_PAGE', 30);

function wrap($String, $ForceMatch = '', $IPSearch = false) {
    $placeholder = '?';
    if (!$ForceMatch) {
        global $match;
    } else {
        $match = $ForceMatch;
    }
    if ($match == ' REGEXP ') {
        if (strpos($String, '\'') || preg_match('/^.*\\\\$/i', $String)) {
            error('Regex contains illegal characters.');
        }
        $placeholder = '?';
    }

    if ($match == ' LIKE ') {
        // Fuzzy search
        // Stick in wildcards at beginning and end of string unless string starts or ends with |
        $prefix = '';
        if ((!(str_starts_with($String, '|'))) && ($IPSearch === false)) {
            $prefix = '%';
        } else if (str_starts_with($String, '|')) {
            $prefix = '';
            $String = substr($String, 1);
        }

        $suffix = '';
        if (!(str_ends_with($String, '|'))) {
            $suffix = '%';
        } else {
            $suffix = '';
            $String = substr($String, 0, -1);
        }

        $placeholder = "CONCAT ('{$prefix}', ?, '{$suffix}')";
    }

    return [$placeholder, $String];
}

function date_compare($Field, $Operand, $Date1, $Date2 = '')
{
    $Return = [];
    $params = [];

    switch ($Operand) {
        case 'on':
            $Return[] = "{$Field} >= ?";
            $Return[] = "{$Field} <= ?";
            $params[] = "{$Date1} 00:00:00";
            $params[] = "{$Date1} 23:59:59";
            break;
        case 'before':
            $Return[] = "{$Field} < ?";
            $params[] = "{$Date1} 00:00:00";
            break;
        case 'after':
            $Return[] = "{$Field} > ?";
            $params[] = "{$Date1} 23:59:59";
            break;
        case 'between':
            $Return[] = "{$Field} >= ?";
            $Return[] = "{$Field} <= ?";
            $params[] = "{$Date1} 00:00:00";
            $params[] = "{$Date2} 00:00:00";
            break;
    }

    return [implode(' AND ', $Return), $params];
}

function num_compare($Field, $Operand, $Num1, $Num2 = '')
{
    $Return = [];
    $params = [];

    switch ($Operand) {
        case 'equal':
            $Return[] = "{$Field} = ?";
            $params[] = $Num1;
            break;
        case 'above':
            $Return[] = "{$Field} > ?";
            $params[] = $Num1;
            break;
        case 'below':
            $Return[] = "{$Field} < ?";
            $params[] = $Num1;
            break;
        case 'between':
            $Return[] = "{$Field} > ?";
            $Return[] = "{$Field} < ?";
            $params[] = $Num1;
            $params[] = $Num2;
            break;
        default:
            print_r($Return);
            die();
    }

    return [implode(' AND ', $Return), $params];
}

// Arrays, regexes, and all that fun stuff we can use for validation, form generation, etc

$DateChoices = ['inarray' => ['on', 'before', 'after', 'between']];
$SingleDateChoices = ['inarray' => ['on', 'before', 'after']];
$NumberChoices = ['inarray' => ['equal', 'above', 'below', 'between', 'buffer']];
$YesNo = ['inarray' => ['any', 'yes', 'no']];
$OrderVals = ['inarray' => ['Username', 'Ratio', 'IP', 'Email', 'Joined', 'Last Seen', 'Uploaded', 'Downloaded', 'Invites', 'Snatches']];
$WayVals = ['inarray' => ['Ascending', 'Descending']];

$Stylesheets = $master->repos->stylesheets->getAll();


if (count($_GET)) {
    $DateRegex = ['regex'=>'/\d{4}-\d{2}-\d{2}/'];

    $classIDs = [];
    foreach ($classes as $classID => $Value) {
        $classIDs[]=$classID;
    }

    $Val->SetFields('comment', '0', 'string', 'Comment is too long.', ['maxlength'=>512]);

    $Val->SetFields('joined', '0', 'inarray', 'Invalid joined field', $DateChoices);
    $Val->SetFields('join1',  '0', 'regex', 'Invalid join1 field', $DateRegex);
    $Val->SetFields('join2',  '0', 'regex', 'Invalid join2 field', $DateRegex);

    $Val->SetFields('lastactive',  '0', 'inarray', 'Invalid lastactive field', $DateChoices);
    $Val->SetFields('lastactive1', '0', 'regex', 'Invalid lastactive1 field', $DateRegex);
    $Val->SetFields('lastactive2', '0', 'regex', 'Invalid lastactive2 field', $DateRegex);

    $Val->SetFields('ratio', '0', 'inarray', 'Invalid ratio field', $NumberChoices);
    $Val->SetFields('uploaded', '0', 'inarray', 'Invalid uploaded field', $NumberChoices);
    $Val->SetFields('downloaded', '0', 'inarray', 'Invalid downloaded field', $NumberChoices);
    $Val->SetFields('snatched', '0', 'inarray', 'Invalid snatched field', $NumberChoices);

    $Val->SetFields('matchtype', '0', 'inarray', 'Invalid matchtype field', ['inarray' => ['strict', 'fuzzy', 'regex']]);

    $Val->SetFields('enabled', '0', 'inarray', 'Invalid enabled field', ['inarray' => ['', 0, 1, 2]]);
    $Val->SetFields('class', '0', 'inarray', 'Invalid class', ['inarray'=>$classIDs]);
    $Val->SetFields('donor', '0', 'inarray', 'Invalid donor field', $YesNo);

    $Val->SetFields('order', '0', 'inarray', 'Invalid ordering', $OrderVals);
    $Val->SetFields('way', '0', 'inarray', 'Invalid way', $WayVals);

    $Val->SetFields('passkey', '0', 'string', 'Invalid passkey', ['maxlength'=>32]);
    $Val->SetFields('avatar', '0', 'string', 'Avatar URL too long', ['maxlength'=>512]);
    $Val->SetFields('stylesheet', '0', 'inarray', 'Invalid stylesheet',  ['inarray'=>array_unique(array_keys($Stylesheets))]);
    $Val->SetFields('cc', '0', 'string', 'Invalid Country Code', ['maxlength'=>2]);

    $Err = $Val->ValidateForm($_GET);

    if (!$Err) {
        // Passed validation. Let's rock.
        $RunQuery = false; // if we should run the search

        if (isset($_GET['matchtype']) && $_GET['matchtype'] == 'strict') {
            $match = ' = ';
        } elseif (isset($_GET['matchtype']) && $_GET['matchtype'] == 'regex') {
            $match = ' REGEXP ';
        } else {
            $match = ' LIKE ';
        }

        $OrderTable = [
            'Username'    => 'u.Username',
            'Joined'      => 'ui1.JoinDate',
            'Last Seen'   => 'um1.LastAccess',
            'Uploaded'    => 'um1.Uploaded',
            'Downloaded'  => 'um1.Downloaded',
            'Ratio'       => '(um1.Uploaded/um1.Downloaded)',
            'Invites'     => 'um1.Invites',
            'Snatches'    => 'Snatches',
        ];

        if (check_perms('users_view_email')) {
            $OrderTable['Email'] = 'e1.Address';
        }

        if ( check_perms('users_view_ips')) {
             $OrderTable['IP'] = 'ip.StartAddress';
        }

        $WayTable = ['Ascending'=>'ASC', 'Descending'=>'DESC'];

        $Where = [];
        $Having = [];
        $Join = [];
        $Order = '';

        $SQL = 'SQL_CALC_FOUND_ROWS
            um1.ID,
            u.IRCNick,
            um1.Uploaded,
            um1.Downloaded,'.PHP_EOL;
        if (($_GET['snatched'] ?? 'off') == "off") {
            $SQL .= "'X' AS Snatches,".PHP_EOL;
        } else {
            $SQL .= "(SELECT COUNT(uid) FROM xbt_snatched AS xs WHERE xs.uid=um1.ID) AS Snatches,".PHP_EOL;
        }
        $SQL .=
           'um1.PermissionID,
            e1.Address,
            um1.Enabled,
            INET6_NTOA(ip.StartAddress),'.PHP_EOL;
        if (empty($_GET['tracker_ip'] ?? '')) {
            $SQL .= "'' AS TrackerIP1,".PHP_EOL;
        } else {
            $SQL .= "INET6_NTOA(xfu.ipv4) AS TrackerIP1,".PHP_EOL;
        }
        $SQL .=
           'um1.Invites,
            ui1.Donor,
            ui1.JoinDate,
            um1.LastAccess
            FROM users_main AS um1
            JOIN users_info AS ui1 ON ui1.UserID=um1.ID
            JOIN users AS u ON u.ID=um1.ID
            LEFT JOIN ips AS ip ON ip.ID=u.IPID
            LEFT JOIN emails AS e1 ON e1.ID=u.EmailID'.PHP_EOL;
        $searchParams = [];
        $havingParams = [];

        if (!empty($_GET['username'])) {
            list($placeholder, $param) = wrap($_GET['username']);
            $Where[] = "u.Username {$match} {$placeholder}";
            $searchParams[] = $param;
        }

        if (!empty($_GET['irc_nick'])) {
            list($placeholder, $param) = wrap($_GET['irc_nick']);
            $Where[] = "u.IRCNick {$match} {$placeholder}";
            $searchParams[] = $param;
        }

        if (!empty($_GET['email']) && check_perms('users_view_email')) {
            $Join['e2']=' LEFT JOIN emails AS e2 ON e2.UserID=u.ID ';
            list($placeholder, $param) = wrap($_GET['email']);
            $Where[] = "e2.Address {$match} {$placeholder}";
            $searchParams[] = $param;
        }

        if (!empty($_GET['email_cnt']) && check_perms('users_view_email')) {
            $Query = "SELECT UserID FROM emails GROUP BY UserID HAVING COUNT(DISTINCT Address) ";
            if ($_GET['emails_opt'] === 'equal') {
                $operator = '=';
            }
            if ($_GET['emails_opt'] === 'above') {
                $operator = '>';
            }
            if ($_GET['emails_opt'] === 'below') {
                $operator = '<';
            }
            $Query .= "{$operator} ?";
            $userIDs = $master->db->rawQuery($Query, [$_GET['email_cnt']])->fetchAll(\PDO::FETCH_COLUMN);
            if (!empty($userIDs)) {
                $inQuery = implode(', ', array_fill(0, count($userIDs), '?'));
                $Where[] = "um1.ID IN ({$inQuery})";
                $searchParams = array_merge($searchParams, $userIDs);
            }
        }

        $_GET['ip'] = trim($_GET['ip'] ?? '');
        if (!empty($_GET['ip']) && check_perms('users_view_ips')) {
            try {
                $range = \IPLib\Factory::rangeFromString($_GET['ip']);
                if (!is_null($range)) {
                    $ipSearch = new \Luminance\Entities\IP($range->getStartAddress());
                    if (strpos($range, '/') !== false) {
                        list(, $ipSearch->Netmask) = explode('/', (string)$range);
                    }

                    if ($range->getStartAddress() !== $range->getEndAddress()) {
                        $params = [
                            inet_pton($range->getStartAddress()),
                            inet_pton($range->getEndAddress()),
                            inet_pton($range->getEndAddress()),
                        ];

                        $IPs = $master->db->rawQuery(
                            "SELECT ID
                               FROM ips AS ip
                              WHERE StartAddress BETWEEN ? AND ?
                                AND (EndAddress >= ? OR EndAddress IS NULL)",
                            $params
                        )->fetchAll(\PDO::FETCH_COLUMN);

                    } else {
                        $params = [
                            inet_pton($range->getStartAddress()),
                        ];

                        $IPs = $master->db->rawQuery(
                            "SELECT ID
                               FROM ips AS ip
                              WHERE StartAddress = ?",
                            $params
                        )->fetchAll(\PDO::FETCH_COLUMN);
                    }

                    if (!empty($IPs)) {
                        $params = $IPs;
                        $inQuery = implode(', ', array_fill(0, count($IPs), '?'));

                        $sql = "SELECT u.ID
                                  FROM users AS u
                                  WHERE u.IPID IN ({$inQuery})";

                        if (isset($_GET['ip_history'])) {
                            $sql .= " UNION DISTINCT
                                     SELECT uhi.UserID
                                       FROM users_history_ips AS uhi
                                      WHERE uhi.IPID IN ({$inQuery})";
                            $params = array_merge($params, $IPs);
                        }

                        $IPUsers = $master->db->rawQuery(
                            $sql,
                            $params
                        )->fetchAll(\PDO::FETCH_COLUMN);
                    }

                    if (!empty($IPUsers)) {
                        $inQuery = implode(', ', array_fill(0, count($IPUsers), '?'));
                        $IPWhere = "u.ID IN ({$inQuery})"; // u p here
                        $Where[] = $IPWhere;
                        $searchParams = array_merge($searchParams, $IPUsers);
                    }
                } else {
                    $master->flasher->error("Invalid search IP!");
                }
            } catch(\Luminance\Errors\InternalError $e) {}
        }

        if (!empty($_GET['cc'])) {
            if ($_GET['cc_op'] == "equal") {
                $Where[] = "um1.ipcc = ?";
            } else {
                $Where[] = "um1.ipcc != ?";
            }
            $searchParams[] = $_GET['cc'];
        }

        if (!empty($_GET['tracker_ip']) && check_perms('users_view_ips')) {
            $Join['xfu'] = ' JOIN xbt_files_users AS xfu ON um1.ID = xfu.uid ';
            list($placeholder, $param) = wrap($_GET['tracker_ip'], '', true);
            $Where[] = "INET6_NTOA(xfu.ipv4) {$match} {$placeholder}";
            $searchParams[] = $param;
        }

        if (!empty($_GET['comment'])) {
            list($placeholder, $param) = wrap($_GET['comment']);
            $Where[] = "ui1.AdminComment {$match} {$placeholder}";
            $searchParams[] = $param;
        }


        if (!empty($_GET['invites1'])) {
            $Invites1 = round($_GET['invites1'] ?? 0);
            $Invites2 = round($_GET['invites2'] ?? 0);
            list($placeholders, $params) = num_compare('Invites', $_GET['invites'], $Invites1, $Invites2);
            $Where[] = $placeholders;
            $searchParams = array_merge($searchParams, $params);
        }

        if (!empty($_GET['join1'])) {
            list($placeholders, $params) = date_compare('ui1.JoinDate', $_GET['joined'], $_GET['join1'], $_GET['join2']);
            $Where[] = $placeholders;
            $searchParams = array_merge($searchParams, $params);
        }

        if (!empty($_GET['lastactive1'])) {
            list($placeholders, $params) = date_compare('um1.LastAccess', $_GET['lastactive'], $_GET['lastactive1'], $_GET['lastactive2']);
            $Where[] = $placeholders;
            $searchParams = array_merge($searchParams, $params);
        }

        if (!empty($_GET['ratio1'])) {
            $Decimals = strlen(array_pop(explode('.', $_GET['ratio1'])));
            if (!$Decimals) { $Decimals = 0; }

            list($placeholders, $params) = num_compare("ROUND(Uploaded / Downloaded, {$Decimals})", $_GET['ratio'], $_GET['ratio1'], $_GET['ratio2']);
            $Where[] = $placeholders;
            $searchParams = array_merge($searchParams, $params);
        }

        if (!empty($_GET['uploaded1'])) {
            $Upload1 = round($_GET['uploaded1'] ?? 0);
            $Upload2 = round($_GET['uploaded2'] ?? 0);
            if ($_GET['uploaded']!='buffer') {
                list($placeholders, $params) = num_compare("ROUND(Uploaded / (1024 ** 3))", $_GET['uploaded'], $Upload1, $Upload2);
                $Where[] = $placeholders;
                $searchParams = array_merge($searchParams, $params);
            } else {
                // why 1023?
                list($placeholders, $params) = num_compare("ROUND((Uploaded / (1024 ** 3)) - (Downloaded / (1024 ** 2) / 1023))", 'between', $Upload1 * 0.9, $Upload1 * 1.1);
                $Where[] = $placeholders;
                $searchParams = array_merge($searchParams, $params);
            }
        }

        if (!empty($_GET['downloaded1'])) {
            $Download1 = round($_GET['downloaded1'] ?? 0);
            $Download2 = round($_GET['downloaded2'] ?? 0);
            list($placeholders, $params) = num_compare("ROUND(Downloaded / (1024 ** 3))", $_GET['downloaded'], $Download1, $Download2);
            $Where[] = $placeholders;
            $searchParams = array_merge($searchParams, $params);
        }

        if (!empty($_GET['snatched1'])) {
            $Snatched1 = round($_GET['snatched1'] ?? 0);
            $Snatched2 = round($_GET['snatched2'] ?? 0);
            list($placeholders, $params) = num_compare("Snatches", $_GET['snatched'], $Snatched1, $Snatched2);
            $Having[] = $placeholders;
            $havingParams = array_merge($havingParams, $params);
        }

        if (!empty($_GET['enabled'])) {
            list($placeholder, $param) = wrap($_GET['enabled'], '=');
            $Where[] = "um1.Enabled = {$placeholder}";
            $searchParams[] = $param;
        }

        if (!empty($_GET['class'])) {
            list($placeholder, $param) = wrap($_GET['class'], '=');
            if ($classes[$_GET['class']]['IsUserClass'] == '1') {
                $Where[] = "um1.PermissionID = {$placeholder}";
            } else {
                $Where[] = "um1.GroupPermissionID = {$placeholder}";
            }
            $searchParams[] = $param;
        }

        $_GET['donor'] = ($_GET['donor'] ?? '');
        if ($_GET['donor'] == 'yes') {
            $Where[]='ui1.Donor=\'1\'';
        } elseif ($_GET['donor'] == 'no') {
            $Where[]='ui1.Donor=\'0\'';
        }

        if ($_GET['disabled_ip'] ?? false) {
            if ($_GET['ip_history']) {
                if (!isset($Join['hi'])) {
                    $Join['hi']=' JOIN users_history_ips AS hi ON hi.UserID=um1.ID ';
                }
                $Join['hi2']=' JOIN users_history_ips AS hi2 ON hi2.IPID=hi.IPID ';
                $Join['um2']=' JOIN users_main AS um2 ON um2.ID=hi2.UserID AND um2.Enabled=\'2\' ';
            } else {
                $Join['um2']=' JOIN users_main AS um2 ON um2.IP=um1.IP AND um2.Enabled=\'2\' ';
            }
        }

        if (!empty($_GET['passkey'])) {
            list($placeholder, $param) = wrap($_GET['passkey']);
            $Where[] = "um1.torrent_pass {$match} {$placeholder}";
            $searchParams[] = $param;
        }

        if (!empty($_GET['avatar'])) {
            list($placeholder, $param) = wrap($_GET['avatar']);
            $Where[] = "ui1.Avatar {$match} {$placeholder}";
            $searchParams[] = $param;
        }

        if (!empty($_GET['stylesheet'])) {
            $Where[]='ui1.StyleID='.wrap($_GET['stylesheet'], '=');
            list($placeholder, $param) = wrap($_GET['stylesheet'], '=');
            $Where[] = "ui1.StyleID = {$placeholder}";
            $searchParams[] = $param;
        }

        $_GET['order'] = $_GET['order'] ?? 'Joined';
        $_GET['way']   = $_GET['way']   ?? 'Descending';

        if ($OrderTable[$_GET['order']] && $WayTable[$_GET['way']]) {
            $Order = 'ORDER BY '.$OrderTable[$_GET['order']].' '.$WayTable[$_GET['way']].PHP_EOL;
        }

        //---------- Finish generating the search string

        $SQL = "SELECT DISTINCT {$SQL}";
        $SQL .= implode(' ', $Join);

        if (count($Where)) {
            $SQL .= 'WHERE '.implode(PHP_EOL.'AND ', $Where).PHP_EOL;
        }

        if (count($Having)) {
            $SQL .= 'HAVING '.implode(PHP_EOL.'AND ', $Having).PHP_EOL;
            $searchParams = array_merge($searchParams, $havingParams);
        }
        $SQL .= $Order;

        if (count($Where)>0 || count($Join)>0 || count($Having)>0) {
            $RunQuery = true;
        }

        list($Page, $Limit) = page_limit(USERS_PER_PAGE);
        $SQL.= "LIMIT {$Limit}";
    } else { error($Err); }

}

show_header('User search', 'jquery');
?>
<div class="thin">
    <h2>Search results</h2>
    <script>
        function Toggle_view(elem_id) {
            jQuery('#'+elem_id+'div').toggle();

            if (jQuery('#'+elem_id+'div').is(':hidden'))
                jQuery('#'+elem_id+'button').text('(Show)');
            else
                jQuery('#'+elem_id+'button').text('(Hide)');

            return false;
        }
    </script>

    <div class="head clear">
        <span style="float:left;">Help</span>
        <span style="float:right;"><a id="as-helpbutton" href="#" onclick="return Toggle_view('as-help');">(Show)</a></span>
    </div>
    <div class="box">
        <div id="as-helpdiv" class="pad" style="display: none;">
            <h4>Ratio and uploaded/downloaded amounts</h4>
            <p>
                Users ratio can contain decimals (e.g. 1.525)<br>
                Uploaded and Downloaded search fields must be in GiB.<br>
                Selecting "Buffer" in the Uploaded field will search directly the difference in uploaded and downloaded amounts.
            </p>

            <h4>Search operators</h4>
            <table>
                <tr><td>Equal</td><td>Anything that equals to Input1 (Input2 is optional)</td></tr>
                <tr><td>On</td><td>Similar than Equal but for a date (whole day)</td></tr>
                <tr><td>Above</td><td>Anything above Input1 (Input2 is optional)</td></tr>
                <tr><td>After</td><td>Any date after Input1 (Input2 is optional)</td></tr>
                <tr><td>Below</td><td>Anything below Input1 (Input2 is optional)</td></tr>
                <tr><td>Before</td><td>Any date before Input1 (Input2 is optional)</td></tr>
                <tr><td>Between</td><td>Anything between Input1 and Input2</td></tr>
            </table>
        </div>
    </div>

    <div class="head">Advanced search</div>
    <div class="box pad">
    <form action="user.php" method="post">
        <input type="hidden" name="action" value="search" />
        <table>
            <tr>
                <td class="label nobr">Username:</td>
                <td width="30%">
                    <input type="text" name="username" size="20" value="<?=display_str($_GET['username'] ?? '')?>" />
                </td>
                <td class="label nobr">Joined:</td>
                <td width="40%">
                    <select name="joined">
                        <option value="on"      <?php selected('joined', 'on')      ?>>On</option>
                        <option value="before"  <?php selected('joined', 'before')  ?>>Before</option>
                        <option value="after"   <?php selected('joined', 'after')   ?>>After</option>
                        <option value="between" <?php selected('joined', 'between') ?>>Between</option>
                    </select>
                    <input type="date" name="join1" size="6" value="<?=display_str($_GET['join1'] ?? '')?>" />
                    <input type="date" name="join2" size="6" value="<?=display_str($_GET['join2'] ?? '')?>" />
                </td>
                <td class="label nobr">Enabled:</td>
                <td width="30%">
                    <select name="enabled">
                        <option value="" <?php selected('enabled', '')  ?>>Any</option>
                        <option value="0"<?php selected('enabled', '0') ?>>Unconfirmed</option>
                        <option value="1"<?php selected('enabled', '1') ?>>Enabled</option>
                        <option value="2"<?php selected('enabled', '2') ?>>Disabled</option>
                        <option value="3"<?php selected('enabled', '3') ?>>Retired</option>
                    </select>
                </td>
            </tr>
            <tr>
<?php if (check_perms('users_view_email')) { ?>
                <td class="label nobr">Email:</td>
                <td>
                    <input type="text" name="email" size="20" value="<?=display_str($_GET['email'] ?? '')?>" />
                </td>
<?php } else { ?>
                <td class="label nobr"></td>
                <td>
                </td>
<?php } ?>
                <td class="label nobr">Last active:</td>
                <td width="30%">
                    <select name="lastactive">
                        <option value="on"      <?php selected('lastactive', 'on')      ?>>On</option>
                        <option value="before"  <?php selected('lastactive', 'before')  ?>>Before</option>
                        <option value="after"   <?php selected('lastactive', 'after')   ?>>After</option>
                        <option value="between" <?php selected('lastactive', 'between') ?>>Between</option>
                    </select>
                    <input type="date" name="lastactive1" size="6" value="<?=display_str($_GET['lastactive1'] ?? '')?>" />
                    <input type="date" name="lastactive2" size="6" value="<?=display_str($_GET['lastactive2'] ?? '')?>" />
                </td>
                <td class="label nobr">Class:</td>
                <td>
                    <select name="class">
                        <option value="" <?php selected('class', '') ?>>Any</option>
<?php 	foreach ($classes as $class) { ?>
                    <option value="<?=$class['ID'] ?>" <?php selected('class', $class['ID']) ?>><?=cut_string($class['Name'], 10, 1, 1).' ('.$class['Level'].')'?></option>
<?php 	} ?>
                    </select>
                </td>
            </tr>
            <tr>
<?php if (check_perms('users_view_ips')) { ?>
                <td class="label nobr">IP:</td>
                <td>
                    <input type="text" name="ip" size="20" value="<?=display_str($_GET['ip'] ?? '')?>" />
                </td>
<?php } else { ?>
                <td class="label nobr"></td>
                <td>
                </td>
<?php } ?>
                <td class="label nobr">Ratio:</td>
                <td width="30%">
                    <select name="ratio">
                        <option value="equal"   <?php selected('ratio', 'equal')   ?>>Equal</option>
                        <option value="above"   <?php selected('ratio', 'above')   ?>>Above</option>
                        <option value="below"   <?php selected('ratio', 'below')   ?>>Below</option>
                        <option value="between" <?php selected('ratio', 'between') ?>>Between</option>
                    </select>
                    <input type="text" name="ratio1" size="6" value="<?=display_str($_GET['ratio1'] ?? '')?>" />
                    <input type="text" name="ratio2" size="6" value="<?=display_str($_GET['ratio2'] ?? '')?>" />
                </td>
                <td class="label nobr">Donor:</td>
                <td>
                    <select name="donor">
                        <option value=""    <?php selected('donor', '')    ?>>Any</option>
                        <option value="yes" <?php selected('donor', 'yes') ?>>Yes</option>
                        <option value="no"  <?php selected('donor', 'no')  ?>>No</option>
                    </select>
                </td>

            </tr>
            <?php if ($master->options->AuthUserEnable) {?>
            <tr>
                <td class="label nobr">IRC Nick:</td>
                <td>
                    <input type="text" name="irc_nick" size="20" value="<?=display_str($_GET['irc_nick'] ?? '')?>" />
                </td>
                <td class="label nobr"></td>
                <td>
                </td>
                <td class="label nobr"></td>
                <td>
                </td>
            </tr>
            <? } else { ?>
                <td class="label nobr"></td>
                <td>
                </td>
<?php } ?>
            <tr>
                <td class="label nobr">Comment:</td>
                <td>
                    <input type="text" name="comment" size="20" value="<?=display_str($_GET['comment'] ?? '')?>" />
                </td>
                <td class="label nobr">Uploaded:</td>
                <td width="30%">
                    <select name="uploaded">
                        <option value="equal"   <?php selected('uploaded', 'equal')   ?>>Equal</option>
                        <option value="above"   <?php selected('uploaded', 'above')   ?>>Above</option>
                        <option value="below"   <?php selected('uploaded', 'below')   ?>>Below</option>
                        <option value="between" <?php selected('uploaded', 'between') ?>>Between</option>
                        <option value="buffer"  <?php selected('uploaded', 'buffer')  ?>>Buffer</option>
                    </select>
                    <input type="text" name="uploaded1" size="6" value="<?=display_str($_GET['uploaded1'] ?? '')?>" />
                    <input type="text" name="uploaded2" size="6" value="<?=display_str($_GET['uploaded2'] ?? '')?>" />
                </td>
                <td class="label nobr">Stylesheet:</td>
                <td>
                    <select name="stylesheet" id="stylesheet">
                        <option value="">Any</option>
                        <?php  foreach ($Stylesheets as $Style) { ?>
                            <option value="<?=$Style['ID']?>"<?php selected('stylesheet', $Style['ID'])?>><?=$Style['ProperName']?></option>
                        <?php  } ?>
                    </select>
                </td>
            </tr>
            <tr>
                <td class="label nobr">Invites:</td>
                <td>
                    <select name="invites">
                        <option value="equal"   <?php selected('invites', 'equal')   ?>>Equal</option>
                        <option value="above"   <?php selected('invites', 'above')   ?>>Above</option>
                        <option value="below"   <?php selected('invites', 'below')   ?>>Below</option>
                        <option value="between" <?php selected('invites', 'between') ?>>Between</option>
                    </select>
                    <input type="text" name="invites1" size="6" value="<?=display_str($_GET['invites1'] ?? '')?>" />
                    <input type="text" name="invites2" size="6" value="<?=display_str($_GET['invites2'] ?? '')?>" />

                </td>
                <td class="label nobr">Downloaded:</td>
                <td width="30%">
                    <select name="downloaded">
                        <option value="equal"   <?php selected('downloaded', 'equal')   ?>>Equal</option>
                        <option value="above"   <?php selected('downloaded', 'above')   ?>>Above</option>
                        <option value="below"   <?php selected('downloaded', 'below')   ?>>Below</option>
                        <option value="between" <?php selected('downloaded', 'between') ?>>Between</option>
                    </select>
                    <input type="text" name="downloaded1" size="6" value="<?=display_str($_GET['downloaded1'] ?? '')?>" />
                    <input type="text" name="downloaded2" size="6" value="<?=display_str($_GET['downloaded2'] ?? '')?>" />
                </td>
                <td class="label nobr">Country Code:</td>
                <td width="30%">
                    <select name="cc_op">
                        <option value="equal"    <?php selected('cc_op', 'equal')     ?>>Equals</option>
                        <option value="not_equal"<?php selected('cc_op', 'not_equal') ?>>Not Equal</option>
                    </select>
                    <input type="text" name="cc" size="2" value="<?=display_str($_GET['cc'] ?? '')?>" />
                </td>
            </tr>
            <tr>
                <td class="label nobr">Passkey:</td>
                <td>
                    <input type="text" name="passkey" size="20" value="<?=display_str($_GET['passkey'] ?? '')?>" />
                </td>
                <td class="label nobr">Snatched:</td>
                <td width="30%">
                    <select name="snatched">
                        <option value="equal"   <?php selected('snatched', 'equal')   ?>>Equal</option>
                        <option value="above"   <?php selected('snatched', 'above')   ?>>Above</option>
                        <option value="below"   <?php selected('snatched', 'below')   ?>>Below</option>
                        <option value="between" <?php selected('snatched', 'between') ?>>Between</option>
                        <option value="off"     <?php selected('snatched', 'off')     ?>>Off</option>
                    </select>
                    <input type="text" name="snatched1" size="6" value="<?=display_str($_GET['snatched1'] ?? '')?>" />
                    <input type="text" name="snatched2" size="6" value="<?=display_str($_GET['snatched2'] ?? '')?>" />
                </td>
                <td class="label nobr">Disabled IP:</td>
                <td>
                    <input type="checkbox" name="disabled_ip" value="1" <?php selected('disabled_ip', '1', 'checked') ?> />
                </td>
            </tr>
            <tr>
                <td class="label nobr">Avatar:</td>
                <td>
                    <input type="text" name="avatar" size="20" value="<?=display_str($_GET['avatar'] ?? '')?>" />
                </td>
<?php if (check_perms('users_view_ips')) { ?>
                <td class="label nobr">Tracker IP:</td>
                <td>
                    <input type="text" name="tracker_ip" size="20" value="<?=display_str($_GET['tracker_ip'] ?? '')?>" />
                </td>
<?php } else { ?>
                <td class="label nobr"></td><td></td>
<?php } ?>
                <td class="label nobr">Extra</td>
                <td>
<?php if (check_perms('users_view_ips')) { ?>
                    <input type="checkbox" name="ip_history" id="ip_history" value="1" <?php selected('ip_history', '1', 'checked') ?> />
                    <label for="ip_history">IP History</label>
<?php } ?>
                </td>
            </tr>
            <tr>
                <td class="label nobr">Type</td>
                <td>
                    Strict <input type="radio" name="matchtype" value="strict" <?php selected('matchtype', 'strict', 'checked', $_GET) ?> /> |
                    Fuzzy  <input type="radio" name="matchtype" value="fuzzy"  <?php selected('matchtype', 'fuzzy',  'checked', $_GET) ?> /> |
                    Regex  <input type="radio" name="matchtype" value="regex"  <?php selected('matchtype', 'regex',  'checked', $_GET) ?> />
                </td>
                <td class="label nobr">Order:</td>
                <td class="nobr">
                    <select name="order">
                    <?php
                        foreach (array_shift($OrderVals) as $Cur) { ?>
                        <option value="<?=$Cur?>"<?php selected('order', $Cur) ?>><?=$Cur?></option>
                    <?php 	}?>
                    </select>
                    <select name="way">
                    <?php 	foreach (array_shift($WayVals) as $Cur) { ?>
                        <option value="<?=$Cur?>"<?php  selected('way', $Cur) ?>><?=$Cur?></option>
                    <?php 	}?>
                    </select>
                </td>
                <td class="label nobr"># Of Emails:</td>
                <td>
                    <select name="emails_opt">
                        <option value="equal" <?php selected('emails_opt', 'equal') ?>>Equal</option>
                        <option value="above" <?php selected('emails_opt', 'above') ?>>Above</option>
                        <option value="below" <?php selected('emails_opt', 'below')  ?>>Below</option>
                    </select>
                    <input type="text" name="email_cnt" size="6" value="<?=display_str($_GET['email_cnt'] ?? '')?>" />
                </td>
            </tr>
            <tr>
                <td colspan="6" class="center">
                    <input type="submit" value="Search users" />
                </td>
            </tr>
        </table>
    </form>
    </div>
<?php
$NumResults = 0;
if ($RunQuery) {
    $results = $master->db->rawQuery($SQL, $searchParams)->fetchAll(\PDO::FETCH_NUM);
    $NumResults = $master->db->foundRows();
} else {
    $results = [];
}
?>
    <div class="linkbox">
<?php
$Pages = get_pages($Page, $NumResults, USERS_PER_PAGE, 11);
echo $Pages;
?>
    </div>
    <div class="box pad center">
        <table width="100%">
            <tr class="colhead">
                <td>Username</td>
                <!--Do we want IRC Nick to also display in the list -->
                <?php if ($master->options->AuthUserEnable) {?>
                <td>IRC Nick</td>
                <?php } ?>
                <td>Ratio</td>
<?php if (check_perms('users_view_ips')) { ?>
                <td>IP</td>
<?php } ?>
<?php if (check_perms('users_view_email')) { ?>
                <td>Email</td>
<?php } ?>
                <td>Joined</td>
                <td>Last Seen</td>
                <td>Upload</td>
                <td>Download</td>
                <td title="downloads (number of torrent files downloaded)">Dlds</td>
                <td title="snatched (number of torrents completed)">Sn'd</td>
                <td title="invites">Inv's</td>
            </tr>
<?php
foreach ($results as $result) {
    list($userID, $IRCNick, $Uploaded, $Downloaded, $Snatched, $class, $Email, $enabled,
         $IP, $trackerIP1, $Invites, $Donor, $JoinDate, $LastAccess) = $result;
?>
            <tr>
                <td><?=format_username($userID, $Donor, true, $enabled, $class)?></td>
                <!--Do we want IRC Nick to also display in the list -->
                <?php if (($master->options->AuthUserEnable) == true) {?>
                <td><?=$IRCNick?></td>
                <?php } ?>
                <td><?=ratio($Uploaded, $Downloaded)?></td>
<?php if (check_perms('users_view_ips')) { ?>
                <td><?="<span title=\"account ip\">".display_ip($IP)."</span>";
                  if ($trackerIP1) echo "<br/><span title=\"current tracker ip\">".display_ip($trackerIP1)."</span>";
                  //if ($trackerIP2) echo "<br/><span title=\"tracker ip history\">".display_ip($trackerIP2)."</span>";
            ?>
                </td>
<?php } ?>
<?php if (check_perms('users_view_email')) { ?>
                <td><?=display_str($Email)?></td>
<?php } ?>
                <td><?=time_diff($JoinDate)?></td>
                <td><?=time_diff($LastAccess)?></td>
                <td><?=get_size($Uploaded)?></td>
                <td><?=get_size($Downloaded)?></td>
<?php $Downloads = $master->db->rawQuery(
    "SELECT COUNT(ud.UserID)
       FROM users_downloads AS ud
       JOIN torrents AS t ON t.ID = ud.TorrentID
      WHERE ud.UserID = ?",
    [$userID]
)->fetchColumn(); ?>
                <td><?=(int) $Downloads?></td>
                <td><?=is_integer_string($Snatched) ? number_format($Snatched) : display_str($Snatched)?></td>
                <td><?=is_integer_string($Invites) ? number_format($Invites) : display_str($Invites)?></td>
            </tr>
<?php
}
?>
        </table>
    </div>
    <div class="linkbox">
<?=$Pages?>
    </div>
</dvi>
<?php
show_footer();
