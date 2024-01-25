<?php
if (!check_perms('site_debug')) { error(403); }

show_header('User Dupes by Sessions', 'jquery');

$sessDupes = $this->db->rawQuery(
    "SELECT s.UserID,
            s.ClientID,
            EXISTS (
                SELECT *
                  FROM sessions s2
                 WHERE s2.ClientID = s.ClientID
                   AND s2.UserID <> s.UserID
            ) as is_duplicate
       FROM sessions AS s
   ORDER BY s.ClientID"
)->fetchAll();

$counter = 0;

?>

<!-- Begin Printing Box -->
<div class="box pad center">
<td><b>BETA:</b>If a user is on this list, it is likely they are multi accounting. Please perform your own additional research and do not solely use this BETA tool to disable a user.<br>
This tool compares user browser information for possible matches.<br>
Compare each user to find matching ClientIDs</td><br><br>
<td>Possible Dupes:<br>
    <table width="100%">
        <tr class="colhead">
            <td>User</td>
            <td>ClientID</td>
        </tr>
        <?php
        foreach ($sessDupes as $sessDupe) {
            //Begin user dupe data
            //Here we will include only the results we want to see
            if ($sessDupe['is_duplicate'] == 1) {
                $row = ($row ?? 'a') == 'a' ? 'b' : 'a';
            ?>
                <tr class="row<?=$row?>">
                    <td><a href="/user.php?id=<?=$sessDupe['UserID']?>"><?=format_username($sessDupe['UserID'])?></a></td>
                    <td><?=$sessDupe['ClientID']?></td>
                    <!--<td>Dupe:<?=$sessDupe['is_duplicate']?></td><br> -->
                </tr>
                <?php
                $counter++;
            }
        }
 ?>
    </table><br></td>

<tr><td><br>
    <!-- How many results did we find? -->
    Dupes Found: <?= $counter ?> <br>
    Total Entries Found: <?= count($sessDupes) ?> (Returning total found rows, not total dupes)
</td></tr></div>

<?php

show_footer();
