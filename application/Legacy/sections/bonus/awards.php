<?php
enforce_login();
show_header('Medals and Awards');

$DB->query("SELECT b.Title, b.Type, b.Description, b.Cost, b.Image,
                (CASE WHEN Type='Shop' THEN 2
                      WHEN ba.ID IS NOT NULL THEN 0
                      ELSE 1
                 END) AS Sorter
              FROM badges AS b
              LEFT JOIN badges_auto AS ba ON b.ID=ba.BadgeID
              WHERE ba.ID IS NULL OR ba.Active = 1
              ORDER BY Sorter, b.Sort"); // b.Type,

$Awards = $DB->to_array(false, MYSQLI_BOTH);

?>

<div class="thin">
    <h2>Medals and Awards</h2>
<?php
    $Row = 'a';
      $LastType='';
    foreach ($Awards as $Award) {
        list($Name, $Type, $Desc, $Cost, $Image, $Sorter) = $Award;

            if ($LastType != $Sorter) {     // && $Type != 'Unique') {
                if ($LastType!='') {  ?>
      </div>
<?php
                }
?>
      <div class="" style="width:40%;float:right;margin:0 4% 20px 4%;display:inline-block;">
            <div class="head pad">
                <?php
                switch ($Sorter) {
                    case 2:
                        echo "Medals available for purchase in the bonus shop";
                        break;
                    case 0:
                        echo "Medals automatically awarded by the system";
                        break;
                    case 1:
                        echo "Medals awarded by the staff";
                        break;
                }
                ?>
            </div>
<?php
                $Row = 'a';
                $LastType=$Sorter;
            }

        $Row = ($Row == 'a') ? 'b' : 'a';
?>
        <div class="row<?=$Row?> pad">
                <h3 class="pad" style="float:left;"><?=display_str($Name)?></h3>
<?php           if ($Type=='Shop') echo '<strong style="float:right;margin-top:2px;">Cost: '.number_format($Cost).'</strong>'; ?>
                <div class="badge" style="width:100%;height:40px;clear:both;">
                    <img style="text-align:center" src="<?=STATIC_SERVER.'common/badges/'.$Image?>" title="<?=$Desc?>" alt="<?=$Name?>" />
                </div>
                <div class="pad" style="width:100%;">
                    <p><?=$Desc?></p>
                </div>
        </div>
<?php	}  ?>
    </div>
    <div class="clear"></div>
</div>

<?php
show_footer();
