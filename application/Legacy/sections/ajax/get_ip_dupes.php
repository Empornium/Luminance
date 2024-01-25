<?php
if (!check_perms('users_view_ips')) error(403,true);

if (empty($_REQUEST['ip']) || !preg_match('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/', $_REQUEST['ip'])) {
    error("IP error: ".display_str($_REQUEST['ip']),true);
}

$IP = $_REQUEST['ip'];

$UserList = $master->db->rawQuery(
    "SELECT h.UserID,
            u.Username,
            m.PermissionID,
            m.GroupPermissionID,
            m.Enabled,
            i.Donor,
            i.JoinDate,
            m.LastAccess,
            m.Uploaded,
            m.Downloaded,
            e.Address,
            COUNT(DISTINCT ud.TorrentID) AS Grabbed,
            COUNT(DISTINCT x.fid) AS Snatched
       FROM users_history_ips AS h
       JOIN ips ON h.IPID=ips.ID
       JOIN users AS u ON u.ID=h.UserID
       JOIN users_main AS m ON m.ID=u.ID
       JOIN users_info AS i ON i.UserID=u.ID
       JOIN emails AS e on e.ID=u.EmailID
  LEFT JOIN users_downloads AS ud ON ud.UserID=m.ID
  LEFT JOIN xbt_snatched AS x ON x.uid=m.ID
      WHERE ips.StartAddress = INET6_ATON(?)
      GROUP BY h.UserID
      ORDER BY m.LastAccess DESC
      LIMIT 50",
      [$IP]
)->fetchAll(\PDO::FETCH_NUM);

$HTML= '<td colspan="5"><div class="box"><table class="box">';
$HTML .= '<tr class="colhead"><td>User</td><td>Email</td><td>Last Seen</td><td>Join Date</td><td class="center" title="Grabbed torrent files">grabbed</td><td class="center" title="Snatched 100% torrents">snatched</td><td class="center">Up\Down</td><td class="center">Ratio</td></tr>';
$HTML .='<tr class="rowa"><td colspan="8" class="center"><span style="color:blue">'. display_str($IP) .'</span></td></tr>';

foreach ($UserList as $User) {
    list($userID, $Username, $PermID, $GroupPermID, $enabled, $IsDonor, $JoinDate, $LastAccess,
            $Uploaded, $Downloaded, $Email, $Grabbed, $Snatched) = $User;

    $row = ($row == 'b') ? 'a' : 'b';
    $HTML .='<tr class="row'.$row.'"><td>'.format_username($userID, $IsDonor, true, $enabled, $PermID, false, false, $GroupPermID).'</td>';
    $HTML .='<td>'. display_str($Email) .'</td>';
    $HTML .='<td>'. time_diff($LastAccess) .'</td>';
    $HTML .='<td>'. time_diff($JoinDate) .'</td>';
    $HTML .='<td class="center">'. number_format($Grabbed) .'</td>';
    $HTML .='<td class="center">'. number_format($Snatched) .'</td>';
    $HTML .='<td class="center">'. get_size($Uploaded) .' \\ '. get_size($Downloaded) .'</td>';
    $HTML .='<td class="center">'. ratio($Uploaded, $Downloaded) .'</td></tr>';

}

$HTML.='</table></div></td>';

echo json_encode([$HTML]);
