<?php

if (empty($_REQUEST['groupid']) || !is_number($_REQUEST['groupid']) ) {
     error(0);
}
$GroupID = (int) $_REQUEST['groupid'];

if (!check_perms('users_edit_badges')) {
    error(403);
}

$DB->query("SELECT Name, Comment from groups WHERE ID=$GroupID");
if ($DB->record_count()==0) error(0);
list($Name, $Description) = $DB->next_record();

$DB->query("SELECT
                UserID,
                Username
            FROM users_groups as ug
            JOIN users_main as um ON um.ID = ug.UserID
            WHERE GroupID=$GroupID");

$Users = $DB->to_array(false,MYSQLI_BOTH);

if (!$Users) { error("Cannot make an award to this group as there are no users in this group"); }

show_header('Mass Award', 'upload,bbcode,inbox');

include(SERVER_ROOT.'/classes/class_text.php');
$Text = new TEXT;

?>
<div class="thin">
    <h2>Give Award To All Users in Group: <?=$Name?></h2>

    <div class="colhead">Member list<span style="float:right;"><a href="#" onclick="$('#ulist').toggle(); this.innerHTML=(this.innerHTML=='(Hide)'?'(View)':'(Hide)'); return false;">(View)</a></span></div>
      <div id="ulist" class="box pad hidden">
<?php
           foreach ($Users as $User) {
               list($UserID,$Username) = $User; ?>
                <a href="/user.php?id=<?=$UserID?>"><?=$Username?></a><br/>
<?php            }      ?>
      </div>

      <div class="colhead">Select Award</div>
      <div class="pad box addbadges">
            <p>Shop and single type items can be awarded once to each user and multiple type items many times by every user<br/>
              note: if you award a single or shop type award to users who already have it they will not receive it again</p>

            <form action="groups.php" method="post">
                    <input type="hidden" name="action" value="takemassaward" />
                    <input type="hidden" name="applyto" value="group" />
                    <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                    <input type="hidden" name="groupid" value="<?=$GroupID?>" />
                <table class="noborder">
<?php
                    $DB->query("SELECT
                                    b.ID,
                                    Type,
                                    Title,
                                    Description,
                                    Image,
                                    IF(ba.ID IS NULL,FALSE,TRUE) AS Auto
                               FROM badges AS b
                               LEFT JOIN badges_auto AS ba ON b.ID=ba.BadgeID
                               WHERE Type != 'Shop' AND Type!='Unique'
                                 AND ba.ID is NULL
                               ORDER BY Sort");
                    $AvailableBadges = $DB->to_array();

                    foreach ($AvailableBadges as $Badge) { // = $DB->next_record()
                        list($ID, $Type, $Name, $Tooltip, $Image, $Auto, $Available) = $Badge;
?>
                        <tr>
                            <td width="60px">
                            <div class="badge">
<?php
                                echo '<img src="'.STATIC_SERVER.'common/badges/'.$Image.'" title="('.$Type.') '.$Tooltip.'" alt="'.$Name.'" />';
?>
                            </div>
                            </td>
                            <td>
                                <input  type="radio" name="addbadge" value="<?=$ID?>" />
                                        <label for="addbadge"> <?=$Name;
                                                if($Type=='Unique') echo " *(unique)";
                                                elseif ($Auto) echo " (automatically awarded)";
                                                else echo " ($Type)";  ?></label>
                                <br />
                                <input class="long" type="text" id="addbadge<?=$ID?>" name="addbadge<?=$ID?>" value="<?=$Tooltip?>" />
                            </td>
                        </tr>
<?php
                    }
?>
                        <tr>
                            <td colspan="2" class="center">
                                <input type="submit" name="submit" value="give award" title="Give selected award to all members in this group" /><br />
                            </td>
                        </tr>

                </table>
            </form>
     </div>

</div>
<?php
show_footer();
