<?php
if (!check_perms('users_mod') || !check_perms('admin_clear_cache')) {
    error(403);
}

if (!empty($_GET['flush'])) {
    authorize();
    $master->cache->flush();
} else if (!empty($_GET['key']) && $_GET['type'] == "clear") {
    authorize();
    if (preg_match('/(.*?)(\d+)\.\.(\d+)(.*?)$/', $_GET['key'], $matches) && is_numeric($matches[2]) && is_numeric($matches[3])) {
        for ($i=$matches[2]; $i<=$matches[3]; $i++) {
            $master->cache->deleteValue($matches[1].$i.$matches[4]);
        }
        echo '<div class="save_message">Keys '.display_str($_GET['key']).' cleared!</div>';
    } else {
        $master->cache->deleteValue($_GET['key']);
        echo '<div class="save_message">Key '.display_str($_GET['key']).' cleared!</div>';
    }
}

show_header('Clear a cache key');

?>
    <div class="thin">
    <h2>Clear a cache key</h2>

    <form method="get" action="" name="clear_cache">
        <input type="hidden" name="action" value="clear_cache" />
        <table cellpadding="2" cellspacing="1" border="0" align="center">
            <tr valign="top">
                <td align="right">Key</td>
                <td align="left">
                    <input type="text" name="key" id="key" class="inputtext" value="<?=display_str($_GET['key'] ?? '')?>" />
                    <select name="type">
                        <option value="view">View</option>
                        <option value="clear">Clear</option>
                    </select>
                    <input type="submit" value="key" class="submit" />
                                        <input type="submit" name="flush" value="Flush all Cache Keys" class="submit" />
                </td>
            </tr>
<?php  if (!empty($_GET['key']) && $_GET['type'] == "view") { ?>
            <tr>
                <td colspan="2">
                    <?php
                    // Below will generate a PHPStan error, but it's fine, we want to do this.
                    // @phpstan-ignore-next-line ?>
                    <pre><?php display_array(var_dump($master->cache->getValue($_GET['key']))); ?></pre>
                </td>
            </tr>
<?php  } ?>
        </table>
        <input type="hidden" name="auth" value="<?=$activeUser['AuthKey']?>" />
    </form>
    </div>
<?php
show_footer();
