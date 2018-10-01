<?php
/* AJAX Previews, simple stuff. */

$Text = new Luminance\Legacy\Text;

if (!empty($_POST['AdminComment'])) {
    echo $Text->full_format($_POST['AdminComment'],true);
} else {
    $Content = $Text->full_format($_REQUEST['body'],  get_permissions_advtags($LoggedUser['ID'], $LoggedUser['CustomPermissions']), true);
    if ($Text->has_errors()) {
        $bbErrors = implode('<br/>', $Text->get_errors());
        $Content = ("<br/><br/>There are errors in your bbcode (unclosed tags)<br/><br/>$bbErrors<br/><div class=\"box\"><div class=\"post_content\">$Content</div></div>");
    }
      echo $Content;
}
