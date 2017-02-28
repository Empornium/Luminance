<?php
/*-- TODO ---------------------------//
Writeup how to use the VALIDATE class, add in support for form id checks
Complete the number and date validation
Finish the GenerateJS stuff
//-----------------------------------*/

class validate
{
    public $Fields=array();
    public $OnlyValidateKeys =false;
    public $Weight;
    public function SetFields($FieldName,$Required,$FieldType,$ErrorMessage,$Options=array())
    {
        $this->Fields[$FieldName]['Type']=strtolower($FieldType);
        $this->Fields[$FieldName]['Required']=$Required;
        $this->Fields[$FieldName]['ErrorMessage']=$ErrorMessage;
        if (isset($Options['maxlength'])) {
            $this->Fields[$FieldName]['MaxLength']=$Options['maxlength'];
        }
        if (isset($Options['minlength'])) {
            $this->Fields[$FieldName]['MinLength']=$Options['minlength'];
        }
        if (isset($Options['comparefield'])) {
            $this->Fields[$FieldName]['CompareField']=$Options['comparefield'];
        }
        if (isset($Options['allowperiod'])) {
            $this->Fields[$FieldName]['AllowPeriod']=$Options['allowperiod'];
        }
        if (isset($Options['allowcomma'])) {
            $this->Fields[$FieldName]['AllowComma']=$Options['allowcomma'];
        }
        if (isset($Options['inarray'])) {
            $this->Fields[$FieldName]['InArray']=$Options['inarray'];
        }
        if (isset($Options['regex'])) {
            $this->Fields[$FieldName]['Regex']=$Options['regex'];
        }
        if (isset($Options['minimages'])) {
            $this->Fields[$FieldName]['MinImages']=$Options['minimages'];
        }
        if (isset($Options['maximages'])) {
            $this->Fields[$FieldName]['MaxImages']=$Options['maximages'];
        }
        if (isset($Options['maxfilesize'])) {
            $this->Fields[$FieldName]['MaxFilesize']=$Options['maxfilesize'];
        }
        if (isset($Options['maxfilesizeKB'])) {
            $this->Fields[$FieldName]['MaxFilesize']=$Options['maxfilesizeKB']*1024;
        }
        if (isset($Options['maxfilesizeMB'])) {
            $this->Fields[$FieldName]['MaxFilesize']=$Options['maxfilesizeMB']*1024*1024;
        }
        if (isset($Options['maxfilesizeGB'])) {
            $this->Fields[$FieldName]['MaxFilesize']=$Options['maxfilesizeGB']*1024*1024*1024;
        }
        if (isset($Options['dimensions'])) {
            $this->Fields[$FieldName]['MaxWidth']=$Options['dimensions'][0];
            $this->Fields[$FieldName]['MaxHeight']=$Options['dimensions'][1];
        }
        if (isset($Options['maxwordlength'])) {
            $this->Fields[$FieldName]['MaxWordLength']=$Options['maxwordlength'];
        }
    }

    public function ValidateForm($ValidateArray, $Text = null)
    {
        reset($this->Fields);
        foreach ($this->Fields as $FieldKey => $Field) {
                $ValidateRaw=$ValidateArray[$FieldKey];
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
                            if ($Field['Type']=="string") {
                                  if (isset($Field['MaxLength'])) { $MaxLength=$Field['MaxLength']; } else { $MaxLength=255; }
                                  if (isset($Field['MinLength'])) { $MinLength=$Field['MinLength']; } else { $MinLength=1; }
                                  if (isset($Field['MaxWordLength'])) { $MaxWordLength=$Field['MaxWordLength']; } else { $MaxWordLength=0; }

                                  if (strlen($ValidateVar)>$MaxLength) { return "$Field[ErrorMessage] Max length of field is $MaxLength characters."; } elseif (strlen($ValidateVar)<$MinLength) { return "$Field[ErrorMessage] Min length of field is $MinLength characters."; } elseif ($MaxWordLength>0) {
                                      $Words = explode(' ', $ValidateVar);
                                      foreach ($Words as $Word) {
                                          if ($Word && strlen($Word) > $MaxWordLength )
                                              return "$Field[ErrorMessage] The maximum allowed length of a single word is $MaxWordLength, please add some spaces in your text.";
                                      }
                                  }

                            } elseif ($Field['Type']=="number") {
                                  if (isset($Field['MaxLength'])) { $MaxLength=$Field['MaxLength']; } else { $MaxLength=''; }
                                  if (isset($Field['MinLength'])) { $MinLength=$Field['MinLength']; } else { $MinLength=0; }

                                  $Match='0-9';
                                  if (isset($Field['AllowPeriod'])) { $Match.='.'; }
                                  if (isset($Field['AllowComma'])) { $Match.=','; }

                                  if (preg_match('/[^'.$Match.']/', $ValidateVar) || strlen($ValidateVar)<1) { return $Field['ErrorMessage']; } elseif ($MaxLength!="" && $ValidateVar>$MaxLength) { return $Field['ErrorMessage']."!!"; } elseif ($ValidateVar<$MinLength) { return $Field['ErrorMessage']."$MinLength"; }

                            } elseif ($Field['Type']=="email") {
                                    if (isset($Field['MaxLength'])) { $MaxLength=$Field['MaxLength']; } else { $MaxLength=255; }
                                    if (isset($Field['MinLength'])) { $MinLength=$Field['MinLength']; } else { $MinLength=6; }

                                    if (strlen($ValidateVar)>$MaxLength) { return $Field['ErrorMessage']; }
                                    if (strlen($ValidateVar)<$MinLength) { return $Field['ErrorMessage']; }
                                    if (!preg_match("/^".EMAIL_REGEX."$/i", $ValidateVar)) { return $Field['ErrorMessage']; }
                                    // get validation result
                                    $result = validate_email($ValidateVar);
                                    if ($result !== TRUE) return "$Field[ErrorMessage]<br/>$result";

                            } elseif ($Field['Type']=="link") {
                                  if (isset($Field['MaxLength'])) { $MaxLength=$Field['MaxLength']; } else { $MaxLength=255; }
                                  if (isset($Field['MinLength'])) { $MinLength=$Field['MinLength']; } else { $MinLength=10; }

                                  if (!preg_match('/^(https?):\/\/([a-z0-9\-\_]+\.)+([a-z]{1,5}[^\.])(\/[^<>]+)*$/i', $ValidateVar)) { return $Field['ErrorMessage']; } elseif (strlen($ValidateVar)>$MaxLength) { return $Field['ErrorMessage']." (must be < $MaxLength)"; } elseif(strlen($ValidateVar)<$MinLength) { return $Field['ErrorMessage']." (must be > $MinLength)"; }

                            } elseif ($Field['Type']=="username") {
                                    if (isset($Field['MaxLength'])) { $MaxLength=$Field['MaxLength']; } else { $MaxLength=20; }
                                    if (isset($Field['MinLength'])) { $MinLength=$Field['MinLength']; } else { $MinLength=1; }

                                    if (preg_match('/[^a-z0-9_\-?\.]/i', $ValidateVar)) { return $Field['ErrorMessage']; } elseif (strlen($ValidateVar)>$MaxLength) { return $Field['ErrorMessage']; } elseif(strlen($ValidateVar)<$MinLength) { return $Field['ErrorMessage']; }

                            } elseif ($Field['Type']=="checkbox") {
                                    if (!isset($ValidateArray[$FieldKey])) { return $Field['ErrorMessage']; }

                            } elseif ($Field['Type']=="compare") {
                                    if ($ValidateArray[$Field['CompareField']]!=$ValidateVar) { return $Field['ErrorMessage']; }

                            } elseif ($Field['Type']=="inarray") {
                                    if (array_search($ValidateVar, $Field['InArray'])===false) { return $Field['ErrorMessage']; }

                            } elseif ($Field['Type']=="regex") {
                                    if (!preg_match($Field['Regex'], $ValidateVar)) { return $Field['ErrorMessage']; }

                            } elseif ($Field['Type']=="image") {

                                // Validate an imageurl :
                                // 1) validite function: checks url format / url length / whitelist
                                // 2) optional image dimensions
                                // 3) optional filesize of image (probably should not use in large batches as has to fetch remote image)

                                    // Get parameters to validate against from fields set
                                    if (isset($Field['MaxLength'])) { $MaxLength=$Field['MaxLength']; } else { $MaxLength=255; }
                                    if (isset($Field['MinLength'])) { $MinLength=$Field['MinLength']; } else { $MinLength=10; }
                                    if (isset($Field['MaxFilesize'])) { $MaxFileSize=$Field['MaxFilesize']; } else { $MaxFileSize=-1; }
                                    if (isset($Field['MaxWidth'])) { $MaxWidth=$Field['MaxWidth']; } else { $MaxWidth=-1; }
                                    if (isset($Field['MaxHeight'])) { $MaxHeight=$Field['MaxHeight']; } else { $MaxHeight=-1; }

                                    if (isset($Field['Regex'])) { $WLRegex=$Field['Regex']; } else { $WLRegex='/nohost.com/'; }

                                    // get validation result
                                    $result = validate_imageurl($ValidateVar, $MinLength, $MaxLength, $WLRegex);
                                    if ($result !== TRUE) return "$Field[ErrorMessage]<br/>$result";

                                    // check image dimensions if max dimensions are specified
                                    if ($MaxWidth>=0 && $MaxHeight>=0) {
                                        $image_attribs = getimagesize($ValidateVar);
                                        if ($image_attribs!==FALSE) { // i guess we should ignore it if it fails .. hmmm...
                                            list($width, $height, $type, $attr) = $image_attribs;
                                            if ($width>$MaxWidth || $height > $MaxHeight)
                                                return "$Field[ErrorMessage]<br/>Image dimensions are too big; width: {$width}px  height: {$height}px<br/>Max Image dimensions; width: {$MaxWidth}px  height: {$MaxHeight}px<br/>File: $ValidateVar";
                                        }
                                    }

                                    // check remote filesize if max specififed
                                    if ($MaxFileSize>=0) {
                                        $filesize = get_remote_file_size($ValidateVar);
                                        //if ($filesize<0) return "error getting filesize";
                                        if ($filesize>$MaxFileSize)
                                            return "$Field[ErrorMessage]<br/>Filesize is too big: " . get_size($filesize). "<br/>MaxFilesize: ".get_size($MaxFileSize). "<br/>File: $ValidateVar";
                                    }

                            } elseif ($Field['Type']=="desc") {
                                    // desc Type gets 3 checks for the price of one
                                    // 1)desc length 2)imglink as valid url 3)imglinks against whitelist
                                    // this kind of breaks the pattern of this class but screw it...
                                    // we will hardcode changes to return messages as this class matches fields by
                                    // name (so one check per field only) and I dont want to redesign it

                                    if (isset($Field['MaxLength'])) { $MaxLength=$Field['MaxLength']; } else { $MaxLength=255; }
                                    if (isset($Field['MinLength'])) { $MinLength=$Field['MinLength']; } else { $MinLength=1; }

                                    if (isset($Field['MinImages'])) { $MinImages=$Field['MinImages']; } else { $MinImages=0; }
                                    if (isset($Field['MaxImages'])) { $MaxImages=$Field['MaxImages']; } else { $MaxImages=255; }
                                    if (isset($Field['MaxWidth']))  { $MaxWidth=$Field['MaxWidth'];   } else { $MaxWidth=-1; }
                                    if (isset($Field['MaxHeight'])) { $MaxHeight=$Field['MaxHeight']; } else { $MaxHeight=-1; }

                                    if (!$Text) {
                                        include(SERVER_ROOT . '/classes/class_text.php');
                                        $Text = new TEXT();
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

                                    if (isset($Field['Regex'])) { $WLRegex=$Field['Regex']; } else { $WLRegex='/nohost.com/'; }

                                    // get all the image urls in the field ; inside [img]url[/img] && [img=url] tags
                                    $num = preg_match_all('#(?|\[thumb\](.*?)\[/thumb\]|\[img\](.*?)\[/img\]|\[imgnm\](.*?)\[/imgnm\]|\](.*?)\[/imgalt\]|\[img\=(.*?)\])#ism', $ValidateVar, $imageurls);
                                    $image_filesize_total=0;

                                    if ($num && $num >= $MinImages) { // if there are no img tags then it validates
                                        if ($num > $MaxImages)
                                            return "Too many images in presentation (Max $MaxImages)";
                                        for ($j=0;$j<$num;$j++) {
                                             // validate each image url
                                             // (for the moment use hardcoded image lengths but ideally they should
                                             // probably be taken from some new option fields).
                                            $result = validate_imageurl($imageurls[1][$j], 12, 255, $WLRegex);
                                            if ($result !== TRUE) { return $Field['ErrorMessage'].' field: ' .$result; }

                                            // check image dimensions if max dimensions are specified
                                            if ($MaxWidth>=0 && $MaxHeight>=0) {
                                                $image_attribs = getimagesize($imageurls[1][$j]);
                                                if ($image_attribs!==FALSE) { // i guess we should ignore it if it fails .. hmmm...
                                                    list($width, $height, $type, $attr) = $image_attribs;
                                                    if ($width>$MaxWidth || $height > $MaxHeight)
                                                        return "$Field[ErrorMessage] field:<br/>Image dimensions are too big; width: {$width}px  height: {$height}px<br/>Max Image dimensions; width: {$MaxWidth}px  height: {$MaxHeight}px<br/>File: {$imageurls[1][$j]}";
                                                }
                                            }
                                         }
                                         $this->Weight = get_presentation_size($imageurls[1]);
                                         if($this->Weight >= 25*1024*1024)
                                             return "Presentation is too big! ".get_size($this->Weight)." (Max 25MiB)";
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
        foreach ($this->Fields as $FieldKey => $Field) {
            if ($Field['Type']=="string") {
                $ValItem='	if($(\'#'.$FieldKey.'\').raw().value==""';
                if (!empty($Field['MaxLength'])) { $ValItem.=' || $(\'#'.$FieldKey.'\').raw().value.length>'.$Field['MaxLength']; } else { $ValItem.=' || $(\'#'.$FieldKey.'\').raw().value.length>255'; }
                if (!empty($Field['MinLength'])) { $ValItem.=' || $(\'#'.$FieldKey.'\').raw().value.length<'.$Field['MinLength']; }
                $ValItem.=') { return showError(\''.$FieldKey.'\',\''.$Field['ErrorMessage'].'\'); }'."\r\n";

            } elseif ($Field['Type']=="number") {
                $Match='0-9';
                if (!empty($Field['AllowPeriod'])) { $Match.='.'; }
                if (!empty($Field['AllowComma'])) { $Match.=','; }

                $ValItem='	if($(\'#'.$FieldKey.'\').raw().value.match(/[^'.$Match.']/) || $(\'#'.$FieldKey.'\').raw().value.length<1';
                if (!empty($Field['MaxLength'])) { $ValItem.=' || $(\'#'.$FieldKey.'\').raw().value/1>'.$Field['MaxLength']; }
                if (!empty($Field['MinLength'])) { $ValItem.=' || $(\'#'.$FieldKey.'\').raw().value/1<'.$Field['MinLength']; }
                $ValItem.=') { return showError(\''.$FieldKey.'\',\''.$Field['ErrorMessage'].'\'); }'."\r\n";

            } elseif ($Field['Type']=="email") {
                $ValItem='	if(!validEmail($(\'#'.$FieldKey.'\').raw().value)';
                if (!empty($Field['MaxLength'])) { $ValItem.=' || $(\'#'.$FieldKey.'\').raw().value.length>'.$Field['MaxLength']; } else { $ValItem.=' || $(\'#'.$FieldKey.'\').raw().value.length>255'; }
                if (!empty($Field['MinLength'])) { $ValItem.=' || $(\'#'.$FieldKey.'\').raw().value.length<'.$Field['MinLength']; } else { $ValItem.=' || $(\'#'.$FieldKey.'\').raw().value.length<6'; }
                $ValItem.=') { return showError(\''.$FieldKey.'\',\''.$Field['ErrorMessage'].'\'); }'."\r\n";

            } elseif ($Field['Type']=="link") {
                $ValItem='	if(!validLink($(\'#'.$FieldKey.'\').raw().value)';
                if (!empty($Field['MaxLength'])) { $ValItem.=' || $(\'#'.$FieldKey.'\').raw().value.length>'.$Field['MaxLength']; } else { $ValItem.=' || $(\'#'.$FieldKey.'\').raw().value.length>255'; }
                if (!empty($Field['MinLength'])) { $ValItem.=' || $(\'#'.$FieldKey.'\').raw().value.length<'.$Field['MinLength']; } else { $ValItem.=' || $(\'#'.$FieldKey.'\').raw().value.length<10'; }
                $ValItem.=') { return showError(\''.$FieldKey.'\',\''.$Field['ErrorMessage'].'\'); }'."\r\n";

            } elseif ($Field['Type']=="username") {
                $ValItem='	if($(\'#'.$FieldKey.'\').raw().value.match(/[^a-zA-Z0-9_\-]/)';
                if (!empty($Field['MaxLength'])) { $ValItem.=' || $(\'#'.$FieldKey.'\').raw().value.length>'.$Field['MaxLength']; }
                if (!empty($Field['MinLength'])) { $ValItem.=' || $(\'#'.$FieldKey.'\').raw().value.length<'.$Field['MinLength']; }
                $ValItem.=') { return showError(\''.$FieldKey.'\',\''.$Field['ErrorMessage'].'\'); }'."\r\n";

            } elseif ($Field['Type']=="regex") {
                $ValItem='	if (!$(\'#'.$FieldKey.'\').raw().value.match('.$Field['Regex'].')) { return showError(\''.$FieldKey.'\',\''.$Field['ErrorMessage'].'\'); }'."\r\n";

            } elseif ($Field['Type']=="date") {
                $DisplayError=$FieldKey."month";
                if (isset($Field['MinLength']) && $Field['MinLength']==3) { $Day='$(\'#'.$FieldKey.'day\').raw().value'; $DisplayError.=",".$FieldKey."day"; } else { $Day="1"; }
                $DisplayError.=",".$FieldKey."year";
                $ValItemHold='	if (!validDate($(\'#'.$FieldKey.'month\').raw().value+\'/\'+'.$Day.'+\'/\'+$(\'#'.$FieldKey.'year\').raw().value)) { return showError(\''.$DisplayError.'\',\''.$Field['ErrorMessage'].'\'); }'."\r\n";

                if (empty($Field['Required'])) {
                    $ValItem='	if($(\'#'.$FieldKey.'month\').raw().value!=""';
                    if (isset($Field['MinLength']) && $Field['MinLength']==3) { $ValItem.=' || $(\'#'.$FieldKey.'day\').raw().value!=""'; }
                    $ValItem.=' || $(\'#'.$FieldKey.'year\').raw().value!="") {'."\r\n";
                    $ValItem.=$ValItemHold;
                    $ValItem.="	}\r\n";
                } else {
                    $ValItem.=$ValItemHold;
                }

            } elseif ($Field['Type']=="checkbox") {
                $ValItem='	if (!$(\'#'.$FieldKey.'\').checked) { return showError(\''.$FieldKey.'\',\''.$Field['ErrorMessage'].'\'); }'."\r\n";

            } elseif ($Field['Type']=="compare") {
                $ValItem='	if ($(\'#'.$FieldKey.'\').raw().value!=$(\'#'.$Field['CompareField'].'\').raw().value) { return showError(\''.$FieldKey.','.$Field['CompareField'].'\',\''.$Field['ErrorMessage'].'\'); }'."\r\n";
            }

            if (empty($Field['Required']) && $Field['Type']!="date") {
                $ReturnJS.='	if ($(\'#'.$FieldKey.'\').raw().value!="") {'."\r\n	";
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
}

function get_presentation_size($urls) {

    // make sure the rolling window isn't greater than the # of urls
    $rolling_window = 25;
    $rolling_window = (sizeof($urls) < $rolling_window) ? sizeof($urls) : $rolling_window;

    $curl_arr = array();
    $total=0;

    // add additional curl options here
    $std_options = array(CURLOPT_RETURNTRANSFER => TRUE,
    CURLOPT_FOLLOWLOCATION => TRUE,
    CURLOPT_HEADER => TRUE,
    CURLOPT_NOBODY => TRUE,
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

    do {
        while(($execrun = curl_multi_exec($master, $running)) == CURLM_CALL_MULTI_PERFORM);
        usleep(50000);
        if($execrun != CURLM_OK)
            break;
        // a request was just completed -- find out which one
        while($done = curl_multi_info_read($master)) {
            $info = curl_getinfo($done['handle']);
            if ($info['http_code'] == 200)  {
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

function get_remote_file_size($url, $user = "", $pw = "")
{
    ob_start();
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_NOBODY, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);

    if (!empty($user) && !empty($pw)) {
        $headers = array('Authorization: Basic ' . base64_encode("$user:$pw"));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    // slightly convoluted way to get remote filesize but is more bulletproof than @fsockopen methods
    $ok = curl_exec($ch);
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

            return get_remote_file_size($url, $user, $pw );
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
