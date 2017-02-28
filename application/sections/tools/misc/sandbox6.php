<?php
enforce_login();
show_header('Staff Awarded Medals');

$DB->query("SELECT b.Title, b.Type, b.Description, b.Cost, b.Image , b.Badge, b.Rank
              FROM badges AS b
              LEFT JOIN badges_auto AS ba ON b.ID=ba.BadgeID
              WHERE ba.ID IS NULL AND Type!='Shop'
           ORDER BY b.Sort"); // b.Type,

$Awards = $DB->to_array(false, MYSQLI_BOTH);

?>

<div class="thin">
    <h2>Medals and Awards</h2>
    <div class="box pad">

        <pre>
<?php
        $LastBadge='';

        foreach ($Awards as $Award) {
            list($Name, $Type, $Desc, $Cost, $Image, $Badge, $Rank) = $Award;

            if ($LastBadge != $Badge) {

                $Str .= "[size=3]$Name ($Badge)[/size]\n";

                $LastBadge = $Badge;
            }

            $Str .= "$Name (Rank $Rank)\n";
            $Str .= "$Desc \n";
            $Str .= "[img]http://".SITE_URL."/".STATIC_SERVER."common/badges/{$Image}[/img]\n";
            $Str .= "[quote]criteria: [/quote]\n\n";

        }

        echo $Str;
?>
        </pre>
    </div>
</div>

<?php
show_footer();
