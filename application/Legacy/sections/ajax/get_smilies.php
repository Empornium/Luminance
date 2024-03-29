<?php
/*  Draw smiley list from indexfrom to indexto on demand via ajax.
 *  - allows us to get away with a stupidly large number of smilies
 *    without having to load them all every time the bbcode helper pops up
 */

// set the output to be served as xml
header("Content-type: text/xml");

$bbCode = new \Luminance\Legacy\Text;

$indexfrom = $_REQUEST['indexfrom'];
if ( !is_integer_string($indexfrom)) { $indexfrom = 0; }
$indexto = -1;
if (isset($_REQUEST['indexto'])) {
    $indexto = $_REQUEST['indexto'];
    if ( !is_integer_string($indexto)) { $indexto = -1; }
}

$bbCode->draw_smilies_from_XML($indexfrom, $indexto);
