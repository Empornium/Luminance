<?php
namespace Luminance\Legacy;
/*-- TODO ---------------------------//
Writeup how to use the VALIDATE class, add in support for form id checks
Complete the number and date validation
Finish the GenerateJS stuff
//-----------------------------------*/

class Validate
{
    public $Fields=[];
    public $OnlyValidateKeys =false;
    public $Weight;
    public function SetFields($FieldName,$Required,$FieldType,$ErrorMessage,$Options=array())
    {
        $Field = ['Name' => $FieldName, 'Type' => strtolower($FieldType), 'Required' => $Required, 'ErrorMessage' => $ErrorMessage];
        if (isset($Options['maxlength'])) {
            $Field['MaxLength']=$Options['maxlength'];
        }
        if (isset($Options['minlength'])) {
            $Field['MinLength']=$Options['minlength'];
        }
        if (isset($Options['comparefield'])) {
            $Field['CompareField']=$Options['comparefield'];
        }
        if (isset($Options['allowperiod'])) {
            $Field['AllowPeriod']=$Options['allowperiod'];
        }
        if (isset($Options['allowcomma'])) {
            $Field['AllowComma']=$Options['allowcomma'];
        }
        if (isset($Options['inarray'])) {
            $Field['InArray']=$Options['inarray'];
        }
        if (isset($Options['regex'])) {
            $Field['Regex']=$Options['regex'];
        }
        if (isset($Options['minimages'])) {
            $Field['MinImages']=$Options['minimages'];
        }
        if (isset($Options['maximages'])) {
            $Field['MaxImages']=$Options['maximages'];
        }
        if (isset($Options['maxfilesize'])) {
            $Field['MaxFilesize']=$Options['maxfilesize'];
        }
        if (isset($Options['maxfilesizeKB'])) {
            $Field['MaxFilesize']=$Options['maxfilesizeKB']*1024;
        }
        if (isset($Options['maxfilesizeMB'])) {
            $Field['MaxFilesize']=$Options['maxfilesizeMB']*1024*1024;
        }
        if (isset($Options['maxfilesizeGB'])) {
            $Field['MaxFilesize']=$Options['maxfilesizeGB']*1024*1024*1024;
        }
        if (isset($Options['dimensions'])) {
            $Field['MaxWidth']=$Options['dimensions'][0];
            $Field['MaxHeight']=$Options['dimensions'][1];
        }
        if (isset($Options['maxwordlength'])) {
            $Field['MaxWordLength']=$Options['maxwordlength'];
        }
        if (isset($Options['mindate'])) {
            $Field['MinDate']=$Options['mindate'];
        }
        if (isset($Options['maxdate'])) {
            $Field['MaxDate']=$Options['maxdate'];
        }
        $this->Fields[] = $Field;
    }

    public function ValidateForm($ValidateArray, $Text = null)
    {
        reset($this->Fields);
        foreach ($this->Fields as $Field) {
            $ValidateRaw=$ValidateArray[$Field['Name']];
            if (is_array($ValidateRaw))
                $VarArray = $ValidateRaw;
            else {
                $VarArray = array();
                $VarArray[] = $ValidateRaw;
            }
            foreach ($VarArray as $Key=>$ValidateVar) {
                if (is_array($this->OnlyValidateKeys) && !in_array($Key, $this->OnlyValidateKeys))
                    continue;

                if ($ValidateVar!="" || !empty($Field['Required']) || $Field['Type']=="date") {
                    switch($Field['Type']) {

                        case 'string':
                            $MaxLength     = (isset($Field['MaxLength']))     ? $Field['MaxLength']     : 255;
                            $MinLength     = (isset($Field['MinLength']))     ? $Field['MinLength']     :   1;
                            $MaxWordLength = (isset($Field['MaxWordLength'])) ? $Field['MaxWordLength'] :   0;

                            if (strlen($ValidateVar)>$MaxLength) {
                                return "$Field[ErrorMessage] Max length of field is $MaxLength characters.";
                            } elseif (strlen($ValidateVar)<$MinLength) {
                                return "$Field[ErrorMessage] Min length of field is $MinLength characters.";
                            } elseif ($MaxWordLength>0) {
                                $Words = explode(' ', $ValidateVar);
                                foreach ($Words as $Word) {
                                    if ($Word && strlen($Word) > $MaxWordLength )
                                        return "$Field[ErrorMessage] The maximum allowed length of a single word is $MaxWordLength, please add some spaces in your text.";
                                }
                            }
                            break;

                        case 'number':
                        case 'int':
                        case 'float':
                        case 'double':
                            $MaxLength   = (isset($Field['MaxLength']))    ? $Field['MaxLength']   : null;
                            $MinLength   = (isset($Field['MinLength']))    ? $Field['MinLength']   : 0;

                            $Match='0-9';
                            if (isset($Field['AllowPeriod'])) $Match.='.';
                            if (isset($Field['AllowComma'])) $Match.=',';

                            if (preg_match('/[^'.$Match.']/', $ValidateVar) || strlen($ValidateVar)<1) {
                                return $Field['ErrorMessage'];
                            } elseif (!is_null($MaxLength) && $ValidateVar>$MaxLength) {
                                return $Field['ErrorMessage']."!!";
                            } elseif ($ValidateVar<$MinLength) {
                                return $Field['ErrorMessage']."$MinLength";
                            }
                            break;

                        case 'bool':
                            if(!is_bool($ValidateVar)) return $Field['ErrorMessage'];
                            break;

                        case 'email':
                            $MaxLength   = (isset($Field['MaxLength']))    ? $Field['MaxLength']   : 255;
                            $MinLength   = (isset($Field['MinLength']))    ? $Field['MinLength']   :  6;

                            if (strlen($ValidateVar)>$MaxLength) return $Field['ErrorMessage'];
                            if (strlen($ValidateVar)<$MinLength) return $Field['ErrorMessage'];
                            if (!preg_match("/^".EMAIL_REGEX."$/i", $ValidateVar)) return $Field['ErrorMessage'];
                            // get validation result
                            $result = validate_email($ValidateVar);
                            if ($result !== true) return "$Field[ErrorMessage]<br/>".display_str($result);
                            break;

                        case 'link':
                            $MaxLength   = (isset($Field['MaxLength']))    ? $Field['MaxLength']   : 255;
                            $MinLength   = (isset($Field['MinLength']))    ? $Field['MinLength']   :  10;

                            if (!preg_match('/^(https?):\/\/([a-z0-9\-\_]+\.)+([a-z]{1,5}[^\.])(\/[^<>]+)*$/i', $ValidateVar)) {
                                return $Field['ErrorMessage'];
                            } elseif (strlen($ValidateVar)>$MaxLength) {
                                return $Field['ErrorMessage']." (must be < $MaxLength)";
                            } elseif(strlen($ValidateVar)<$MinLength) {
                                return $Field['ErrorMessage']." (must be > $MinLength)";
                            }
                            break;

                        case 'username':
                            $MaxLength   = (isset($Field['MaxLength']))    ? $Field['MaxLength']   : 20;
                            $MinLength   = (isset($Field['MinLength']))    ? $Field['MinLength']   :  1;

                            if (preg_match('/[^a-z0-9_\-?\.]/i', $ValidateVar)) {
                                return $Field['ErrorMessage'];
                            } elseif (strlen($ValidateVar)>$MaxLength) {
                                return $Field['ErrorMessage'];
                            } elseif(strlen($ValidateVar)<$MinLength) {
                                return $Field['ErrorMessage'];
                            }
                            break;

                        #TODO reconsider this...
                        case 'checkbox':
                            if (!isset($ValidateArray[$Field['Name']])) return $Field['ErrorMessage'];
                            break;

                        case 'compare':
                            if ($ValidateArray[$Field['CompareField']]!=$ValidateVar) return $Field['ErrorMessage'];
                            break;

                        case 'inarray':
                        case 'enum':
                            if (!is_array($Field['InArray'])) return $Field['ErrorMessage'] .' (InArray is not an array)';
                            $test = array_search($ValidateVar, $Field['InArray']);
                            if ($test===false || $test===null) return $Field['ErrorMessage'];
                            break;

                        case 'regex':
                            if (!preg_match($Field['Regex'], $ValidateVar)) return $Field['ErrorMessage'];
                            break;

                        case 'date':
                            $MinDate = date('Y-m-d H:i:s', strtotime((isset($Field['MinDate'])) ? $Field['MinDate'] : '0000-00-00 00:00:00'));
                            $MaxDate = date('Y-m-d H:i:s', strtotime((isset($Field['MaxDate'])) ? $Field['MaxDate'] : '+10 years'));
                            $date = date('Y-m-d H:i:s', strtotime($ValidateVar));
                            if (strtotime($date)) {
                                if ($date > $MaxDate) return $Field['ErrorMessage'];
                                if ($date < $MinDate) return $Field['ErrorMessage'];
                            } else {
                                return "$Field[ErrorMessage]<br/>Invalid Date";
                            }
                            break;

                        case 'ip':
                            if (!inet_pton($ValidateVar)) {
                                return $Field['ErrorMessage'];
                            }
                            break;

                        case 'cidr':
                            if (strpos($ValidateVar, '/') !== false) {
                                list($address, $netmask) = explode('/', $ValidateVar);
                            } else {
                                $address = $ValidateVar;
                                $netmask = null;
                            }

                            $ip = inet_pton($address);
                            if(!$ip) {
                                return $Field['ErrorMessage'];
                            }

                            if (!is_null($netmask)) {
                                if ((int)$netmask > strlen($ip)*8) {
                                    return $Field['ErrorMessage'];
                                }
                            }
                        break;

                        case 'image':

                        // Validate an imageurl :
                        // 1) validite function: checks url format / url length / whitelist
                        // 2) optional image dimensions
                        // 3) optional filesize of image (probably should not use in large batches as has to fetch remote image)

                            // Get parameters to validate against from fields set
                            $MaxLength      = (isset($Field['MaxLength']))         ? $Field['MaxLength']        : 255;
                            $MinLength      = (isset($Field['MinLength']))         ? $Field['MinLength']        :  10;
                            $MaxFileSize    = (isset($Field['MaxFilesize']))       ? $Field['MaxFilesize']      :  -1;
                            $MaxWidth       = (isset($Field['MaxWidth']))          ? $Field['MaxWidth']         :  -1;
                            $MaxHeight      = (isset($Field['MaxHeight']))         ? $Field['MaxHeight']        :  -1;
                            $MaxImageWeight = (isset($Field['MaxImageWeight']))    ? $Field['MaxImageWeight']   :  (25*1024*1024);

                            $WLRegex = (isset($Field['Regex'])) ? $Field['Regex'] : '/.*/';

                            // get validation result
                            $result = validate_imageurl($ValidateVar, $MinLength, $MaxLength, $WLRegex);
                            if ($result !== true) return "$Field[ErrorMessage]<br/>".display_str($result);

                            // check image dimensions if max dimensions are specified
                            if ($MaxWidth>=0 && $MaxHeight>=0) {
                                $image_attribs = getimagesize($ValidateVar);
                                if ($image_attribs!==false) { // i guess we should ignore it if it fails .. hmmm...
                                    list($width, $height, $type, $attr) = $image_attribs;
                                    if ($width>$MaxWidth || $height > $MaxHeight)
                                        return "$Field[ErrorMessage]<br/>Image dimensions are too big; width: {$width}px  height: {$height}px<br/>Max Image dimensions; width: {$MaxWidth}px  height: {$MaxHeight}px<br/>File: ".display_str($ValidateVar);
                                }
                            }

                            // check remote filesize if max specififed
                            if ($MaxFileSize>=0) {
                                $filesize = $this->get_remote_file_size($ValidateVar);
                                if ($filesize<0)
                                    return "$Field[ErrorMessage]<br/>Imagehost did not return file size.";
                                if ($filesize>$MaxFileSize)
                                    return "$Field[ErrorMessage]<br/>Filesize is too big: " . get_size($filesize). "<br/>MaxFilesize: ".get_size($MaxFileSize). "<br/>File: ".display_str($ValidateVar);
                            }
                            break;

                        case 'desc':
                            // desc Type gets 3 checks for the price of one
                            // 1)desc length 2)imglink as valid url 3)imglinks against whitelist
                            // this kind of breaks the pattern of this class but screw it...
                            // we will hardcode changes to return messages as this class matches fields by
                            // name (so one check per field only) and I dont want to redesign it

                            $MaxLength      = (isset($Field['MaxLength']))         ? $Field['MaxLength']        : 255;
                            $MinLength      = (isset($Field['MinLength']))         ? $Field['MinLength']        :   1;
                            $MaxImages      = (isset($Field['MaxImages']))         ? $Field['MaxImages']        : 255;
                            $MinImages      = (isset($Field['MinImages']))         ? $Field['MinImages']        :   0;
                            $MaxWidth       = (isset($Field['MaxWidth']))          ? $Field['MaxWidth']         :  -1;
                            $MaxHeight      = (isset($Field['MaxHeight']))         ? $Field['MaxHeight']        :  -1;
                            $MaxImageWeight = (isset($Field['MaxImageWeight']))    ? $Field['MaxImageWeight']   :  (25*1024*1024);

                            $WLRegex = (isset($Field['Regex'])) ? $Field['Regex'] : '/.*/';

                            if (!$Text) {
                                $Text = new Text;
                            }
                            //$TextLength =  strlen($Text->db_clean_search($ValidateVar));
                            $TextLength =  $Text->text_count($ValidateVar);
                            $RealLength =  strlen($ValidateVar);

                            if ($TextLength>$MaxLength) {
                                $Field['ErrorMessage'] =  "Error: ".$Field['ErrorMessage']." must be less than $MaxLength characters long.";
                                $Field['ErrorMessage'] .= " (counted:$TextLength all:$RealLength)";

                                return $Field['ErrorMessage'];
                            } elseif ($TextLength<$MinLength) {
                                $Field['ErrorMessage'] =  "Error: ".$Field['ErrorMessage']." must be more than $MinLength characters long.";
                                $Field['ErrorMessage'] .= " (counted:$TextLength all:$RealLength)";

                                return $Field['ErrorMessage'];
                            }

                            //  Check image urls inside the desc text against the whitelist.
                            //  the whitelist is set inside the $Field['Regex'] var (in options arrary in ->SetFields)

                            // get all the image urls in the field ; inside [img]url[/img] && [img=url] tags
                            $num = preg_match_all('#(?|\[thumb\](.*?)\[/thumb\]|\[img(?:=[0-9,]*)?\](.*?)\[/img\]|\[imgnm\](.*?)\[/imgnm\]|\[imgalt\](.*?)\[/imgalt\]|\[img\=[0-9,]*(.*?)\])#ism', $ValidateVar, $imageurls);

                            if ($num && $num >= $MinImages) { // if there are no img tags then it validates
                                if ($num > $MaxImages)
                                    return "Too many images in presentation (Max $MaxImages)";
                                for ($j=0;$j<$num;$j++) {
                                     // validate each image url
                                     // (for the moment use hardcoded image lengths but ideally they should
                                     // probably be taken from some new option fields).
                                    $result = validate_imageurl($imageurls[1][$j], 12, 255, $WLRegex);
                                    if ($result !== true) { return $Field['ErrorMessage'].' field: ' .display_str($result); }

                                    // check image dimensions if max dimensions are specified
                                    if ($MaxWidth>=0 && $MaxHeight>=0) {
                                        $image_attribs = getimagesize($imageurls[1][$j]);
                                        if ($image_attribs!==false) { // i guess we should ignore it if it fails .. hmmm...
                                            list($width, $height, $type, $attr) = $image_attribs;
                                            if ($width>$MaxWidth || $height > $MaxHeight)
                                                return "$Field[ErrorMessage] field:<br/>Image dimensions are too big; width: {$width}px  height: {$height}px<br/>Max Image dimensions; width: {$MaxWidth}px  height: {$MaxHeight}px<br/>File: ".display_str($imageurls[1][$j]);
                                        }
                                    }
                                 }

                                 // Count total size of all images, don't count twice
                                 $this->Weight = $this->get_presentation_size(array_unique($imageurls[1]));
                                 if($this->Weight >= $MaxImageWeight)
                                     return "Presentation is too big! ".get_size($this->Weight)." (Max ".get_size($MaxImageWeight).")";
                            } elseif ($MinImages> 0 && $num < $MinImages) {  // if there are no img tags then it validates unless required flag is set
                                //if (!empty($Field['Required'])) {
                                    // this kind of breaks the pattern of this class but screw it...
                                    // we will hardcode a change to return message to avoid having to do the
                                    // preg_match_all(regex) again or adding another return msg variable
                                    if ($MinImages == 1)
                                        $Field['ErrorMessage'] = "There are no images in your description. You are required to have screenshots for every scene.";
                                    else
                                        $Field['ErrorMessage'] = "There are not enough images in your description. You are required to have screenshots for every scene.";

                                    return $Field['ErrorMessage'];
                                //}
                            }
                        break;

                        // Forum posts and comments
                        case 'post':
                            global $master;

                            // As of right now, we only check images,
                            // we can skip everything, if it's disabled
                            if (!$master->options->ImagesCheck) {
                                break;
                            }

                            // Max. number of images in posts
                            $MaxImages = (int) $master->options->MaxImagesCount; // Global option
                            $MaxImages = isset($Field['MaxImages']) ? (int) $Field['MaxImages'] : $MaxImages; // Local option

                            // Max. size for images in posts (MB)
                            $MaxWeight = (int) $master->options->MaxImagesWeight;
                            $MaxWeight = isset($Field['MaxImagesWeight']) ? (int) $Field['MaxImageWeight'] : $MaxWeight;
                            $MaxWeight = $MaxWeight * 1024 * 1024;

                            // Parse all images inside the post
                            // TODO: have a global RegEx for this?
                            $Matched = preg_match_all('#(?|\[thumb\](.*?)\[/thumb\]|\[img\](.*?)\[/img\]|\[imgnm\](.*?)\[/imgnm\]|\[imgalt\](.*?)\[/imgalt\]|\[img\=(.*?)\])#ism', $ValidateVar, $ImagesMatches);

                            if ($Matched) {
                                $ImagesURLs = array_unique($ImagesMatches[1]);
                                if (count($ImagesURLs) > $MaxImages) {
                                    $Error  = "Your post contains too many images. (Max: $MaxImages)<br>";
                                    $Error .= "Try posting the direct links instead.";
                                    return $Error;
                                }
                                $TotalSize = 0;
                                foreach ($ImagesURLs as $ImagesURL) {
                                    $FileSize = $this->get_remote_file_size($ImagesURL);
                                    if ($FileSize<0)
                                        return "$Field[ErrorMessage]<br/>Imagehost did not return file size.";
                                    if ($FileSize > 0) { $TotalSize += $FileSize; }
                                }
                                if ($TotalSize > $MaxWeight) {
                                    $TotalSize = round($TotalSize / 1024 / 1024, 2);
                                    $MaxWeight = round($MaxWeight / 1024 / 1024, 2);
                                    $Error  = "Your post contains too many images. (Weight: $TotalSize MB - Max: $MaxWeight MB)<br>";
                                    $Error .= "Try posting thumbnails instead or simply post the direct links.";
                                    return $Error;
                                }
                            }

                            break;
                    }
                }  // if (dovalidation)

            }
        } // foreach
    } // function

    public function GenerateJS($FormID)
    {
        $ReturnJS="<script type=\"text/javascript\" language=\"javascript\">\r\n";
        $ReturnJS.="//<![CDATA[\r\n";
        $ReturnJS.="function formVal() {\r\n";
        $ReturnJS.="	clearErrors('".$FormID."');\r\n";

        reset($this->Fields);
        foreach ($this->Fields as $Field) {
            if ($Field['Type']=="string") {
                $ValItem='	if($(\'#'.$Field['Name'].'\').raw().value==""';
                if (!empty($Field['MaxLength'])) { $ValItem.=' || $(\'#'.$Field['Name'].'\').raw().value.length>'.$Field['MaxLength']; } else { $ValItem.=' || $(\'#'.$Field['Name'].'\').raw().value.length>255'; }
                if (!empty($Field['MinLength'])) { $ValItem.=' || $(\'#'.$Field['Name'].'\').raw().value.length<'.$Field['MinLength']; }
                $ValItem.=') { return showError(\''.$Field['Name'].'\',\''.$Field['ErrorMessage'].'\'); }'."\r\n";

            } elseif ($Field['Type']=="number") {
                $Match='0-9';
                if (!empty($Field['AllowPeriod'])) { $Match.='.'; }
                if (!empty($Field['AllowComma'])) { $Match.=','; }

                $ValItem='	if($(\'#'.$Field['Name'].'\').raw().value.match(/[^'.$Match.']/) || $(\'#'.$Field['Name'].'\').raw().value.length<1';
                if (!empty($Field['MaxLength'])) { $ValItem.=' || $(\'#'.$Field['Name'].'\').raw().value/1>'.$Field['MaxLength']; }
                if (!empty($Field['MinLength'])) { $ValItem.=' || $(\'#'.$Field['Name'].'\').raw().value/1<'.$Field['MinLength']; }
                $ValItem.=') { return showError(\''.$Field['Name'].'\',\''.$Field['ErrorMessage'].'\'); }'."\r\n";

            } elseif ($Field['Type']=="email") {
                $ValItem='	if(!validEmail($(\'#'.$Field['Name'].'\').raw().value)';
                if (!empty($Field['MaxLength'])) { $ValItem.=' || $(\'#'.$Field['Name'].'\').raw().value.length>'.$Field['MaxLength']; } else { $ValItem.=' || $(\'#'.$Field['Name'].'\').raw().value.length>255'; }
                if (!empty($Field['MinLength'])) { $ValItem.=' || $(\'#'.$Field['Name'].'\').raw().value.length<'.$Field['MinLength']; } else { $ValItem.=' || $(\'#'.$Field['Name'].'\').raw().value.length<6'; }
                $ValItem.=') { return showError(\''.$Field['Name'].'\',\''.$Field['ErrorMessage'].'\'); }'."\r\n";

            } elseif ($Field['Type']=="link") {
                $ValItem='	if(!validLink($(\'#'.$Field['Name'].'\').raw().value)';
                if (!empty($Field['MaxLength'])) { $ValItem.=' || $(\'#'.$Field['Name'].'\').raw().value.length>'.$Field['MaxLength']; } else { $ValItem.=' || $(\'#'.$Field['Name'].'\').raw().value.length>255'; }
                if (!empty($Field['MinLength'])) { $ValItem.=' || $(\'#'.$Field['Name'].'\').raw().value.length<'.$Field['MinLength']; } else { $ValItem.=' || $(\'#'.$Field['Name'].'\').raw().value.length<10'; }
                $ValItem.=') { return showError(\''.$Field['Name'].'\',\''.$Field['ErrorMessage'].'\'); }'."\r\n";

            } elseif ($Field['Type']=="username") {
                $ValItem='	if($(\'#'.$Field['Name'].'\').raw().value.match(/[^a-zA-Z0-9_\-]/)';
                if (!empty($Field['MaxLength'])) { $ValItem.=' || $(\'#'.$Field['Name'].'\').raw().value.length>'.$Field['MaxLength']; }
                if (!empty($Field['MinLength'])) { $ValItem.=' || $(\'#'.$Field['Name'].'\').raw().value.length<'.$Field['MinLength']; }
                $ValItem.=') { return showError(\''.$Field['Name'].'\',\''.$Field['ErrorMessage'].'\'); }'."\r\n";

            } elseif ($Field['Type']=="regex") {
                $ValItem='	if (!$(\'#'.$Field['Name'].'\').raw().value.match('.$Field['Regex'].')) { return showError(\''.$Field['Name'].'\',\''.$Field['ErrorMessage'].'\'); }'."\r\n";

            } elseif ($Field['Type']=="date") {
                $DisplayError=$Field['Name']."month";
                if (isset($Field['MinLength']) && $Field['MinLength']==3) { $Day='$(\'#'.$Field['Name'].'day\').raw().value'; $DisplayError.=",".$Field['Name']."day"; } else { $Day="1"; }
                $DisplayError.=",".$Field['Name']."year";
                $ValItemHold='	if (!validDate($(\'#'.$Field['Name'].'month\').raw().value+\'/\'+'.$Day.'+\'/\'+$(\'#'.$Field['Name'].'year\').raw().value)) { return showError(\''.$DisplayError.'\',\''.$Field['ErrorMessage'].'\'); }'."\r\n";

                if (empty($Field['Required'])) {
                    $ValItem='	if($(\'#'.$Field['Name'].'month\').raw().value!=""';
                    if (isset($Field['MinLength']) && $Field['MinLength']==3) { $ValItem.=' || $(\'#'.$Field['Name'].'day\').raw().value!=""'; }
                    $ValItem.=' || $(\'#'.$Field['Name'].'year\').raw().value!="") {'."\r\n";
                    $ValItem.=$ValItemHold;
                    $ValItem.="	}\r\n";
                } else {
                    $ValItem.=$ValItemHold;
                }

            } elseif ($Field['Type']=="checkbox") {
                $ValItem='	if (!$(\'#'.$Field['Name'].'\').checked) { return showError(\''.$Field['Name'].'\',\''.$Field['ErrorMessage'].'\'); }'."\r\n";

            } elseif ($Field['Type']=="compare") {
                $ValItem='	if ($(\'#'.$Field['Name'].'\').raw().value!=$(\'#'.$Field['CompareField'].'\').raw().value) { return showError(\''.$Field['Name'].','.$Field['CompareField'].'\',\''.$Field['ErrorMessage'].'\'); }'."\r\n";
            }

            if (empty($Field['Required']) && $Field['Type']!="date") {
                $ReturnJS.='	if ($(\'#'.$Field['Name'].'\').raw().value!="") {'."\r\n	";
                $ReturnJS.=$ValItem;
                $ReturnJS.="	}\r\n";
            } else {
                $ReturnJS.=$ValItem;
            }
            $ValItem='';
        }

        $ReturnJS.="}\r\n";
        $ReturnJS.="//]]>\r\n";
        $ReturnJS.="</script>\r\n";

        return $ReturnJS;
    }

    public function get_presentation_size($urls) {

        // make sure the rolling window isn't greater than the # of urls
        $rolling_window = 25;
        $rolling_window = (sizeof($urls) < $rolling_window) ? sizeof($urls) : $rolling_window;

        $curl_arr = array();
        $total=0;

        // add additional curl options here
        $std_options = array(CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HEADER => true,
        CURLOPT_NOBODY => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 2);
        $options = ($custom_options) ? ($std_options + $custom_options) : $std_options;

        // initialise all curl handles first
        foreach($urls as $i => $url) {
            $ch[] = curl_init();
        }

        $master = curl_multi_init();

        // preload the window
        for ($i = 0; $i < $rolling_window; $i++) {
            $options[CURLOPT_URL] = $urls[$i];
            curl_setopt_array($ch[$i],$options);
            curl_multi_add_handle($master, $ch[$i]);
        }

        // Reset
        $i = 0;

        do {
            while(($execrun = curl_multi_exec($master, $running)) == CURLM_CALL_MULTI_PERFORM);
            usleep(50000);
            if($execrun != CURLM_OK)
                break;
            // a request was just completed -- find out which one
            while($done = curl_multi_info_read($master)) {
                $info = curl_getinfo($done['handle']);
                if ($info['http_code'] == 200 && !is_null($ch[$i])) {
                    $total += curl_getinfo($done['handle'],  CURLINFO_CONTENT_LENGTH_DOWNLOAD);

                    // start a new request (it's important to do this before removing the old one)
                    $options[CURLOPT_URL] = $urls[$i];
                    curl_setopt_array($ch[$i],$options);
                    curl_multi_add_handle($master, $ch[$i]);

                    // increment the handle pointer
                    $i++;

                    // remove the curl handle that just completed
                    curl_multi_remove_handle($master, $done['handle']);
                } else {
                    // request failed.  add error handling.
                }
            }
        } while ($running);

        curl_multi_close($master);
        return $total;
    }

    public function get_remote_file_size($url, $user = "", $pw = "")
    {
        ob_start();
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_NOBODY, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); // Ignore SSL issues when checking files
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); // Ignore SSL issues when checking files

        if (!empty($user) && !empty($pw)) {
            $headers = array('Authorization: Basic ' . base64_encode("$user:$pw"));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        // slightly convoluted way to get remote filesize but is more bulletproof than @fsockopen methods
        $ok = curl_exec($ch);
        $errors = curl_error($ch);
        curl_close($ch);
        $retheaders = ob_get_contents();
        ob_end_clean();

        $return = false;
        $retheaders = explode("\n", $retheaders);
        foreach ($retheaders as $header) {
            // follow redirect
            $s = 'Location: ';
            if (substr(strtolower ($header), 0, strlen($s)) == strtolower($s)) {
                $url = trim(substr($header, strlen($s)));

                return $this->get_remote_file_size($url, $user, $pw );
            }

            // parse for content length
            $s = "Content-Length: ";
            if (substr(strtolower ($header), 0, strlen($s)) == strtolower($s)) {
                $return = trim(substr($header, strlen($s)));

                return $return ? $return : -2;
            }
        }

        return -1;
    }

}
