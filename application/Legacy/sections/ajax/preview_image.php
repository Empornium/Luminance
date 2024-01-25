<?php
/* AJAX Previews, simple stuff. */

$bbCode = new \Luminance\Legacy\Text;

$Imageurl = $_REQUEST['image']; // Don't use URL decode.
if (!empty($Imageurl)) {
    if ($bbCode->valid_url($Imageurl)) {
        echo $bbCode->full_format('[align=center][img]'.$Imageurl.'[/img][/align]',false,true);
    } else {
        echo "<div style=\"text-align: center;\"><strong class=\"important_text\">Not a valid url</strong></div>";
    }
} else {
    echo "<div style=\"text-align: center;\"><strong class=\"important_text\">No Cover Image</strong></div>";
}
