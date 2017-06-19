<?php
if (!check_perms('admin_manage_site_options')) {
    error(403);
}

show_header('Manage Site Options');

?>


<div class="thin">
    <div class="head">Site Options</div>
    <div class="box">
        <div>
            <form action="tools.php" method="post">
                <input type="hidden" name="action" value="take_site_options" />
                <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
<?php
foreach($master->options->getSections() as $section) {
    ?>
                <div class="site_option_section">
                    <h2><?=ucfirst($section)?></h2>
    <?php
    $displayRow=1;
    foreach($master->options->getAll($section) as $name => $option) {
        if (isset($option['displayRow']) && ($option['displayRow'] > $displayRow)) {
            $displayRow = $option['displayRow'];
        ?>
                    <div class="clear"></div>
        <?php
        }
        ?>
                    <div class="site_option">
                        <div class="input-label"><?=$option['description']?></div>
                        <?php if ($option['type'] == 'int') { ?>
                            <input type="text" title="<?=$option['description']?>" name="<?=$name?>" size="5" value="<?=$option['value']?>" />

                        <?php } elseif ($option['type'] == 'date') {
                            $option['value'] = date('Y-m-d\TH:i', $option['value']);
                            ?>
                            <input type="datetime-local" title="<?=$option['description']?>" name="<?=$name?>" size="20" value="<?=$option['value']?>" />
                        <?php } elseif ($option['type'] == 'bool') { ?>
                            <label class="switch">
                                <input type="checkbox" title="<?=$option['description']?>" name="<?=$name?>" <?=($option['value']) ? 'checked' : '' ?>/>
                                <div class="slider round"></div>
                            </label>
                        <?php } elseif ($option['type'] == 'enum') { ?>
                            <select name="<?=$name?>">
                            <?php foreach($option['validation']['inarray'] as $value) {?>
                                <option value="<?=$value?>" <?=($option['value']==$value)?'selected':''?>><?=$value?></option>
                            <?php } ?>
                            </select>
                        <?php } else { ?>
                            <input type="text" title="<?=$option['description']?>" name="<?=$name?>" size="20" value="<?=$option['value']?>" />
                        <?php } ?>
                    </div>
        <?php
    }
    ?>
                </div>
                <div class="clear"></div>
    <?php
}
?>

                <div class="submit_container pad">
                    <input type="submit" name='submit' value="Reset Site Options" />
                    <input type="submit" name='submit' value="Save Site Options" />
                </div>
            </form>
        </div>
    </div>
</div>

<?php
show_footer();
