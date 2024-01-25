<?php
/* AJAX Previews, simple stuff. */

$bbCode = new \Luminance\Legacy\Text;
$Validate = new \Luminance\Legacy\Validate;

//******************************************************************************//
//--------------- Validate data in upload form ---------------------------------//
//** note: if the same field is set to be validated more than once then each time it is set it overwrites the previous test
//** ie.. one test per field max, last one set for a specific field is what is used
//$Validate->SetFields('title', '1', 'string', 'Title must be between 2 and 200 characters.', ['maxlength' => 200, 'minlength' => 2]);
//$Validate->SetFields('tags', '1', 'string', 'You must enter at least one tag. Maximum length is 10000 characters.', ['maxlength' => 10000, 'minlength' => 2]);
$whitelist_regex = get_whitelist_regex();
$Validate->SetFields('image', '0', 'image', 'The image URL you entered was not valid.', ['regex' => $whitelist_regex, 'maxlength' => 255, 'minlength' => 12]);
$Validate->SetFields('desc', '1', 'desc', 'Description', ['regex' => $whitelist_regex, 'minimages' => 1, 'maxlength' => 1000000, 'minlength' => 20]);
//$Validate->SetFields('category', '1', 'inarray', 'Please select a category.', ['inarray' => array_keys($newCategories)]);
//$Validate->SetFields('rules', '1', 'require', 'Your torrent must abide by the rules.');

$Err = $Validate->ValidateForm($_POST, $bbCode); // Validate the form

$Response = [];

$Response[0] = $Err;

$Content = $_POST['desc']; // Don't use URL decode.
$Content .= "[br][br]".($_POST['templatefooter'] ?? '');
$Imageurl = $_POST['image']; // Don't use URL decode.

if (!empty($Imageurl)) {
    if ($bbCode->valid_url($Imageurl)) {
        $Imageurl = '<img style="max-width: 100%;" src="' . $Imageurl . '" onclick="lightbox.init(this,220);" />';
        //$Imageurl = $bbCode->full_format('[align=center][img]'.$Imageurl.'[/img][/align]',false,true);
    } else {
        $Imageurl = "<div style=\"text-align: center;\"><strong class=\"important_text\">Not a valid url</strong></div>";
    }
} else {
    $Imageurl = "<div style=\"text-align: center;\"><strong class=\"important_text\">No Cover Image</strong></div>";
}

$Response[1] = '<br/>
          <div class="cover_image">
                <div class="head">Presentation Weight</div>
                <div class="box box_albumart" style="text-align: center">
                    <strong>' . get_size($Validate->Weight) . '</strong>
                </div>
          </div>
          <div class="cover_image">
                <div class="head">Cover Image</div>
                <div class="box box_albumart">' . $Imageurl . '</div>
          </div>
          <div class="head">Description</div>
          <div class="box pad">
          <div class="body">
               ' . $bbCode->full_format($Content, get_permissions_advtags($activeUser['ID'], $activeUser['CustomPermissions']), true) . '
          </div>
          </div><br/>';

echo json_encode($Response);
