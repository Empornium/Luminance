<?php
$time_start = microtime(true);

$DoFix = isset($_POST['submit']) && $_POST['submit']=='Fix Titles';
$WordLength = isset($_POST['wordlength'])? (int) $_POST['wordlength'] : 64;

show_header("Fix Torrent Titles");

$DB->query("SELECT ID, Name FROM torrents_group WHERE CHAR_LENGTH(Name)>$WordLength ORDER BY id");

$numtorrents = $DB->record_count();

?>
<div class="thin">
    <h2>Fix Torrent Titles</h2>

    <form method="post" action="" name="create_user">
        <div class="head"></div>
        <div class="box pad">
            <input type="hidden" name="action" value="sandbox3" />
            <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
            Max Word length in titles: <input type="text" name="wordlength" size="3" value="<?=$WordLength?>" /><br/>
<?php
        echo "Selected $numtorrents torrents for examination (max wordlength=$WordLength) ... <br/><br/><code class=\"bbcode\">";

        $i=0;
        $updaterow = array();

        while ( list($ID, $Title) = $DB->next_record()  ) {
            $Words = explode(' ', $Title);
            $found = false;
            foreach ($Words as &$word) {
                $len = strlen($word);
                if ($len <= $WordLength) continue;

                $cutat = strrpos($word, '.', $WordLength - $len);
                if ($cutat===false) $cutat = strrpos($word, '-', $WordLength - $len);
                if ($cutat===false) $cutat = $WordLength-4;
                $word = substr($word, 0, $cutat).' '.substr($word, $cutat+1);
                $found = true;
            }

            if ($found) {
                echo "<br/>---- 012345678901234567890123456789012345678901234567-50-234567-60-2345--8901234567-80-234567890<br/><br/>";
                echo "OLD: $Title<br/>";
                $Title = implode(' ', $Words);
                echo "New: $Title<br/>";

                $updaterow[] = "(" . $ID . ", '" . db_string($Title) . "')";
                $i++;
            }
        }

        if ($DoFix && $i>0) {
            $DB->query("INSERT INTO torrents_group (ID, Name) VALUES "
                    . implode(',', $updaterow)
                    . " ON DUPLICATE KEY UPDATE Name=Values(Name)");
        }

        echo "</code><br/>" . ($DoFix? 'FIXED':'Found' ) ." $i titles with overlong words in them<br/>";

        $time = microtime(true) - $time_start;
        echo "<br/>execution time: $time seconds<br/>";
?>
            <input type="submit" name="submit" value="Search" />
        </div>

<?php  if (!$DoFix) {  ?>
        <div class="head"></div>
        <div class="box pad">
            <input type="submit" name="submit" value="Fix Titles" />
        </div>
<?php  }  ?>
    </form>
</div>
<?php
show_footer();
