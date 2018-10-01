<?php
/* AJAX Previews, simple stuff. */

$Text = new Luminance\Legacy\Text;
$Validate = new Luminance\Legacy\Validate;

$Validate->SetFields('thread', '0', 'number', 'The value you entered was not a number.', array('minlength' => 1));
$Validate->SetFields('title', '1', 'string', 'You must enter a Title.', array('maxlength' => 200, 'minlength' => 2, 'maxwordlength'=>TITLE_MAXWORD_LENGTH));
$whitelist_regex = get_whitelist_regex();
$Validate->SetFields('image', '0', 'image', 'The image URL you entered was not valid.', array('regex' => $whitelist_regex, 'maxlength' => 255, 'minlength' => 12));
$Validate->SetFields('body', '1', 'desc', 'Description', array('regex' => $whitelist_regex, 'minimages'=>0, 'maxlength' => 1000000, 'minlength' => 0));

$Err = $Validate->ValidateForm($_POST, $Text); // Validate the form

$str ='';
if ($Err) $str = '<div class="pad center warning">'.$Err."</div>\n";

$title = $_POST['title'];
$body = $_POST['body'];
$author = $_POST['author'];
$image = $_POST['image'];

if ($image) $str .= '<div class="pad center"><img style="max-width: 100%;max-height:1000px;" src="'.$image.'" /></div>';

echo '  <div class="head">
            <strong>'. display_str($title).' </strong> - posted Just now by '.display_str($author).'
            - <a href="#quickreplypreview">[Edit]</a>
            <a href="#quickreplypreview">[Delete]</a>
        </div>
        <div class="box blog">'.$str.'
            <div class="pad">'.$Text->full_format($body, get_permissions_advtags($LoggedUser['ID'], $LoggedUser['CustomPermissions']), true).'</div>
        </div>';
