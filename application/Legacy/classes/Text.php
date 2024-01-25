<?php
namespace Luminance\Legacy;

use Luminance\Core\Entity;

use Luminance\Errors\BBCodeError;

use Luminance\Entities\Torrent;

use Luminance\Services\Debug;

class Text {

    // import smileys from their trait
    use TextSmileys;

    // tag=>max number of attributes
    static private $ValidTags = [
        'ratiolist'    => 0,        'code'         => 1,        'codeblock'    => 1,
        'you'          => 0,        'h5v'          => 1,        'yt'           => 1,
        'vimeo'        => 1,        'video'        => 1,        'flash'        => 1,
        'banner'       => 0,        'thumb'        => 0,        '#'            => 1,
        'anchor'       => 1,        'mcom'         => 0,        'table'        => 1,
        'th'           => 1,        'tr'           => 1,        'td'           => 1,
        'bg'           => 1,        'cast'         => 0,        'details'      => 0,
        'info'         => 0,        'plot'         => 0,        'screens'      => 0,
        'br'           => 0,        'hr'           => 0,        'font'         => 1,
        'center'       => 0,        'spoiler'      => 1,        'b'            => 0,
        'u'            => 0,        'i'            => 0,        's'            => 0,
        'sup'          => 0,        'sub'          => 0,        '*'            => 0,
        'user'         => 0,        'n'            => 0,        'inlineurl'    => 0,
        'align'        => 1,        'color'        => 1,        'colour'       => 1,
        'size'         => 1,        'url'          => 1,        'img'          => 1,
        'quote'        => 1,        'pre'          => 1,        'tex'          => 0,
        'hide'         => 1,        'plain'        => 0,        'important'    => 0,
        'torrent'      => 0,        'request'      => 0,        'collage'      => 0,
        'thread'       => 0,        'forum'        => 0,        'rank'         => 1,
        'tip'          => 1,        'imgnm'        => 1,        'imgalt'       => 1,
        'article'      => 1,        'id'           => 1,        'mediainfo'    => 0,
        'uploader'     => 1,        'staffpm'      => 0,        'geoip'        => 0,
        '```'          => 1,
    ];

    private $AdvancedTagOnly = [
        'mcom',
        'hide',
    ];


    //  font name (display) => fallback fonts (css)
    private $Fonts = [
        'Aleo'                   => "Aleo, sans-serif;",
        'Arial'                  => "Arial, 'Helvetica Neue', Helvetica, sans-serif;",
        'Arial Black'            => "'Arial Black', 'Arial Bold', Gadget, sans-serif;",
        'Caviar Dreams'          => "'Caviar Dreams', sans-serif;",
        'Comic Sans MS'          => "'Comic Sans MS', cursive, sans-serif;",
        'Courier New'            => "'Courier New', Courier, 'Lucida Sans Typewriter', 'Lucida Typewriter', monospace;",
        'fapping'                => "Fapping, serif;",
        'Franklin Gothic Medium' => "'Franklin Gothic Medium', 'Franklin Gothic', 'ITC Franklin Gothic', Arial, sans-serif;",
        'Georgia'                => "Georgia, Times, 'Times New Roman', serif;",
        'Helvetica'              => "'Helvetica Neue', Helvetica, Arial, sans-serif;",
        'Impact'                 => "Impact, Haettenschweiler, 'Franklin Gothic Bold', Charcoal, 'Helvetica Inserat', 'Bitstream Vera Sans Bold', 'Arial Black', sans-serif;",
        'Lucida Console'         => "'Lucida Console', Monaco, monospace;",
        'Lucida Sans Unicode'    => "'Lucida Sans Unicode', 'Lucida Grande', 'Lucida Sans', Geneva, Verdana, sans-serif;",
        'Microsoft Sans Serif'   => "'Microsoft Sans Serif', Helvetica, sans-serif;",
        'Palatino Linotype'      => "Palatino, 'Palatino Linotype', 'Palatino LT STD', 'Book Antiqua', Georgia, serif;",
        'Quantico'               => "Quantico, sans-serif;",
        'Tahoma'                 => "Tahoma, Verdana, Segoe, sans-serif;",
        'Times New Roman'        => "TimesNewRoman, 'Times New Roman', Times, Baskerville, Georgia, serif;",
        'Trebuchet MS'           => "'Trebuchet MS', 'Lucida Grande', 'Lucida Sans Unicode', 'Lucida Sans', Tahoma, sans-serif;",
        'Verdana'                => "Verdana, Geneva, sans-serif;",
    ];

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

    public $cachedBBCode = [];

    public static $bbcode = [];
    public static $time = 0.0;

    private $attributeRegex = '';
    private $markdownRegex  = '';
    private $openTagRegex   = '';
    private $wikiTagRegex   = '';
    private $tagParserRegex = '';

    public function __construct() {
        global $master;

        $this->attributeRegex = "(?:[^\n'\"\[\]]|\[\d*\])+";
        $this->markdownRegex  = "(?:^|\n)(```)({$this->attributeRegex})?";
        $this->openTagRegex   = "((\[[a-zA-Z*#5]+)(={$this->attributeRegex})?\])";
        $this->wikiTagRegex   = "(\[\[[^\n\"'\[\]]+\]\])";
        $this->tagParserRegex = "/{$this->markdownRegex}|{$this->openTagRegex}|{$this->wikiTagRegex}/";

        // Automatic love :)
        if (!empty($master->settings->main->site_short_name)) {
            $love = ''.strtolower($master->settings->main->site_short_name).'love';
            if (file_exists($master->publicPath.'/static/common/smileys/'.$love.'.gif')) {
                if (array_key_exists(':'.$love.':', $this->Smileys) === false) {
                    $this->Smileys[':'.$love.':'] = $love.'.gif';
                }
            }
        }

        foreach ($this->Smileys as $Key => $Val) {
            $this->Smileys[$Key] = '<img class="bbcode smiley" src="/static/common/smileys/' . $Val . '" alt="' . $Key . '" />';
        }

        foreach ($this->Icons as $Key => $Val) {
            $this->Icons[$Key] = '<img class="bbcode icon" src="/static/common/icons/' . $Val . '" alt="' . $Key . '" />';
        }
    }

    public function has_errors() {
        return count($this->Errors) > 0;
    }

    public function get_errors() {
        return $this->Errors;
    }

    public function full_format($BBCode, $AdvancedTags = false, $ShowErrors = false, $CacheKey = null) {
        $formatStartTime=microtime(true);
        if (!empty($CacheKey)) {
            if (array_key_exists($CacheKey, $this->cachedBBCode)) {
                return $this->cachedBBCode[$CacheKey];
            }
        }

        if (empty($BBCode)) {
            return '';
        }

        $this->Advanced = $AdvancedTags;
        $this->ShowErrors = $ShowErrors;
        $this->Errors = [];

        # Normalize endofline characters to Unix
        $str = str_replace(["\r\n", "\r", "\n"], "\n", $BBCode);
        $str = display_str($str);
        $Tree = $this->parse($str);
        $Tree = $this->mutateTree($Tree);
        $Tree = $this->validateTree($Tree);
        $HTML  = $this->to_html($Tree);

        // Formatting cleanup
        $HTML = preg_replace('/\h*(\v)/', '$1', $HTML);
        $HTML = preg_replace('/(?<=[ ])[ ]/', '&nbsp;', $HTML);
        $HTML = nl2br($HTML);

        if (!empty($CacheKey)) {
            $this->cachedBBCode[$CacheKey] = $HTML;
        }

        $formatEndTime=microtime(true);
        if (Debug::getEnabled()) {
            self::$bbcode[] = [
                'bbcode'     => $BBCode,
                'microtime' => ($formatEndTime-$formatStartTime)*1000,
            ];
            self::$time+=($formatEndTime-$formatStartTime)*1000;
        }
        return $HTML;
    }


    static private $MutateTags = [
        'spoiler' => [
            'img'    => 'spimg',
            'imgnm'  => 'spimgnm',
            'imgalt' => 'spimgalt',
            'banner' => 'spbanner',
        ],
    ];

    protected function mutateTree($Tree, $Mutation = []) {
        // Check the tree first
        if (!is_array($Tree)) {
            return;

        }
        // Recurse the tree looking for mutations
        foreach ($Tree as &$block) {
            if (!isset($block['Type'], $block['Val'])) {
                continue;
            }
            // Check if any mutations should be applied
            if (!empty($Mutation)) {
                if (in_array($block['Type'], array_keys($Mutation))) {
                    $block['Type'] = $Mutation[$block['Type']];
                }
            }
            if (in_array($block['Type'], array_keys(self::$MutateTags))) {
                // Decend into the tree with a new mutation
                $newMutation = array_merge($Mutation, self::$MutateTags[$block['Type']]);
                $block['Val'] = $this->mutateTree($block['Val'], $newMutation);
            }
            if (is_array($block['Val'])) {
                // Decend into the tree with the current mutation
                $block['Val'] = $this->mutateTree($block['Val'], $Mutation);
            }
        }
        return $Tree;
    }

    static private $CheckTags = [
        'tr' => ['table'],
        'th' => ['tr'],
        'td' => ['tr'],
    ];

    protected function validateTree($Tree, $Parent=null) {
        // Check the tree first
        if (!is_array($Tree)) {
            return;
        }
        // Recurse the tree looking for mismatched tags
        foreach ($Tree as $Index => $block) {
            if (!isset($block['Type'], $block['Val']))
                continue;
            if (in_array($block['Type'], array_keys(self::$CheckTags))) {
                if (!in_array($Parent, self::$CheckTags[$block['Type']])) {
                    // log an error (when submitting)
                    $this->Errors[] = "<span class=\"error_label\">illegal placement of [{$block['Type']}] tag</span>";
                    // Delete orphaned tag (when viewing)
                    unset($Tree[$Index]);
                    continue;
                }
            }
            if (is_array($block['Val'])) {
                // Recurse the tree
                $Tree[$Index]['Val'] = $this->validateTree($block['Val'], $block['Type']);
            }
        }
        return $Tree;
    }

    /**
     * Validates the bbcode for bad tags (unclosed/mixed tags)
     *
     * @param  string  $str          The text to be validated
     * @param  boolean $AdvancedTags Whether AdvancedTags are allowed (this is only for the preview if errorout=true)
     * @param  boolean $ErrorOut     If $ErrorOut=true then on errors the error page will be displayed with a preview of the errors (If false the function just returns the validate result)
     * @return boolean True if there are no bad tags and false otherwise
     */
    public function validate_bbcode($str, $AdvancedTags = false, $ErrorOut = true, $FurtherCheck = true) {
        global $master;
        $preview = $this->full_format($str, $AdvancedTags, true);
        if ($this->has_errors()) {
            if ($ErrorOut) {
                $bbErrors = implode('<br/>', $this->get_errors());
                throw new BBCodeError("There are errors in your bbcode", "There are errors in your bbcode <br/><br/>{$bbErrors}<br/>If the tag(s) highlighted do actually have a closing tag then you probably have overlapping tags
                        <br/>ie.<br/><span style=\"font-weight:bold\">[b]</span> [i] your text <span style=\"font-weight:bold\">[/b] </span>[/i] <span style=\"color:red\">(wrong)</span> - <em>tags must be nested, when they overlap like this it throws an error</em>
                        <br/><span style=\"font-weight:bold\">[b]</span> [i] your text [/i] <span style=\"font-weight:bold\">[/b]</span> <span style=\"color:green\">(correct)</span> - <em>properly nested tags</em></div><br/><div class=\"head\">Your post</div><div class=\"box pad\">
                        <div class=\"box\"><div class=\"post_content\">{$preview}</div></div><br/>
                        <div style=\"font-style:italic;text-align:center;cursor:pointer;\"><a onclick=\"window.history.go(-1);\">click here or use the back button in your browser to return to your message</a></div>");
            }

            return false;
        }

        if ($FurtherCheck) {

            // As of right now, we only check images,
            // we can skip everything, if it's disabled
            if (!$master->options->ImagesCheck || $master->request->user->class->Level >= $master->options->ImagesCheckMinClass) {
                return true;
            }

            // Max. number of images in posts
            $MaxImages = (int) $master->options->MaxImagesCount;

            // Check ount first
            if (count($this->displayed_images) > $MaxImages) {
                $Error  = "Your post contains too many images. (Max: $MaxImages)<br>";
                $Error .= "Try posting the direct links instead.";
                throw new BBCodeError($Error);
            }

            // Max. size for images in posts (MB)
            $MaxWeight = (int) $master->options->MaxImagesWeight;
            $MaxWeight = $MaxWeight * 1024 * 1024;

            $Validate = new \Luminance\Legacy\Validate();
            $post_size = $Validate->get_presentation_size(array_keys($this->displayed_images));
            if($post_size > $MaxWeight) {
                $post_size = round($post_size / 1024 / 1024, 2);
                $MaxWeight = round($MaxWeight / 1024 / 1024, 2);
                $Error  = "Your post contains too many images. (Weight: $post_size MB - Max: $MaxWeight MB)<br>";
                $Error .= "Try posting thumbnails instead or simply post the direct links.";
                throw new BBCodeError($Error);
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
    public function validate_imageurl($imageurl) {
        if (check_perms('site_skip_imgwhite')) return true;
        $whitelist_regex = get_whitelist_regex();
        return validate_imageurl($imageurl, 10, 511, $whitelist_regex, '');
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

        $regexes[] = $master->settings->main->internal_urls_regex;
        $regexes[] = $master->getRouteRegex();
        foreach ($regexes as $regex) {
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
        }
        return false;
    }


    public function strip_bbcode($str) {
        $str = display_str($str);
        $str = $this->parse($str);
        $str = $this->raw_text($str);
        $str = nl2br($str);
        return $str;
    }

    // how much readable text is in string
    public function text_count($str) {
        //remove tags
        $str = $this->db_clean_search($str);
        //remove endofline
        $str = str_replace(["\r\n", "\n", "\r"], '', $str);
        $str = trim($str);

        return mb_strlen($str);
    }

    // I took a shortcut here and made this function instead of using strip_bbcode
    // since it's purpose is a bit different.
    public function db_clean_search($str) {
        # Auto-populate smileys
        foreach (array_keys($this->Smileys) as $smiley) {
            # Just in case anyone includes a custom smiley that has regex characters in it
            $smiley = preg_quote($smiley);
            $remove[] = "/{$smiley}/i";
        }

        # Auto-populate Icons
        foreach (array_keys($this->Icons) as $icon) {
            # Just in case anyone includes a custom smiley that has regex characters in it
            $icon = preg_quote($icon);
            $remove[] = "/\[{$icon}\]/i";
        }

        $complexTags = [
            'flash',      'img',          'imgalt',
            'imgnm',      'banner',       'thumb',
            'h5v',        'audio',        'tex',
            'torrent',    'request',      'collage',
            'thread',     'forum',        'user',
            'staffpm',    'uploader',     'media',
            'url',        'video',
        ];

        # Auto-populate tags
        foreach ($complexTags as $tag) {
            $tag = preg_quote($tag);
            $remove[] = "/\[{$tag}=.*?\].?\[\/{$tag}.*?\]/i";
        }

        # Auto-populate tags
        foreach (self::$ValidTags as $tag => $attributes) {
            # Skip complex tags, they're already covered
            if (array_key_exists($tag, $complexTags)) {
                continue;
            }

            # Handle normal tags, with or without attributes
            $tag = preg_quote($tag);
            if ($attributes > 0) {
                $remove[] = "/\[(\/)?{$tag}\]/i";
            } else {
                $remove[] = "/\[(\/)?{$tag}=.*?\]/i";
            }
        }

        $str = preg_replace($remove, '', $str);
        $str = preg_replace('/[\r\n]+/', ' ', $str);

        return $str;
    }

    public function valid_url($str, $Extension = '', $Inline = false) {
        global $master;
        $valid_external = preg_match(getValidUrlRegex($Extension, $Inline), $str);
        $valid_route = preg_match($master->getRouteRegex(), $str);
        return $valid_external || $valid_route;
    }

    public function inlineTransform($str = '') {
        global $master;

        # Support markdown via regex replacements for BBCode tags
        $str = preg_replace('/(?<=^|\s)\=\=\=\=([^=\n]+?)\=\=\=\=(?=$|\s)/i', '[size=3]$1[/size]', $str);
        $str = preg_replace('/(?<=^|\s)\=\=\=([^=\n]+?)\=\=\=(?=$|\s)/i',     '[size=5]$1[/size]', $str);
        $str = preg_replace('/(?<=^|\s)\=\=([^=\n]+?)\=\=(?=$|\s)/i',         '[size=7]$1[/size]', $str);
        $str = preg_replace('/(?<=^|\s)\[([^\[\]]+)]\(([^()]+)\)(?=$|\s)/i',  '[url=$2]$1[/url]',  $str);
        $str = preg_replace('/(?<=^|\s)\*\*\*([^*\n]+?)\*\*\*(?=$|\s)/i',     '[b][i]$1[/i][/b]',  $str);
        $str = preg_replace('/(?<=^|\s)___([^*\n]+?)___(?=$|\s)/i',           '[b][i]$1[/i][/b]',  $str);
        $str = preg_replace('/(?<=^|\s)\*\*([^*\n]+?)\*\*(?=$|\s)/i',         '[b]$1[/b]',         $str);
        $str = preg_replace('/(?<=^|\s)__([^*\n]+?)__(?=$|\s)/i',             '[b]$1[/b]',         $str);
        $str = preg_replace('/(?<=^|\s)\*([^_\n]+?)\*(?=$|\s)/i',             '[i]$1[/i]',         $str);
        $str = preg_replace('/(?<=^|\s)_([^_\n]+?)_(?=$|\s)/i',               '[i]$1[/i]',         $str);
        $str = preg_replace('/(?<=^|\s)~~([^~\n]+?)~~(?=$|\s)/i',             '[s]$1[/s]',         $str);
        $str = preg_replace('/^-\s?([^-\n].*?)$/m',                           '[*]$1',             $str);
        $str = preg_replace('/^-{3,}$/m',                                     '[hr]',              $str);

        # Put [inlineurl] at the start of each URL
        $str = preg_replace('|http(s)?://|i', '[inlineurl]http$1://', $str);

        $callback = function($matches) {
            return str_replace("[inlineurl]","",$matches[0]);
        };
        # For markdown links, remove any [inlineurl] in the url tag
        $str = preg_replace_callback('/(?<=\[url=)(\[inlineurl\])/m', $callback, $str);

        # For anonymized links, remove any [inlineurl] in the middle of the link
        $str = preg_replace_callback('/(?<=\[inlineurl\])(\S*\[inlineurl\]\S*)/m', $callback, $str);

        # Static relative routes after anonymizer
        $str = preg_replace($master->getRouteRegex(), '[inlineurl]$1', $str);

        return $str;
    }

    /* How parsing works

      Parsing takes $str, breaks it into blocks, and builds it into $blocks.
      Blocks start at the beginning of $str, when the parser encounters a [, and after a tag has been closed.
      This is all done in a loop.

      EXPLANATION OF PARSER LOGIC

      1) Find the next tag (regex)
      1a) If there aren't any tags left, write everything remaining to a block and return (done parsing)
      1b) If the next tag isn't where the pointer is, write everything up to there to a text block.
      2) See if it's a [[wiki-link]] or an ordinary tag, and get the tag name
      3) If it's not a wiki link:
      3a) check it against the self::$ValidTags array to see if it's actually a tag and not [bullshit]
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

    public function parse($str) {
        $position = 0; // Pointer to keep track of where we are in $str
        $blockLength = strlen($str);
        $blocks = []; // Blocks on this level of the parse tree

        while ($position < $blockLength) {
            $block = '';

            // 1) Find the next tag (regex)
            // [name(=attribute)?]|[[wiki-link]]
            $isTag = preg_match($this->tagParserRegex, $str, $Tag, PREG_OFFSET_CAPTURE, $position);

            // 1a) If there aren't any tags left, write everything remaining to a block
            if (!$isTag) {
                # Perform the inline BBCode transformations, reset the position and then loop back round if any transformations occurred
                $str = $this->inlineTransform(substr($str, $position));
                $blockLength = strlen($str);
                $position = 0;

                # Check if we injected a new tag
                $isInlineTag = preg_match($this->tagParserRegex, $str, $Tag, PREG_OFFSET_CAPTURE, $position);

                if ($isInlineTag) {
                    # And now we need to re-parse this block
                    continue;
                } else {
                    # If there aren't any tags left, write everything remaining to a block
                    $blocks[] = $str;
                    break;
                }
            }

            // 1b) If the next tag isn't where the pointer is, write everything up to there to a text block.
            $TagPos = $Tag[0][1];
            if ($TagPos > $position) {
                $blocks = array_merge($blocks, $this->parse(substr($str, $position, $TagPos - $position)));
                $position = $TagPos;
            }

            // 2) See if it's a [[wiki-link]] or an ordinary tag, and get the tag name
            if (!empty($Tag[6][0])) { // Wiki-link
                $WikiLink = true;
                $TagName = substr($Tag[6][0], 2, -2);
                $Attrib = '';
            } else { // 3) If it's not a wiki link:
                $WikiLink = false;
                $attrOffset = 0;
                $attrIndex = 0;
                $TagName = '';

                if ($Tag[1][0] === '```') {
                    $TagName = $Tag[1][0];
                    $attrOffset = 0;
                    $attrIndex = 2;
                } else {
                    $TagName = strtolower(substr($Tag[4][0], 1));
                    $attrOffset = 1;
                    $attrIndex = 5;
                }

                //3a) check it against the self::$ValidTags array to see if it's actually a tag and not [bullshit]
                if (!isset(self::$ValidTags[$TagName])) {
                    $blocks[] = substr($str, $position, ($TagPos - $position) + strlen($Tag[0][0]));
                    $position = $TagPos + strlen($Tag[0][0]);
                    continue;
                }

                // Check if user is allowed to use moderator tags (different from Advanced, which is determined
                // by the original post author).
                // We're using ShowErrors as a proxy for figuring out if we're editing or just viewing
                if ($this->ShowErrors && in_array($TagName, $this->AdvancedTagOnly) && !check_perms('forum_moderate')) {
                    $this->Errors[] = "<span class=\"error_label\">illegal tag [$TagName]</span>";
                }

                $MaxAttribs = self::$ValidTags[$TagName];

                // 3b) Get the attribute, if it exists [name=attribute]
                if (!empty($Tag[$attrIndex][0])) {
                    $Attrib = substr($Tag[$attrIndex][0], $attrOffset);
                } else {
                    $Attrib = '';
                }
            }

            // 4) Move the pointer past the end of the tag
            $position = $TagPos + strlen($Tag[0][0]);

            // 5) Find out where the tag closes (beginning of [/tag])
            // Unfortunately, BBCode doesn't have nice standards like xhtml
            // [*] and http:// follow different formats
            // Thus, we have to handle these before we handle the majority of tags
            //5a) Different for different types of tag. Some tags don't close, others are weird like [*]
            if ($TagName == 'video' || $TagName == 'yt' || $TagName == 'vimeo') {
                $block = '';

            } elseif ($TagName == 'inlineurl') { // We did a big replace early on to turn http:// into [inlineurl]http://
                // Let's say the block can stop at a newline, a space or another BBCode tag
                $closeTag = strcspn($str, " \r\n[", $position);
                if ($closeTag === false) { // block finishes with URL
                    $closeTag = $blockLength;
                }
                # uwotm8?
                if (preg_match('/[!;,.?:]+$/', substr($str, $position, $closeTag), $match)) {
                    $closeTag -= strlen($match[0]);
                }
                $URL = substr($str, $position, $closeTag);
                if (substr($URL, -1) == ')' && substr_count($URL, '(') < substr_count($URL, ')')) {
                    $closeTag--;
                    $URL = substr($URL, 0, -1);
                }
                $block = $URL; // Get the URL
                // strcspn returns the number of characters after the offset $position, not after the beginning of the string
                // Therefore, we use += instead of the = everywhere else
                $position += $closeTag; // 5d) Move the pointer past the end of the [/close] tag.

            } elseif ($WikiLink == true || in_array($TagName, ['ratiolist', 'n', 'br', 'hr', 'cast', 'details', 'info', 'plot', 'screens', 'you'])) {
                // Don't need to do anything - empty tag with no closing

            } elseif ($TagName === '*') {
                // We're in a list. Find where it ends
                $NewLine = $position;
                do {
                    // Don't overrun
                    if ($NewLine == $blockLength) {
                        break;
                    }
                    // Look for \n[*]
                    $NewLine = strpos($str, "\n", $NewLine + 1);
                } while ($NewLine !== false && substr($str, $NewLine + 1, 3) == '[' . $TagName . ']');

                $closeTag = $NewLine;
                if ($closeTag === false) { // block finishes with list
                    $closeTag = $blockLength;
                }
                $block = substr($str, $position, $closeTag - $position); // Get the list
                $position = $closeTag; // 5d) Move the pointer past the end of the [/close] tag.

            } elseif ($TagName === '```') {
                $position++; // skip past newline
                $closeTag = null;
                // preg_match instead of stripos as markdown requires that code tag end at the start of a line
                $foundCloseTag = preg_match('/(?:^|\n)(```)/', $str, $closeTag, PREG_OFFSET_CAPTURE, $position);

                if ($foundCloseTag === 0) {                            // lets try and deal with badly formed bbcode in a better way
                    $positionstart = max($TagPos - 20, 0);
                    $positionend   = min($position + 20, $blockLength );
                    $errnum        = count($this->Errors);

                    $postlink = '<a class="postlink error" href="#err'.$errnum.'" title="scroll to error"><span class="postlink"></span></a>';

                    $this->Errors[] = "<span class=\"error_label\">unclosed $TagName markdown: $postlink</span><blockquote class=\"bbcode error\">..." . substr($str, $positionstart, $TagPos - $positionstart)
                            .'<code class="error">'.$Tag[0][0].'</code>'. substr($str, $position, $positionend - $position) .'... </blockquote>';

                    if ($this->ShowErrors) {
                        $block = $TagName;
                        $TagName = 'error';
                        $Attrib = $errnum;
                    } else {
                        $TagName = 'ignore'; // tells the parser to skip this empty tag
                    }
                    $closeTag = $position;
                    break;
                }
                $closetaglength = 0;

                # TODO this is occasionally producing warnings on array access, need to debug
                $closeTag = $closeTag[1][1] - 1;

                // 5c) Get the contents between [open] and [/close] and call it the block.
                $block = substr($str, $position, $closeTag - $position);

                // 5d) Move the pointer past the end of the [/close] tag.
                $closetaglength = strlen($TagName) + 1;
                $position = $closeTag + $closetaglength;

            } else {
                //5b) If it's a normal tag, it may have versions of itself nested inside
                $closeTag = $position - 1;
                $inTagPos = $position - 1;
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
                    $closeTag = stripos($str, '[/' . $TagName . ']', $closeTag + 1);
                    if ($closeTag === false) {
                        if ($TagName == '#' || $TagName == 'anchor') {
                            // automatically close open anchor tags (otherwise it wraps the entire text
                            // in an <a> tag which then get stripped from the end as they are way out of place and you have
                            // open <a> tags in the code - but links without any href so its a subtle break
                            $closeTag = $position;
                            $closetaglength = 0;
                        } elseif ($TagName == 'img') { // This handles the single [img=] tags
                            $block = true; // Bypass check below
                            $closeTag = $position;
                            $closetaglength = 0;
                        } else {
                            // lets try and deal with badly formed bbcode in a better way
                            $positionstart = max($TagPos- 20, 0);
                            $positionend   = min($position + 20, $blockLength);
                            $errnum        = count($this->Errors);

                            $postlink = '<a class="postlink error" href="#err'.$errnum.'" title="scroll to error"><span class="postlink"></span></a>';

                            $this->Errors[] = "<span class=\"error_label\">unclosed [$TagName] tag: $postlink</span><blockquote class=\"bbcode error\">..." . substr($str, $positionstart, $TagPos - $positionstart)
                                    .'<code class="error">'.$Tag[0][0].'</code>'. substr($str, $position, $positionend - $position) .'... </blockquote>';

                            if ($this->ShowErrors) {
                                $block = "[$TagName]";
                                $TagName = 'error';
                                $Attrib = $errnum;
                            } else {
                                $TagName = 'ignore'; // tells the parser to skip this empty tag
                            }
                            $closeTag = $position;
                            $closetaglength = 0;

                        }
                        break;
                    } else {
                        // Skip inner tag check for tags that don't process bbcode
                        if (in_array($TagName, ['code', 'codeblock', 'pre', 'plain'])) {
                            break;
                        }

                        $NumInCloses++; // Majority of cases
                    }

                    // Is there another open tag inside this one?
                    $openTag = preg_match($InOpenRegex, $str, $inTag, PREG_OFFSET_CAPTURE, $inTagPos + 1);
                    if (!$openTag || $inTag[0][1] > $closeTag) {
                        break;
                    } else {
                        $inTagPos = $inTag[0][1];
                        $NumInOpens++;
                    }
                } while ($NumInOpens > $NumInCloses);

                // Find the internal block inside the tag
                if (!$block) {
                    // 5c) Get the contents between [open] and [/close] and call it the block.
                    $block = substr($str, $position, $closeTag - $position);
                }

                // 5d) Move the pointer past the end of the [/close] tag.
                $position = $closeTag + $closetaglength;
            }

            // 6) Depending on what type of tag we're dealing with, create an array with the attribute and block.
            switch ($TagName) {
                case 'h5v': // html5 video tag
                    $blocks[] = ['Type' => 'h5v', 'Attr' => $Attrib, 'Val' => $block];
                    break;
                case 'video': // youtube, streamable and vimeo only
                case 'yt':
                case 'vimeo':
                    $blocks[] = ['Type' => 'video', 'Attr' => $Attrib, 'Val' => ''];
                    break;
                case 'flash':
                    $blocks[] = ['Type' => 'flash', 'Attr' => $Attrib, 'Val' => $block];
                    break;
                /* case 'link':
                  $blocks[] = ['Type'=>'link', 'Attr'=>$Attrib, 'Val'=>$this->parse($block)];
                  break; */
                case 'anchor':
                case '#':
                    $blocks[] = ['Type' => $TagName, 'Attr' => $Attrib, 'Val' => $this->parse($block)];
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
                    $blocks[] = ['Type' => $TagName, 'Val' => ''];
                    break;
                case 'font':
                    $blocks[] = ['Type' => 'font', 'Attr' => $Attrib, 'Val' => $this->parse($block)];
                    break;
                case 'center': // lets just swap a center tag for an [align=center] tag
                    $blocks[] = ['Type' => 'align', 'Attr' => 'center', 'Val' => $this->parse($block)];
                    break;
                case 'inlineurl':
                    $blocks[] = ['Type' => 'inlineurl', 'Attr' => $block, 'Val' => ''];
                    break;
                case 'url':
                    if (empty($Attrib)) { // [url]http://...[/url] - always set URL to attribute
                        $blocks[] = ['Type' => 'url', 'Attr' => $block, 'Val' => ''];
                    } else {
                        $blocks[] = ['Type' => 'url', 'Attr' => $Attrib, 'Val' => $this->parse($block)];
                    }
                    break;
                case 'quote':
                    $blocks[] = ['Type' => 'quote', 'Attr' => $Attrib, 'Val' => $this->parse($block)];
                    break;

                case 'imgnm':
                    $blocks[] = ['Type' => 'imgnm',  'Attr' => $Attrib, 'Val' => $block];
                    break;
                case 'imgalt':
                    $blocks[] = ['Type' => 'imgalt', 'Attr' => $Attrib, 'Val' => $block];
                    break;

                case 'img':
                case 'image':
                    if (is_bool($block)) $block = '';
                    if (empty($block)) {
                        $Elements = explode(',', $Attrib);
                        $block = end($Elements);
                        $Attrib = preg_replace('/,?'.preg_quote($block, '/').'/i', '', $Attrib);
                    }
                    $blocks[] = ['Type' => 'img', 'Attr' => $Attrib, 'Val' => $block];
                    break;
                case 'banner':
                case 'thumb':
                    if (empty($block)) {
                        $block = $Attrib;
                    }
                    $blocks[] = ['Type' => $TagName, 'Val' => $block];
                    break;
                case 'aud':
                case 'mp3':
                case 'audio':
                    if (empty($block)) {
                        $block = $Attrib;
                    }
                    $blocks[] = ['Type' => 'aud', 'Val' => $block];
                    break;

                case 'user':
                case 'torrent':
                case 'request':
                case 'collage':
                case 'thread':
                case 'forum':
                    $blocks[] = ['Type' => $TagName, 'Val' => $block];
                    break;

                case 'tex':
                    $blocks[] = ['Type' => 'tex', 'Val' => $block];
                    break;
                case 'pre':
                case 'plain':
                    $blocks[] = ['Type' => $TagName, 'Val' => $block];
                    break;

                case '```':
                case 'code':
                case 'codeblock':
                    $blocks[] = ['Type' => $TagName, 'Attr' => $Attrib, 'Val' => $block];
                break;

                case 'mediainfo':
                    $blocks[] = ['Type' => $TagName, 'Val' => $block];
                    break;

                case 'hide':
                    break; // not seen

                case 'spoiler':
                    $blocks[] = ['Type' => $TagName, 'Attr' => $Attrib, 'Val' => $this->parse($block)];
                    break;

                //case '#': using this for anchor short tag... not used on old emp so figure should be okay
                case '*':
                    $newBlock = [
                        'Type'      => 'list',
                        'Val'       => explode('[' . $TagName . ']', $block),
                        'ListType'  => $TagName === '*' ? 'ul' : 'ol',
                        'Tag'       => $TagName,
                    ];
                    foreach ($newBlock['Val'] as $Key => $Val) {
                        $newBlock['Val'][$Key] = $this->parse(trim($Val));
                    }
                    $blocks[] = $newBlock;
                    break;
                case 'n':
                case 'ignore': // not a tag but can be used internally
                    break; // n serves only to disrupt bbcode (backwards compatibility - use [pre])

                case 'error':  // not a tag but can be used internally
                    $blocks[] = ['Type' => 'error', 'Attr' => $Attrib, 'Val' => $block];
                    break;

                case 'geoip':
                    $blocks[] = ['Type' => $TagName, 'Val' => $block];
                    break;

                default:
                    if ($WikiLink == true) {
                        $blocks[] = ['Type' => 'wiki', 'Val' => $TagName];
                    } else {

                        // Basic tags, like [b] or [size=5]
                        if (isset($Attrib) && $MaxAttribs > 0) {
                            $blocks[] = ['Type' => $TagName, 'Attr' => $Attrib, 'Val' => $this->parse($block)];
                        } else {
                            $blocks[] = ['Type' => $TagName, 'Val' => $this->parse($block)];
                        }
                    }
            }
        }

        return $blocks;
    }

    public function get_allowed_colors() {
        static $ColorAttribs;
        if (!$ColorAttribs) { // only define it once per page
            // now with more colors!
            $ColorAttribs = ['aliceblue', 'antiquewhite', 'aqua', 'aquamarine', 'azure', 'beige', 'bisque', 'black', 'blanchedalmond', 'blue', 'blueviolet',
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
                'tomato','turquoise','violet','wheat','white','whitesmoke','yellow','yellowgreen'];
        }

        return $ColorAttribs;
    }

    public function is_color_attrib(&$Attrib) {
        global $master;

        $Att = strtolower($Attrib);
        $ShortClasses = $master->repos->permissions->getShortNames();

        // convert class names to class colors
        if (array_key_exists($Att, $ShortClasses)) {
            $Attrib = '#' . $ShortClasses[$Att]['Color'];
            $Att = strtolower($Attrib);
        }
        // if in format #rgb hex then return as is
        if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/', $Att)) return true;

        // check and capture #rgba format
        if (preg_match('/^#(?|([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})|([0-9a-f]{1})([0-9a-f]{1})([0-9a-f]{1})([0-9a-f]{1}))$/', $Att, $matches) ) {
            // capture #rgba hex and convert into rgba(r,g,b,a) format (from base 16 to base 10 0->255)
            for ($position=1;$position<4;$position++) {
                if (strlen($matches[$position])==1) $matches[$position] = "$matches[$position]$matches[$position]";
                $matches[$position] = base_convert($matches[$position], 16, 10);
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

    public function extract_attributes($Attrib, $MaxNumber=-1) {
        $Elements = [];
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
            }
        }

        return $Elements;
    }

    public function get_css_attributes($Attrib, $AllowMargin=true, $AllowColor=true, $AllowWidth=true, $AllowNoBorder=true, $AllowImage=true) {
        $InlineStyle = '';
        if (isset($Attrib) && $Attrib) {
            $attributes = explode(",", $Attrib);
            if ($attributes) {
                $InlineStyle = ' style="';
                foreach ($attributes as $att) {
                    if ($AllowColor && substr($att, 0, 9) == 'gradient:') {
                        $InlineStyle .= 'background: linear-gradient(';
                        $LinearArr = explode(';', substr($att, 9));
                        $LinearAttr = [];
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
                        $validURL = $this->validate_imageurl($att);
                        if($this->ShowErrors && $validURL !== true) {
                            $this->Errors[] = "<span class=\"error_label\">{$validURL}:</span><blockquote class=\"bbcode error\">".'<code class="error">'.$att.'</code></blockquote>';
                            break;
                        }
                        $this->displayed_images[$att] = true;
                        $escapees = ["'",   '"',  "(",  ")",  " "];
                        $escaped  = ["\'", '\"', "\(", "\)", "\ "];
                        $sanitisedurl = str_replace($escapees, $escaped, $att);
                        $InlineStyle .= "background-image: url(".$sanitisedurl.");";
                        //$InlineStyle .= "background: url('$att') no-repeat center center;";

                    } elseif ($AllowWidth && preg_match('/^([0-9]+?)px$/', $att, $matches)) {
                        if ((int) $matches[1] > 920) $matches[1] = '920';
                        $InlineStyle .= 'width:' . $matches[1] . 'px;';

                    } elseif ($AllowWidth && preg_match('/^([0-9]{1,3})%?$/', $att, $matches)) {
                        if ((int) $matches[1] > 100) $matches[1] = '100';
                        $InlineStyle .= 'width:' . $matches[1] . '%;';

                    } elseif ($AllowMargin && in_array($att, ['left', 'center', 'right'])) {
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
                    } elseif ($AllowNoBorder && in_array($att, ['nball', 'nb', 'noborder'])) {
                        $InlineStyle .= 'border:none;';
                    } elseif ($AllowMargin && in_array($att, ['nopad'])) {
                        $InlineStyle .= 'padding:0px;';
                    }
                }
                $InlineStyle .= '"';
            }
        }

        return $InlineStyle;
    }

    public function get_css_classes($Attrib, $matchClasses) {
        if ($Attrib == '') return '';
        $classes='';
        foreach ($matchClasses as $class) {
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

    public function remove_text_between_tags($array, $matchTagRegex = false) {
        foreach ($array as $position => $value) {
            if (is_string($array[$position])) {
                $array[$position] = '';
            } elseif ($matchTagRegex !== false && !preg_match($matchTagRegex, $array[$position]['Type'])) {
                $array[$position] = '';
            }
        }

        return $array;
    }

    public function get_size_attributes($Attrib) {
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

    public function remove_anon($url) {
        $anonurl = (defined('ANONYMIZER_URL') ? ANONYMIZER_URL : 'http://anonym.es/?');
        return str_replace($anonurl, '', $url);
    }

    public function anon_url($url) {
        global $master;
        if (preg_match($master->settings->main->non_anon_urls_regex, $url)) {
            return $url;
        }
        return (defined('ANONYMIZER_URL') ? ANONYMIZER_URL : 'http://anonym.es/?').$url;
    }

    public function to_html($Array) {
        global $master;
        $this->Levels++;
        # Hax prevention: execution limit
        if ($this->Levels > 20) return;
        $str = '';

        foreach ((array)$Array as $block) {
            if (is_string($block)) {
                $str.=$this->smileys($block);
                continue;
            }
            if (is_array($block) && !array_key_exists('Type', $block) && count($block) == 1) {
                # frustrating, but this happens because of reparsing for regex transformations
                $str .= $this->to_html($block);
            }
            switch ($block['Type']) {
                case 'article': // link to article
                    $LocalURL = $this->local_url($block['Attr']);
                    if ($LocalURL && preg_match('#^/articles\.php.*[\?&]topic=(.*)#i', $LocalURL)) {
                        $str .= $this->articleTag($block['Attr']);
                    } else if (!empty($block['Attr']) && preg_match('/^[a-z0-9\-\_.()\@&]+$/', strtolower($block['Attr'])))
                        $str.='<a class="bbcode article" href="/articles/view/' .strtolower($block['Attr']). '">' . $this->to_html($block['Val']) . '</a>';
                    else
                        $str.='[article='. $block['Attr'] . ']' . $this->to_html($block['Val']) . '[/article]';
                    break;
                case 'mediainfo': // mediainfo block
                    $MediaInfo = new MediaInfo;
                    // HTML cleanup for MediaInfo
                    $NFO = html_entity_decode($block['Val']);
                    $NFO = str_replace("\xc2\xa0",' ', $NFO);
                    $MediaInfo->parse($NFO);
                    $str.=$MediaInfo->output;
                    break;
                case 'tip': // a tooltip
                    if (!empty($block['Attr']))
                        $str.='<span class="bbcode tooltip" title="' .display_str($block['Attr']) . '">' . $this->to_html($block['Val']) . '</span>';
                    else
                        $str.='[tip='. $block['Attr'] . ']' . $this->to_html($block['Val']) . '[/tip]';
                    break;
                case 'quote':
                    $this->NoMedia++; // No media elements inside quote tags
                    if (!empty($block['Attr'])) {
                        // [quote=name,[F|T|R|C]number1,number2]
                        $quoteAttrs = explode(",", $block['Attr']);
                        if (count($quoteAttrs) >= 3) {
                            list($qname, $qID1, $qID2) = $quoteAttrs;
                        } else {
                            $qname = $block['Attr'];
                            $qID1 = null;
                            $qID2 = null;
                        }
                        $postlink = '';
                        if ($qID1) {  // if we have numbers
                            $qType = substr($qID1, 0, 1); /// F or T or C or R (forums/torrents/collags/requests)
                            $qID1 = substr($qID1, 1);
                            if (in_array($qType, ['f', 't', 'c', 'r']) && is_integer_string($qID1) && is_integer_string($qID2)) {
                                switch ($qType) {
                                    case 'f':
                                        $postlink = '<a class="postlink" href="/forum/thread/' . $qID1 . '?postid=' . $qID2 . '#post' . $qID2 . '"><span class="postlink"></span></a>';
                                        break;
                                    case 't':
                                        $postlink = '<a class="postlink" href="/torrents.php?id=' . $qID1 . '&postid=' . $qID2 . '#post' . $qID2 . '"><span class="postlink"></span></a>';
                                        break;
                                    case 'c':
                                        $postlink = '<a class="postlink" href="/collage/' . $qID1 . '?postid=' . $qID2 . '#post' . $qID2 . '"><span class="postlink"></span></a>';
                                        break;
                                    case 'r':
                                        $postlink = '<a class="postlink" href="/requests.php?action=view&id=' . $qID1 . '#post' . $qID2 . '"><span class="postlink"></span></a>';
                                        break;
                                }
                            }
                        }
                        $str.= '<span class="quote_label"><strong>' . display_str($qname) . '</strong>: ' . $postlink . '</span>';
                    }
                    $str.='<blockquote class="bbcode">' . $this->to_html($block['Val']) . '</blockquote>';
                    $this->NoMedia--;
                    break;
                case 'error': // used internally to display bbcode errors in preview
                    // haha, a legitimate use of the blink tag (!)
                    $str.="<a id=\"err{$block['Attr']}\"></a><blink><code class=\"error\" title=\"You have an unclosed {$block['Val']} tag in your bbCode!\">{$block['Val']}</code></blink>";
                    break;
                case 'geoip':
                    if ($this->Advanced) {
                        $str .= $master->render->geoip($block['Val']);
                    } else {
                        $str .= "[geoip]{$block['Val']}[/geoip]";
                    }
                    break;
                case 'you':
                    if ($this->Advanced) {
                        $str.='<a href="/user.php?id=' . $master->request->user->ID . '">' . $master->request->user->Username . '</a>';
                    } else {
                        $str.='[you]';
                    }
                    break;
                case 'video':
                    // Supports youtube, vimeo and streamable for now.
                    $validURL = $this->validate_imageurl($block['Attr']);
                    if($this->ShowErrors && $validURL !== true) {
                        $this->Errors[] = "<span class=\"error_label\">{$validURL}:</span><blockquote class=\"bbcode error\">".'<code class="error">'.$block['Attr'].'</code></blockquote>';
                        break;
                    }

                    $videoUrl = null;
                    $YoutubeID = null;

                    if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $block['Attr'], $matches))
                        $YoutubeID = $matches[1];
                    elseif (preg_match('/^https?:\/\/vimeo.com\/([0-9]+)$/i', $block['Attr'], $matches))
                        $videoUrl = 'https://player.vimeo.com/video/'.$matches[1];
                    elseif(preg_match('/^https?:\/\/streamable.com\/([0-9a-zA-Z]+)$/i', $block['Attr'], $matches))
                        $videoUrl = 'https://streamable.com/s/'.$matches[1];

                    if ($this->NoMedia > 0) {
                        $str .= '<a rel="noreferrer" target="_blank" href="' . $videoUrl . '">' . $videoUrl . '</a> (video)';
                        break;
                    }
                    else {
                        if (!empty($videoUrl))
                            $str.='<iframe class="bb_video" src="'.$videoUrl.'" allowfullscreen></iframe>';
                        elseif (!empty($YoutubeID))
                            $str.='<div class="youtube" data-embed="'.$YoutubeID.'"><div class="play-button"></div></div>';
                        else
                            $str.='[video=' . $block['Attr'] . ']';
                    }
                    break;
                case 'h5v':
                    // html5 video tag
                    $Attributes= $this->extract_attributes($block['Attr'], 920);
                    if (!empty($block['Val'])) {
                        $validURL = $this->validate_imageurl($block['Val']);
                        if($this->ShowErrors && $validURL !== true) {
                            $this->Errors[] = "<span class=\"error_label\">{$validURL}:</span><blockquote class=\"bbcode error\">".'<code class="error">'.$block['Val'].'</code></blockquote>';
                            break;
                        }
                    }
                    if (!empty($block['Attr'])) {
                        $validURL = $this->validate_imageurl($block['Attr']);
                        if($this->ShowErrors && $validURL !== true) {
                            $this->Errors[] = "<span class=\"error_label\">{$validURL}:</span><blockquote class=\"bbcode error\">".'<code class="error">'.$block['Attr'].'</code></blockquote>';
                            break;
                        }
                    }
                    if ( ($block['Attr'] != '' && count($Attributes)==0) || $block['Val'] == '' ) {
                        $str.='[h5v' . ($block['Attr'] != ''?'='. $block['Attr']:'')  . ']' . $this->to_html($block['Val']) . '[/h5v]';
                    } else {
                        $Sources = explode(',', $block['Val']);

                        if ($this->NoMedia > 0) {
                            foreach ($Sources as $Source) {
                                $videoUrl = str_replace('[inlineurl]', '', $Source);
                                $str .= '<a rel="noreferrer" target="_blank" href="' . $videoUrl . '">' . $videoUrl . '</a> (video)';
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

                            if (empty($parameters)) {
                                $str .= '<video controls>';
                            } else {
                                $str .= '<video '.$parameters.' controls>';
                            }
                            foreach ($Sources as $Source) {
                                $lastdot = strripos($Source, '.');
                                $mime = substr($Source, $lastdot+1);
                                if($mime=='ogv')$mime='ogg'; // all others are same as ext (webm=webm, mp4=mp4, ogg=ogg)
                                $str .= '<source src="'. str_replace('[inlineurl]', '', $Source).'" type="video/'.$mime.'">';
                            }
                            $str .= 'Your browser does not support the html5 video tag. Please upgrade your browser.</video>';
                        }
                    }
                    break;
                case 'flash':
                    $validURL = $this->validate_imageurl($block['Attr']);
                    if($this->ShowErrors && $validURL !== true) {
                        $this->Errors[] = "<span class=\"error_label\">{$validURL}:</span><blockquote class=\"bbcode error\">".'<code class="error">'.$block['Attr'].'</code></blockquote>';
                        break;
                    }
                    // note: as a non attribute the link has been auto-formatted as [inlinelink]link.url
                    if (($block['Attr'] != '' && !preg_match('/^([0-9]{2,4})\,([0-9]{2,4})$/', $block['Attr'], $matches))
                            || strpos($block['Val'], '[inlineurl]') === FALSE) {
                        $str.='[flash=' . ($block['Attr'] != ''?'='. $block['Attr']:'') . ']' . $this->to_html($block['Val']) . '[/flash]';
                    } else {
                        if ($block['Attr'] == '' || count($matches) < 3) {
                            if (!$matches[1])
                                $matches[1] = 500;
                            if (!$matches[2])
                                $matches[2] = $matches[1];
                        }
                        $block['Val'] = str_replace('[inlineurl]', '', $block['Val']);

                        if ($this->NoMedia > 0)
                            $str .= '<a rel="noreferrer" target="_blank" href="' . $block['Val'] . '">' . $block['Val'] . '</a> (flash)';
                        else
                            $str .= '<object classid="clsid:D27CDB6E-AE6D-11CF-96B8-444553540000" codebase="http://active.macromedia.com/flash2/cabs/swflash.cab#version=5,0,0,0" height="' . $matches[2] . '" width="' . $matches[1] . '"><param name="movie" value="' . $block['Val'] . '"><param name="play" value="false"><param name="loop" value="false"><param name="quality" value="high"><param name="allowScriptAccess" value="never"><param name="allowNetworking" value="internal"><embed  type="application/x-shockwave-flash" pluginspage="http://www.macromedia.com/shockwave/download/index.cgi?P1_Prod_Version=ShockwaveFlash" play="false" loop="false" quality="high" allowscriptaccess="never" allownetworking="internal"  src="' . $block['Val'] . '" height="' . $matches[2] . '" width="' . $matches[1] . '"><param name="wmode" value="transparent"></object>';
                    }
                    break;

                case 'url':
                    // Make sure the URL has a label
                    if (empty($block['Val'])) {
                        $block['Val'] = $block['Attr'];
                        $NoName = true; // If there isn't a Val for this
                    } else {
                        $block['Val'] = $this->to_html($block['Val']);
                        $NoName = false;
                    }
                    //remove the local host/anonym.es from address if present
                    $block['Attr'] = $this->remove_anon($block['Attr']);
                    // first test if is in format /local.php or #anchorname
                    if (preg_match('/^#[a-zA-Z0-9\-\_.,%\@~&=:;()+*\^$!#|]+$|^\/[a-zA-Z0-9\-\_.,%\@~&=:;()+*\^$!#|]+\.php[a-zA-Z0-9\?\-\_.,%\@~&=:;()+*\^$!#|]*$/', $block['Attr'])) {
                        // a local link or anchor link
                        $str.='<a class="link" href="' . $block['Attr'] . '">' . $block['Val'] . '</a>';
                    } elseif (!$this->valid_url($block['Attr'])) {
                        // not a valid tag
                        $str.='[url=' . $block['Attr'] . ']' . $block['Val'] . '[/url]';
                    } else {
                        $LocalURL = $this->local_url($block['Attr']);
                        if ($LocalURL) {
                            if ($NoName) {
                                $block['Val'] = substr($LocalURL, 1);
                            }
                            $str.='<a href="' . $LocalURL . '">' . $block['Val'] . '</a>';
                        } else {
                            $target = '';
                            if (!$master->request->user->options('NotForceLinks')) {
                                $target = 'target="_blank" ';
                            }
                            $str.='<a rel="noreferrer" ' . $target . 'href="' . $this->anon_url($block['Attr']) . '">' . $block['Val'] . '</a>';
                        }
                    }
                    break;

                case 'anchor':
                case '#':
                    if (!preg_match('/^[a-zA-Z0-9\-\_]+$/', $block['Attr'])) {
                        $str.='[' . $block['Type'] . '=' . $block['Attr'] . ']' . $this->to_html($block['Val']) . '[/' . $block['Type'] . ']';
                    } else {
                        $str.='<a class="anchor" id="' . $block['Attr'] . '">' . $this->to_html($block['Val']) . '</a>';
                    }
                    break;


                case 'mcom':
                    $positionnnerhtml = $this->to_html($block['Val']);
                    while (ends_with($positionnnerhtml, "\n")) {
                        $positionnnerhtml = substr($positionnnerhtml, 0, -strlen("\n"));
                    }
                    $str.='<div class="modcomment">' . $positionnnerhtml . '<div class="after">[ <a href="/articles/view/tutorials">Help</a> | <a href="/articles/view/rules">Rules</a> ]</div><div class="clear"></div></div>';
                    break;

                case 'table':
                    $InlineStyle = $this->get_css_attributes($block['Attr']);
                    if ($InlineStyle === FALSE) {
                        $str.='[' . $block['Type'] . '=' . $block['Attr'] . ']' . $this->to_html($block['Val']) . '[/' . $block['Type'] . ']';
                    } else {
                        $block['Val'] = $this->remove_text_between_tags($block['Val'], "/^tr$/");
                        $tableclass = $this->get_css_classes($block['Attr'], [['nball','noborder'],'nopad','vat','vam','vab']);
                        $str.='<table class="bbcode' . $tableclass . '"' . $InlineStyle . '><tbody>' . $this->to_html($block['Val']) . '</tbody></table>';
                    }
                    break;
                case 'tr':
                    $InlineStyle = $this->get_css_attributes($block['Attr'], false, true, false, true);

                    if ($InlineStyle === FALSE) {
                        $str.='[' . $block['Type'] . '=' . $block['Attr'] . ']' . $this->to_html($block['Val']) . '[/' . $block['Type'] . ']';
                    } else {
                        $block['Val'] = $this->remove_text_between_tags($block['Val'], "/^th$|^td$/");
                        $tableclass = $this->get_css_classes($block['Attr'], ['nopad']);
                        $str.='<' . $block['Type'] . ' class="bbcode'.$tableclass.'"' . $InlineStyle . '>' . $this->to_html($block['Val']) . '</' . $block['Type'] . '>';
                    }
                    break;
                case 'th':
                case 'td':
                    $InlineStyle = $this->get_css_attributes($block['Attr'], false);
                    if ($InlineStyle === FALSE) {
                        $str.='[' . $block['Type'] . '=' . $block['Attr'] . ']' . $this->to_html($block['Val']) . '[/' . $block['Type'] . ']';
                    } else {
                        $tableclass = $this->get_css_classes($block['Attr'], ['nopad','vat','vam','vab']);
                        $str.='<'. $block['Type'] .' class="bbcode'.$tableclass.'"' . $InlineStyle . '>' . $this->to_html($block['Val']) . '</' . $block['Type'] . '>';
                    }
                    break;

                case 'bg':
                    $InlineStyle = $this->get_css_attributes($block['Attr'], true, true, true, false);
                    if (!$InlineStyle || $InlineStyle == '') {
                        $str.='[bg=' . $block['Attr'] . ']' . $this->to_html($block['Val']) . '[/bg]';
                    } else {
                        $tableclass = $this->get_css_classes($block['Attr'], ['nopad']);
                        $str.='<div class="bbcode'.$tableclass.'"' . $InlineStyle . '>' . $this->to_html($block['Val']) . '</div>';
                    }
                    break;

                case 'cast':
                case 'details':
                case 'info':
                case 'plot':
                case 'screens': // [cast] [details] [info] [plot] [screens]
                    if (!isset($this->Icons[$block['Type']])) {
                        $str.='[' . $block['Type'] . ']';
                    } else {
                        $str.= $this->Icons[$block['Type']];
                    }
                    break;
                case 'br':
                    $str.='<br />';
                    break;
                case 'hr':
                    $str.='<hr />';
                    break;
                case 'font':
                    if (!isset($this->Fonts[$block['Attr']])) {
                        $str.='[font=' . $block['Attr'] . ']' . $this->to_html($block['Val']) . '[/font]';
                    } else {
                        $str.='<span style="font-family: ' . $this->Fonts[$block['Attr']] . '">' . $this->to_html($block['Val']) . '</span>';
                    }
                    break;
                case 'b':
                    $str.='<strong>' . $this->to_html($block['Val']) . '</strong>';
                    break;
                case 'u':
                    $str.='<span style="text-decoration: underline;">' . $this->to_html($block['Val']) . '</span>';
                    break;
                case 'i':
                    $str.='<em>' . $this->to_html($block['Val']) . "</em>";
                    break;
                case 's':
                    $str.='<span style="text-decoration: line-through">' . $this->to_html($block['Val']) . '</span>';
                    break;
                case 'sup':
                    $str.='<sup>' . $this->to_html($block['Val']) . '</sup>';
                    break;
                case 'sub':
                    $str.='<sub>' . $this->to_html($block['Val']) . '</sub>';
                    break;
                case 'important':
                    $str.='<strong class="important_text">' . $this->to_html($block['Val']) . '</strong>';
                    break;
                case 'uploader':
                    $str .= $this->uploaderTag($block['Val'], $block['Attr']);
                    break;
                case 'user':
                    $str .= $this->userTag($block['Val']);
                    break;
                case 'torrent':
                    $str .= $this->torrentTag($block['Val']);
                    break;
                case 'request':
                    $str .= $this->requestTag($block['Val']);
                    break;
                case 'collage':
                    $str .= $this->collageTag($block['Val']);
                    break;
                case 'thread':
                    $str .= $this->threadTag($block['Val']);
                    break;
                case 'forum':
                    $str .= $this->forumTag($block['Val']);
                    break;
                case 'staffpm':
                    $str .= $this->staffPMTag($block['Val']);
                    break;
                case 'tex':
                    $str.='[tex]'.$block['Val'].'[/tex]';
                    break;
                case 'plain':
                    $str.=$block['Val'];
                    break;
                case 'pre':
                    $str.='<pre>' . $block['Val'] . '</pre>';
                    break;
                case '```':
                case 'code':
                    $CSS = 'bbcode';
                    $Lang = $this->prism_supported($block['Attr']);
                    if (!empty($Lang)) $CSS .= ' '.$Lang;
                    if (strpos($block['Val'], "\n") === false) {
                        $str.='<code class="'.$CSS.'">' . $block['Val'] . '</code>';
                    } else {
                        $str.='<pre class="bbcodeblock"><code class="'.$CSS.'">' . $block['Val'] . '</code></pre>';
                    }
                    break;
                case 'codeblock':
                    $CSS = 'bbcodeblock';
                    $Lang = $this->prism_supported($block['Attr']);
                    if(!empty($Lang)) $CSS .= ' '.$Lang;
                    $str.='<pre class="bbcodeblock"><code class="'.$CSS.'">' . $block['Val'] . '</code></pre>';
                    break;
                case 'list':
                    $str .= '<' . $block['ListType'] . '>';
                    foreach ($block['Val'] as $Line) {

                        $str.='<li>' . $this->to_html($Line) . '</li>';
                    }
                    $str.='</' . $block['ListType'] . '>';
                    break;
                case 'align':
                    $ValidAttribs = ['left', 'center', 'justify', 'right'];
                    if (!in_array($block['Attr'], $ValidAttribs)) {
                        $str.='[align=' . $block['Attr'] . ']' . $this->to_html($block['Val']) . '[/align]';
                    } else {
                        $extraCSS = '';
                        if ($block['Attr'] == 'left') {
                                              $extraCSS = 'margin-right: auto;';
                        } elseif ($block['Attr'] == 'center') {
                                              $extraCSS = 'margin-right: auto; margin-left: auto;';
                        } elseif ($block['Attr'] == 'justify') {
                                              $extraCSS = 'margin-right: auto; margin-left: auto;';
                        } elseif ($block['Attr'] == 'right') {
                            $extraCSS = 'margin-left: auto;';
                        }
                        $str.='<div style="text-align:' . $block['Attr'] . ';' . $extraCSS . '">' . $this->to_html($block['Val']) . '</div>';
                    }
                    break;
                case 'color':
                case 'colour':
                    if (!$this->is_color_attrib($block['Attr'])) {
                        $str.='[color=' . $block['Attr'] . ']' . $this->to_html($block['Val']) . '[/color]';
                    } else {
                        $str.='<span style="color:' . $block['Attr'] . '">' . $this->to_html($block['Val']) . '</span>';
                    }
                    break;
                case 'rank':
                    if (!$this->is_color_attrib($block['Attr'])) {
                        $str.='[rank=' . $block['Attr'] . ']' . $this->to_html($block['Val']) . '[/rank]';
                    } else {
                        $str.='<span style="font-weight:bold;color:' . $block['Attr'] . ';">' . $this->to_html($block['Val']) . '</span>';
                    }
                    break;
                case 'size':
                    $ValidAttribs = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10'];
                    if (!in_array($block['Attr'], $ValidAttribs)) {
                        $str.='[size=' . $block['Attr'] . ']' . $this->to_html($block['Val']) . '[/size]';
                    } else {
                        $str.='<span class="size' . $block['Attr'] . '">' . $this->to_html($block['Val']) . '</span>';
                    }
                    break;
                case 'hide':
                    $str.='<strong>' . (($block['Attr']) ? $block['Attr'] : 'Hidden text') . '</strong>: <a href="javascript:void(0);" onclick="BBCode.spoiler(this);">Show</a>';
                    $str.='<blockquote class="hidden spoiler">' . $this->to_html($block['Val']) . '</blockquote>';
                    break;
                case 'spoiler':
                    $str.='<strong>' . (($block['Attr']) ? $block['Attr'] : 'Hidden text') . '</strong>: <a href="javascript:void(0);" onclick="BBCode.spoiler(this);">Show</a>';
                    $str.='<blockquote class="hidden spoiler">' . $this->to_html($block['Val']) . '</blockquote>';
                    break;

                case 'img':
                case 'imgnm':
                case 'imgalt':
                case 'banner':
                case 'spimg':
                case 'spimgnm':
                case 'spimgalt':
                case 'spbanner':

                    $block['Val'] = str_replace('[inlineurl]', '', $block['Val']);
                    $cssclass = "";

                    // Images with resize attributes
                    $resize = '';
                    if (($block['Type'] == 'img' || $block['Type'] == 'spimg') && !empty($block['Attr'])) {
                        $Elements = explode(',', $block['Attr']);
                        // Width
                        if (!empty($Elements[0]))
                            $resize .= 'width="'.intval($Elements[0]).'" ';
                        // Height
                        if (!empty($Elements[1]))
                            $resize .= 'height="'.intval($Elements[1]).'" ';
                    }

                    if ($block['Type'] == 'imgnm' || $block['Type'] == 'spimgnm') $cssclass .= ' nopad';
                    if (!empty($block['Attr']) && in_array($block['Type'], ['imgnm', 'imgalt', 'spimgnm', 'spimgalt']) ) $alttext = $block['Attr'];
                    else $alttext = $block['Val'];

                    if (preg_match('/^(\/[a-zA-Z0-9\-\_.,%\@~&=:;()+*\^$!#|]+)+[a-zA-Z0-9\?\-\_.,%\@~&=:;()+*\^$!#|]*$/', $block['Val'])) {
                        // a local link or anchor link
                        $str.='<img class="bbcode scale_image'.$cssclass.'" onclick="lightbox.init(this,500);" alt="'.$alttext.'" src="'.$block['Val'].'" />';
                        break;

                    } elseif (!$this->valid_url($block['Val'])) {
                        # Invalid URL, just reconstruct the tag and display it as text
                        if (array_key_exists('Attr', $block)) {
                            $str.="[{$block['Type']}={$block['Attr']}]{$block['Val']}[/{$block['Type']}]";
                        } else {
                            $str.="[{$block['Type']}]{$block['Val']}[/{$block['Type']}]";
                        }
                        break;
                    }
                    $LocalURL = $this->local_url($block['Val']);
                    $validURL = $this->validate_imageurl($block['Val']);
                    if($this->ShowErrors && $validURL !== true) {
                        $this->Errors[] = "<span class=\"error_label\">{$validURL}:</span><blockquote class=\"bbcode error\">".'<code class="error">'.$block['Val'].'</code></blockquote>';
                        break;
                    }
                    $this->displayed_images[$block['Val']] = true;

                    if (!$LocalURL && $this->NoMedia > 0) {
                        $str.='<a rel="noreferrer" target="_blank" href="' . $block['Val'] . '">' . $block['Val'] . '</a> (image)';
                        break;
                    }
                    $block['Val'] = $this->proxify_url($block['Val']);
                    // If the img is inside of a spoiler tag (spimg) load the src into the data-src instead
                    $str.='<img class="bbcode scale_image'.$cssclass.'" onclick="lightbox.init(this,500);" alt="'.$alttext.'" '.(in_array($block['Type'], ['spimg', 'spimgnm', 'spimgalt', 'spbanner']) ? 'data-' : '').'src="'.$block['Val'].'" '.$resize.'/>';
                    break;

                case 'thumb':
                    if ($this->NoMedia > 0 && $this->valid_url($block['Val'])) {
                        $str.='<a rel="noreferrer" target="_blank" href="' . $block['Val'] . '">' . $block['Val'] . '</a> (image)';
                        break;
                    }
                    $validURL = $this->validate_imageurl($block['Val']);
                    if($this->ShowErrors && $validURL !== true) {
                        $this->Errors[] = "<span class=\"error_label\">{$validURL}:</span><blockquote class=\"bbcode error\">".'<code class="error">'.$block['Val'].'</code></blockquote>';
                        break;
                    }
                    if (!$this->valid_url($block['Val'])) {
                        $str.='[thumb]' . $block['Val'] . '[/thumb]';
                    } else {
                        $str.='<img class="bbcode thumb_image" onclick="lightbox.init(this,300);" alt="' . $block['Val'] . '" src="' . $block['Val'] . '" />';
                    }
                    $this->displayed_images[$block['Val']] = true;
                    break;

                case 'audio':
                    if ($this->NoMedia > 0 && $this->valid_url($block['Val'])) {
                        $str.='<a rel="noreferrer" target="_blank" href="' . $block['Val'] . '">' . $block['Val'] . '</a> (audio)';
                        break;
                    }
                    $validURL = $this->validate_imageurl($block['Val']);
                    if($this->ShowErrors && $validURL !== true) {
                        $this->Errors[] = "<span class=\"error_label\">{$validURL}:</span><blockquote class=\"bbcode error\">".'<code class="error">'.$block['Val'].'</code></blockquote>';
                        break;
                    }
                    if (!$this->valid_url($block['Val'], '\.(mp3|ogg|wav)')) {
                        $str.='[aud]' . $block['Val'] . '[/aud]';
                    } else {
                        //TODO: Proxy this for staff?
                        $str.='<audio controls="controls" src="' . $block['Val'] . '"><a rel="noreferrer" target="_blank" href="' . $block['Val'] . '">' . $block['Val'] . '</a></audio>';
                    }
                    break;

                case 'inlineurl':
                    $block['Attr'] = $this->remove_anon($block['Attr']);
                    if (!$this->valid_url($block['Attr'], '', true)) {
                        $Array = $this->parse($block['Attr']);
                        $block['Attr'] = $Array;
                        $str.=$this->to_html($block['Attr']);
                    } else {
                        $LocalURL = $this->local_url($block['Attr']);
                        if ($LocalURL) {
                            if (preg_match('#^/articles/view/(.*)#i', $LocalURL)) {
                                $str .= $this->articleTag($LocalURL);
                            } else if (preg_match('#^/torrents\.php(?!.*type=|.*action=).*[\?&](?:id|torrentid)=(\d+)#i', $LocalURL)) {
                                $str .= $this->torrentTag($LocalURL);
                            } else if (preg_match('#^/details\.php(?!.*type=|.*action=).*[\?&](?:id|torrentid)=(\d+)#i', $LocalURL)) {
                                $str .= $this->torrentTag($LocalURL);
                            } else if (preg_match('#^/requests\.php\?.*view.*id=(\d+)#i', $LocalURL)) {
                                $str .= $this->requestTag($LocalURL);
                            } else if (preg_match('#^/collage/(\d+)#i', $LocalURL)) {
                                $str .= $this->collageTag($LocalURL);
                            } else if (preg_match('#^/forum/thread/(\d+)#i', $LocalURL)) {
                                $str .= $this->threadTag($LocalURL);
                            } else if (preg_match('#^/forum/(\d+)#i', $LocalURL)) {
                                $str .= $this->forumTag($LocalURL);
                            } else if (preg_match('#^/user\.php.*[\?&](.*)id=(\d+)#i', $LocalURL)) {
                                $str .= $this->userTag($LocalURL);
                            } else if (preg_match('#^/staffpm\.php.*[\?&](.*)id=(\d+)#i', $LocalURL)) {
                                $str .= $this->staffPMTag($LocalURL);
                            } else {
                                $str.='<a href="' . $LocalURL . '">' . substr($LocalURL, 1) . '</a>';
                            }
                        } else {
                            if (!$master->request->user->options('NotForceLinks')) {
                                $target = 'target="_blank" ';
                            } else {
                                $target = '';
                            }
                            $str.='<a rel="noreferrer" ' . $target .'href="' . $this->anon_url($block['Attr']) . '">' . $block['Attr'] . '</a>';
                        }
                    }
                    break;

                case 'ratiolist':
                    if (!$this->Advanced)
                        $str.= '[ratiolist]';
                    else {

                    $table = '<table>
                      <tr class="colhead">
                            <td>Amount downloaded</td>
                            <td>Required ratio (0% seeded)</td>
                            <td>Required ratio (100% seeded)</td>
                      </tr>
                      <tr class="row'.($master->request->user->Downloaded < 5*1024*1024*1024?'a':'b').'">
                            <td>0-5GB</td>
                            <td>0.00</td>
                            <td>0.00</td>
                      </tr>
                      <tr class="row'.($master->request->user->Downloaded >= 5*1024*1024*1024 && $master->request->user->Downloaded < 10*1024*1024*1024?'a':'b').'">
                            <td>5-10GB</td>
                            <td>0.10</td>
                            <td>0.00</td>
                      </tr>
                      <tr class="row'.($master->request->user->Downloaded >= 10*1024*1024*1024 && $master->request->user->Downloaded < 20*1024*1024*1024?'a':'b').'">
                            <td>10-20GB</td>
                            <td>0.15</td>
                            <td>0.00</td>
                      </tr>
                      <tr class="row'.($master->request->user->Downloaded >= 20*1024*1024*1024 && $master->request->user->Downloaded < 30*1024*1024*1024?'a':'b').'">
                            <td>20-30GB</td>
                            <td>0.20</td>
                            <td>0.00</td>
                      </tr>
                      <tr class="row'.($master->request->user->Downloaded >= 30*1024*1024*1024 && $master->request->user->Downloaded < 40*1024*1024*1024?'a':'b').'">
                            <td>30-40GB</td>
                            <td>0.30</td>
                            <td>0.05</td>
                      </tr>
                      <tr class="row'.($master->request->user->Downloaded >= 40*1024*1024*1024 && $master->request->user->Downloaded < 50*1024*1024*1024?'a':'b').'">
                            <td>40-50GB</td>
                            <td>0.40</td>
                            <td>0.10</td>
                      </tr>
                      <tr class="row'.($master->request->user->Downloaded >= 50*1024*1024*1024 && $master->request->user->Downloaded < 60*1024*1024*1024?'a':'b').'">
                            <td>50-60GB</td>
                            <td>0.50</td>
                            <td>0.20</td>
                      </tr>
                      <tr class="row'.($master->request->user->Downloaded >= 60*1024*1024*1024 && $master->request->user->Downloaded < 80*1024*1024*1024?'a':'b').'">
                            <td>60-80GB</td>
                            <td>0.50</td>
                            <td>0.30</td>
                      </tr>
                      <tr class="row'.($master->request->user->Downloaded >= 80*1024*1024*1024 && $master->request->user->Downloaded < 100*1024*1024*1024?'a':'b').'">
                            <td>80-100GB</td>
                            <td>0.50</td>
                            <td>0.40</td>
                      </tr>
                      <tr class="row'.($master->request->user->Downloaded >= 100*1024*1024*1024?'a':'b').'">
                            <td>100+GB</td>
                            <td>0.50</td>
                            <td>0.50</td>
                      </tr>
                </table>';
                        $table = str_replace("\n", '', $table);
                        $str .= $table;
                    }
                    break;

                case 'id':
                    if ($this->Advanced) {
                        $str.='<span id="'. $block['Attr'] .'">' . $this->to_html($block['Val']) . '</span>';
                    } else {
                        $str .= $this->to_html($block['Val']);
                    }
                    break;
            }
        }
        $this->Levels--;

        return $str;
    }

    public function prism_supported($lang) {
        if (!empty($lang)) {
            $lang = strtolower($lang);
            $supported = [
                'markup', 'css', 'clike', 'javascript', 'abap', 'abnf', 'actionscript',
                'ada', 'al', 'antlr4', 'apacheconf', 'apl', 'applescript', 'aql',
                'arduino', 'arff', 'asciidoc', 'asm6502', 'aspnet', 'autohotkey',
                'autoit', 'bash', 'basic', 'batch', 'bbcode', 'bison', 'bnf', 'brainfuck',
                'brightscript', 'bro', 'c', 'concurnas', 'csharp', 'cpp', 'cil',
                'coffeescript', 'cmake', 'clojure', 'crystal', 'csp', 'css-extras',
                'd', 'dart', 'dax', 'diff', 'django', 'dns-zone-file', 'docker',
                'ebnf', 'eiffel', 'ejs', 'elixir', 'elm', 'etlua', 'erb', 'erlang',
                'excel-formula', 'fsharp', 'factor', 'firestore-security-rules',
                'flow', 'fortran', 'ftl', 'gcode', 'gdscript', 'gedcom', 'gherkin',
                'git', 'glsl', 'gml', 'go', 'graphql', 'groovy', 'haml', 'handlebars',
                'haskell', 'haxe', 'hcl', 'http', 'hpkp', 'hsts', 'ichigojam', 'icon',
                'iecst', 'inform7', 'ini', 'io', 'j', 'java', 'javadoc', 'javadoclike',
                'javastacktrace', 'jolie', 'jq', 'jsdoc', 'js-extras', 'js-templates',
                'json', 'jsonp', 'json5', 'julia', 'keyman', 'kotlin', 'latex', 'latte',
                'less', 'lilypond', 'liquid', 'lisp', 'livescript', 'llvm', 'lolcode',
                'lua', 'makefile', 'markdown', 'markup-templating', 'matlab', 'mel',
                'mizar', 'monkey', 'moonscript', 'n1ql', 'n4js', 'nand2tetris-hdl',
                'nasm', 'neon', 'nginx', 'nim', 'nix', 'nsis', 'objectivec', 'ocaml',
                'opencl', 'oz', 'parigp', 'parser', 'pascal', 'pascaligo', 'pcaxis',
                'peoplecode', 'perl', 'php', 'phpdoc', 'php-extras', 'plsql', 'powerquery',
                'powershell', 'processing', 'prolog', 'properties', 'protobuf', 'pug',
                'puppet', 'pure', 'python', 'q', 'qml', 'qore', 'r', 'racket', 'jsx',
                'tsx', 'renpy', 'reason', 'regex', 'rest', 'rip', 'roboconf', 'robotframework',
                'ruby', 'rust', 'sas', 'sass', 'scss', 'scala', 'scheme', 'shell-session',
                'smalltalk', 'smarty', 'solidity', 'solution-file', 'soy', 'sparql',
                'splunk-spl', 'sqf', 'sql', 'stylus', 'swift', 'tap', 'tcl', 'textile',
                'toml', 'tt2', 'turtle', 'twig', 'typescript', 't4-cs', 't4-vb', 't4-templating',
                'unrealscript', 'vala', 'vbnet', 'velocity', 'verilog', 'vhdl', 'vim',
                'visual-basic', 'warpscript', 'wasm', 'wiki', 'xeora', 'xojo', 'xquery',
                'yaml', 'zig'];
            if (in_array($lang, $supported)) {
                return 'language-'.$lang;
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
     * @return array $matches
     */
    private function getQueryParameters($value, $section, $customRegex = []) {
        global $master;

        // Escape slashes
        $section = str_replace('\/', '/', $section);
        $section = str_replace('/', '\/', $section);

        // The rule for ID always defaults to \d+ unless overridden
        // If $customRegex['id'] is not set, we create it
        $positiondRegex = isset($customRegex['id']) ? $customRegex['id'] : ($customRegex['id'] = '\d+');
        // Match tag contents like [torrent]1234[/torrent]
        $pattern = '/^(?<id>'.$positiondRegex.')$';
        // Match legacy URLs like /forums.php?action=viewthread&threadid=1234
        $pattern .= '|^(' . $master->settings->main->internal_urls_regex . ')?\/'.$section.'(\.php)?\??(?<queryString>.*)/i';
        $pathMatches = [];
        $queryMatches = [];
        $success = preg_match($pattern, $value, $pathMatches);

        // Parse catched URL parameters
        if ($success && !empty($pathMatches['queryString']))
            parse_str(html_entity_decode(parse_url($value, PHP_URL_QUERY)), $queryMatches);

        $matches = array_merge($pathMatches, $queryMatches);
        // Further validation
        foreach ($matches as $key => &$val) {
            // Check keys
            if (!array_key_exists($key, $customRegex)){
                unset($matches[$key]);
                continue;
            }

            // Re-escape input
            $val = display_str($val);

            // Custom RegEx rule
            if (isset($customRegex[$key]) && !preg_match('/^'.$customRegex[$key].'$/', $val)) {
                unset($matches[$key]);
            }
        }

        return $matches;
    }

    private function articleTag($value) {
        $matches = $this->getQueryParameters($value, 'articles/view/(?<topic>[a-z0-9\-\_.()\@&]+)', ['topic' => '[a-z0-9\-\_.()\@&]+']);
        if (!empty($matches['topic'])) {
            $Title = getArticleTitle($matches['topic']);
            if ($Title) {
                $str = '<a href="/articles/view/'.$matches['topic'].'"><span class="taglabel">Article: </span>'.display_str($Title).'</a>';
            } else {
                $str = '<a title="Article not found, maybe deleted, or never existed" href="/articles/view/'.$matches['topic'].'"><span class="taglabel">Article: </span> #'.$matches['topic'].'</a>';
            }
        } else {
            $str = '[article]' . str_replace('[inlineurl]', '', $value) . '[/article]';
        }
        return $str;
    }

   private function torrentTag($value) {
        $matches = $this->getQueryParameters($value, '(torrents|details)', ['torrentid' => '\d+', 'postid' => '\d+']);
        foreach (['torrentid', 'id'] as $key) {
            if ((array_key_exists($key, $matches)) && (!empty($matches[$key]))) {
                $torrentID = $matches[$key];
                break;
            }
        }
        if (!empty($torrentID)) {
            global $master;
            $torrent = $master->repos->torrents->load($torrentID);
            if ($torrent instanceof Torrent) {
                $username = anon_username($torrent->uploader->Username, $torrent->Anonymous);
                $overlay = get_overlay_html($torrent->Title, $username, $torrent->group->Image, $torrent->Seeders, $torrent->Leechers, $torrent->Size, $torrent->Snatched);
                $str  = '<script>var overlay'.$torrent->ID.' = '.json_encode($overlay).'</script>';
                $str .= '<a onmouseover="return overlib(overlay'.$torrent->ID.', FULLHTML);" onmouseout="return nd();" href="/torrents.php?id='.$torrent->GroupID.'&amp;torrentid='.$torrent->ID.'"><span class="taglabel">Torrent: </span>'.display_str($torrent->Title).'</a>';
                if (!empty($matches['postid'])) {
                    $str .= '&nbsp;&nbsp;<a onmouseover="return overlib(overlay'.$torrent->ID.', FULLHTML);" onmouseout="return nd();" href="/torrents.php?torrentid='.$torrent->ID.'&amp;postid='.$matches['postid'].'#post'.$matches['postid'].'"><span class="taglabel">comment: </span>'.$matches['postid'].'</a>';
                }
                return $str;
            }
            return '<a title="Torrent not found, maybe deleted, or never existed" href="/torrents.php?torrentid='.$torrentID.'"><span class="taglabel">Torrent: </span> #'.$torrentID.'</a>';
        }
        return '[torrent]' . str_replace('[inlineurl]', '', $value) . '[/torrent]';
    }

    private function requestTag($value) {
        global $master;
        $matches = $this->getQueryParameters($value, 'requests', ['page' => '\d+', 'postid' => '\d+']);
        if (!empty($matches['id'])) {
            $Request = get_requests([$matches['id']], true);
            if (!empty($Request['matches'][$matches['id']])) {
                include_once($master->applicationPath.'/Legacy/sections/requests/functions.php');
                $Request = $Request['matches'][$matches['id']];
                $RequestVotes = get_votes_array($Request['ID']);
                $VoteCount = count($RequestVotes['Voters']);
                $IsFilled = ($Request['TorrentID'] != 0);
                $Overlay = get_request_overlay_html($Request['Title'], $Request['Username'], $Request['Image'], $RequestVotes['TotalBounty'], $VoteCount, $IsFilled);
                $str  = '<script>var overlay_req'.$Request['ID'].' = '.json_encode($Overlay).'</script>';
                $str .= '<a onmouseover="return overlib(overlay_req'.$Request['ID'].', FULLHTML);" onmouseout="return nd();" href="/requests.php?action=view&id='.$matches['id'].'"><span class="taglabel">Request: </span>'.display_str($Request['Title']).'</a>';
                if (!empty($matches['postid'])) {
                    $Page = !empty($matches['page']) ? '&page='.$matches['page'] : '';
                    $str .= '&nbsp;&nbsp;<a onmouseover="return overlib(overlay_req'.$Request['ID'].', FULLHTML);" onmouseout="return nd();" href="/requests.php?action=view&id='.$matches['id'].$Page.'#post'.$matches['postid'].'"><span class="taglabel">comment: </span>'.$matches['postid'].'</a>';
                }
            } else {
                $str = '<a title="Request not found, maybe deleted, or never existed" href="/requests.php?action=view&id='.$matches['id'].'"><span class="taglabel">Request: </span> #'.$matches['id'].'</a>';
            }
        } else {
            $str = '[request]' . str_replace('[inlineurl]', '', $value) . '[/request]';
        }
        return $str;
    }

    private function collageTag($value) {
        $matches = $this->getQueryParameters($value, 'collage/(?<collageid>\d+)', ['collageid' => '\d+', 'page' => '\d+', 'postid' => '\d+']);
        if (!empty($matches['collageid'])) {
            $Title = getCollageName($matches['collageid']);
            if ($Title) {
                $str = '<a href="/collage/'.$matches['collageid'].'"><span class="taglabel">Collage: </span>'.display_str($Title).'</a>';
                if (!empty($matches['postid'])) {
                    $Page = !empty($matches['page']) ? '&page='.$matches['page'] : '';
                    $str .= '&nbsp;&nbsp;<a href="/collage/'.$matches['collageid'].'#post'.$matches['postid'].'"><span class="taglabel">comment: </span>'.$matches['postid'].'</a>';
                }
            } else {
                $str = '<a title="Collage not found, maybe deleted, or never existed" href="/collage/'.$matches['collageid'].'"><span class="taglabel">Collage: </span> #'.$matches['collageid'].'</a>';
            }
        } else {
            $str = '[collage]' . str_replace('[inlineurl]', '', $value) . '[/collage]';
        }
        return $str;
    }

    private function threadTag($value) {
        $matches = $this->getQueryParameters($value, '(forums|forum/thread/(?<threadid>\d+))', ['threadid' => '\d+', 'postid' => '\d+']);
        if (!empty($matches['threadid'])) {
            $Title = getThreadName($matches['threadid']);
            if ($Title) {
                $str = '<a href="/forum/thread/'.$matches['threadid'].'"><span class="taglabel">Thread: </span>'.display_str($Title).'</a>';
                if (!empty($matches['postid'])) {
                    $str .= '&nbsp;&nbsp;<a href="/forum/thread/'.$matches['threadid'].'?postid='.$matches['postid'].'#post'.$matches['postid'].'"><span class="taglabel">post: </span>'.$matches['postid'].'</a>';
                }
            } else {
                $str = '<a title="Thread not found, maybe deleted, or never existed" href="/forum/thread/'.$matches['threadid'].'"><span class="taglabel">Thread: </span> #'.$matches['threadid'].'</a>';
            }
        } else {
            $str = '[thread]' . str_replace('[inlineurl]', '', $value) . '[/thread]';
        }
        return $str;
    }

    private function forumTag($value) {
        $matches = $this->getQueryParameters($value, '(forums|forum/(?<forumid>\d+))', ['forumid' => '\d+']);
        if (!empty($matches['forumid'])) {
            $Title = getForumName($matches['forumid']);
            if ($Title) {
                $str = '<a href="/forum/'.$matches['forumid'].'"><span class="taglabel">Forum: </span>'.display_str($Title).'</a>';
            } else {
                $str = '<a title="Forum not found, maybe deleted, or never existed" href="/forum/'.$matches['forumid'].'"><span class="taglabel">Forum: </span> #'.$matches['forumid'].'</a>';
            }
        } else {
            $str = '[forum]' . str_replace('[inlineurl]', '', $value) . '[/forum]';
        }
        return $str;
    }

    private function staffPMTag($value) {
        $matches = $this->getQueryParameters($value, 'staffpm');
        if (!empty($matches['id'])) {
            $Title = getStaffPMSubject($matches['id']);
            if ($Title) {
                $str = '<a href="/staffpm.php?action=viewconv&id='.$matches['id'].'"><span class="taglabel">StaffPM: </span>'.display_str($Title).'</a>';
            } else {
                $str = '<a title="StaffPM not found, maybe deleted, above your level, or never existed" href="/staffpm.php?action=viewconv&id='.$matches['id'].'"><span class="taglabel">StaffPM: </span> #'.$matches['id'].'</a>';
            }
        } else {
            $str = '[staffpm]' . str_replace('[inlineurl]', '', $value) . '[/staffpm]';
        }
        return $str;
    }

    private function uploaderTag($value, $attrs = '') {
        global $master;
        $attrs = trim($attrs);
        $torrent = $master->repos->torrents->load($value);
        if ($torrent instanceof Torrent) {
            return torrent_username($torrent->uploader->ID, boolval($torrent->Anonymous));
        } else {
            if (!empty($attrs)) {
                $attrs = explode(',', $attrs);
                if (count($attrs) == 1) {
                    return torrent_username($attrs[0], true);
                } elseif (count($attrs) > 1) {
                    return torrent_username($attrs[0], boolval($attrs[1]));
                }
            } else {
                $str= 'System';
            }
        }
    }

    private function userTag($value) {
        $matches = $this->getQueryParameters($value, 'user');
        if (!empty($matches['id'])) {
            $Title = getUserName($matches['id']);
            if ($Title) {
                $str = '<a href="/user.php?id='.$matches['id'].'"><span class="taglabel">User: </span>'.display_str($Title).'</a>';
            } else {
                $str = '<a title="User not found, maybe deleted, or never existed" href="/user.php?id='.$matches['id'].'"><span class="taglabel">User: </span> #'.$matches['id'].'</a>';
            }
        } else {
            $str = '[user]' . str_replace('[inlineurl]', '', $value) . '[/user]';
        }
        return $str;
    }

    public function raw_text($Array, $stripURL = false) {
        $str = '';
        foreach ($Array as $block) {
            if (is_string($block)) {
                $str.=$block;
                continue;
            }
            switch ($block['Type']) {

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
                    $str.=$this->raw_text($block['Val']);
                    break;
                case 'tr':
                    $str.=$this->raw_text($block['Val'])."\n";
                    break;
                case 'br':
                    $str.= "\n";
                    break;
                case 'tex': //since this will never strip cleanly, just remove it
                    break;
                case '```':
                case 'user':
                case 'pre':
                case 'code':
                case 'audio':
                case 'img':
                case 'imgalt':
                case 'imgnm':
                    $str.=$block['Val'];
                    break;
                case 'list':
                    foreach ($block['Val'] as $Line) {
                        $str.=$block['Tag'] . $this->raw_text($Line);
                    }
                    break;

                case 'url':
                case 'link':
                    if ($stripURL)
                        break;
                    // Make sure the URL has a label
                    if (empty($block['Val'])) {
                        $block['Val'] = $block['Attr'];
                    } else {
                        $block['Val'] = $this->raw_text($block['Val']);
                    }

                    $str.=$block['Val'];
                    break;

                case 'inlineurl':
                    if (!$this->valid_url($block['Attr'], '', true)) {
                        $Array = $this->parse($block['Attr']);
                        $block['Attr'] = $Array;
                        $str.=$this->raw_text($block['Attr']);
                        $str.="RAW";
                    } else {
                        $str.=$block['Attr'];
                    }

                    break;
                default:
                    break;
            }
        }

        return $str;
    }

    public function smileys($str) {
        global $master;
        if ($master->request->user->options('DisableSmileys', false)) {
            return $str;
        }
        $str = strtr($str, $this->Smileys);

        return $str;
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

    public function display_bbcode_assistant($textarea, $AllowAdvancedTags = false, $start_num_smilies = 0, $load_increment = 240, $load_increment_first = 30) {
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
        <?php if (check_perms('forum_moderate')) { ?>
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
                            <a class="bb_help" href="/articles/view/bbcode" target="_blank">Help</a>
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

        <?php if (check_perms('forum_moderate')) { ?>
                                <a class="bb_button" style="border: 2px solid #600;" onclick="tag('mcom', '<?= $textarea; ?>')" title="Staff Comment: [mcom]text[/mcom]" alt="Mod comment">Mod</a>
                                <a class="bb_button" style="border: 2px solid #600;" onclick="tag('hide', '<?= $textarea; ?>')" title="Hidden Comment: [hide]text[/hide]" alt="Hidden comment">Hide</a>
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
    public function draw_smilies_from_XML($positionndexfrom = 0, $positionndexto = -1) {
        $count = 0;
        echo "<smilies>";
        foreach ($this->Smileys as $Key => $Val) {
            if ($positionndexto >= 0 && $count >= $positionndexto) {
                break;
            }
            if ($count >= $positionndexfrom) {
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

    public function draw_smilies_from($positionndexfrom = 0, $positionndexto = -1, $textarea = '') {
        $count = 0;
        foreach ($this->Smileys as $Key => $Val) {
            if ($positionndexto >= 0 && $count >= $positionndexto) {
                break;
            }
            if ($count >= $positionndexfrom) {  // ' &nbsp;' .$Key. - jsut for printing in dev
                echo '<a class="bb_smiley" title="' . $Key . '" href="javascript:insert(\' ' . $Key . ' \',\'' . $textarea . '\');">' . $Val . '</a>';
            }
            $count++;
        }
        reset($this->Smileys);
    }

    public function draw_all_smilies($Sort = true, $AZ = true) {
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

    public function clean_bbcode($str, $Advanced) {
        // Change mcom tags into quote tags for non-mod users
        if (!check_perms('forum_moderate')) {
            $str = preg_replace('/\[mcom\]/i', '[quote=Staff Comment]', $str);
            $str = preg_replace('/\[\/mcom\]/i', '[/quote]', $str);
            $str = preg_replace('/\[hide\]/i', '[quote=Hidden Comment]', $str);
            $str = preg_replace('/\[\/hide\]/i', '[/quote]', $str);
            $str = preg_replace('/\[flash=([^\]])*\]/i', '[quote=Flash Object]', $str);
            $str = preg_replace('/\[\/flash\]/i', '[/quote]', $str);
        }

        return $str;
    }

}

/*
  //Uncomment this part to test the class via command line:
  public function display_str($str) {return $str;}
  public function check_perms($Perm) {return true;}
  $str = "hello
  [pre]http://anonym.es/?http://whatshirts.portmerch.com/
  ====hi====
  ===hi===
  ==hi==[/pre]
  ====hi====
  hi";
  $bbCode = new \Luminance\Legacy\Text;
  echo $bbCode->full_format($str);
  echo "\n"
 */
