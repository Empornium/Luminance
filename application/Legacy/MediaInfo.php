<?php
namespace Luminance\Legacy;

/*
 * Adapted from https://github.com/d1fferent/miparse
 */
 
class MediaInfo
{

    // options
    public    $checkEncodingSettings = FALSE;
    public    $characterSet = 'ISO-8859-1';  // hopefully your site is UTF-8, if so change this default

    // outputs
    public    $filename = 'Mediainfo Log';
    public    $sanitizedLog = '';
    public    $audio = array();
    public    $text = array();
    public    $logs = array(); // will contain an object for each mediainfo log processed

    // internal use
    private $hadBlankLine = FALSE; // only parse as a mediainfo log if it included a blank line
    private $audionum = 0;
    private $textnum = 0;
    private $currentSection = ''; // tracks log section while parsing

    /**
     * Public interface for parsing text containing any amount of mediainfo logs
     * @param string $string    input text
     *
     * @property-write string $output    final HTML output
     * @property-write array $logs    one object per log
    */
    public function parse($string) {

        $string = trim($string);
        $output = array();
        $outputblock = 0; // counter
        $logcount = 0;

        //flags
        $inmi = false; // currently in a mediainfo log
        $insection = false; // currently in a mediainfo log section
        $anymi = false; // debug

        //regexes
        $mistart="/^(?:general$|unique ?id(\/string)?\s*:|complete ?name\s*:|format\s*:\s*(matroska|avi|bdav)$)/i";
        $misection="/^(?:(?:video|audio|text|menu)(?:\s\#\d+?)*)$/i";

        // split on newlines
        $lines = preg_split("/\r\n|\n|\r/", $string);

        // main loop
        for ($i=0, $imax=count($lines); $i < $imax; $i++) {
            $line = trim($lines[$i]);
            $prevline = trim($lines[$i-1]);

            if (strlen($line) == 0) { // blank line?
                $insection = false;
                $output[$outputblock] .= "\n";
                continue;
            }

            if (!$inmi) {
                if (preg_match($mistart, $line)) { // start of a mediainfo log?

                    $Log = new MEDIAINFO;  // create an instance of the class
                    if ($this->checkEncodingSettings === TRUE) {
                        $Log->checkEncodingSettings = TRUE;
                    }

                    $inmi = true;
                    $anymi = true;
                    $insection = true;
                    $Log->currentSection = "general";
                    $outputblock++;
                }
            }

            if ($inmi && $insection && !strlen($line) == 0) {
                $line = $Log->parseProperties($line); // parse "property : value" pairs
            }

            if ($inmi && !$insection) {
                if (preg_match($misection, $line)) { // is it a section start?
                    $insection = true;
                    $Log->currentSection = $line;
                    if (stripos($Log->currentSection, "audio") > -1) {
                        $Log->audionum++;
                    }
                    if (stripos($Log->currentSection, "text") > -1) {
                        $Log->textnum++;
                    }
                    if (strlen($prevline) == 0) {
                        $Log->hadBlankLine = true;
                    }
                    $output[$outputblock] .= self::sanitizeHTML($line) . "\n";
                    continue;
                }
            }

            if ($inmi && !$insection && strlen($prevline) == 0) {
                // end of a mediainfo log

                if ($Log->hadBlankLine) {
                    $Log->sanitizedLog = $output[$outputblock];
                    $output[$outputblock] = $Log->addHTML();
                    $this->logs[$logcount] = $Log; // store current $Log object in array
                    $logcount++;
                }
                $outputblock++;

                // reset flags
                $inmi = false;
                $insection = false;
                $Log->currentSection='';
                $i--;
            }

            // all tests false? then:
            $output[$outputblock] .= self::sanitizeHTML($line) . "\n";
        }

        if ($inmi && $Log->hadBlankLine) { // need to close mi block?
            $Log->sanitizedLog = $output[$outputblock];
            $output[$outputblock] = $Log->addHTML();
            $this->logs[$logcount] = $Log; // store current $Log object in array
        }

        $this->output = trim(implode("", $output));
    }


    /**
     * parse "property : value" pairs, load data into object
     * @param string $line
     * @return string
    */
    protected function parseProperties($line) {
        $array = explode(":", $line, 2);
        $property = strtolower(trim($array[0]));
        $property = preg_replace("#/string$#", "", $property);
        $value = trim($array[1]);

        if (strtoupper($array[0]) == $array[0]) {
            // ignore ALL CAPS tags, as set by mkvmerge 7
            $property = "";
        }

        if ($this->currentSection === "general") {
            switch ($property) {
                case "complete name":
                case "completename":
                    $this->filename = self::stripPath($value);
                    $line = "Complete name : " . $this->filename;
                    break;
                case "format":
                    $this->generalformat = $value;
                    break;
                case "duration":
                    $this->duration = $value;
                    break;
                case "file size":
                case "filesize":
                    $this->filesize = $value;
                    break;
            }
        } else if (stripos($this->currentSection, "video") > -1) {
            switch ($property) {
                case "format":
                    $this->videoformat = $value;
                    break;
                case "format version":
                case "format_version":
                    $this->videoformatversion = $value;
                    break;
                case "codec id":
                case "codecid":
                    $this->codec = strtolower($value);
                    break;
                case "width":
                    $this->width = self::parseSize($value);
                    break;
                case "height":
                    $this->height = self::parseSize($value);
                    break;
                case "writing library":
                case "encoded_library":
                    $this->writinglibrary = $value;
                    break;
                case "frame rate mode":
                case "framerate_mode":
                    $this->frameratemode = $value;
                    break;
                case "frame rate":
                case "framerate":
                    // if variable this becomes Original frame rate
                    $this->framerate = preg_replace('/\(.*/', ' fps', $value); // clean
                    break;
                case "display aspect ratio":
                case "displayaspectratio":
                    $this->aspectratio = str_replace("/", ":", $value); // mediainfo sometimes uses / instead of :
                    break;
                case "bit rate":
                case "bitrate":
                    $this->bitrate = $value;
                    break;
                case "bit rate mode":
                case "bitrate_mode":
                    $this->bitratemode = $value;
                    break;
                case "nominal bit rate":
                case "bitrate_nominal":
                    $this->nominalbitrate = $value;
                    break;
                case "bits/(pixel*frame)":
                case "bits-(pixel*frame)":
                    $this->bpp = $value;
                    break;
                case "bit depth":
                case "bitdepth":
                    $this->bitdepth = $value;
                    break;
                case "encoding settings":
                    $this->encodingsettings = $value;
                    break;
            }
        } else if (stripos($this->currentSection, "audio") > -1) {
            switch ($property) {
                case "format":
                    $this->audio[$this->audionum]['format'] = $value;
                    break;
                case "bit rate":
                case "bitrate":
                    $this->audio[$this->audionum]['bitrate'] = $value;
                    break;
                case "channel(s)":
                    $this->audio[$this->audionum]['channels'] = $value;
                    break;
                case "title":
                    $this->audio[$this->audionum]['title'] = $value;
                    break;
                case "language":
                    $this->audio[$this->audionum]['lang'] = $value;
                    break;
                case "format profile":
                case "format_profile":
                    $this->audio[$this->audionum]['profile'] = $value;
                    break;
            }
        }    else if (stripos($this->currentSection, "text") > -1) {
            switch ($property) {
                case "title":
                    $this->text[$this->textnum]['title-lang'] = $value;
                    break;
                case "language":
                    $this->text[$this->textnum]['lang'] = $value;
                    break;
                case "default":
                    $this->text[$this->textnum]['default'] = $value;
                    break;
            }
        }

        return $line;
    }

    /**
     * convert language to country code
     * @return string
    */
   private function getLocaleCodeForDisplayLanguage($language)
   {
       $language = strtolower($language);

       $languageCodes = [
           "afar"                                                       => "aa",
           "abkhazian"                                                  => "ab",
           "avestan"                                                    => "ae",
           "afrikaans"                                                  => "af",
           "akan"                                                       => "ak",
           "amharic"                                                    => "am",
           "aragonese"                                                  => "an",
           "arabic"                                                     => "ar",
           "assamese"                                                   => "as",
           "avaric"                                                     => "av",
           "aymara"                                                     => "ay",
           "azerbaijani"                                                => "az",
           "bashkir"                                                    => "ba",
           "belarusian"                                                 => "be",
           "bulgarian"                                                  => "bg",
           "bihari"                                                     => "bh",
           "bislama"                                                    => "bi",
           "bambara"                                                    => "bm",
           "bengali"                                                    => "bn",
           "tibetan"                                                    => "bo",
           "breton"                                                     => "br",
           "bosnian"                                                    => "bs",
           "catalan"                                                    => "ca",
           "chechen"                                                    => "ce",
           "chamorro"                                                   => "ch",
           "corsican"                                                   => "co",
           "cree"                                                       => "cr",
           "czech"                                                      => "cs",
           "church slavic"                                              => "cu",
           "chuvash"                                                    => "cv",
           "welsh"                                                      => "cy",
           "danish"                                                     => "da",
           "german"                                                     => "de",
           "divehi"                                                     => "dv",
           "dzongkha"                                                   => "dz",
           "ewe"                                                        => "ee",
           "greek"                                                      => "el",
           "english"                                                    => "gb",
           "english (forced)"                                           => "gb",
           "esperanto"                                                  => "eo",
           "spanish"                                                    => "es",
           "estonian"                                                   => "et",
           "basque"                                                     => "eu",
           "persian"                                                    => "fa",
           "fulah"                                                      => "ff",
           "finnish"                                                    => "fi",
           "fijian"                                                     => "fj",
           "faroese"                                                    => "fo",
           "french"                                                     => "fr",
           "western frisian"                                            => "fy",
           "irish"                                                      => "ga",
           "scottish gaelic"                                            => "gd",
           "galician"                                                   => "gl",
           "guarani"                                                    => "gn",
           "gujarati"                                                   => "gu",
           "manx"                                                       => "gv",
           "hausa"                                                      => "ha",
           "hebrew"                                                     => "he",
           "hindi"                                                      => "hi",
           "hiri motu"                                                  => "ho",
           "croatian"                                                   => "hr",
           "haitian"                                                    => "ht",
           "hungarian"                                                  => "hu",
           "armenian"                                                   => "hy",
           "herero"                                                     => "hz",
           "interlingua (international auxiliary language association)" => "ia",
           "indonesian"                                                 => "id",
           "interlingue"                                                => "ie",
           "igbo"                                                       => "ig",
           "sichuan yi"                                                 => "ii",
           "inupiaq"                                                    => "ik",
           "ido"                                                        => "io",
           "icelandic"                                                  => "is",
           "italian"                                                    => "it",
           "inuktitut"                                                  => "iu",
           "japanese"                                                   => "ja",
           "javanese"                                                   => "jv",
           "georgian"                                                   => "ka",
           "kongo"                                                      => "kg",
           "kikuyu"                                                     => "ki",
           "kwanyama"                                                   => "kj",
           "kazakh"                                                     => "kk",
           "kalaallisut"                                                => "kl",
           "khmer"                                                      => "km",
           "kannada"                                                    => "kn",
           "korean"                                                     => "ko",
           "kanuri"                                                     => "kr",
           "kashmiri"                                                   => "ks",
           "kurdish"                                                    => "ku",
           "komi"                                                       => "kv",
           "cornish"                                                    => "kw",
           "kirghiz"                                                    => "ky",
           "latin"                                                      => "la",
           "luxembourgish"                                              => "lb",
           "ganda"                                                      => "lg",
           "limburgish"                                                 => "li",
           "lingala"                                                    => "ln",
           "lao"                                                        => "lo",
           "lithuanian"                                                 => "lt",
           "luba-katanga"                                               => "lu",
           "latvian"                                                    => "lv",
           "malagasy"                                                   => "mg",
           "marshallese"                                                => "mh",
           "maori"                                                      => "mi",
           "macedonian"                                                 => "mk",
           "malayalam"                                                  => "ml",
           "mongolian"                                                  => "mn",
           "marathi"                                                    => "mr",
           "malay"                                                      => "ms",
           "maltese"                                                    => "mt",
           "burmese"                                                    => "my",
           "nauru"                                                      => "na",
           "norwegian bokmal"                                           => "nb",
           "north ndebele"                                              => "nd",
           "nepali"                                                     => "ne",
           "ndonga"                                                     => "ng",
           "dutch"                                                      => "nl",
           "norwegian nynorsk"                                          => "nn",
           "norwegian"                                                  => "no",
           "south ndebele"                                              => "nr",
           "navajo"                                                     => "nv",
           "chichewa"                                                   => "ny",
           "occitan"                                                    => "oc",
           "ojibwa"                                                     => "oj",
           "oromo"                                                      => "om",
           "oriya"                                                      => "or",
           "ossetian"                                                   => "os",
           "panjabi"                                                    => "pa",
           "pali"                                                       => "pi",
           "polish"                                                     => "pl",
           "pashto"                                                     => "ps",
           "portuguese"                                                 => "pt",
           "quechua"                                                    => "qu",
           "raeto-romance"                                              => "rm",
           "kirundi"                                                    => "rn",
           "romanian"                                                   => "ro",
           "russian"                                                    => "ru",
           "kinyarwanda"                                                => "rw",
           "sanskrit"                                                   => "sa",
           "sardinian"                                                  => "sc",
           "sindhi"                                                     => "sd",
           "northern sami"                                              => "se",
           "sango"                                                      => "sg",
           "sinhala"                                                    => "si",
           "slovak"                                                     => "sk",
           "slovenian"                                                  => "sl",
           "samoan"                                                     => "sm",
           "shona"                                                      => "sn",
           "somali"                                                     => "so",
           "albanian"                                                   => "sq",
           "serbian"                                                    => "sr",
           "swati"                                                      => "ss",
           "southern sotho"                                             => "st",
           "sundanese"                                                  => "su",
           "swedish"                                                    => "sv",
           "swahili"                                                    => "sw",
           "tamil"                                                      => "ta",
           "telugu"                                                     => "te",
           "tajik"                                                      => "tg",
           "thai"                                                       => "th",
           "tigrinya"                                                   => "ti",
           "turkmen"                                                    => "tk",
           "tagalog"                                                    => "tl",
           "tswana"                                                     => "tn",
           "tonga"                                                      => "to",
           "turkish"                                                    => "tr",
           "tsonga"                                                     => "ts",
           "tatar"                                                      => "tt",
           "twi"                                                        => "tw",
           "tahitian"                                                   => "ty",
           "uighur"                                                     => "ug",
           "ukrainian"                                                  => "uk",
           "urdu"                                                       => "ur",
           "uzbek"                                                      => "uz",
           "venda"                                                      => "ve",
           "vietnamese"                                                 => "vi",
           "volapuk"                                                    => "vo",
           "walloon"                                                    => "wa",
           "wolof"                                                      => "wo",
           "xhosa"                                                      => "xh",
           "yiddish"                                                    => "yi",
           "yoruba"                                                     => "yo",
           "zhuang"                                                     => "za",
           "chinese"                                                    => "zh",
           "zulu"                                                       => "zu",
           "sdh"                                                        => "gb",
        ];

        return isset($languageCodes[$language]) ? $languageCodes[$language] : null;
   }


   /**
    * compute mediainfo specs and add HTML
    * @return string HTML
   */
   protected function addHTML() {
        $this->codeccomputed = $this::computeCodec();

        $miaudio = array();
        for ($i=1; $i < count($this->audio)+1; $i++) {
            if (strtolower($this->audio[$i]['format']) === "mpeg audio") {
                switch (strtolower($this->audio[$i]['profile'])) {
                    case "layer 3":
                        $this->audio[$i]['format'] = "MP3";
                        break;
                    case "layer 2":
                        $this->audio[$i]['format'] = "MP2";
                        break;
                    case "layer 1":
                        $this->audio[$i]['format'] = "MP1";
                        break;
                }
            }

            $chansreplace = array(
                ' '        => '',
                'channels' => 'ch',
                'channel'  => 'ch',
                '1ch'      => '1.0ch',
                '7ch'      => '6.1ch',
                '6ch'      => '5.1ch',
                '2ch'      => '2.0ch'
            );
            $chans = str_ireplace(array_keys($chansreplace), $chansreplace, $this->audio[$i]['channels']);

            $result =
                $this->audio[$i]['lang']
                . " " . $chans
                . " " . $this->audio[$i]['format'];
            if ($this->audio[$i]['bitrate']) {
                $result .= " @ " . $this->audio[$i]['bitrate'];
            }
            if ($this->audio[$i]['title']
                && (stripos($this->filename, $this->audio[$i]['title']) === FALSE) ) { // ignore audio track title if it contains filename
                $result .= " (" . $this->audio[$i]['title'] . ")";
            }
            $miaudio[] = $result;
        }

        $misubs = array();
        for ($i=1; $i < count($this->text)+1; $i++) {

            if($this->text[$i]['title-lang'])
             $result = $this->text[$i]['title-lang'];
            elseif($this->text[$i]['lang'])
             $result = $this->text[$i]['lang'];
            elseif($this->text[$i]['default'])
             $result = 'Default';
            $misubs[] = $result;
        }

        if (strtolower($this->frameratemode) != "constant" && $this->frameratemode) {
            $this->framerate = $this->frameratemode;
        }

        if (!$this->bitrate) {
            if (strtolower($this->bitratemode) === "variable") {
                $this->bitrate = "Variable";
            } else {
                $this->bitrate = $this->nominalbitrate;
                $italicBitrate = TRUE;
            }
        }

        // begin building HTML //
        $midiv_start = "<a href='#' onclick='javascript:toggleDisplay(this.nextSibling); return false;' title='View raw mediainfo'> "
        . self::sanitizeHTML($this->filename)
        . " &raquo;</a><div class='mediainfo' style='display:none;'>";
        $midiv_end = "</div>";

        $table = '<table class="mediainfo"><tr><td>'
        . '<table class="nobr noborder"><caption>General</caption>'
        . '<tr><td>Container:&nbsp;&nbsp;</td><td>'. self::sanitizeHTML($this->generalformat)
        . '</td></tr><tr><td>Runtime:&nbsp;</td><td>' . self::sanitizeHTML($this->duration)
        . '</td></tr><tr><td>Size:&nbsp;</td><td>' . self::sanitizeHTML($this->filesize);

        $table .= '</td></tr></table></td>'
        . '<td><table class="nobr noborder"><caption>Video</caption>'
        . '<tr><td>Codec:&nbsp;</td><td>' . self::sanitizeHTML($this->codeccomputed);

        if (stripos($this->bitdepth, "10 bit") !== FALSE) {
            $table .= " (10-bit)";
        }

        $table .= '</td></tr><tr><td>Resolution:&nbsp;</td><td>' . self::sanitizeHTML($this->width) . 'x' . self::sanitizeHTML($this->height) . "&nbsp;" . self::displayDimensions()
        . '</td></tr><tr><td>Aspect&nbsp;ratio:&nbsp;&nbsp;</td><td>' . self::sanitizeHTML($this->aspectratio)
        . '</td></tr><tr><td>Frame&nbsp;rate:&nbsp;</td><td>' . self::sanitizeHTML($this->framerate)
        . '</td></tr><tr><td>Bit&nbsp;rate:&nbsp;</td><td>';

//        if ($italicBitrate === TRUE) {
//            $table .= "<em>" . self::sanitizeHTML($this->bitrate) . "</em>";
//        } else {
            $table .= self::sanitizeHTML($this->bitrate);
//        }

        $table .= '</td></tr>' // <tr><td>BPP:&nbsp;</td><td>' . self::sanitizeHTML($this->bpp)
        . '</table></td><td>'
        . '<table class="nobr noborder"><caption>Audio</caption>';

        for ($i=0; $i < count($miaudio); $i++) {
            $table .= '<tr><td>#' . intval($i+1) .': &nbsp;</td><td>'
            . self::sanitizeHTML($miaudio[$i]) . '</td></tr>';
        }

      if($misubs){

        $table .= '</table></td><td>'
        . '<table class="nobr noborder"><caption>Subtitles</caption>';

         for ($i = 0, $c = count($misubs); $i < $c; $i++) {
             $Iteration = intval($i + 1);
             $Flag      = '';
             $Language  = self::sanitizeHTML($misubs[$i]);

             // Consider 'default' as english
             if($misubs[$i] === 'Default')
                $misubs[$i] = 'English';

             // Get country code
             $cd = trim(preg_replace('/\(SDH\)/', '', $misubs[$i])); // Remove SDH mention
             $cd = $this->getLocaleCodeForDisplayLanguage($cd);

             // Add a flag if there's an image for it
             if ($cd)
                 $Flag = '<img src="/static/common/flags/iso16/'.$cd.'.png" alt="'.$Language.'" title="'.$Language.'" />';

             $table .= "<tr><td>#$Iteration:</td><td>$Flag</td><td>$Language</td></tr>";
         }
      }

        $table .= '</table></td></tr>';

        if ($this->checkEncodingSettings && $this->encodingsettings) {
            $poorSpecs = $this->checkEncodingSettings();
            if ($poorSpecs) {
                $table .= '<tr><td colspan="3">
                Encoding specs checks: '
                . self::sanitizeHTML($poorSpecs)
                . '</td></tr>';
            }
        }

      $table .= '</table>';

        return "<div>" . $midiv_start . $this->sanitizedLog . $midiv_end . $table . "</div>";
    }

    /**
     * check video encoding settings
     * @return string or null
    */
    protected function checkEncodingSettings() {
        $poorSpecs = array();
        $settings = explode("/", $this->encodingsettings);

        foreach($settings as $str) {
            $arr = explode("=", $str);
            $property = strtolower( trim($arr[0]) );
            $value = trim( $arr[1] );

            switch ($property) {
                case "rc_lookahead":
                    if ($value < 60) {
                        $poorSpecs[] = "rc_lookahead=".$value." (<60)";
                    }
                    break;

                case "subme":
                    if ($value < 9) {
                        $poorSpecs[] = "subme=".$value." (<9)";
                    }
                    break;
            }
        }

        return implode(". ", $poorSpecs);
    }

    /**
     * calculates approximate display dimensions of anamorphic video
     * @return string HTML or null
    */
    private function displayDimensions() {
        $w = intval($this->width);
        $h = intval($this->height);
        if ($h < 1 || $w < 1 || !$this->aspectratio) {
            return; // bad input
        }

        $ar = explode(":", $this->aspectratio);
        if (count($ar) > 1) {
            $ar = $ar[0] / $ar[1]; // e.g. 4:3 becomes 1.333...
        } else {
            $ar = $ar[0];
        }

        $calcw = intval($h * $ar);
        $calch = intval($w / $ar);
        $output = $calcw . "x" . $h;
        $outputAlt = $w . "x" . $calch;

        $chk = 27;
        $chkw = $calcw > ($w-$chk) && $calcw < ($w+$chk);
        $chkh = $calch > ($h-$chk) && $calch < ($h+$chk);
        if ($chkw && $chkh) {
            // calculated dimensions are +/-$chk pixels of source dimensions, return null
            return;
        }

        if ( ($w * $calch) > ($calcw * $h) ) { // pick greater overall size
            $tmp = $output;
            $output = $outputAlt;
            $outputAlt = $tmp;
        }

        return "~&gt;&nbsp;<span title='Alternatively "
            . $outputAlt . "'>" . $output . "</span>";
    }

    /**
     * Removes unneeded data from $string when calculating width and height in pixels
     * @param string $string
     * @return string
    */
    private function parseSize($string) {
        return str_replace(array('pixels', ' '), null, $string);
    }

    /**
     * Calculates the codec of the input mediainfo file
     * @return string
    */
    private function computeCodec() {
        switch (strtolower($this->videoformat)) {
            case "mpeg video":
                switch (strtolower($this->videoformatversion)) {
                    case "version 2":
                        return "MPEG-2";
                    case "version 1":
                        return "MPEG-1";
                }
                return $this->videoformat;
        }

        switch (strtolower($this->codec)) {
            case "div3":
                return "DivX 3";
            case "divx":
            case "dx50":
                return "DivX";
            case "xvid":
                return "XviD";
            case "x264":
                return "x264";
            case "x265":
                return "x265";
        }

        $chk = strtolower($this->codec);
        $wl = strtolower($this->writinglibrary);
        $vf = strtolower($this->videoformat);
        // H264/5 is a codex, x264/5 is a library for creating videos of that codec.
        if ($chk === "v_mpeg4/iso/avc" || $chk === "avc1" || $vf === "avc" || strpos($wl, "x264 core") > -1) {
            return "H264";
        }
        if ($chk === "v_mpegh/iso/hevc" || $chk === "hevc1" || $vf === "hevc" || strpos($wl, "x265") > -1) {
            return "H265";
        }
    }

    /**
     * Removes file path from $string
     * @param string $string
     * @return string
    */
    private function stripPath($string) {
        $string = str_replace("\\", "/", $string);
        $path_parts = pathinfo($string);
        return $path_parts['basename'];
    }


    /**
     * Function to sanitize user input
     * @param mixed $value str or array
     * @return mixed sanitized output
    */
    private function sanitizeHTML (&$value) {

        if (is_array($value)){
            foreach ($value as $k => $v){
                $value[$k] = self::sanitizeHTML($v);
            }
        }

        return htmlentities((string) $value, ENT_QUOTES, $this->characterSet);
    }

} // end class
