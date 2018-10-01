<?php
namespace Luminance\Legacy;

class Text
{
    // tag=>max number of attributes 'link'=>1,
    private $ValidTags = [
        'ratiolist'  => 0,
        'code'       => 1,
        'codeblock'  => 1,
        'you'        => 0,
        'h5v'        => 1,
        'yt'         => 1,
        'vimeo'      => 1,
        'video'      => 1,
        'flash'      => 1,
        'banner'     => 0,
        'thumb'      => 0,
        '#'          => 1,
        'anchor'     => 1,
        'mcom'       => 0,
        'table'      => 1,
        'th'         => 1,
        'tr'         => 1,
        'td'         => 1,
        'bg'         => 1,
        'cast'       => 0,
        'details'    => 0,
        'info'       => 0,
        'plot'       => 0,
        'screens'    => 0,
        'br'         => 0,
        'hr'         => 0,
        'font'       => 1,
        'center'     => 0,
        'spoiler'    => 1,
        'b'          => 0,
        'u'          => 0,
        'i'          => 0,
        's'          => 0,
        'sup'        => 0,
        'sub'        => 0,
        '*'          => 0,
        'user'       => 0,
        'n'          => 0,
        'inlineurl'  => 0,
        'inlinesize' => 1,
        'align'      => 1,
        'color'      => 1,
        'colour'     => 1,
        'size'       => 1,
        'url'        => 1,
        'img'        => 1,
        'quote'      => 1,
        'pre'        => 1,
        'tex'        => 0,
        'hide'       => 1,
        'plain'      => 0,
        'important'  => 0,
        'torrent'    => 0,
        'request'    => 0,
        'collage'    => 0,
        'thread'     => 0,
        'forum'      => 0,
        'rank'       => 1,
        'tip'        => 1,
        'imgnm'      => 1,
        'imgalt'     => 1,
        'article'    => 1,
        'id'         => 1,
        'mediainfo'  => 0,
    ];

    //  font name (display) => fallback fonts (css)
    private $Fonts = [
        'Arial'                  => "Arial, 'Helvetica Neue', Helvetica, sans-serif;",
        'Arial Black'            => "'Arial Black', 'Arial Bold', Gadget, sans-serif;",
        'Comic Sans MS'          => "'Comic Sans MS', cursive, sans-serif;",
        'Courier New'            => "'Courier New', Courier, 'Lucida Sans Typewriter', 'Lucida Typewriter', monospace;",
        'Franklin Gothic Medium' => "'Franklin Gothic Medium', 'Franklin Gothic', 'ITC Franklin Gothic', Arial, sans-serif;",
        'Georgia'                => "Georgia, Times, 'Times New Roman', serif;",
        'Helvetica'              => "'Helvetica Neue', Helvetica, Arial, sans-serif;",
        'Impact'                 => "Impact, Haettenschweiler, 'Franklin Gothic Bold', Charcoal, 'Helvetica Inserat', 'Bitstream Vera Sans Bold', 'Arial Black', sans-serif;",
        'Lucida Console'         => "'Lucida Console', Monaco, monospace;",
        'Lucida Sans Unicode'    => "'Lucida Sans Unicode', 'Lucida Grande', 'Lucida Sans', Geneva, Verdana, sans-serif;",
        'Microsoft Sans Serif'   => "'Microsoft Sans Serif', Helvetica, sans-serif;",
        'Palatino Linotype'      => "Palatino, 'Palatino Linotype', 'Palatino LT STD', 'Book Antiqua', Georgia, serif;",
        'Tahoma'                 => "Tahoma, Verdana, Segoe, sans-serif;",
        'Times New Roman'        => "TimesNewRoman, 'Times New Roman', Times, Baskerville, Georgia, serif;",
        'Trebuchet MS'           => "'Trebuchet MS', 'Lucida Grande', 'Lucida Sans Unicode', 'Lucida Sans', Tahoma, sans-serif;",
        'Verdana'                => "Verdana, Geneva, sans-serif;",
    ];
    //  icon tag => img  //[cast][details][info][plot][screens]
    private $Icons = [
        'cast'    => "cast11.png",
        'details' => "details11.png",
        'info'    => "info11.png",
        'plot'    => "plot11.png",
        'screens' => "screens11.png",
    ];
    private $NoMedia = 0; // If media elements should be turned into URLs
    private $Levels = 0; // nesting level
    private $Advanced = false; // allow advanced tags to be printed
    private $ShowErrors = false;
    private $Errors = [];

    private $displayed_images = [];

    //public $Smilies = array(); // for testing only
    public function __construct()
    {
        include(SERVER_ROOT.'/Legacy/classes/Smileys.php');
        $this->Smileys = $Smileys;
        foreach ($this->Smileys as $Key => $Val) {
            $this->Smileys[$Key] = '<img src="/static/common/smileys/' . $Val . '" alt="' . $Key . '" />';
            //$this->Smilies[] = $Val;
        }
        reset($this->Smileys);

        foreach ($this->Icons as $Key => $Val) {
            $this->Icons[$Key] = '<img src="/static/common/icons/' . $Val . '" alt="' . $Key . '" />';
        }
        reset($this->Icons);
    }

    public function has_errors()
    {
        return count($this->Errors) > 0;
    }

    public function get_errors()
    {
        return $this->Errors;
    }

    public function full_format($Str, $AdvancedTags = false, $ShowErrors = false)
    {
        $this->Advanced = $AdvancedTags;
        $this->ShowErrors = $ShowErrors;
        $this->Errors = array();

        $Str = display_str($Str);

        //Inline links
        $Str = preg_replace('/\[bg\=/i', '[bgg=', $Str);
        $Str = preg_replace('/\[td\=/i', '[tdd=', $Str);
        $Str = preg_replace('/\[tr\=/i', '[trr=', $Str);
        $Str = preg_replace('/\[th\=/i', '[thh=', $Str);

        // URL Prefix Exceptions
        $URLPrefix = implode('|', [
            '\[url\]|\[url\=',               // Links
            '\[vide|\[vime',                 // Videos
            '\[img\=|\[img\]|\[thum|\[bann', // Images
            '\[h5v\]|\[h5v\=',               // HTML5
            '\[bgg\=',                       // Background
            '\[tdd\=|\[trr\=|\[tabl',        // Tables
            '\[imgn|\[imga',                 // More Images
        ]);

        $Str = preg_replace('/(' . $URLPrefix . ')\s+/i', '$1', $Str);
        $Str = preg_replace('/(?<!' . $URLPrefix . ')http(s)?:\/\//i', '[inlineurl]http$1://', $Str);

        // For anonym.to and archive.org links, remove any [inlineurl] in the middle of the link
        $callback = create_function('$matches', 'return str_replace("[inlineurl]","",$matches[0]);');
        $Str = preg_replace_callback('/(?<=\[inlineurl\]|' . $URLPrefix . ')(\S*\[inlineurl\]\S*)/m', $callback, $Str);

        $Str = preg_replace('/\=\=\=\=([^=]+?)\=\=\=\=/i', '[inlinesize=3]$1[/inlinesize]', $Str);
        $Str = preg_replace('/\=\=\=([^=]+?)\=\=\=/i', '[inlinesize=5]$1[/inlinesize]', $Str);
        $Str = preg_replace('/\=\=([^=]+?)\=\=/i', '[inlinesize=7]$1[/inlinesize]', $Str);

        $Str = preg_replace('/\[bgg\=/i', '[bg=', $Str);
        $Str = preg_replace('/\[tdd\=/i', '[td=', $Str);
        $Str = preg_replace('/\[trr\=/i', '[tr=', $Str);
        $Str = preg_replace('/\[thh\=/i', '[th=', $Str);

        $Str = $this->parse($Str);
        $Str = $this->validate_stack($Str);
        $HTML = $this->to_html($Str);

        // Formatting cleanup
        $HTML = str_replace('  ', '&nbsp;&nbsp;', $HTML);
        $HTML = nl2br($HTML);

        return $HTML;
    }

    private $CheckTags = [
      'tr' => ['table'],
      'th' => ['tr'],
      'td' => ['tr'],
    ];

    protected function validate_stack($Stack, $Parent=null) {
        $ordered_tags = array_keys($this->CheckTags);
        foreach ($Stack as $Index => $Block) {
            if (!isset($Block['Type'], $Block['Val']))
                continue;
            if (in_array($Block['Type'], $ordered_tags)) {
                if (!in_array($Parent, $this->CheckTags[$Block['Type']])) {
                    // log an error (when submitting)
                    $this->Errors[] = "<span class=\"error_label\">illegal placement of [{$Block[Type]}] tag</span>";
                    // Delete orphaned tag (when viewing)
                    unset($Stack[$Index]);
                    continue;
                }
            }
            if (is_array($Block['Val'])) {
                // Recurse the stack
                $Stack[$Index]['Val'] = $this->validate_stack($Block['Val'], $Block['Type']);
            }
        }
        return $Stack;
    }

    /**
     * Validates the bbcode for bad tags (unclosed/mixed tags)
     *
     * @param  string  $Str          The text to be validated
     * @param  boolean $AdvancedTags Whether AdvancedTags are allowed (this is only for the preview if errorout=true)
     * @param  boolean $ErrorOut     If $ErrorOut=true then on errors the error page will be displayed with a preview of the errors (If false the function just returns the validate result)
     * @return boolean True if there are no bad tags and false otherwise
     */
    public function validate_bbcode($Str, $AdvancedTags = false, $ErrorOut = true, $FurtherCheck = true)
    {
        global $master, $LoggedUser;
        $preview = $this->full_format($Str, $AdvancedTags, true, true);
        if ($this->has_errors()) {
            if ($ErrorOut) {
                $bbErrors = implode('<br/>', $this->get_errors());
                error("There are errors in your bbcode <br/><br/>$bbErrors<br/>If the tag(s) highlighted do actually have a closing tag then you probably have overlapping tags
                        <br/>ie.<br/><span style=\"font-weight:bold\">[b]</span> [i] your text <span style=\"font-weight:bold\">[/b] </span>[/i] <span style=\"color:red\">(wrong)</span> - <em>tags must be nested, when they overlap like this it throws an error</em>
                        <br/><span style=\"font-weight:bold\">[b]</span> [i] your text [/i] <span style=\"font-weight:bold\">[/b]</span> <span style=\"color:green\">(correct)</span> - <em>properly nested tags</em></div><br/><div class=\"head\">Your post</div><div class=\"box pad\">
                        <div class=\"box\"><div class=\"post_content\">$preview</div></div><br/>
                        <div style=\"font-style:italic;text-align:center;cursor:pointer;\"><a onclick=\"window.history.go(-1);\">click here or use the back button in your browser to return to your message</a></div>");
            }

            return false;
        }

        if ($FurtherCheck) {

            // As of right now, we only check images,
            // we can skip everything, if it's disabled
            if (!$master->options->ImagesCheck || $LoggedUser['Class'] >= $master->options->ImagesCheckMinClass) {
                return true;
            }

            // Max. number of images in posts
            $MaxImages = (int) $master->options->MaxImagesCount;

            // Check ount first
            if (count($this->displayed_images) > $MaxImages) {
                $Error  = "Your post contains too many images. (Max: $MaxImages)<br>";
                $Error .= "Try posting the direct links instead.";
                error($Error);
            }

            // Max. size for images in posts (MB)
            $MaxWeight = (int) $master->options->MaxImagesWeight;
            $MaxWeight = $MaxWeight * 1024 * 1024;

            $Validate = new Validate();
            $post_size = $Validate->get_presentation_size(array_keys($this->displayed_images));
            if($post_size > $MaxWeight) {
                $post_size = round($post_size / 1024 / 1024, 2);
                $MaxWeight = round($MaxWeight / 1024 / 1024, 2);
                $Error  = "Your post contains too many images. (Weight: $post_size MB - Max: $MaxWeight MB)<br>";
                $Error .= "Try posting thumbnails instead or simply post the direct links.";
                error($Error);
            }
        }

        return true;
    }

    /**
     * Validates the bbcode for bad image URLs
     *
     * @param  string  $imageurl          The text to be validated
     * @return boolean True if there are no bad tags and false otherwise
     */
    public function validate_imageurl($imageurl)
    {
        if (check_perms('site_skip_imgwhite')) return true;
        $whitelist_regex = get_whitelist_regex();
        $result = validate_imageurl($imageurl, 10, 255, $whitelist_regex, '');
        if ($result !== TRUE) $result=FALSE;
        return $result;
    }

    public function proxify_url($url) {
        global $master;

        if ($master->settings->site->image_proxy_url && !$this->validate_imageurl($url)) {
            $host = strtolower(parse_url($url, PHP_URL_HOST));
            $sha256 = hash('sha256', $url, true);
            $hash_str = strtr(base64_encode($sha256), '+/', '-_');
            $parts = [
                $master->settings->site->image_proxy_url,
                'u',
                $host,
                substr($hash_str, 0, 2),
                substr($hash_str, 2, 8)."?url=".urlencode($url)
            ];
            $target_url = implode('/', $parts);
        } else {
            $target_url = $url;
        }
        return $target_url;
    }

    public function local_url($url, $always_full = false) {
        global $master;

        $regex = $master->settings->main->internal_urls_regex;
        if (preg_match($regex, $url)) {
            $prepared_url = '';
            if ($always_full) {
                $prepared_url .= ($master->request->ssl) ? 'https://' : 'http://';
                $prepared_url .= $master->server['HTTP_HOST'];
            }
            $prepared_url .= preg_replace('#^(://|[^/])+#', '', $url);
            if (!strlen($prepared_url)) {
                $prepared_url = '/';
            }
            return $prepared_url;
        }
        return false;
    }


    public function strip_bbcode($Str)
    {
        $Str = display_str($Str);

        //Inline links
        $Str = preg_replace('/(?<!(\[url\]|\[url\=|\[img\=|\[img\]))http(s)?:\/\//i', '$1[inlineurl]http$2://', $Str);
        $Str = $this->parse($Str);
        $Str = $this->raw_text($Str);

        $Str = nl2br($Str);

        return $Str;
    }

    // how much readable text is in string
    public function text_count($Str)
    {
        //remove tags
        $Str = $this->db_clean_search($Str);
        //remove endofline
        $Str = str_replace(array("\r\n", "\n", "\r"), '', $Str);
        $Str = trim($Str);

        return mb_strlen($Str);
    }

    // I took a shortcut here and made this function instead of using strip_bbcode since it's purpose is a bit
    // different.
    public function db_clean_search($Str)
    {
        foreach ($this->Smileys as $key => $value) {
            $remove[] = "/$key/i";
        }

        // anchors
        $remove[] = '/\[\#.*?\]/i';
        $remove[] = '/\[\/\#\]/i';
        $remove[] = '/\[anchor.*?\]/i';
        $remove[] = '/\[\/anchor\]/i';

        $remove[] = '/\[align.*?\]/i';
        $remove[] = '/\[\/align\]/i';

        $remove[] = '/\[article.*?\]/i';
        $remove[] = '/\[\/article\]/i';

        $remove[] = '/\[mediainfo\]/i';
        $remove[] = '/\[\/mediainfo\]/i';

        $remove[] = '/\[audio\].*?\[\/audio\]/i';

        $remove[] = '/\[b\]/i';
        $remove[] = '/\[\/b\]/i';

        $remove[] = '/\[banner\].*?\[\/banner\]/i';

        $remove[] = '/\[bg.*?\]/i';
        $remove[] = '/\[\/bg\]/i';

        $remove[] = '/\[br\]/i';

        $remove[] = '/\[cast\]/i';

        $remove[] = '/\[center.*?\]/i';
        $remove[] = '/\[\/center\]/i';

        $remove[] = '/\[codeblock.*?\]/i';
        $remove[] = '/\[\/codeblock\]/i';

        $remove[] = '/\[code.*?\]/i';
        $remove[] = '/\[\/code\]/i';

        $remove[] = '/\[color.*?\]/i';
        $remove[] = '/\[\/color\]/i';

        $remove[] = '/\[colour.*?\]/i';
        $remove[] = '/\[\/colour\]/i';

        $remove[] = '/\[details\]/i';

        $remove[] = '/\[flash.*?\].*?\[\/flash\]/i';

        $remove[] = '/\[font.*?\]/i';
        $remove[] = '/\[\/font\]/i';

        $remove[] = '/\[link.*?\]/i';
        $remove[] = '/\[\/link\]/i';

        $remove[] = '/\[h5v.*?\].*?\[\/h5v\]/i';

        $remove[] = '/\[hide\]/i';
        $remove[] = '/\[\/hide\]/i';

        $remove[] = '/\[hr\]/i';

        $remove[] = '/\[i\]/i';
        $remove[] = '/\[\/i\]/i';

        $remove[] = '/\[id.*?\]/i';
        $remove[] = '/\[\/id\]/i';

        $remove[] = '/\[img.*?\].*?\[\/img\]/i';
        $remove[] = '/\[imgalt.*?\].*?\[\/imgalt\]/i';
        $remove[] = '/\[imgnm.*?\].*?\[\/imgnm\]/i';

        $remove[] = '/\[important\]/i';
        $remove[] = '/\[\/important\]/i';

        $remove[] = '/\[info\]/i';

        $remove[] = '/\[list\]/i';
        $remove[] = '/\[\/list\]/i';

        $remove[] = '/\[mcom\]/i';
        $remove[] = '/\[\/mcom\]/i';

        $remove[] = '/\[media.*?\].*?\[\/media\]/i';

        $remove[] = '/\[plain\]/i';
        $remove[] = '/\[\/plain\]/i';

        $remove[] = '/\[plot\]/i';

        $remove[] = '/\[pre\]/i';
        $remove[] = '/\[\/pre\]/i';

        $remove[] = '/\[quote\]/i';
        $remove[] = '/\[\/quote\]/i';

        $remove[] = '/\[rank.*?\]/i';
        $remove[] = '/\[\/rank\]/i';

        $remove[] = '/\[s\]/i';
        $remove[] = '/\[\/s\]/i';

        $remove[] = '/\[size.*?\]/i';
        $remove[] = '/\[\/size\]/i';

        $remove[] = '/\[spoiler\]/i';
        $remove[] = '/\[\/spoiler\]/i';

        // Table elements
        $remove[] = '/\[table.*?\]/i';
        $remove[] = '/\[\/table\]/i';
        $remove[] = '/\[tr.*?\]/i';
        $remove[] = '/\[\/tr\]/i';
        $remove[] = '/\[th.*?\]/i';
        $remove[] = '/\[\/th\]/i';
        $remove[] = '/\[td.*?\]/i';
        $remove[] = '/\[\/td\]/i';

        $remove[] = '/\[tex\].*?\[\/tex\]/i';

        $remove[] = '/\[tip.*?\]/i';
        $remove[] = '/\[\/tip\]/i';

        $remove[] = '/\[thumb\].*?\[\/thumb\]/i';

        $remove[] = '/\[torrent\].*?\[\/torrent\]/i';
        $remove[] = '/\[request\].*?\[\/request\]/i';
        $remove[] = '/\[collage\].*?\[\/collage\]/i';
        $remove[] = '/\[thread\].*?\[\/thread\]/i';
        $remove[] = '/\[forum\].*?\[\/forum\]/i';

        $remove[] = '/\[u\]/i';
        $remove[] = '/\[\/u\]/i';

        $remove[] = '/\[url.*?\].*?\[\/url\]/i';

        $remove[] = '/\[user\]/i';
        $remove[] = '/\[\/user\]/i';

        $remove[] = '/\[video.*?\].*?\[\/video.*?\]/i';

        $remove[] = '/\[you\]/i';

        $remove[] = '/\[yt.*?\]/i';
        $remove[] = '/\[vimeo.*?\]/i';

        $Str = preg_replace($remove, '', $Str);
        $Str = preg_replace('/[\r\n]+/', ' ', $Str);

        return $Str;
    }

    public function valid_url($Str, $Extension = '', $Inline = false)
    {
        return preg_match(getValidUrlRegex($Extension, $Inline), $Str);
    }


    /* How parsing works

      Parsing takes $Str, breaks it into blocks, and builds it into $Array.
      Blocks start at the beginning of $Str, when the parser encounters a [, and after a tag has been closed.
      This is all done in a loop.

      EXPLANATION OF PARSER LOGIC

      1) Find the next tag (regex)
      1a) If there aren't any tags left, write everything remaining to a block and return (done parsing)
      1b) If the next tag isn't where the pointer is, write everything up to there to a text block.
      2) See if it's a [[wiki-link]] or an ordinary tag, and get the tag name
      3) If it's not a wiki link:
      3a) check it against the $this->ValidTags array to see if it's actually a tag and not [bullshit]
      If it's [not a tag], just leave it as plaintext and move on
      3b) Get the attribute, if it exists [name=attribute]
      4) Move the pointer past the end of the tag
      5) Find out where the tag closes (beginning of [/tag])
      5a) Different for different types of tag. Some tags don't close, others are weird like [*]
      5b) If it's a normal tag, it may have versions of itself nested inside - eg:
      [quote=bob]*
      [quote=joe]I am a redneck!**[/quote]
      Me too!
     * **[/quote]
      If we're at the position *, the first [/quote] tag is denoted by **.
      However, our quote tag doesn't actually close there. We must perform
      a loop which checks the number of opening [quote] tags, and make sure
      they are all closed before we find our final [/quote] tag (***).

      5c) Get the contents between [open] and [/close] and call it the block.
      In many cases, this will be parsed itself later on, in a new parse() call.
      5d) Move the pointer past the end of the [/close] tag.
      6) Depending on what type of tag we're dealing with, create an array with the attribute and block.
      In many cases, the block may be parsed here itself. Stick them in the $Array.
      7) Increment array pointer, start again (past the end of the [/close] tag)

     */

    public function parse($Str)
    {
        $i = 0; // Pointer to keep track of where we are in $Str
        $Len = strlen($Str);
        $Array = array();
        $ArrayPos = 0;

        while ($i < $Len) {
            $Block = '';

            // 1) Find the next tag (regex)
            // [name(=attribute)?]|[[wiki-link]]
            $IsTag = preg_match("/((\[[a-zA-Z*#5]+)(=(?:[^\n'\"\[\]]|\[\d*\])+)?\])|(\[\[[^\n\"'\[\]]+\]\])/", $Str, $Tag, PREG_OFFSET_CAPTURE, $i);

            // 1a) If there aren't any tags left, write everything remaining to a block
            if (!$IsTag) {
                // No more tags
                $Array[$ArrayPos] = substr($Str, $i);
                break;
            }

            // 1b) If the next tag isn't where the pointer is, write everything up to there to a text block.
            $TagPos = $Tag[0][1];
            if ($TagPos > $i) {
                $Array[$ArrayPos] = substr($Str, $i, $TagPos - $i);
                ++$ArrayPos;
                $i = $TagPos;
            }

            // 2) See if it's a [[wiki-link]] or an ordinary tag, and get the tag name
            if (!empty($Tag[4][0])) { // Wiki-link
                $WikiLink = true;
                $TagName = substr($Tag[4][0], 2, -2);
                $Attrib = '';
            } else { // 3) If it's not a wiki link:
                $WikiLink = false;
                $TagName = strtolower(substr($Tag[2][0], 1));

                //3a) check it against the $this->ValidTags array to see if it's actually a tag and not [bullshit]
                if (!isset($this->ValidTags[$TagName])) {
                    $Array[$ArrayPos] = substr($Str, $i, ($TagPos - $i) + strlen($Tag[0][0]));
                    $i = $TagPos + strlen($Tag[0][0]);
                    ++$ArrayPos;
                    continue;
                }

                // Check if user is allowed to use moderator tags (different from Advanced, which is determined
                // by the original post author).
                // We're using ShowErrors as a proxy for figuring out if we're editing or just viewing
                if ($this->ShowErrors && $this->AdvancedTagOnly[$TagName] && !check_perms('site_moderate_forums')) {
                    $this->Errors[] = "<span class=\"error_label\">illegal tag [$TagName]</span>";
                }

                $MaxAttribs = $this->ValidTags[$TagName];

                // 3b) Get the attribute, if it exists [name=attribute]
                if (!empty($Tag[3][0])) {
                    $Attrib = substr($Tag[3][0], 1);
                } else {
                    $Attrib = '';
                }
            }

            // 4) Move the pointer past the end of the tag
            $i = $TagPos + strlen($Tag[0][0]);

            // 5) Find out where the tag closes (beginning of [/tag])
            // Unfortunately, BBCode doesn't have nice standards like xhtml
            // [*] and http:// follow different formats
            // Thus, we have to handle these before we handle the majority of tags
            //5a) Different for different types of tag. Some tags don't close, others are weird like [*]
            if ($TagName == 'video' || $TagName == 'yt' || $TagName == 'vimeo') {
                $Block = '';
            } elseif ($TagName == 'inlineurl') { // We did a big replace early on to turn http:// into [inlineurl]http://
                // Let's say the block can stop at a newline or a space
                $CloseTag = strcspn($Str, " \n\r", $i);
                if ($CloseTag === false) { // block finishes with URL
                    $CloseTag = $Len;
                }
                if (preg_match('/[!;,.?:]+$/', substr($Str, $i, $CloseTag), $Match)) {
                    $CloseTag -= strlen($Match[0]);
                }
                $URL = substr($Str, $i, $CloseTag);
                if (substr($URL, -1) == ')' && substr_count($URL, '(') < substr_count($URL, ')')) {
                    $CloseTag--;
                    $URL = substr($URL, 0, -1);
                }
                $Block = $URL; // Get the URL
                // strcspn returns the number of characters after the offset $i, not after the beginning of the string
                // Therefore, we use += instead of the = everywhere else
                $i += $CloseTag; // 5d) Move the pointer past the end of the [/close] tag.
            } elseif ($WikiLink == true || $TagName == 'ratiolist' || $TagName == 'n' || $TagName == 'br' || $TagName == 'hr' || $TagName == 'cast' || $TagName == 'details' || $TagName == 'info' || $TagName == 'plot' || $TagName == 'screens' || $TagName == 'you') {
                // Don't need to do anything - empty tag with no closing
            } elseif ($TagName === '*') {   //  || $TagName === '#' - no longer list tag
                // We're in a list. Find where it ends
                $NewLine = $i;
                do {
                    // Don't overrun
                    if ($NewLine == $Len) {
                        break;
                    }
                    // Look for \n[*]
                    $NewLine = strpos($Str, "\n", $NewLine + 1);
                } while ($NewLine !== false && substr($Str, $NewLine + 1, 3) == '[' . $TagName . ']');

                $CloseTag = $NewLine;
                if ($CloseTag === false) { // block finishes with list
                    $CloseTag = $Len;
                }
                $Block = substr($Str, $i, $CloseTag - $i); // Get the list
                $i = $CloseTag; // 5d) Move the pointer past the end of the [/close] tag.
            } else {
                //5b) If it's a normal tag, it may have versions of itself nested inside
                $CloseTag = $i - 1;
                $InTagPos = $i - 1;
                $NumInOpens = 0;
                $NumInCloses = -1;

                $InOpenRegex = '/\[(' . $TagName . ')';
                if ($MaxAttribs > 0) {
                    $InOpenRegex.="(=[^\n'\"\[\]]+)?";
                }
                $InOpenRegex.='\]/i';

                $closetaglength = strlen($TagName) + 3;

                // Every time we find an internal open tag of the same type, search for the next close tag
                // (as the first close tag won't do - it's been opened again)
                do {
                    $CloseTag = stripos($Str, '[/' . $TagName . ']', $CloseTag + 1);
                    if ($CloseTag === false) {
                        if ($TagName == '#' || $TagName == 'anchor') {
                            // automatically close open anchor tags (otherwise it wraps the entire text
                            // in an <a> tag which then get stripped from the end as they are way out of place and you have
                            // open <a> tags in the code - but links without any href so its a subtle break
                            $CloseTag = $i;
                            $closetaglength = 0;
                        } elseif ($TagName == 'img') { // This handles the single [img=] tags
                            $Block = true; // Bypass check below
                            $CloseTag = $i;
                            $closetaglength = 0;
                        } else {
                            // lets try and deal with badly formed bbcode in a better way
                            $istart = max(  $TagPos- 20, 0 );
                            $iend = min( $i + 20, $Len );
                            $errnum = count($this->Errors); // &nbsp; <a class="error" href="#err'.$errnum.'">goto error</a>

                            $postlink = '<a class="postlink error" href="#err'.$errnum.'" title="scroll to error"><span class="postlink"></span></a>';

                            $this->Errors[] = "<span class=\"error_label\">unclosed [$TagName] tag: $postlink</span><blockquote class=\"bbcode error\">..." . substr($Str, $istart, $TagPos - $istart)
                                    .'<code class="error">'.$Tag[0][0].'</code>'. substr($Str, $i, $iend - $i) .'... </blockquote>';

                            if ($this->ShowErrors) {
                                $Block = "[$TagName]";
                                $TagName = 'error';
                                $Attrib = $errnum;
                            } else {
                                $TagName = 'ignore'; // tells the parser to skip this empty tag
                            }
                            $CloseTag = $i;
                            $closetaglength = 0;

                        }
                        break;
                    } else {
                        $NumInCloses++; // Majority of cases
                    }

                    // Is there another open tag inside this one?
                    $OpenTag = preg_match($InOpenRegex, $Str, $InTag, PREG_OFFSET_CAPTURE, $InTagPos + 1);
                    if (!$OpenTag || $InTag[0][1] > $CloseTag) {
                        break;
                    } else {
                        $InTagPos = $InTag[0][1];
                        $NumInOpens++;
                    }
                } while ($NumInOpens > $NumInCloses);

                // Find the internal block inside the tag
                if (!$Block)
                    $Block = substr($Str, $i, $CloseTag - $i); // 5c) Get the contents between [open] and [/close] and call it the block.

                $i = $CloseTag + $closetaglength; // 5d) Move the pointer past the end of the [/close] tag.
            }

            // 6) Depending on what type of tag we're dealing with, create an array with the attribute and block.
            switch ($TagName) {
                case 'h5v': // html5 video tag
                    $Array[$ArrayPos] = array('Type' => 'h5v', 'Attr' => $Attrib, 'Val' => $Block);
                    break;
                case 'video': // youtube, streamable and vimeo only
                case 'yt':
                case 'vimeo':
                    $Array[$ArrayPos] = array('Type' => 'video', 'Attr' => $Attrib, 'Val' => '');
                    break;
                case 'flash':
                    $Array[$ArrayPos] = array('Type' => 'flash', 'Attr' => $Attrib, 'Val' => $Block);
                    break;
                /* case 'link':
                  $Array[$ArrayPos] = array('Type'=>'link', 'Attr'=>$Attrib, 'Val'=>$this->parse($Block));
                  break; */
                case 'anchor':
                case '#':
                    $Array[$ArrayPos] = array('Type' => $TagName, 'Attr' => $Attrib, 'Val' => $this->parse($Block));
                    break;
                case 'br':
                case 'hr':
                case 'cast':
                case 'details':
                case 'info':
                case 'plot':
                case 'screens':
                case 'you':
                case 'ratiolist':
                    $Array[$ArrayPos] = array('Type' => $TagName, 'Val' => '');
                    break;
                case 'font':
                    $Array[$ArrayPos] = array('Type' => 'font', 'Attr' => $Attrib, 'Val' => $this->parse($Block));
                    break;
                case 'center': // lets just swap a center tag for an [align=center] tag
                    $Array[$ArrayPos] = array('Type' => 'align', 'Attr' => 'center', 'Val' => $this->parse($Block));
                    break;
                case 'inlineurl':
                    $Array[$ArrayPos] = array('Type' => 'inlineurl', 'Attr' => $Block, 'Val' => '');
                    break;
                case 'url':
                    if (empty($Attrib)) { // [url]http://...[/url] - always set URL to attribute
                        $Array[$ArrayPos] = array('Type' => 'url', 'Attr' => $Block, 'Val' => '');
                    } else {
                        $Array[$ArrayPos] = array('Type' => 'url', 'Attr' => $Attrib, 'Val' => $this->parse($Block));
                    }
                    break;
                case 'quote':
                    $Array[$ArrayPos] = array('Type' => 'quote', 'Attr' => $Attrib, 'Val' => $this->parse($Block));
                    break;

                case 'imgnm':
                    $Array[$ArrayPos] = array('Type' => 'imgnm',  'Attr' => $Attrib, 'Val' => $Block);
                    break;
                case 'imgalt':
                    $Array[$ArrayPos] = array('Type' => 'imgalt', 'Attr' => $Attrib, 'Val' => $Block);
                    break;

                case 'img':
                case 'image':
                    if (is_bool($Block)) $Block = '';
                    if (empty($Block)) {
                        $Elements = explode(',', $Attrib);
                        $Block = end($Elements);
                        $Attrib = preg_replace('/,?'.preg_quote($Block, '/').'/i', '', $Attrib);
                    }
                    $Array[$ArrayPos] = array('Type' => 'img', 'Attr' => $Attrib, 'Val' => $Block);
                    break;
                case 'banner':
                case 'thumb':
                    if (empty($Block)) {
                        $Block = $Attrib;
                    }
                    $Array[$ArrayPos] = array('Type' => $TagName, 'Val' => $Block);
                    break;
                case 'aud':
                case 'mp3':
                case 'audio':
                    if (empty($Block)) {
                        $Block = $Attrib;
                    }
                    $Array[$ArrayPos] = array('Type' => 'aud', 'Val' => $Block);
                    break;
                case 'user':
                    $Array[$ArrayPos] = array('Type' => 'user', 'Val' => $Block);
                    break;

                case 'torrent':
                case 'request':
                case 'collage':
                case 'thread':
                case 'forum':
                    $Array[$ArrayPos] = array('Type' => $TagName, 'Val' => $Block);
                    break;

                case 'tex':
                    $Array[$ArrayPos] = array('Type' => 'tex', 'Val' => $Block);
                    break;
                case 'pre':
                case 'plain':
                    $Block = strtr($Block, array('[inlineurl]' => ''));
                    $Block = preg_replace('/\[inlinesize\=3\](.*?)\[\/inlinesize\]/i', '====$1====', $Block);
                    $Block = preg_replace('/\[inlinesize\=5\](.*?)\[\/inlinesize\]/i', '===$1===', $Block);
                    $Block = preg_replace('/\[inlinesize\=7\](.*?)\[\/inlinesize\]/i', '==$1==', $Block);

                    $Array[$ArrayPos] = array('Type' => $TagName, 'Val' => $Block);
                    break;

                case 'code':
                case 'codeblock':
                    $Block = strtr($Block, array('[inlineurl]' => ''));
                    $Block = preg_replace('/\[inlinesize\=3\](.*?)\[\/inlinesize\]/i', '====$1====', $Block);
                    $Block = preg_replace('/\[inlinesize\=5\](.*?)\[\/inlinesize\]/i', '===$1===', $Block);
                    $Block = preg_replace('/\[inlinesize\=7\](.*?)\[\/inlinesize\]/i', '==$1==', $Block);

                    $Array[$ArrayPos] = array('Type' => $TagName, 'Attr' => $Attrib, 'Val' => $Block);
                break;

                case 'mediainfo':
                    $Array[$ArrayPos] = array('Type' => $TagName, 'Val' => $Block);
                    break;
                case 'hide':
                    $ArrayPos--;
                    break; // not seen
                case 'spoiler':
                    $Array[$ArrayPos] = array('Type' => $TagName, 'Attr' => $Attrib, 'Val' => $this->parse($Block));
                    break;
                //case '#': using this for anchor short tag... not used on old emp so figure should be okay
                case '*':
                    $Array[$ArrayPos] = array('Type' => 'list');
                    $Array[$ArrayPos]['Val'] = explode('[' . $TagName . ']', $Block);
                    $Array[$ArrayPos]['ListType'] = $TagName === '*' ? 'ul' : 'ol';
                    $Array[$ArrayPos]['Tag'] = $TagName;
                    foreach ($Array[$ArrayPos]['Val'] as $Key => $Val) {
                        $Array[$ArrayPos]['Val'][$Key] = $this->parse(trim($Val));
                    }
                    break;
                case 'n':
                case 'ignore': // not a tag but can be used internally
                    $ArrayPos--;
                    break; // n serves only to disrupt bbcode (backwards compatibility - use [pre])
                case 'error':  // not a tag but can be used internally
                    $Array[$ArrayPos] = array('Type' => 'error', 'Attr' => $Attrib, 'Val' => $Block);
                    break;
                default:
                    if ($WikiLink == true) {
                        $Array[$ArrayPos] = array('Type' => 'wiki', 'Val' => $TagName);
                    } else {

                        // Basic tags, like [b] or [size=5]

                        $Array[$ArrayPos] = array('Type' => $TagName, 'Val' => $this->parse($Block));
                        if (isset($Attrib) && $MaxAttribs > 0) {
                            // $Array[$ArrayPos]['Attr'] = strtolower($Attrib);
                            $Array[$ArrayPos]['Attr'] = $Attrib;
                        }
                    }
            }

            $ArrayPos++; // 7) Increment array pointer, start again (past the end of the [/close] tag)
        }
//echo "<pre>";
//var_dump($Array);die();
//echo "</pre>";

        return $Array;
    }

    public function get_allowed_colors()
    {
        static $ColorAttribs;
        if (!$ColorAttribs) { // only define it once per page
            // now with more colors!
            $ColorAttribs = array( 'aliceblue', 'antiquewhite', 'aqua', 'aquamarine', 'azure', 'beige', 'bisque', 'black', 'blanchedalmond', 'blue', 'blueviolet',
                'brown','burlywood','cadetblue','chartreuse','chocolate','coral','cornflowerblue','cornsilk','crimson','cyan','darkblue','darkcyan','darkgoldenrod',
                'darkgray','darkgreen','darkgrey','darkkhaki','darkmagenta','darkolivegreen','darkorange','darkorchid','darkred','darksalmon','darkseagreen','darkslateblue',
                'darkslategray','darkslategrey','darkturquoise','darkviolet','deeppink','deepskyblue','dimgray','dimgrey','dodgerblue','firebrick','floralwhite','forestgreen',
                'fuchsia','gainsboro','ghostwhite','gold','goldenrod','gray','grey','green','greenyellow','honeydew','hotpink','indianred','indigo','ivory','khaki','lavender',
                'lavenderblush','lawngreen','lemonchiffon','lightblue','lightcoral','lightcyan','lightgoldenrodyellow','lightgray','lightgreen','lightgrey','lightpink',
                'lightsalmon','lightseagreen','lightskyblue','lightslategray','lightslategrey','lightsteelblue','lightyellow','lime','limegreen','linen','magenta','maroon',
                'mediumaquamarine','mediumblue','mediumorchid','mediumpurple','mediumseagreen','mediumslateblue','mediumspringgreen','mediumturquoise','mediumvioletred',
                'midnightblue','mintcream','mistyrose','moccasin','navajowhite','navy','oldlace','olive','olivedrab','orange','orangered','orchid','palegoldenrod','palegreen',
                'paleturquoise','palevioletred','papayawhip','peachpuff','peru','pink','plum','powderblue','purple','red','rosybrown','royalblue','saddlebrown','salmon',
                'sandybrown','seagreen','seashell','sienna','silver','skyblue','slateblue','slategray','slategrey','snow','springgreen','steelblue','tan','teal','thistle',
                'tomato','turquoise','violet','wheat','white','whitesmoke','yellow','yellowgreen' );
        }

        return $ColorAttribs;
    }

    public function is_color_attrib(&$Attrib)
    {
        global $ClassNames;

        $Att = strtolower($Attrib);

        // convert class names to class colors
        if (isset($ClassNames[$Att]['Color'])) {
            $Attrib = '#' . $ClassNames[$Att]['Color'];
            $Att = strtolower($Attrib);
        }
        // if in format #rgb hex then return as is
        if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/', $Att)) return true;

        // check and capture #rgba format
        if (preg_match('/^#(?|([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})|([0-9a-f]{1})([0-9a-f]{1})([0-9a-f]{1})([0-9a-f]{1}))$/', $Att, $matches) ) {
            // capture #rgba hex and convert into rgba(r,g,b,a) format (from base 16 to base 10 0->255)
            for ($i=1;$i<4;$i++) {
                if (strlen($matches[$i])==1) $matches[$i] = "$matches[$i]$matches[$i]";
                $matches[$i] = base_convert($matches[$i], 16, 10);
            }
            if (strlen($matches[4])==1) $matches[4] = "$matches[4]$matches[4]";
            // alpha channel is in 0->1.0 range not 0->255 (!)
            $matches[4] = number_format( base_convert($matches[4], 16, 10) /255 , 2);
            // attribute is in rgb(r,g,b,a) format for alpha channel
            $Attrib = "rgba($matches[1],$matches[2],$matches[3],$matches[4])";

            return true;
        }

        // if not in #rgb or #rgba format then check for allowed colors
        return in_array($Att, $this->get_allowed_colors());
    }

    public function extract_attributes($Attrib, $MaxNumber=-1)
    {
        $Elements=array();
        if (isset($Attrib) && $Attrib) {
            $attributes = explode(",", $Attrib);
            if ($attributes) {
                foreach ($attributes as &$att) {

                    if ($this->is_color_attrib($att)) {
                        $Elements['color'][] = $att;

                    } elseif (preg_match('/^([0-9]*)$/', $att, $matches)) {
                        if ($MaxNumber>-1 && $att>$MaxNumber) $att = $MaxNumber;
                        $Elements['number'][] = $att;

                    } elseif ( $this->valid_url($att) ) {
                        $Elements['url'][] = $att;
                    }
                }
                $InlineStyle .= '"';
            }
        }

        return $Elements;
    }

    public function get_css_attributes($Attrib, $AllowMargin=true, $AllowColor=true, $AllowWidth=true, $AllowNoBorder=true, $AllowImage=true)
    {
        $InlineStyle = '';
        if (isset($Attrib) && $Attrib) {
            $attributes = explode(",", $Attrib);
            if ($attributes) {
                $InlineStyle = ' style="';
                foreach ($attributes as $att) {
                    if ($AllowColor && substr($att, 0, 9) == 'gradient:') {
                        $InlineStyle .= 'background: linear-gradient(';
                        $LinearArr = explode(';', substr($att, 9));
                        $LinearAttr = array();
                        // Check integrity
                        if (sizeof($LinearArr) < 2) return '';
                        foreach ($LinearArr as $arr) {
                            // Check so that the gradient is using the correct attributes
                            if (preg_match('/^to left bottom|right bottom|bottom left|bottom right|'.
                                         'left top|right top|top left|top right|left|right|top|bottom$/', $arr) ||
                                    preg_match('/^[0-9]{1,3}deg$/', $arr) ||
                                    $this->is_color_attrib($arr)) {
                                $LinearAttr[] = $arr;
                            // People love shortcuts..
                            } elseif (preg_match('/^lb|rb|bl|br|lt|rt|tl|tr|l|r|t|b$/', $arr)) {
                                $arr = preg_replace('/^lb$/', 'to left bottom', $arr);
                                $arr = preg_replace('/^rb$/', 'to right bottom', $arr);
                                $arr = preg_replace('/^bl$/', 'to left bottom', $arr);
                                $arr = preg_replace('/^br$/', 'to right bottom', $arr);
                                $arr = preg_replace('/^lt$/', 'to left top', $arr);
                                $arr = preg_replace('/^rt$/', 'to right top', $arr);
                                $arr = preg_replace('/^tl$/', 'to left top', $arr);
                                $arr = preg_replace('/^tr$/', 'to right top', $arr);
                                $arr = preg_replace('/^l$/', 'to left', $arr);
                                $arr = preg_replace('/^r$/', 'to right', $arr);
                                $arr = preg_replace('/^t$/', 'to top', $arr);
                                $arr = preg_replace('/^b$/', 'to bottom', $arr);
                                $LinearAttr[] = $arr;
                            } else {
                                // Is the attribute using color stops? (#rgb xx%)
                                $split = explode(' ', $arr);
                                if (sizeof($split) == 2 && $this->is_color_attrib($split[0]) && preg_match('/^[0-9]{1,3}%$/', $split[1])) {
                                    $LinearAttr[] = implode(' ', $split);
                                } else {
                                    return '';
                                }
                            }
                        }
                        $InlineStyle .= implode(',', $LinearAttr) . ');';
                    } elseif ($AllowColor && $this->is_color_attrib($att)) {
                        $InlineStyle .= 'background-color:' . $att . ';';
                    } elseif ($AllowImage && $this->valid_url($att) ) {
                        if($this->ShowErrors && !$this->validate_imageurl($att)) {
                            $this->Errors[] = "<span class=\"error_label\">Not an approved Imagehost:</span><blockquote class=\"bbcode error\">".'<code class="error">'.$att.'</code></blockquote>';
                            break;
                        }
                        $this->displayed_images[$att] = true;
                        $escapees = array( "'",   '"',  "(",  ")",  " ");
                        $escaped  = array("\'", '\"', "\(", "\)", "\ ");
                        $sanitisedurl = str_replace($escapees, $escaped, $att);
                        $InlineStyle .= "background-image: url(".$sanitisedurl.");";
                        //$InlineStyle .= "background: url('$att') no-repeat center center;";

                    } elseif ($AllowWidth && preg_match('/^([0-9]{1,3})px$/', $att, $matches)) {
                        if ((int) $matches[1] > 920) $matches[1] = '920';
                        $InlineStyle .= 'width:' . $matches[1] . 'px;';

                    } elseif ($AllowWidth && preg_match('/^([0-9]{1,3})%?$/', $att, $matches)) {
                        if ((int) $matches[1] > 100) $matches[1] = '100';
                        $InlineStyle .= 'width:' . $matches[1] . '%;';

                    } elseif ($AllowMargin && in_array($att, array('left', 'center', 'right'))) {
                        switch ($att) {
                            case 'left':
                                $InlineStyle .= 'margin: 0px auto 0px 0px;';
                                break;
                            case 'right':
                                $InlineStyle .= 'margin: 0px 0px 0px auto;';
                                break;
                            case 'center':
                            default:
                                $InlineStyle .= 'margin: 0px auto;';
                                break;
                        }
                    } elseif ($AllowNoBorder && in_array($att, array('nball', 'nb', 'noborder'))) { //  'nball',
                        $InlineStyle .= 'border:none;';
                    } elseif ($AllowMargin && in_array($att, array('nopad'))) {
                        $InlineStyle .= 'padding:0px;';
                    }
                }
                $InlineStyle .= '"';
            }
        }

        return $InlineStyle;
    }

    public function get_css_classes($Attrib, $MatchClasses)
    {
        if ($Attrib == '') return '';
        $classes='';
        foreach ($MatchClasses as $class) {
            if ( is_array($class)) {
                $class_match = $class[0];
                $class_replace = $class[1];
            } else {
                $class_match = $class;
                $class_replace = $class;
            }
            if (stripos($Attrib, $class_match) !== FALSE) $classes .= " $class_replace";
        }

        return $classes;
    }

    public function remove_text_between_tags($Array, $MatchTagRegex = false)
    {
        $count = count($Array);
        for ($i = 0; $i <= $count; $i++) {
            if (is_string($Array[$i])) {
                $Array[$i] = '';
            } elseif ($MatchTagRegex !== false && !preg_match($MatchTagRegex, $Array[$i]['Type'])) {
                $Array[$i] = '';
            }
        }

        return $Array;
    }

    public function get_size_attributes($Attrib)
    {
        if ($Attrib == '') {
            return '';
        }
        if (preg_match('/([0-9]{2,4})\,([0-9]{2,4})/', $Attrib, $matches)) {
            if (count($matches) < 3) {
                if (!$matches[1]) $matches[1] = 640;
                if (!$matches[2]) $matches[2] = 385;
            }

            return ' width="' . $matches[1] . '" height="' . $matches[2] . '" ';
        }

        return '';
    }

    public function remove_anon($url)
    {
        $anonurl = (defined('ANONYMIZER_URL') ? ANONYMIZER_URL : 'http://anonym.to/?');
        return str_replace($anonurl, '', $url);
    }

    public function anon_url($url)
    {
        global $master;
        if (preg_match($master->settings->main->non_anon_urls_regex, $url)) {
            return $url;
        }
        return (defined('ANONYMIZER_URL') ? ANONYMIZER_URL : 'http://anonym.to/?').$url;
    }

    public function to_html($Array)
    {
        global $LoggedUser;
        $this->Levels++;
        # Hax prevention: execution limit
        if ($this->Levels > 20) return;
        $Str = '';

        foreach ((array)$Array as $Block) {
            if (is_string($Block)) {
                $Str.=$this->smileys($Block);
                continue;
            }
            switch ($Block['Type']) {
                case 'article': // link to article
                    $LocalURL = $this->local_url($Block['Attr']);
                    if ($LocalURL && preg_match('#^/articles\.php.*[\?&]topic=(.*)#i', $LocalURL)) {
                        $Str .= $this->articleTag($Block['Attr']);
                    } else if (!empty($Block['Attr']) && preg_match('/^[a-z0-9\-\_.()\@&]+$/', strtolower($Block['Attr'])))
                        $Str.='<a class="bbcode article" href="articles.php?topic=' .strtolower($Block['Attr']). '">' . $this->to_html($Block['Val']) . '</a>';
                    else
                        $Str.='[article='. $Block['Attr'] . ']' . $this->to_html($Block['Val']) . '[/article]';
                    break;
                case 'mediainfo': // mediainfo block
                    $MediaInfo = new MediaInfo;
                    // HTML cleanup for MediaInfo
                    $NFO = html_entity_decode($Block['Val']);
                    $NFO = str_replace("\xc2\xa0",' ', $NFO);
                    $MediaInfo->parse($NFO);
                    $Str.=$MediaInfo->output;
                    break;
                case 'tip': // a tooltip
                    if (!empty($Block['Attr']))
                        $Str.='<span class="bbcode tooltip" title="' .display_str($Block['Attr']) . '">' . $this->to_html($Block['Val']) . '</span>';
                    else
                        $Str.='[tip='. $Block['Attr'] . ']' . $this->to_html($Block['Val']) . '[/tip]';
                    break;
                case 'quote':
                    $this->NoMedia++; // No media elements inside quote tags
                    if (!empty($Block['Attr'])) {
                        // [quote=name,[F|T|R|C]number1,number2]
                        list($qname, $qID1, $qID2) = explode(",", $Block['Attr']);
                        if ($qID1) {  // if we have numbers
                            $qType = substr($qID1, 0, 1); /// F or T or C or R (forums/torrents/collags/requests)
                            $qID1 = substr($qID1, 1);
                            if (in_array($qType, array('f', 't', 'c', 'r')) && is_number($qID1) && is_number($qID2)) {
                                switch ($qType) {
                                    case 'f':
                                        $postlink = '<a class="postlink" href="forums.php?action=viewthread&threadid=' . $qID1 . '&postid=' . $qID2 . '#post' . $qID2 . '"><span class="postlink"></span></a>';
                                        break;
                                    case 't':
                                        $postlink = '<a class="postlink" href="torrents.php?id=' . $qID1 . '&postid=' . $qID2 . '#post' . $qID2 . '"><span class="postlink"></span></a>';
                                        break;
                                    case 'c':
                                        $postlink = '<a class="postlink" href="collages.php?action=comments&collageid=' . $qID1 . '#post' . $qID2 . '"><span class="postlink"></span></a>';
                                        break;
                                    case 'r':
                                        $postlink = '<a class="postlink" href="requests.php?action=view&id=' . $qID1 . '#post' . $qID2 . '"><span class="postlink"></span></a>';
                                        break;
                                }
                            }
                        }
                        $Str.= '<span class="quote_label"><strong>' . display_str($qname) . '</strong>: ' . $postlink . '</span>';
                    }
                    $Str.='<blockquote class="bbcode">' . $this->to_html($Block['Val']) . '</blockquote>';
                    $this->NoMedia--;
                    break;
                case 'error': // used internally to display bbcode errors in preview
                    // haha, a legitimate use of the blink tag (!)
                    $Str.="<a id=\"err$Block[Attr]\"></a><blink><code class=\"error\" title=\"You have an unclosed $Block[Val] tag in your bbCode!\">$Block[Val]</code></blink>";
                    break;
                case 'you':
                    if ($this->Advanced)
                        $Str.='<a href="user.php?id=' . $LoggedUser['ID'] . '">' . $LoggedUser['Username'] . '</a>';
                    else
                        $Str.='[you]';
                    break;
                case 'video':
                    // Supports youtube, vimeo and streamable for now.
                    if($this->ShowErrors && !$this->validate_imageurl($Block['Attr'])) {
                        $this->Errors[] = "<span class=\"error_label\">Not an approved Imagehost:</span><blockquote class=\"bbcode error\">".'<code class="error">'.$Block['Attr'].'</code></blockquote>';
                        break;
                    }

                    $videoUrl = '';
                    if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $Block['Attr'], $matches))
                        //$videoUrl = 'https://www.youtube-nocookie.com/embed/'.$matches[1];
                        $YoutubeID = $matches[1];
                    elseif (preg_match('/^https?:\/\/vimeo.com\/([0-9]+)$/i', $Block['Attr'], $matches))
                        $videoUrl = 'https://player.vimeo.com/video/'.$matches[1];
                    elseif(preg_match('/^https?:\/\/streamable.com\/([0-9a-zA-Z]+)$/i', $Block['Attr'], $matches))
                      $videoUrl = 'https://streamable.com/s/'.$matches[1];

                    if ($this->NoMedia > 0) {
                        $Str .= '<a rel="noreferrer" target="_blank" href="' . $videoUrl . '">' . $videoUrl . '</a> (video)';
                        break;
                    }
                    else {
                        if ($videoUrl != '')
                            $Str.='<iframe class="bb_video" src="'.$videoUrl.'" allowfullscreen></iframe>';
                        elseif ($YoutubeID != '')
                            $Str.='<div class="youtube" data-embed="'.$YoutubeID.'"><div class="play-button"></div></div>';
                        else
                            $Str.='[video=' . $Block['Attr'] . ']';
                    }
                    break;
                case 'h5v':
                    // html5 video tag
                    $Attributes= $this->extract_attributes($Block['Attr'], 920);
                    if($this->ShowErrors && !$this->validate_imageurl($Block['Val'])) {
                        $this->Errors[] = "<span class=\"error_label\">Not an approved Imagehost:</span><blockquote class=\"bbcode error\">".'<code class="error">'.$Block['Val'].'</code></blockquote>';
                        break;
                    }
                    if($this->ShowErrors && !$this->validate_imageurl($Block['Attr'])) {
                        $this->Errors[] = "<span class=\"error_label\">Not an approved Imagehost:</span><blockquote class=\"bbcode error\">".'<code class="error">'.$Block['Attr'].'</code></blockquote>';
                        break;
                    }

                    if ( ($Block['Attr'] != '' && count($Attributes)==0) || $Block['Val'] == '' ) {
                        $Str.='[h5v' . ($Block['Attr'] != ''?'='. $Block['Attr']:'')  . ']' . $this->to_html($Block['Val']) . '[/h5v]';
                    } else {
                        $Sources = explode(',', $Block['Val']);

                        if ($this->NoMedia > 0) {
                            foreach ($Sources as $Source) {
                                $videoUrl = str_replace('[inlineurl]', '', $Source);
                                $Str .= '<a rel="noreferrer" target="_blank" href="' . $videoUrl . '">' . $videoUrl . '</a> (video)';
                            }
                            break;
                        }
                        else {
                            $parameters = '';
                            if (isset($Attributes['number']) && count($Attributes['number']) >= 2) {
                                $parameters = ' width="'.$Attributes['number'][0].'" height="'.$Attributes['number'][1].'" ';
                            }
                            if (isset($Attributes['url']) && count($Attributes['url']) >= 1) {
                                $parameters .= ' poster="'.$Attributes['url'][0].'" ';
                            }

                            $Str .= '<video '.$parameters.' controls>';
                            foreach ($Sources as $Source) {
                                $lastdot = strripos($Source, '.');
                                $mime = substr($Source, $lastdot+1);
                                if($mime=='ogv')$mime='ogg'; // all others are same as ext (webm=webm, mp4=mp4, ogg=ogg)
                                $Str .= '<source src="'. str_replace('[inlineurl]', '', $Source).'" type="video/'.$mime.'">';
                            }
                            $Str .= 'Your browser does not support the html5 video tag. Please upgrade your browser.</video>';
                        }
                    }
                    break;
                case 'flash':
                    if($this->ShowErrors && !$this->validate_imageurl($Block['Attr'])) {
                        $this->Errors[] = "<span class=\"error_label\">Not an approved Imagehost:</span><blockquote class=\"bbcode error\">".'<code class="error">'.$Block['Attr'].'</code></blockquote>';
                        break;
                    }
                    // note: as a non attribute the link has been auto-formatted as [inlinelink]link.url
                    if (($Block['Attr'] != '' && !preg_match('/^([0-9]{2,4})\,([0-9]{2,4})$/', $Block['Attr'], $matches))
                            || strpos($Block['Val'], '[inlineurl]') === FALSE) {
                        $Str.='[flash=' . ($Block['Attr'] != ''?'='. $Block['Attr']:'') . ']' . $this->to_html($Block['Val']) . '[/flash]';
                    } else {
                        if ($Block['Attr'] == '' || count($matches) < 3) {
                            if (!$matches[1])
                                $matches[1] = 500;
                            if (!$matches[2])
                                $matches[2] = $matches[1];
                        }
                        $Block['Val'] = str_replace('[inlineurl]', '', $Block['Val']);

                        if ($this->NoMedia > 0)
                            $Str .= '<a rel="noreferrer" target="_blank" href="' . $Block['Val'] . '">' . $Block['Val'] . '</a> (flash)';
                        else
                            $Str .= '<object classid="clsid:D27CDB6E-AE6D-11CF-96B8-444553540000" codebase="http://active.macromedia.com/flash2/cabs/swflash.cab#version=5,0,0,0" height="' . $matches[2] . '" width="' . $matches[1] . '"><param name="movie" value="' . $Block['Val'] . '"><param name="play" value="false"><param name="loop" value="false"><param name="quality" value="high"><param name="allowScriptAccess" value="never"><param name="allowNetworking" value="internal"><embed  type="application/x-shockwave-flash" pluginspage="http://www.macromedia.com/shockwave/download/index.cgi?P1_Prod_Version=ShockwaveFlash" play="false" loop="false" quality="high" allowscriptaccess="never" allownetworking="internal"  src="' . $Block['Val'] . '" height="' . $matches[2] . '" width="' . $matches[1] . '"><param name="wmode" value="transparent"></object>';
                    }
                    break;

                case 'url':
                    // Make sure the URL has a label
                    if (empty($Block['Val'])) {
                        $Block['Val'] = $Block['Attr'];
                        $NoName = true; // If there isn't a Val for this
                    } else {
                        $Block['Val'] = $this->to_html($Block['Val']);
                        $NoName = false;
                    }
                    //remove the local host/anonym.to from address if present
                    $Block['Attr'] = $this->remove_anon($Block['Attr']);
                    // first test if is in format /local.php or #anchorname
                    if (preg_match('/^#[a-zA-Z0-9\-\_.,%\@~&=:;()+*\^$!#|]+$|^\/[a-zA-Z0-9\-\_.,%\@~&=:;()+*\^$!#|]+\.php[a-zA-Z0-9\?\-\_.,%\@~&=:;()+*\^$!#|]*$/', $Block['Attr'])) {
                        // a local link or anchor link
                        $Str.='<a class="link" href="' . $Block['Attr'] . '">' . $Block['Val'] . '</a>';
                    } elseif (!$this->valid_url($Block['Attr'])) {
                        // not a valid tag
                        $Str.='[url=' . $Block['Attr'] . ']' . $Block['Val'] . '[/url]';
                    } else {
                        $LocalURL = $this->local_url($Block['Attr']);
                        if ($LocalURL) {
                            if ($NoName) {
                                $Block['Val'] = substr($LocalURL, 1);
                            }
                            $Str.='<a href="' . $LocalURL . '">' . $Block['Val'] . '</a>';
                        } else {
                            if (!$LoggedUser['NotForceLinks']) $target = 'target="_blank" ';
                            $Str.='<a rel="noreferrer" ' . $target . 'href="' . $this->anon_url($Block['Attr']) . '">' . $Block['Val'] . '</a>';
                        }
                    }
                    break;

                case 'anchor':
                case '#':
                    if (!preg_match('/^[a-zA-Z0-9\-\_]+$/', $Block['Attr'])) {
                        $Str.='[' . $Block['Type'] . '=' . $Block['Attr'] . ']' . $this->to_html($Block['Val']) . '[/' . $Block['Type'] . ']';
                    } else {
                        $Str.='<a class="anchor" id="' . $Block['Attr'] . '">' . $this->to_html($Block['Val']) . '</a>';
                    }
                    break;


                case 'mcom':
                    $innerhtml = $this->to_html($Block['Val']);
                    while (ends_with($innerhtml, "\n")) {
                        $innerhtml = substr($innerhtml, 0, -strlen("\n"));
                    }
                    $Str.='<div class="modcomment">' . $innerhtml . '<div class="after">[ <a href="articles.php?topic=tutorials">Help</a> | <a href="articles.php?topic=rules">Rules</a> ]</div><div class="clear"></div></div>';
                    break;

                case 'table':
                    $InlineStyle = $this->get_css_attributes($Block['Attr']);
                    if ($InlineStyle === FALSE) {
                        $Str.='[' . $Block['Type'] . '=' . $Block['Attr'] . ']' . $this->to_html($Block['Val']) . '[/' . $Block['Type'] . ']';
                    } else {
                        $Block['Val'] = $this->remove_text_between_tags($Block['Val'], "/^tr$/");
                        $tableclass = $this->get_css_classes($Block['Attr'], array(array('nball','noborder'),'nopad','vat','vam','vab'));
                        $Str.='<table class="bbcode' . $tableclass . '"' . $InlineStyle . '><tbody>' . $this->to_html($Block['Val']) . '</tbody></table>';
                    }
                    break;
                case 'tr':
                    $InlineStyle = $this->get_css_attributes($Block['Attr'], false, true, false, true);

                    if ($InlineStyle === FALSE) {
                        $Str.='[' . $Block['Type'] . '=' . $Block['Attr'] . ']' . $this->to_html($Block['Val']) . '[/' . $Block['Type'] . ']';
                    } else {
                        $Block['Val'] = $this->remove_text_between_tags($Block['Val'], "/^th$|^td$/");
                        $tableclass = $this->get_css_classes($Block['Attr'], array( 'nopad'));
                        $Str.='<' . $Block['Type'] . ' class="bbcode'.$tableclass.'"' . $InlineStyle . '>' . $this->to_html($Block['Val']) . '</' . $Block['Type'] . '>';
                    }
                    break;
                case 'th':
                case 'td':
                    $InlineStyle = $this->get_css_attributes($Block['Attr'], false);
                    if ($InlineStyle === FALSE) {
                        $Str.='[' . $Block['Type'] . '=' . $Block['Attr'] . ']' . $this->to_html($Block['Val']) . '[/' . $Block['Type'] . ']';
                    } else {
                        $tableclass = $this->get_css_classes($Block['Attr'], array( 'nopad','vat','vam','vab'));
                        $Str.='<'. $Block['Type'] .' class="bbcode'.$tableclass.'"' . $InlineStyle . '>' . $this->to_html($Block['Val']) . '</' . $Block['Type'] . '>';
                    }
                    break;

                case 'bg':
                    $InlineStyle = $this->get_css_attributes($Block['Attr'], true, true, true, false);
                    if (!$InlineStyle || $InlineStyle == '') {
                        $Str.='[bg=' . $Block['Attr'] . ']' . $this->to_html($Block['Val']) . '[/bg]';
                    } else {
                        $tableclass = $this->get_css_classes($Block['Attr'], array( 'nopad'));
                        $Str.='<div class="bbcode'.$tableclass.'"' . $InlineStyle . '>' . $this->to_html($Block['Val']) . '</div>';
                    }
                    break;

                case 'cast':
                case 'details':
                case 'info':
                case 'plot':
                case 'screens': // [cast] [details] [info] [plot] [screens]
                    if (!isset($this->Icons[$Block['Type']])) {
                        $Str.='[' . $Block['Type'] . ']';
                    } else {
                        $Str.= $this->Icons[$Block['Type']];
                    }
                    break;
                case 'br':
                    $Str.='<br />';
                    break;
                case 'hr':
                    $Str.='<hr />';
                    break;
                case 'font':
                    if (!isset($this->Fonts[$Block['Attr']])) {
                        $Str.='[font=' . $Block['Attr'] . ']' . $this->to_html($Block['Val']) . '[/font]';
                    } else {
                        $Str.='<span style="font-family: ' . $this->Fonts[$Block['Attr']] . '">' . $this->to_html($Block['Val']) . '</span>';
                    }
                    break;
                case 'b':
                    $Str.='<strong>' . $this->to_html($Block['Val']) . '</strong>';
                    break;
                case 'u':
                    $Str.='<span style="text-decoration: underline;">' . $this->to_html($Block['Val']) . '</span>';
                    break;
                case 'i':
                    $Str.='<em>' . $this->to_html($Block['Val']) . "</em>";
                    break;
                case 's':
                    $Str.='<span style="text-decoration: line-through">' . $this->to_html($Block['Val']) . '</span>';
                    break;
                case 'sup':
                    $Str.='<sup>' . $this->to_html($Block['Val']) . '</sup>';
                    break;
                case 'sub':
                    $Str.='<sub>' . $this->to_html($Block['Val']) . '</sub>';
                    break;
                case 'important':
                    $Str.='<strong class="important_text">' . $this->to_html($Block['Val']) . '</strong>';
                    break;
                case 'user':
                    $Str .= $this->userTag($Block['Val']);
                    break;


                case 'torrent':
                    $Str .= $this->torrentTag($Block['Val']);
                    break;
                case 'request':
                    $Str .= $this->requestTag($Block['Val']);
                    break;
                case 'collage':
                    $Str .= $this->collageTag($Block['Val']);
                    break;
                case 'thread':
                    $Str .= $this->threadTag($Block['Val']);
                    break;
                case 'forum':
                    $Str .= $this->forumTag($Block['Val']);
                    break;

                case 'tex':
                    $Str.='[tex]'.$Block['Val'].'[/tex]';
                    break;
                case 'plain':
                    $Str.=$Block['Val'];
                    break;
                case 'pre':
                    $Str.='<pre>' . $Block['Val'] . '</pre>';
                    break;
                case 'code':
                    $CSS = 'bbcode';
                    $Lang = $this->prism_supported($Block['Attr']);
                    if(!empty($Lang)) $CSS .= ' '.$Lang;
                    $Str.='<code class="'.$CSS.'">' . $Block['Val'] . '</code>';
                    break;
                case 'codeblock':
                    $CSS = 'bbcodeblock';
                    $Lang = $this->prism_supported($Block['Attr']);
                    if(!empty($Lang)) $CSS .= ' '.$Lang;
                    $Str.='<preclass="bbcodeblock"><code class="'.$CSS.'">' . $Block['Val'] . '</code></pre>';
                    break;
                case 'list':
                    $Str .= '<' . $Block['ListType'] . '>';
                    foreach ($Block['Val'] as $Line) {

                        $Str.='<li>' . $this->to_html($Line) . '</li>';
                    }
                    $Str.='</' . $Block['ListType'] . '>';
                    break;
                case 'align':
                    $ValidAttribs = array('left', 'center', 'justify', 'right');
                    if (!in_array($Block['Attr'], $ValidAttribs)) {
                        $Str.='[align=' . $Block['Attr'] . ']' . $this->to_html($Block['Val']) . '[/align]';
                    } else {
                        $Str.='<div style="text-align:' . $Block['Attr'] . '">' . $this->to_html($Block['Val']) . '</div>';
                    }
                    break;
                case 'color':
                case 'colour':
                    if (!$this->is_color_attrib($Block['Attr'])) {
                        $Str.='[color=' . $Block['Attr'] . ']' . $this->to_html($Block['Val']) . '[/color]';
                    } else {
                        $Str.='<span style="color:' . $Block['Attr'] . '">' . $this->to_html($Block['Val']) . '</span>';
                    }
                    break;
                case 'rank':
                    if (!$this->is_color_attrib($Block['Attr'])) {
                        $Str.='[rank=' . $Block['Attr'] . ']' . $this->to_html($Block['Val']) . '[/rank]';
                    } else {
                        $Str.='<span style="font-weight:bold;color:' . $Block['Attr'] . ';">' . $this->to_html($Block['Val']) . '</span>';
                    }
                    break;
                case 'inlinesize':
                case 'size':
                    $ValidAttribs = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10');
                    if (!in_array($Block['Attr'], $ValidAttribs)) {
                        $Str.='[size=' . $Block['Attr'] . ']' . $this->to_html($Block['Val']) . '[/size]';
                    } else {
                        $Str.='<span class="size' . $Block['Attr'] . '">' . $this->to_html($Block['Val']) . '</span>';
                    }
                    break;
                case 'hide':
                    $Str.='<strong>' . (($Block['Attr']) ? $Block['Attr'] : 'Hidden text') . '</strong>: <a href="javascript:void(0);" onclick="BBCode.spoiler(this);">Show</a>';
                    $Str.='<blockquote class="hidden spoiler">' . $this->to_html($Block['Val']) . '</blockquote>';
                    break;
                case 'spoiler':
                    $Str.='<strong>' . (($Block['Attr']) ? $Block['Attr'] : 'Hidden text') . '</strong>: <a href="javascript:void(0);" onclick="BBCode.spoiler(this);">Show</a>';
                    $Str.='<blockquote class="hidden spoiler">' . $this->to_html($Block['Val']) . '</blockquote>';
                    break;

                case 'img':
                case 'imgnm':
                case 'imgalt':
                case 'banner':

                    $Block['Val'] = str_replace('[inlineurl]', '', $Block['Val']);
                    $cssclass = "";

                    // Images with resize attributes
                    $resize = '';
                    if ($Block['Type'] == 'img' && !empty($Block['Attr'])) {
                        $Elements = explode(',', $Block['Attr']);
                        // Width
                        if (!empty($Elements[0]))
                            $resize .= 'width="'.intval($Elements[0]).'" ';
                        // Height
                        if (!empty($Elements[1]))
                            $resize .= 'height="'.intval($Elements[1]).'" ';
                    }

                    if ($Block['Type'] == 'imgnm' ) $cssclass .= ' nopad';
                    if ($Block['Attr'] != '' && ($Block['Type'] == 'imgnm' || $Block['Type'] == 'imgalt') ) $alttext = $Block['Attr'];
                    else $alttext = $Block['Val'];

                    if (preg_match('/^(\/[a-zA-Z0-9\-\_.,%\@~&=:;()+*\^$!#|]+)+[a-zA-Z0-9\?\-\_.,%\@~&=:;()+*\^$!#|]*$/', $Block['Val'])) {
                        // a local link or anchor link
                        $Str.='<img class="scale_image'.$cssclass.'" onclick="lightbox.init(this,500);" alt="'.$alttext.'" src="'.$Block['Val'].'" />';
                        break;

                    } elseif (!$this->valid_url($Block['Val'])) {
                        $Str.="[$Block[Type]". ( $Block['Attr'] ? '='.$Block['Attr'] : '' ). "]$Block[Val][/$Block[Type]]";
                        break;
                    }
                    $LocalURL = $this->local_url($Block['Val']);
                    if($this->ShowErrors && !$this->validate_imageurl($Block['Val'])) {
                        $this->Errors[] = "<span class=\"error_label\">Not an approved Imagehost:</span><blockquote class=\"bbcode error\">".'<code class="error">'.$Block['Val'].'</code></blockquote>';
                        break;
                    }
                    $this->displayed_images[$Block['Val']] = true;

                    if (!$LocalURL && $this->NoMedia > 0) {
                        $Str.='<a rel="noreferrer" target="_blank" href="' . $Block['Val'] . '">' . $Block['Val'] . '</a> (image)';
                        break;
                    }
                    $Block['Val'] = $this->proxify_url($Block['Val']);
                    $Str.='<img class="scale_image'.$cssclass.'" onclick="lightbox.init(this,500);" alt="'.$alttext.'" src="'.$Block['Val'].'" '.$resize.'/>';
                    break;

                case 'thumb':
                    if ($this->NoMedia > 0 && $this->valid_url($Block['Val'])) {
                        $Str.='<a rel="noreferrer" target="_blank" href="' . $Block['Val'] . '">' . $Block['Val'] . '</a> (image)';
                        break;
                    }
                    if($this->ShowErrors && !$this->validate_imageurl($Block['Val'])) {
                        $this->Errors[] = "<span class=\"error_label\">Not an approved Imagehost:</span><blockquote class=\"bbcode error\">".'<code class="error">'.$Block['Val'].'</code></blockquote>';
                        break;
                    }
                    if (!$this->valid_url($Block['Val'])) {
                        $Str.='[thumb]' . $Block['Val'] . '[/thumb]';
                    } else {
                        $Str.='<img class="thumb_image" onclick="lightbox.init(this,300);" alt="' . $Block['Val'] . '" src="' . $Block['Val'] . '" />';
                    }
                    $this->displayed_images[$Block['Val']] = true;
                    break;

                case 'audio':
                    if ($this->NoMedia > 0 && $this->valid_url($Block['Val'])) {
                        $Str.='<a rel="noreferrer" target="_blank" href="' . $Block['Val'] . '">' . $Block['Val'] . '</a> (audio)';
                        break;
                    }
                    if($this->ShowErrors && !$this->validate_imageurl($Block['Val'])) {
                        $this->Errors[] = "<span class=\"error_label\">Not an approved Imagehost:</span><blockquote class=\"bbcode error\">".'<code class="error">'.$Block['Val'].'</code></blockquote>';
                        break;
                    }
                    if (!$this->valid_url($Block['Val'], '\.(mp3|ogg|wav)')) {
                        $Str.='[aud]' . $Block['Val'] . '[/aud]';
                    } else {
                        //TODO: Proxy this for staff?
                        $Str.='<audio controls="controls" src="' . $Block['Val'] . '"><a rel="noreferrer" target="_blank" href="' . $Block['Val'] . '">' . $Block['Val'] . '</a></audio>';
                    }
                    break;

                case 'inlineurl':
                    $Block['Attr'] = $this->remove_anon($Block['Attr']);
                    if (!$this->valid_url($Block['Attr'], '', true)) {
                        $Array = $this->parse($Block['Attr']);
                        $Block['Attr'] = $Array;
                        $Str.=$this->to_html($Block['Attr']);
                    } else {
                        $LocalURL = $this->local_url($Block['Attr']);
                        if ($LocalURL) {
                            if (preg_match('#^/articles\.php.*[\?&]topic=(.*)#i', $LocalURL)) {
                                $Str .= $this->articleTag($LocalURL);
                            } else if (preg_match('#^/torrents\.php(?!.*type=|.*action=).*[\?&](?:id|torrentid)=(\d+)#i', $LocalURL)) {
                                $Str .= $this->torrentTag($LocalURL);
                            } else if (preg_match('#^/requests\.php\?.*view.*id=(\d+)#i', $LocalURL)) {
                                $Str .= $this->requestTag($LocalURL);
                            } else if (preg_match('#^/collages\.php.*[\?&](.*)id=(\d+)#i', $LocalURL)) {
                                $Str .= $this->collageTag($LocalURL);
                            } else if (preg_match('#^/forums\.php.*[\?&](.*)threadid=(\d+)#i', $LocalURL)) {
                                $Str .= $this->threadTag($LocalURL);
                            } else if (preg_match('#^/forums\.php.*[\?&](.*)forumid=(\d+)#i', $LocalURL)) {
                                $Str .= $this->forumTag($LocalURL);
                            } else if (preg_match('#^/user\.php.*[\?&](.*)id=(\d+)#i', $LocalURL)) {
                                $Str .= $this->userTag($LocalURL);
                            } else {
                                $Str.='<a href="' . $LocalURL . '">' . substr($LocalURL, 1) . '</a>';
                            }
                        } else {
                            if (!$LoggedUser['NotForceLinks']) $target = 'target="_blank" ';
                            $Str.='<a rel="noreferrer" ' . $target .'href="' . $this->anon_url($Block['Attr']) . '">' . $Block['Attr'] . '</a>';
                        }
                    }
                    break;

                case 'ratiolist':
                    if (!$this->Advanced)
                        $Str.= '[ratiolist]';
                    else {

                    $table = '<table>
                      <tr class="colhead">
                            <td>Amount downloaded</td>
                            <td>Required ratio (0% seeded)</td>
                            <td>Required ratio (100% seeded)</td>
                      </tr>
                      <tr class="row'.($LoggedUser['BytesDownloaded'] < 5*1024*1024*1024?'a':'b').'">
                            <td>0-5GB</td>
                            <td>0.00</td>
                            <td>0.00</td>
                      </tr>
                      <tr class="row'.($LoggedUser['BytesDownloaded'] >= 5*1024*1024*1024 && $LoggedUser['BytesDownloaded'] < 10*1024*1024*1024?'a':'b').'">
                            <td>5-10GB</td>
                            <td>0.10</td>
                            <td>0.00</td>
                      </tr>
                      <tr class="row'.($LoggedUser['BytesDownloaded'] >= 10*1024*1024*1024 && $LoggedUser['BytesDownloaded'] < 20*1024*1024*1024?'a':'b').'">
                            <td>10-20GB</td>
                            <td>0.15</td>
                            <td>0.00</td>
                      </tr>
                      <tr class="row'.($LoggedUser['BytesDownloaded'] >= 20*1024*1024*1024 && $LoggedUser['BytesDownloaded'] < 30*1024*1024*1024?'a':'b').'">
                            <td>20-30GB</td>
                            <td>0.20</td>
                            <td>0.00</td>
                      </tr>
                      <tr class="row'.($LoggedUser['BytesDownloaded'] >= 30*1024*1024*1024 && $LoggedUser['BytesDownloaded'] < 40*1024*1024*1024?'a':'b').'">
                            <td>30-40GB</td>
                            <td>0.30</td>
                            <td>0.05</td>
                      </tr>
                      <tr class="row'.($LoggedUser['BytesDownloaded'] >= 40*1024*1024*1024 && $LoggedUser['BytesDownloaded'] < 50*1024*1024*1024?'a':'b').'">
                            <td>40-50GB</td>
                            <td>0.40</td>
                            <td>0.10</td>
                      </tr>
                      <tr class="row'.($LoggedUser['BytesDownloaded'] >= 50*1024*1024*1024 && $LoggedUser['BytesDownloaded'] < 60*1024*1024*1024?'a':'b').'">
                            <td>50-60GB</td>
                            <td>0.50</td>
                            <td>0.20</td>
                      </tr>
                      <tr class="row'.($LoggedUser['BytesDownloaded'] >= 60*1024*1024*1024 && $LoggedUser['BytesDownloaded'] < 80*1024*1024*1024?'a':'b').'">
                            <td>60-80GB</td>
                            <td>0.50</td>
                            <td>0.30</td>
                      </tr>
                      <tr class="row'.($LoggedUser['BytesDownloaded'] >= 80*1024*1024*1024 && $LoggedUser['BytesDownloaded'] < 100*1024*1024*1024?'a':'b').'">
                            <td>80-100GB</td>
                            <td>0.50</td>
                            <td>0.40</td>
                      </tr>
                      <tr class="row'.($LoggedUser['BytesDownloaded'] >= 100*1024*1024*1024?'a':'b').'">
                            <td>100+GB</td>
                            <td>0.50</td>
                            <td>0.50</td>
                      </tr>
                </table>';
                        $table = str_replace("\n", '', $table);
                        $Str .= $table;
                    }
                    break;

                case 'id':
                    if ($this->Advanced) {
                        $Str.='<span id="'. $Block['Attr'] .'">' . $this->to_html($Block['Val']) . '</span>';
                    } else {
                        $Str .= $this->to_html($Block['Val']);
                    }
                    break;
            }
        }
        $this->Levels--;

        return $Str;
    }

    function prism_supported($Lang) {
        if (!empty($Lang)) {
            $Lang = strtolower($Lang);
            $Supported = [
                'abap',
                'actionscript',
                'ada',
                'apacheconf',
                'apl',
                'applescript',
                'asciidoc',
                'aspnet',
                'autohotkey',
                'autoit',
                'bash',
                'basic',
                'batch',
                'bison',
                'brainfuck',
                'bro',
                'c',
                'clike',
                'coffeescript',
                'cpp',
                'crystal',
                'csharp',
                'css',
                'css-extras',
                'd',
                'dart',
                'diff',
                'django',
                'docker',
                'eiffel',
                'elixir',
                'erlang',
                'fortran',
                'fsharp',
                'gherkin',
                'git',
                'glsl',
                'go',
                'graphql',
                'groovy',
                'haml',
                'handlebars',
                'haskell',
                'haxe',
                'http',
                'icon',
                'inform7',
                'ini',
                'j',
                'jade',
                'java',
                'javascript',
                'jolie',
                'json',
                'jsx',
                'julia',
                'keyman',
                'kotlin',
                'latex',
                'less',
                'livescript',
                'lolcode',
                'lua',
                'makefile',
                'markdown',
                'markup',
                'matlab',
                'mel',
                'mizar',
                'monkey',
                'nasm',
                'nginx',
                'nim',
                'nix',
                'nsis',
                'objectivec',
                'ocaml',
                'oz',
                'parigp',
                'parser',
                'pascal',
                'perl',
                'php',
                'php-extras',
                'powershell',
                'processing',
                'prolog',
                'properties',
                'protobuf',
                'puppet',
                'pure',
                'python',
                'q',
                'qore',
                'r',
                'reason',
                'rest',
                'rip',
                'roboconf',
                'ruby',
                'rust',
                'sas',
                'sass',
                'scala',
                'scheme',
                'scss',
                'smalltalk',
                'smarty',
                'sql',
                'stylus',
                'swift',
                'tcl',
                'textile',
                'twig',
                'typescript',
                'vbnet',
                'verilog',
                'vhdl',
                'vim',
                'wiki',
                'xojo',
                'yaml',
            ];
            if (in_array($Lang, $Supported)) {
                return 'language-'.$Lang;
            } else {
                return null;
            }
        }
    }

    /**
     * Returns a single ID or the parameter(s) of a local URL
     * @param string $value The given input
     * @param string $section Website section
     * @param array $customRegex Custom rules for some parameters
     * @return array $Matches
     */
    function getQueryParameters($value, $section, $customRegex = [])
    {
        // The rule for ID always defaults to \d+ unless overridden
        // If $customRegex['id'] is not set, we create it
        $idRegex = isset($customRegex['id']) ? $customRegex['id'] : ($customRegex['id'] = '\d+');
        $Pattern = '/^(?<id>'.$idRegex.')$|^(' . SITELINK_REGEX . ')?\/'.$section.'\.php\?(?<queryString>.*)/i';
        $Matches = array();
        $success = preg_match($Pattern, $value, $Matches);

        // Parse catched URL parameters
        if ($success && !empty($Matches['queryString']))
            parse_str(html_entity_decode(parse_url($value, PHP_URL_QUERY)), $Matches);

        // Further validation
        foreach ($Matches as $Key => &$Val) {
            // Re-escape input
            $Val = display_str($Val);
            // Custom RegEx rule
            if (isset($customRegex[$Key]) && !preg_match('/^'.$customRegex[$Key].'$/', $Val)) {
                unset($Matches[$Key]);
            }
        }

        return $Matches;
    }

    function articleTag($value)
    {
        $Matches = $this->getQueryParameters($value, 'articles', ['id' => '[a-z0-9\-\_.()\@&]+', 'topic' => '[a-z0-9\-\_.()\@&]+']);
        if (!empty($Matches['topic'])) {
            $Title = getArticleTitle($Matches['topic']);
            if ($Title) {
                $Str = '<a href="articles.php?topic='.$Matches['topic'].'"><span class="taglabel">Article: </span>'.display_str($Title).'</a>';
            } else {
                $Str = '<a title="Article not found, maybe deleted, or never existed" href="torrents.php?id='.$Matches['topic'].'"><span class="taglabel">Article: </span> #'.$Matches['id'].'</a>';
            }
        } else {
            $Str = '[article]' . str_replace('[inlineurl]', '', $value) . '[/article]';
        }
        return $Str;
    }

    function torrentTag($value)
    {
        $Matches = $this->getQueryParameters($value, 'torrents', ['torrentid' => '\d+', 'postid' => '\d+']);
        if (empty($Matches['id']) && !empty($Matches['torrentid'])) $Matches['id'] = $Matches['torrentid'];
        if (!empty($Matches['id'])) {
            $Groups = get_groups(array($Matches['id']), true, true, true);//var_dump($Groups);exit;
            if (!empty($Groups['matches'][$Matches['id']])) {
                $Group = $Groups['matches'][$Matches['id']];
                $Torrent = $Group['Torrents'][$Matches['id']];
                $Username = anon_username($Torrent['Username'], $Torrent['Anonymous']);
                $Overlay = get_overlay_html($Group['Name'], $Username, $Group['Image'], $Torrent['Seeders'], $Torrent['Leechers'], $Torrent['Size'], $Torrent['Snatched']);
                $Str  = '<script>var overlay'.$Group['ID'].' = '.json_encode($Overlay).'</script>';
                $Str .= '<a onmouseover="return overlib(overlay'.$Group['ID'].', FULLHTML);" onmouseout="return nd();" href="torrents.php?id='.$Matches['id'].'"><span class="taglabel">Torrent: </span>'.display_str($Group['Name']).'</a>';
                if (!empty($Matches['postid'])) {
                    $Str .= '&nbsp;&nbsp;<a onmouseover="return overlib(overlay'.$Group['ID'].', FULLHTML);" onmouseout="return nd();" href="torrents.php?id='.$Matches['id'].'&postid='.$Matches['postid'].'#post'.$Matches['postid'].'"><span class="taglabel">comment: </span>'.$Matches['postid'].'</a>';
                }
            } else {
                $Str = '<a title="Torrent not found, maybe deleted, or never existed" href="torrents.php?id='.$Matches['id'].'"><span class="taglabel">Torrent: </span> #'.$Matches['id'].'</a>';
            }
        } else {
            $Str = '[torrent]' . str_replace('[inlineurl]', '', $value) . '[/torrent]';
        }
        return $Str;
    }

    function requestTag($value)
    {
        $Matches = $this->getQueryParameters($value, 'requests', ['page' => '\d+']);
        if (!empty($Matches['id'])) {
            $Request = get_requests(array($Matches['id']), true);
            if (!empty($Request['matches'][$Matches['id']])) {
                include_once(SERVER_ROOT.'/Legacy/sections/requests/functions.php');
                $Request = $Request['matches'][$Matches['id']];
                $RequestVotes = get_votes_array($Request['ID']);
                $VoteCount = count($RequestVotes['Voters']);
                $IsFilled = ($Request['TorrentID'] != 0);
                $Overlay = get_request_overlay_html($Request['Title'], $Request['2'], $Request['Image'], $RequestVotes['TotalBounty'], $VoteCount, $IsFilled);
                $Str  = '<script>var overlay_req'.$Request['ID'].' = '.json_encode($Overlay).'</script>';
                $Str .= '<a onmouseover="return overlib(overlay_req'.$Request['ID'].', FULLHTML);" onmouseout="return nd();" href="requests.php?action=view&id='.$Matches['id'].'"><span class="taglabel">Request: </span>'.display_str($Request['Title']).'</a>';
                if (!empty($Matches['postid'])) {
                    $Page = !empty($Matches['page']) ? '&page='.$Matches['page'] : '';
                    $Str .= '&nbsp;&nbsp;<a onmouseover="return overlib(overlay_req'.$Request['ID'].', FULLHTML);" onmouseout="return nd();" href="requests.php?action=view&id='.$Matches['id'].$Page.'#post'.$Matches['postid'].'"><span class="taglabel">comment: </span>'.$Matches['postid'].'</a>';
                }
            } else {
                $Str = '<a title="Request not found, maybe deleted, or never existed" href="requests.php?action=view&id='.$Matches['id'].'"><span class="taglabel">Request: </span> #'.$Matches['id'].'</a>';
            }
        } else {
            $Str = '[request]' . str_replace('[inlineurl]', '', $value) . '[/request]';
        }
        return $Str;
    }

    function collageTag($value)
    {
        $Matches = $this->getQueryParameters($value, 'collages', ['postid' => '\d+']);
        if (!empty($Matches['id'])) {
            $Title = getCollageName($Matches['id']);
            if ($Title) {
                $Str = '<a href="collages.php?id='.$Matches['id'].'"><span class="taglabel">Collage: </span>'.display_str($Title).'</a>';
                if (!empty($Matches['postid'])) {
                    $Str .= '&nbsp;&nbsp;<a href="collages.php?id='.$Matches['id'].'#post'.$Matches['postid'].'"><span class="taglabel">comment: </span>'.$Matches['postid'].'</a>';
                }
            } else {
                $Str = '<a title="Collage not found, maybe deleted, or never existed" href="collages.php?id='.$Matches['id'].'"><span class="taglabel">Collage: </span> #'.$Matches['id'].'</a>';
            }
        } else {
            $Str = '[collage]' . str_replace('[inlineurl]', '', $value) . '[/collage]';
        }
        return $Str;
    }

    function threadTag($value)
    {
        $Matches = $this->getQueryParameters($value, 'forums', ['threadid' => '\d+', 'postid' => '\d+']);
        if (!empty($Matches['threadid'])) {
            $Title = getThreadName($Matches['threadid']);
            if ($Title) {
                $Str = '<a href="forums.php?action=viewthread&threadid='.$Matches['threadid'].'"><span class="taglabel">Thread: </span>'.display_str($Title).'</a>';
                if (!empty($Matches['postid'])) {
                    $Str .= '&nbsp;&nbsp;<a href="forums.php?action=viewthread&threadid='.$Matches['threadid'].'&postid='.$Matches['postid'].'#post'.$Matches['postid'].'"><span class="taglabel">post: </span>'.$Matches['postid'].'</a>';
                }
            } else {
                $Str = '<a title="Thread not found, maybe deleted, or never existed" href="forums.php?action=viewthread&threadid='.$Matches['threadid'].'"><span class="taglabel">Thread: </span> #'.$Matches['threadid'].'</a>';
            }
        } else {
            $Str = '[thread]' . str_replace('[inlineurl]', '', $value) . '[/thread]';
        }
        return $Str;
    }

    function forumTag($value)
    {
        $Matches = $this->getQueryParameters($value, 'forums', ['forumid' => '\d+']);
        if (!empty($Matches['forumid'])) {
            $Title = getForumName($Matches['forumid']);
            if ($Title) {
                $Str = '<a href="forums.php?action=viewforum&forumid='.$Matches['forumid'].'"><span class="taglabel">Forum: </span>'.display_str($Title).'</a>';
            } else {
                $Str = '<a title="Forum not found, maybe deleted, or never existed" href="forums.php?action=viewforum&forumid='.$Matches['forumid'].'"><span class="taglabel">Forum: </span> #'.$Matches['forumid'].'</a>';
            }
        } else {
            $Str = '[forum]' . str_replace('[inlineurl]', '', $value) . '[/forum]';
        }
        return $Str;
    }

    function userTag($value)
    {
        $Matches = $this->getQueryParameters($value, 'user');
        if (!empty($Matches['id'])) {
            $Title = getUserName($Matches['id']);
            if ($Title) {
                $Str = '<a href="user.php?id='.$Matches['id'].'"><span class="taglabel">User: </span>'.display_str($Title).'</a>';
            } else {
                $Str = '<a title="User not found, maybe deleted, or never existed" href="user.php?id='.$Matches['id'].'"><span class="taglabel">User: </span> #'.$Matches['id'].'</a>';
            }
        } else {
            $Str = '[user]' . str_replace('[inlineurl]', '', $value) . '[/user]';
        }
        return $Str;
    }

    public function raw_text($Array, $StripURL = false)
    {
        $Str = '';
        foreach ($Array as $Block) {
            if (is_string($Block)) {
                $Str.=$Block;
                continue;
            }
            switch ($Block['Type']) {

                case 'b':
                case 'u':
                case 'i':
                case 's':
                case 'sup':
                case 'sub':
                case 'color':
                case 'colour':
                case 'size':
                case 'quote':
                case 'spoiler':
                case 'align':
                case 'center':
                case 'mcom':
                case 'anchor':
                case '#':
                case 'rank':
                case 'tip':
                case 'bg':
                case 'table':
                case 'td':
                    $Str.=$this->raw_text($Block['Val']);
                    break;
                case 'tr':
                    $Str.=$this->raw_text($Block['Val'])."\n";
                    break;
                case 'br':
                    $Str.= "\n";
                    break;
                case 'tex': //since this will never strip cleanly, just remove it
                    break;
                case 'user':
                case 'pre':
                case 'code':
                case 'audio':
                case 'img':
                case 'imgalt':
                case 'imgnm':
                    $Str.=$Block['Val'];
                    break;
                case 'list':
                    foreach ($Block['Val'] as $Line) {
                        $Str.=$Block['Tag'] . $this->raw_text($Line);
                    }
                    break;

                case 'url':
                case 'link':
                    if ($StripURL)
                        break;
                    // Make sure the URL has a label
                    if (empty($Block['Val'])) {
                        $Block['Val'] = $Block['Attr'];
                    } else {
                        $Block['Val'] = $this->raw_text($Block['Val']);
                    }

                    $Str.=$Block['Val'];
                    break;

                case 'inlineurl':
                    if (!$this->valid_url($Block['Attr'], '', true)) {
                        $Array = $this->parse($Block['Attr']);
                        $Block['Attr'] = $Array;
                        $Str.=$this->raw_text($Block['Attr']);
                        $Str.="RAW";
                    } else {
                        $Str.=$Block['Attr'];
                    }

                    break;
                default:
                    break;
            }
        }

        return $Str;
    }

    public function smileys($Str)
    {
        global $LoggedUser;
        if (!empty($LoggedUser['DisableSmileys'])) {
            return $Str;
        }
        $Str = strtr($Str, $this->Smileys);

        return $Str;
    }

    /*
     * --------------------- BBCode assistant -----------------------------
     * added 2012.04.21 - mifune
     * --------------------------------------------------------------------
      // pass in the id of the textarea this bbcode helper affects
      // start_num == num of smilies to load when created
      // $load_increment == number of smilies to add each time user presses load button
      // $load_increment_first == if passed this number of smilies are added the first time user presses load button
      // NOTE: its probably best to call this with default parameters because then the user's browser will cache the
      // ajax result and all subsequent calls will use the cached result - if differetn pages use different parameters
      // they will not get that benefit
     */

    public function display_bbcode_assistant($textarea, $AllowAdvancedTags = false, $start_num_smilies = 0, $load_increment = 240, $load_increment_first = 30)
    {
        global $LoggedUser;
        if ($load_increment_first == -1) {
            $load_increment_first = $load_increment;
        }
        ?>
        <div id="hover_pick<?= $textarea; ?>" style="width: auto; height: auto; position: absolute; border: 0px solid rgb(51, 51, 51); display: none; z-index: 20;"></div>

        <table class="bb_holder">
            <tbody><tr>
                    <td class="colhead" style="padding: 2px 2px 0px 6px">

                        <div class="bb_buttons_left">

                            <a class="bb_button" onclick="tag('b', '<?= $textarea; ?>')" title="Bold text: [b]text[/b]" alt="B"><b>B</b></a>
                            <a class="bb_button" onclick="tag('i', '<?= $textarea; ?>')" title="Italic text: [i]text[/i]" alt="I"><i>I</i></a>
                            <a class="bb_button" onclick="tag('u', '<?= $textarea; ?>')" title="Underline text: [u]text[/u]" alt="U"><u>U</u></a>
                            <a class="bb_button" onclick="tag('s', '<?= $textarea; ?>')" title="Strikethrough text: [s]text[/s]" alt="S"><s>S</s></a>
                            <a class="bb_button" onclick="insert('[hr]', '<?= $textarea; ?>')" title="Horizontal Line: [hr]" alt="HL">hr</a>

                            <a class="bb_button" onclick="url('<?= $textarea; ?>')" title="URL: [url]http://url[/url] or [url=http://url]URL text[/url]" alt="Url">Url</a>
                            <a class="bb_button" onclick="anchor('<?= $textarea; ?>')" title="Anchored heading: [anchor=name]Heading text[/anchor] or [#=name]Heading text[/#]" alt="Anchor">Anchor</a>

                            <a class="bb_button" onclick="image_prompt('<?= $textarea; ?>')" title="Image: [img]http://image_url[/img]" alt="Image">Img</a>
                            <a class="bb_button" onclick="tag('code', '<?= $textarea; ?>')" title="Code display: [code]code[/code]" alt="Code">Code</a>
                            <a class="bb_button" onclick="tag('quote', '<?= $textarea; ?>')" title="Quote text: [quote]text[/quote]" alt="Quote">Quote</a>

                            <a class="bb_button" onclick="video('<?= $textarea; ?>')" title="Youtube video: [video=http://www.youtube.com/v/abcd]" alt="Youtube">Video</a>
        <?php if (check_perms('site_moderate_forums')) { ?>
                            <a class="bb_button" onclick="flash('<?= $textarea; ?>')" title="Flash object: [flash]http://url.swf[/flash]" alt="Flash">Flash</a>
        <?php       } ?>
                            <a class="bb_button" onclick="spoiler('<?= $textarea; ?>')" title="Spoiler: [spoiler=title]hidden text[/spoiler]" alt="Spoiler">Spoiler</a>

                            <a class="bb_button" onclick="insert('[*]', '<?= $textarea; ?>')" title="List item: [*]text" alt="List">List</a>

                            <a class="bb_button" onclick="colorpicker('<?= $textarea; ?>','bg');" title="Background: [bg=color,width% or widthpx,align]text[/bg]" alt="Background">Bg</a>

                            <a class="bb_button" onclick="table('<?= $textarea; ?>')" title="Table: [table=color,width% or widthpx,align][tr][td]text[/td][td]text[/td][/tr][/table]" alt="Table">Table</a>

                        </div>
                        <div class="bb_buttons_right">
                            <img class="bb_icon" src="<?= get_symbol_url('align_center.png') ?>" onclick="wrap('align','','center', '<?= $textarea; ?>')" title="Center Align text: [align=center]text[/align]" alt="Center" />
                            <img class="bb_icon" src="<?= get_symbol_url('align_left.png') ?>" onclick="wrap('align','','left', '<?= $textarea; ?>')" title="Left Align text: [align=left]text[/align]" alt="Left" />
                            <img class="bb_icon" src="<?= get_symbol_url('align_justify.png') ?>" onclick="wrap('align','','justify', '<?= $textarea; ?>')" title="Justify text: [align=justify]text[/align]" alt="Justify" />
                            <img class="bb_icon" src="<?= get_symbol_url('align_right.png') ?>" onclick="wrap('align','','right', '<?= $textarea; ?>')" title="Right Align text: [align=right]text[/align]" alt="Right" />
                            <img class="bb_icon" src="<?= get_symbol_url('text_uppercase.png') ?>" onclick="text('up', '<?= $textarea; ?>')" title="To Uppercase" alt="Up" />
                            <img class="bb_icon" src="<?= get_symbol_url('text_lowercase.png') ?>" onclick="text('low', '<?= $textarea; ?>')" title="To Lowercase" alt="Low" />
                        </div>
                    </td><td class="colhead" style="padding: 2px 6px 0px 0px" rowspan=2>
                        <div class="bb_buttons_right">
                            <a class="bb_help" href="<?= "http://" . SITE_URL . "/articles.php?topic=bbcode" ?>" target="_blank">Help</a>
                        </div>
                    </td></tr><tr>
                    <td class="colhead" style="padding: 2px 2px 0px 6px">
                        <div class="bb_buttons_left">
                            <select class="bb_button" name="fontfont" id="fontfont<?= $textarea; ?>" onchange="font('font',this.value,'<?= $textarea; ?>');" title="Font: [font=fontfamily]text[/font]">
                                <option value="-1">Font Type</option>
        <?php
        foreach ($this->Fonts as $Key => $Val) {
            echo '
                                <option value="' . $Key . '"  style="font-family: ' . $Val . '">' . $Key . '</option>';
        }
        ?>
                            </select>

                            <select  class="bb_button" name="fontsize" id="fontsize<?= $textarea; ?>" onchange="font('size',this.value,'<?= $textarea; ?>');" title="Text Size: [size=number]text[/size]">
                                <option value="-1" selected="selected">Font size</option>
                                <option value="0">0</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5</option>
                                <option value="6">6</option>
                                <option value="7">7</option>
                                <option value="8">8</option>
                                <option value="9">9</option>
                                <option value="10">10</option>
                            </select>
                            <a class="bb_button" onclick="colorpicker('<?= $textarea; ?>','color');" title="Text Color: [color=colorname]text[/color] or [color=#hexnumber]text[/color]" alt="Color">Color</a>

                            <a class="bb_button" onclick="insert('[cast]', '<?= $textarea; ?>')" title="Cast icon: [quote]" alt="cast">cast</a>
                            <a class="bb_button" onclick="insert('[details]', '<?= $textarea; ?>')" title="Details icon: [details]" alt="details">details</a>
                            <a class="bb_button" onclick="insert('[info]', '<?= $textarea; ?>')" title="Info icon: [info]" alt="info">info</a>
                            <a class="bb_button" onclick="insert('[plot]', '<?= $textarea; ?>')" title="Plot icon: [plot]" alt="plot">plot</a>
                            <a class="bb_button" onclick="insert('[screens]', '<?= $textarea; ?>')" title="Screens icon: [screens]" alt="screens">screens</a>

        <?php if (check_perms('site_moderate_forums')) { ?>
                                <a class="bb_button" style="border: 2px solid #600;" onclick="tag('mcom', '<?= $textarea; ?>')" title="Staff Comment: [mcom]text[/mcom]" alt="Mod comment">Mod</a>
        <?php } ?>
                        </div>
                        <div class="bb_buttons_right">
                            <a class="bb_button" onclick="tag('sup', '<?= $textarea; ?>')" title="Superscript text: [sup]text[/sup]" alt="supX">sup<sup>x</sup></a>
                            <a class="bb_button" onclick="tag('sub', '<?= $textarea; ?>')" title="Subscript text: [sub]text[/sub]" alt="subX">sub<sub>x</sub></a>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td colspan=2>
                        <div id="pickerholder<?= $textarea; ?>" class="picker_holder"></div>
                        <div id="smiley_overflow<?= $textarea; ?>" class="bb_smiley_holder">
        <?php if ($start_num_smilies > 0) {
            $this->draw_smilies_from(0, $start_num_smilies, $textarea);
        } ?>
                        </div>
                        <div class="overflow_button">
                            <a href="#" id="open_overflow<?= $textarea; ?>" onclick="if (this.isopen) {Close_Smilies('<?= $textarea; ?>');} else {Open_Smilies(<?= "$start_num_smilies,$load_increment_first,'$textarea'" ?>);};return false;">Show smilies</a>
                            <a href="#" id="open_overflow_more<?= $textarea; ?>" onclick="Open_Smilies(<?= "$start_num_smilies,$load_increment,'$textarea'" ?>);return false;"></a>
                            <span id="smiley_count<?= $textarea; ?>" class="number" style="float:right;"></span>
                            <span id="smiley_max<?= $textarea; ?>" class="number" style="float:left;"></span>
                        </div>
                    </td></tr></tbody></table>
        <?php
    }

    // output smiley data in xml (we dont just draw the html because we want maxsmilies in js)
    public function draw_smilies_from_XML($indexfrom = 0, $indexto = -1)
    {
        $count = 0;
        echo "<smilies>";
        foreach ($this->Smileys as $Key => $Val) {
            if ($indexto >= 0 && $count >= $indexto) {
                break;
            }
            if ($count >= $indexfrom) {
                echo '    <smiley>
        <bbcode>' . $Key . '</bbcode>
        <url>' . htmlentities($Val) . '</url>
    </smiley>';
            }
            $count++;
        }
        reset($this->Smileys);
        echo '    <maxsmilies>' . count($this->Smileys) . '</maxsmilies>
</smilies>';
    }

    public function draw_smilies_from($indexfrom = 0, $indexto = -1, $textarea)
    {
        $count = 0;
        foreach ($this->Smileys as $Key => $Val) {
            if ($indexto >= 0 && $count >= $indexto) {
                break;
            }
            if ($count >= $indexfrom) {  // ' &nbsp;' .$Key. - jsut for printing in dev
                echo '<a class="bb_smiley" title="' . $Key . '" href="javascript:insert(\' ' . $Key . ' \',\'' . $textarea . '\');">' . $Val . '</a>';
            }
            $count++;
        }
        reset($this->Smileys);
    }

    public function draw_all_smilies($Sort = true, $AZ = true)
    {
        $count = 0;
        if ($Sort) {
            if ($AZ)
                ksort($this->Smileys, SORT_STRING | SORT_FLAG_CASE);
            else
                krsort($this->Smileys, SORT_STRING | SORT_FLAG_CASE);
        }
        echo '<div class="box center" style="font-size:1.2em;">';
        foreach ($this->Smileys as $Key => $Val) {
            echo '<div class="bb_smiley pad center" style="display:inline-block;margin:8px 8px;">';
            echo "      $Val <br /><br />$Key";
            echo '</div>';

            $count++;
        }
        echo '</div>';
        reset($this->Smileys);
    }

    public function clean_bbcode($Str, $Advanced)
    {
        // Change mcom tags into quote tags for non-mod users
        if (!check_perms('site_moderate_forums')) {
            $Str = preg_replace('/\[mcom\]/i', '[quote=Staff Comment]', $Str);
            $Str = preg_replace('/\[\/mcom\]/i', '[/quote]', $Str);
            $Str = preg_replace('/\[flash=([^\]])*\]/i', '[quote=Flash Object]', $Str);
            $Str = preg_replace('/\[\/flash\]/i', '[/quote]', $Str);
        }

        return $Str;
    }

}

/*
  //Uncomment this part to test the class via command line:
  public function display_str($Str) {return $Str;}
  public function check_perms($Perm) {return true;}
  $Str = "hello
  [pre]http://anonym.to/?http://whatshirts.portmerch.com/
  ====hi====
  ===hi===
  ==hi==[/pre]
  ====hi====
  hi";
  $Text = NEW TEXT;
  echo $Text->full_format($Str);
  echo "\n"
 */
