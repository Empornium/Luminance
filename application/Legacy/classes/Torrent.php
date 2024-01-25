<?php
namespace Luminance\Legacy;
/*******************************************************************************
|~~~~ Gazelle bencode parser											   ~~~~|
--------------------------------------------------------------------------------

Welcome to the Gazelle bencode parser. bencoding is the way of encoding data
that bittorrent uses in torrent files. When we read the torrent files, we get
one long string that must be parsed into a format we can easily edit - that's
where this file comes into play.

There are 4 data types in bencode:
* String
* Int
* List - array without keys
    - like array('value', 'value 2', 'value 3', 'etc')
* Dictionary - array with string keys
    - like array['key 1'] = 'value 1'; array['key 2'] = 'value 2';

Before you go any further, we recommend reading the sections on bencoding and
metainfo file structure here: http://wiki.theory.org/BitTorrentSpecification

//----- How we store the data -----//

* Strings
    - Stored as php strings. Not difficult to remember.

* Integers
    - Stored as php ints
    - must be casted with (int)

* Lists
    - Stored as a BENCODE_LIST object.
    - The actual list is in BENCODE_LIST::$values, as an array with incrementing integer indices
    - The list in BENCODE_LIST::$values is populated by the BENCODE_LIST::dec() function

* Dictionaries
    - Stored as a BENCODE_DICT object.
    - The actual list is in BENCODE_DICT::$values, as an array with string indices
    - The list in BENCODE_DICT::$values is populated by the BENCODE_DICT::dec() function

//----- BENCODE_* Objects -----//

Lists and dictionaries are stored as objects. They each have the following
functions:

* decode(Type, $Key)
    - Decodes ANY bencoded element, given the type and the key
    - Gets the position and string from $this

* encode($values)
    - Encodes ANY non-bencoded element, given the value

* dec()
    - Decodes either a dictionary or a list, depending on where it's called from
    - Uses the decode() function quite a bit

* enc()
    - Encodes either a dictionary or a list, depending on where it's called from
    - Relies mostly on the encode() function

Finally, as all torrents are just large dictionaries, the TORRENT class extends
the BENCODE_DICT class.


*******************************************************************************/
class Torrent extends BencodeDict {

    public function __construct($val, $isParsed = false){
        # Call parent constructor
        parent::__construct($val, $isParsed);

        # Run standard clean:
        $this->clean();
    }

    public function dump() {
        # Convenience function used for testing and figuring out how we store the data
        print_r($this->Val);
    }

    public function dump_data() {
        # Function which serializes $this->Val for storage
        return base64_encode(serialize($this->Val));
    }

    public function set_announce_url($announce) {
        $this->Val['announce'] = $announce;
    }

    public function set_multi_announce($announces) {
        foreach ($announces as $announce) {
            $announceList[] = new BencodeList([$announce],true);
        }
        $announceList = new BencodeList($announceList,true);
        $this->Val['announce-list'] = $announceList;
    }


    public function set_comment($comment) {
        $this->Val['comment'] = $comment;
    }

    public function set_metadata($metadata) {
        if (empty($metadata)) {
            return;
        }

        $values = [];
        foreach ($metadata as $key => $value) {
            if (empty($value)) {
                continue;
            }
            switch (strtolower($key)) {
                case 'name':
                    $values['title'] = $value;
                    break;

                case 'image':
                    $values['cover url'] = $value;
                    break;

                case 'body':
                    $values['description'] = $value;
                    break;

                case 'taglist':
                    # Encode tags as a list
                    $value =  explode(' ', str_replace('_', '.', $value));
                    $values['taglist'] = new BencodeList($value, true);
                    break;
            }
        }

        $this->Val['metadata'] = new BencodeDict($values, true);
    }

    // Returns an array of:
    // 	* the files in the torrent
    //	* the total size of files described therein
    public function file_list() {
        $fileList = [];
        if (!($this->Val['info']->Val['files'] ?? false)) { # Single file mode
            $totalSize = $this->Val['info']->Val['length'];
            $fileList[]= [$this->Val['info']->Val['length'], $this->Val['info']->Val['name']];
        } else { # Multiple file mode
            $fileNames = [];
            $totalSize = 0;
            $files = $this->Val['info']->Val['files']->Val;
            foreach ($files as $file) {
                $totalSize+=$file->Val['length'];
                $fileSize = $file->Val['length'];

                $fileName = ltrim(implode('/',$file->Val['path']->Val), '/');

                $fileList[] = [$fileSize, $fileName];
                $fileNames[] = $fileName;
            }
            array_multisort($fileNames, $fileList);
        }

        return array($totalSize, $fileList);
    }

    # This function corrects issues with certain bencoded torrents.
    public function use_strict_bencode_specification() {
        $valid = true;
        if (intval($this->Val['info']->Val['piece length']) > 8388608) {
            error('Torrent piece size too large, 8MiB max');
        }

        // Temp because we're not ready for V2 torrents yet
        $Hash = $this->Val['info']->Val['meta version'] ?? 1;
        if ($Hash > 1) {
            error("This torrent is not a V1 Torrent. Please re-create your torrent using torrent protocol 1");
        }

        if (($this->Val['info']->Val['files'] ?? false)) {
            $files = $this->Val['info']->Val['files']->Val;
            foreach ($files as $file) {
                 # Lanz: if any list elements for the file path has zero length, remove them.
                 $values = array_values(array_filter($file->Val['path']->Val, 'strlen'));
                 $path = implode('/', $values);
                 if (!($file->Val['path']->Val === $values)) {
                    $valid = false;
                 }
                 if (mb_strlen($path) > 128) {
                    error("File path too long, 128 characters max: {$path}");
                 }
                 $file->Val['path']->Val = $values;
            }
        }
        return $valid;
    }

    /**
     * This function removes most of the entries from the top level bencode dictionary,
     * the properties which should be left are:
     * * info (this is the core of a .torrent file)
     * * created by
     * * creation date
     * * metadata
     *
     * All other properties are removed, as they exist outside the info dict they do not
     * affect the info hash.
     */
    function clean() {
        if (!is_array($this->Val)){
            return;
        }
        foreach (array_keys($this->Val) as $key) {
            switch ($key) {

                case 'info':            # Info is the bit we really need
                case 'created by':      # This might be useful for debug
                case 'creation date':   # This isn't really useful but let's keep it anyway
                case 'metadata':        # This *SHOULD BE* Luminance specific

                # do nothing
                break;

                default:
                unset($this->Val[$key]);
            }
        }
    }

    public function make_private() {
        # Run standard clean:
        $this->clean();

        # The code below modifies the info hash!
        if (($this->Val['info']->Val['private'] ?? 0)===1) {
            return true; # Torrent is private
        } else {
            # Torrent is not private!
            # add private tracker flag and sort info dictionary
            $this->Val['info']->Val['private'] = 1;
            ksort($this->Val['info']->Val);

            return false;
        }
    }

    public function make_unique() {
        global $master;
        if ($master->options->UniqueTorrents === true) {
            if (!empty($master->settings->main->site_short_name)) {
                $source = $master->settings->main->site_short_name;
            } else {
                $source = $master->settings->main->site_name;
            }

            # The code below modifies the info hash!
            if (($this->Val['info']->Val['source'] ?? null)===$source) {
                return true; # Torrent is already unique to us
            } else {
                # Torrent is not unique!
                # add source tracker string and sort info dictionary
                $this->Val['info']->Val['source'] = $source;
                ksort($this->Val['info']->Val);

                return false;
            }
        } else {
            # Site is set to not enforce uniqueness
            return true;
        }
    }
}
