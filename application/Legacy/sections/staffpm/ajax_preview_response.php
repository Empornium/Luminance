<?php
/* AJAX Previews, simple stuff. */

$Text = new Luminance\Legacy\Text;

if (!empty($_POST['message'])) {
    echo $Text->full_format($_POST['message'], true, true);
}
