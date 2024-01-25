<?php
/* AJAX Previews, simple stuff. */

$bbCode = new \Luminance\Legacy\Text;

if (!empty($_POST['AdminComment'])) {
    echo $bbCode->full_format($_POST['AdminComment'], get_permissions_advtags($activeUser['ID'], $activeUser['CustomPermissions']), true);
} else {
    $Content = $bbCode->full_format($_REQUEST['body'], get_permissions_advtags($activeUser['ID'], $activeUser['CustomPermissions']), true);
    if ($bbCode->has_errors()) {
        $bbErrors = implode('<br/>', $bbCode->get_errors());
        $Content = ("<br/><br/>There are errors in your bbcode (unclosed tags)<br/><br/>$bbErrors<br/><div class=\"box\"><div class=\"post_content\">$Content</div></div>");
    }
      echo $Content;
}
