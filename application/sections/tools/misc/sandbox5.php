<?php
show_header();
?>
<div class="thin">

    <table>
<?php
    $DB->query("SELECT cc, country FROM countries");

    while (list($cc,$country)=$DB->next_record()) {
?>
        <tr>
            <td>
                <img src="static/common/flags/iso64/<?=strtolower($cc)?>.png" alt="<?=$cc?>" />
            </td>
            <td><?=$cc?></td>
            <td><?=$country?>
                <img style="margin-bottom:-2px;" src="static/common/flags/iso16/<?=strtolower($cc)?>.png" alt="(<?=$cc?>)" />
            </td>
        </tr>
<?php
    }
?>
    </table>
</div>
<?php
show_footer();
