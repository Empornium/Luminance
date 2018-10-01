<?php
if (!check_perms('admin_manage_tags')) {
    error(403);
}

$UseMultiInterface= true;

show_header('Official Tags Manager','tagmanager');

printRstMessage();
?>
<div class="thin">
    <h2>Tags Admin</h2>
<?php
    printTagLinks();
?>
    <h2>permanently remove tag</h2>
    <form  class="" action="tools.php" method="post">
        <input type="hidden" name="action" value="tags_admin_alter" />
        <input type="hidden" name="auth" value="<?= $LoggedUser['AuthKey'] ?>" />
        <div class="tagtable">
            <div class="box pad center shadow">
                <div class="pad" style="text-align:left">
                    <h3>Permanently Remove Tag</h3>
                    This section allows you to remove a tag completely from the database. <br/>
                    <strong class="important_text">Note: Use With Caution!</strong> This should only be used to remove things like banned tags,
                    <span style="text-decoration: underline">it irreversibly removes the tag and all instances of it in all torrents.</span>
                </div>

                    <input type="text" id="checktag" name="checktag" title="enter tag you want to remove" />

                    <input type="button" name="gettag" value="check tag exists" onclick="GetTagDetails();" title="this checks the tag exists, you will have to confirm the delete after this" />&nbsp;&nbsp;

                    <input type="text" readonly="readonly" id="deletetag" name="deletetag" title="this tag will be deleted" />
                    <input type="hidden" id="permdeletetagid" name="permdeletetagid" value="" />

<!--
                    <select id="permdeletetagid" name="permdeletetagid" onclick="Get_Taglist_All('permdeletetagid', 'all')" >
                        <option value="0" selected="selected">click to load ALL tags (might take a while)&nbsp;</option>
                    </select>  -->

                    <input class="hidden" disabled="disabled" type="submit" id="deletetagperm" name="deletetagperm" value="Permanently remove tag " title="permanently remove tag" />&nbsp;&nbsp;
            </div>
        </div>
    </form>
    <br/>
    <h2>recount tag uses</h2>
    <form  class="" action="tools.php" method="post">
        <input type="hidden" name="action" value="tags_admin_alter" />
        <input type="hidden" name="auth" value="<?= $LoggedUser['AuthKey'] ?>" />
        <div class="tagtable">
            <div class="box pad center shadow">
                <div class="pad" style="text-align:left">
                    <h3>Recount tag uses</h3>
                    This should never be needed once we go live!<br/>
                    <strong>Note: </strong>  You cannot do any direct harm with this but it may take a while to complete...
                </div>
                <input type="submit" name="recountall" value="Recount all tags " title="recounts the uses for every tag in the database" />&nbsp;&nbsp;
            </div>
        </div>
    </form>

</div>
<?php
show_footer();
