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
    - The actual list is in BENCODE_LIST::$Val, as an array with incrementing integer indices
    - The list in BENCODE_LIST::$Val is populated by the BENCODE_LIST::dec() function

* Dictionaries
    - Stored as a BENCODE_DICT object.
    - The actual list is in BENCODE_DICT::$Val, as an array with string indices
    - The list in BENCODE_DICT::$Val is populated by the BENCODE_DICT::dec() function

//----- BENCODE_* Objects -----//

Lists and dictionaries are stored as objects. They each have the following
functions:

* decode(Type, $Key)
    - Decodes ANY bencoded element, given the type and the key
    - Gets the position and string from $this

* encode($Val)
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
class Torrent extends BencodeDict
{
    public function dump()
    {
        // Convenience function used for testing and figuring out how we store the data
        print_r($this->Val);
    }

    public function dump_data()
    {
        // Function which serializes $this->Val for storage
        return base64_encode(serialize($this->Val));
    }

    /*
    To use this, please remove the announce-list unset in make_private and be sure to still set_announce_url for backwards compatibility
    public function set_multi_announce()
    {
        $Trackers = func_get_args();
        $AnnounceList = new BencodeList(array(),true);
        foreach ($Trackers as $Tracker) {
            $SubList = new BencodeList(array($Tracker),true);
            unset($SubList->Str);
            $AnnounceList->Val[] = $SubList;
        }
        $this->Val['announce-list'] = $AnnounceList;
    }
    */

    public function set_announce_url($Announce)
    {
        $this->Val['announce'] = $Announce;
    }

    public function set_multi_announce($Announces)
    {
        foreach ($Announces as $Announce) {
            $AnnounceList[] = new BencodeList(array($Announce),true);
        }
        $AnnounceList = new BencodeList($AnnounceList,true);
        $this->Val['announce-list'] = $AnnounceList;
    }


    public function set_comment($Comment)
    {
        $this->Val['comment'] = $Comment;
    }
    // Returns an array of:
    // 	* the files in the torrent
    //	* the total size of files described therein
    public function file_list()
    {
        $FileList = array();
        if (!$this->Val['info']->Val['files']) { // Single file mode
            $TotalSize = $this->Val['info']->Val['length'];
            $FileList[]= array($this->Val['info']->Val['length'], $this->Val['info']->Val['name']);
        } else { // Multiple file mode
            $FileNames = array();
            $TotalSize = 0;
            $Files = $this->Val['info']->Val['files']->Val;
            foreach ($Files as $File) {
                $TotalSize+=$File->Val['length'];
                $FileSize = $File->Val['length'];

                $FileName = ltrim(implode('/',$File->Val['path']->Val), '/');

                $FileList[] = array($FileSize, $FileName);
                $FileNames[] = $FileName;
            }
            array_multisort($FileNames, $FileList);
        }

        return array($TotalSize, $FileList);
    }

    // This function corrects issues with certain bencoded torrents.
    public function use_strict_bencode_specification()
    {
        if ($this->Val['info']->Val['files']) {
            $Files = $this->Val['info']->Val['files']->Val;
            foreach ($Files as $File) {
                // Lanz: if any list elements for the file path has zero length, remove them.
                $File->Val['path']->Val = array_values(array_filter($File->Val['path']->Val));
            }
        }
    }

    public function make_private()
    {
        //----- The following properties do not affect the infohash:

        // anounce-list is an unofficial extension to the protocol
        // that allows for multiple trackers per torrent
        unset($this->Val['announce-list']);

        // Bitcomet & Azureus cache peers in here
        unset($this->Val['nodes']);

        // Azureus stores the dht_backup_enable flag here
        unset($this->Val['azureus_properties']);

        // Remove web-seeds
        unset($this->Val['url-list']);

        // Remove libtorrent resume info
        unset($this->Val['libtorrent_resume']);

        //----- End properties that do not affect the infohash
        if ($this->Val['info']->Val['private']===1) {
            return true; // Torrent is private
        } else {
            // Torrent is not private!
            // add private tracker flag and sort info dictionary
            $this->Val['info']->Val['private'] = 1;
            ksort($this->Val['info']->Val);

            return false;
        }
    }
}
