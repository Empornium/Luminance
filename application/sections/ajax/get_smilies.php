<?php
/*  Draw smiley list from indexfrom to indexto on demand via ajax.
 *  - allows us to get away with a stupidly large number of smilies
 *    without having to load them all every time the bbcode helper pops up
 */

// set the output to be served as xml
header("Content-type: text/xml");

include(SERVER_ROOT.'/classes/class_text.php'); // Text formatting class
$Text = new TEXT;

$indexfrom = $_REQUEST['indexfrom'];
if ( !is_number($indexfrom)) { $indexfrom = 0; }
$indexto = -1;
if (isset($_REQUEST['indexto'])) {
    $indexto = $_REQUEST['indexto'];
    if ( !is_number($indexto)) { $indexto = -1; }
}

$Text->draw_smilies_from_XML($indexfrom, $indexto);
