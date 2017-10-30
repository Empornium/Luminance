<?php
$Text = new Luminance\Legacy\Text;

$Body=get_article('connchecker');


if (!isset($_GET['checkip'])) {
    $ipinfo = $master->db->raw_query("SELECT INET6_NTOA(ipv4) AS ipv4, port, active
                                        FROM xbt_files_users
                                       WHERE uid = :userid
                                    ORDER BY active DESC, mtime DESC LIMIT 1",
                                            [':userid' => $LoggedUser[ID]])->fetch(\PDO::FETCH_ASSOC);
    if (is_array($ipinfo)) {
        $_GET['checkip']   = $ipinfo['ipv4'];
        $_GET['checkport'] = $ipinfo['port'];
        $active            = $ipinfo['active'];
        if ($active!='1') $_GET['checkport'] = '';
    } else {
        $_GET['checkip']   = $_SERVER['REMOTE_ADDR'];
    }
}

if (isset($_GET['checkuser']) && is_number($_GET['checkuser']) && $_GET['checkuser'] > 0 && check_perms('users_mod') ) {
    $UserID = $_GET['checkuser'];
    $Username = $master->db->raw_query("SELECT Username FROM users_main WHERE ID=:userid", [':userid' => $UserID])->fetchColumn();
    if (!$Username) {
        $UserID = $LoggedUser['ID'];
        $Username = $LoggedUser['Username'];
    }
} else {
    $UserID = $LoggedUser['ID'];
    $Username = $LoggedUser['Username'];
}



show_header('Connectability Checker','bbcode');
?>
<div class="thin">
    <h2><a href="/user.php?id=<?=$LoggedUser['ID']?>"><?=$LoggedUser['Username']?></a> &gt; Connectability Checker</h2>
<?php   if ($Body) { ?>
    <div class="head"></div>
      <div class="box pad" style="padding:10px 10px 10px 20px;">
            <?=$Text->full_format($Body, true)?>
      </div>
<?php   }   ?>
    <div class="head">Check IP address and port</div>
      <form action="javascript:check_ip('<?=$UserID?>');" method="get">
        <table>
            <tr>
                <td class="label" style="width:80px;">User</td>
                <td>
                    <input type="text" value="<?=$Username?>" size="20" disabled="disabled" />
                </td>
                <td class="label" style="width:80px;">IP</td>
                <td>
                    <input type="text" id="ip" name="ip" value="<?=htmlentities($_GET['checkip'], ENT_QUOTES, 'UTF-8')?>" size="20" />
                </td>
                <td class="label" style="width:80px;">Port</td>
                <td>
                    <input type="text" id="port" name="port" value="<?=htmlentities($_GET['checkport'], ENT_QUOTES, 'UTF-8')?>" size="10" />
                </td>
                <td>
                    <input type="submit" value="Check" />
                </td>
            </tr>
        </table>
    </form><br />
    <div class="head">results</div>
    <div class="box pad"><div id="result" class="messagebar checking"></div></div>
</div>

<script type="text/javascript">

function check_ip(user_id)
{
    var result = $('#result');
    var intervalid = setInterval("$('#result').raw().innerHTML += '.';",1499);
    result.remove_class('alert');
    result.add_class('checking');
    result.raw().innerHTML = 'Checking.';
    ajax.get('ajax.php?action=connchecker&ip='
                            + $('#ip').raw().value
                            + '&port=' + $('#port').raw().value
                            + '&userid=' + user_id, function (response) {
        clearInterval(intervalid);
        result.remove_class('checking');
        var x = json.decode(response);
        if ( is_array(x)) {
            if (x[0] !== true) {
                result.add_class('alert');
            }
            result.raw().innerHTML = x[1];
        } else {    // error from ajax
            //alert(x);
            result.add_class('alert');
            result.raw().innerHTML = 'Invalid response: An error occured';
        }
    });
}
</script>

<?php
show_footer();
