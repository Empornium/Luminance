<?php
enforce_login();

// this page isnt actually linked to from anywhere... its not particularly useful unless you
// are loading a batch of smilies and want to check the existing names in an a-z list lol

include(SERVER_ROOT.'/classes/class_text.php');
$Text = new TEXT;

$Sorted = (isset($_REQUEST['sort']) && !$_REQUEST['sort'])?0:1;
$ASC = (isset($_REQUEST['asc']) && !$_REQUEST['asc'])?0:1;

show_header('Smilies', 'bbcode');

//---------------------------------------------
/*  // a debugging section for finding unused smiley images in the smiley folder
$files = scandir(STATIC_SERVER.'common/smileys');
$smilies = $Text->Smilies;
$Unadded = array();
foreach ($files as $Val) {
    if ( !in_array($Val, $smilies) ) $Unadded[] = $Val;
}
?>
<div class="thin"><div class="box pad"> <?php
//foreach ($smilies as $Val2) {
//    echo "$Val2<br />";
//}
//    echo "<br /><br /><br /><br /><br /><br />";
foreach ($Unadded as $file) {
    echo "$file<br />";
}  ?>
</div></div>
<?php
 */
//---------------------------------------------
?>
<div class="thin">
    <h2>Smilies</h2>

        <div class="linkbox" >

          [<a href="sandbox.php?action=smilies&amp;sort=0"> unsorted </a>] &nbsp;&nbsp;&nbsp;
          [<a href="sandbox.php?action=smilies&amp;sort=1&amp;asc=1"> sort A-Z </a>] &nbsp;&nbsp;&nbsp;
          [<a href="sandbox.php?action=smilies&amp;sort=1&amp;asc=0"> sort Z-A </a>] &nbsp;&nbsp;&nbsp;

        </div>
<?php
    $Text->draw_all_smilies($Sorted, $ASC);
?>

</div>

<?php
show_footer();
