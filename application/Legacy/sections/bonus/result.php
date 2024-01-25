<?php
show_header('Shop result');
?>
<div class="thin">
    <h2>shop result</h2>
    <div class="thin">
        <div class="head">result</div>
        <div class="box pad ">
            <h3 class="center body" style="white-space:pre"><?=display_str($_REQUEST['result'] ?? '')?></h3>
        </div>

        <div class="head">return</div>
        <div class="box pad ">

            <a href="/bonus.php" title="Bonus Shop">Return to the Bonus Shop</a><br />

<?php           if (isset($_REQUEST['retu']) && is_integer_string($_REQUEST['retu'])) {
                    $Uname = $master->db->rawQuery(
                        "SELECT Username
                           FROM users
                          WHERE ID = ?",
                        [$_REQUEST['retu']]
                    )->fetchColumn();
                    if (!($Uname === false)) { ?>
                        <a href="/user.php?id=<?=$_REQUEST['retu']?>" title="Return to user profile">Return to <?=$Uname?>'s profile</a><br />
<?php               }
                }
                if (isset($_REQUEST['rett']) && is_integer_string($_REQUEST['rett'])) {
                    $Tname = $master->db->rawQuery(
                        "SELECT Name
                           FROM torrents_group
                          WHERE ID = ?",
                        [$_REQUEST['rett']]
                    )->fetchColumn();
                    if (!($Tname === false)) { ?>
                        <a href="/torrents.php?id=<?=$_REQUEST['rett']?>" title="Bonus Shop">Return to <?=$Tname?></a><br />
<?php               }
                }
                if (isset($_REQUEST['retsg'])) { ?>
                        <a href="/bonus.php?action=gift">Return to Special Gift Page</a><br />
<?php           } ?>
        </div>
    </div>
</div>
<?php
show_footer();
