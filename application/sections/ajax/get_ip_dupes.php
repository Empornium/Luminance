<?php
if (!check_perms('users_view_ips')) error(403,true);

if (empty($_REQUEST['ip']) || !preg_match('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/', $_REQUEST['ip']) ) {
    error("IP error: ".display_str($_REQUEST['ip']),true);
}

$IP = $_REQUEST['ip'];

$DB->query("SELECT
                h.UserID,
                m.Username,
                m.PermissionID,
                m.GroupPermissionID,
                m.Enabled,
                i.Donor,
                i.Warned,
                i.JoinDate,
                m.LastAccess,
                m.Uploaded,
                m.Downloaded,
                m.Email,
                COUNT(DISTINCT ud.TorrentID) AS Grabbed,
                COUNT(DISTINCT x.fid) AS Snatched
                FROM users_history_ips AS h
                JOIN users_main AS m ON m.ID=h.UserID
                JOIN users_info AS i ON i.UserID=m.ID
                LEFT JOIN users_downloads AS ud ON ud.UserID=m.ID
                LEFT JOIN xbt_snatched AS x ON x.uid=m.ID
                WHERE h.IP = '".db_string($IP)."'
                 GROUP BY h.UserID
                  ORDER BY m.LastAccess DESC
                  LIMIT 50 ");

$UserList = $DB->to_array(false, MYSQLI_NUM);

$HTML= '<td colspan="5"><div class="box"><table class="box">';
$HTML .= '<tr class="colhead"><td>User</td><td>Email</td><td>Last Seen</td><td>Join Date</td><td class="center" title="Grabbed torrent files">grabbed</td><td class="center" title="Snatched 100% torrents">snatched</td><td class="center">Up\Down</td><td class="center">Ratio</td></tr>';
$HTML .='<tr class="rowa"><td colspan="8" class="center"><span style="color:blue">'. display_str($IP) .'</span></td></tr>';

foreach ($UserList as $User) {
    list($UserID, $Username, $PermID, $GroupPermID, $Enabled, $IsDonor, $IsWarned, $JoinDate, $LastAccess,
            $Uploaded, $Downloaded, $Email, $Grabbed, $Snatched) = $User;

    $row = ($row == 'b') ? 'a' : 'b';
    $HTML .='<tr class="row'.$row.'"><td>'.format_username($UserID, $Username, $IsDonor, $IsWarned, $Enabled, $PermID, false, false, $GroupPermID).'</td>';
    $HTML .='<td>'. display_str($Email) .'</td>';
    $HTML .='<td>'. time_diff($LastAccess) .'</td>';
    $HTML .='<td>'. time_diff($JoinDate) .'</td>';
    $HTML .='<td class="center">'. number_format($Grabbed) .'</td>';
    $HTML .='<td class="center">'. number_format($Snatched) .'</td>';
    $HTML .='<td class="center">'. get_size($Uploaded) .' \\ '. get_size($Downloaded) .'</td>';
    $HTML .='<td class="center">'. ratio($Uploaded, $Downloaded) .'</td></tr>';

}

$HTML.='</table></div></td>';

echo json_encode(array($HTML));
