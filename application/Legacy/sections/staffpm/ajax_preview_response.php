<?php
/* AJAX Previews, simple stuff. */

$bbCode = new \Luminance\Legacy\Text;

if (!empty($_POST['message'])) {
    echo $bbCode->full_format($_POST['message'], true, true);
}
