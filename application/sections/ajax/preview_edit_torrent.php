<?php
/* AJAX Previews, simple stuff. */

include(SERVER_ROOT.'/classes/class_text.php'); // Text formatting class
$Text = new TEXT;

$Content = $_REQUEST['body'];
$Imageurl = $_REQUEST['image'];
$AuthorID = (int) $_REQUEST['authorid'];
if (!empty($Imageurl)) {
    if ($Text->valid_url($Imageurl)) {
        $Imageurl = "<div style=\"text-align: center;\" class=\"box pad\">". $Text->full_format('[img]'.$Imageurl.'[/img]',false,true)."</div>";
    } else {
        $Imageurl = "<div style=\"text-align: center;\"><strong class=\"important_text\">Not a valid url</strong></div>";
    }
} else {
    $Imageurl = "<div style=\"text-align: center;\" class=\"box pad\"><strong class=\"important_text\">No Cover Image</strong></div>";
}

echo '
                            <h3>Image</h3>
                            '.$Imageurl.'<br />
                            <h3>Description</h3>
                            <br />
                            <div class="box pad">
                            <div class="body">
            '.$Text->full_format($Content, get_permissions_advtags($AuthorID), true).'
                            </div>
                            </div>';
