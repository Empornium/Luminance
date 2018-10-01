<?php
if (!check_perms('admin_manage_languages')) {
    error(403);
}

show_header('Site Languages', 'jquery');

$images = scandir($master->public_path . '/static/common/flags/iso16', 0);
$images = array_diff($images, array('.', '..'));
?>

<script type="text/javascript">//<![CDATA[
    public function change_image(display_image, flag_image)
    {
        jQuery(display_image).html('<img src="/static/common/flags/iso16/'+jQuery(flag_image).val()+'.png"/>');
    }
    //]]></script>

<div class="thin">
    <h2>Site Languages</h2>
    <div class="head">Official Site Languages</div>
    <div class="box pad">
        The Active site languages appear in the drop down on the user page, if users have languages selected which are then made inactive they will still show up.<br/>
        You can add and delete languages but this should usually be unnecessary - typically you would just make an option active or inactive rather than removing it from the list.
    </div>

    <div class="head">Edit Site Languages</div>
    <table>
        <tr class="colhead">
            <td width="40%">Language</td>
            <td width="10%">ISO Code</td>
            <td width="8%" class="right">Flag</td>
            <td width="14%"></td>
            <td width="8%">Active</td>
            <td width="20%"></td>
        </tr>

<?php
        $DB->query("SELECT  ID, language, code, flag_cc, active FROM languages ORDER BY active DESC, language");

        while (list($id, $language, $code, $flag_cc, $active) = $DB->next_record()) {
            ?>
            <tr>
            <form action="tools.php" method="post">
                <td>
                    <input type="hidden" name="action" value="languages_alter" />
                    <input type="hidden" name="auth" value="<?= $LoggedUser['AuthKey'] ?>" />
                    <input type="hidden" name="id" value="<?= $id ?>" />
                    <input class="medium" type="text" name="language" value="<?=$language?>" />
                </td>
                <td>
                    <input class="medium" type="text" name="code" value="<?=$code?>" />
                </td>
                <td class="right">
                    <span id="lang_image<?=$id?>">
                        <img style="vertical-align: bottom" title="<?=$flag_cc?>" src="//<?=SITE_URL?>/static/common/flags/iso16/<?=$flag_cc?>.png" />
                    </span>
                </td>
                <td>
                    <span style=" ">
                        <select id="flag_image<?=$id?>" name="flag" onchange="change_image('#lang_image<?=$id?>', '#flag_image<?=$id?>');">
                                    <option value="">none</option>
                            <?php  foreach ($images as $key => $value) {
                                    if (strlen($value)==6) {
                                        $value = substr($value, 0, strlen($value)-4  ); // remove .png extension  ?>
                                        <option value="<?= display_str($value) ?>" <?php  if($flag_cc==$value) echo 'selected="selected"';?>><?= $value ?></option>
                            <?php       }
                               }
                            ?>
                        </select>
                    </span>
                </td>
                <td>
                    <input type="checkbox" name="active" value="1" <?php  if($active) echo 'checked="checked"';?> />
                </td>
                <td>
                    <input type="submit" name="submit" value="Edit" />
                    <input type="submit" name="submit" <?php  if(!isset($_GET['del'])) echo 'disabled="disabled"';?> value="Delete" title="To enable the delete button use the &del=1 parameter in the url, but normally you should just deactivate a language option" />
                </td>
            </form>
            </tr>
<?php  } ?>
    </table>

    <br/>
    <div class="head">Add a new language</div>
    <table>
        <tr class="colhead">
            <td width="40%">Language</td>
            <td width="10%">ISO Code</td>
            <td width="8%" class="right">Flag</td>
            <td width="14%"></td>
            <td width="8%">Active</td>
            <td width="20%"></td>
        </tr>
        <tr>
        <form action="tools.php" method="post">
            <td>
                <input type="hidden" name="action" value="languages_alter" />
                <input type="hidden" name="auth" value="<?= $LoggedUser['AuthKey'] ?>" />
                <input class="medium" type="text" name="language" />
            </td>
            <td>
                <input class="medium" type="text" name="code" />
            </td>
            <td>
                <span id="lang_image0" class="right">
                    <img style="vertical-align: bottom" title="<?=$langresult['language']?>" src="//<?=SITE_URL?>/static/common/flags/iso16/<?=$langresult['cc']?>.png" />
                </span>
            </td>
            <td>
                <span style=" ">
                    <select id="flag_image0" name="flag" onchange="change_image('#lang_image0', '#flag_image0');">
                                    <option value="">none</option>
                        <?php  foreach ($images as $key => $value) {
                                if (strlen($value)==6) {
                                    $value = substr($value, 0, strlen($value)-4  ); // remove .png extension  ?>
                                    <option value="<?= display_str($value) ?>"><?= $value ?></option>
                        <?php       }
                           }
                        ?>
                    </select>
                </span>

                <div style="display:inline-block;vertical-align: top;">
                    </div>
            </td>
            <td>
                <input type="checkbox" name="active" value="1" />
            </td>
            <td>
                <input type="submit" value="Create" />
            </td>
        </form>
        </tr>
    </table>
</div>

<?php
show_footer();
