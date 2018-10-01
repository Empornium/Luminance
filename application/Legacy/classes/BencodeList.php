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
class BencodeList extends Bencode
{
    public function enc()
    {
        if (is_null($this->Val)) return 'le';
        $Str = 'l';
        reset($this->Val);
        foreach ($this->Val as $Value) {
            $Str.=$this->encode($Value);
        }

        return $Str.'e';
    }

    // Decode a list
    public function dec()
    {
        $Key = 0; // Array index
        $Length = strlen($this->Str);
        while ($this->Pos<$Length) {
            $Type = $this->Str[$this->Pos];
            // $Type now indicates what type of element we're dealing with
            // It's either an integer (string) , 'i' (an integer), 'l' (a list), 'd' (a dictionary), or 'e' (end of dictionary/list)

            if ($Type == 'e') { // End of list
                $this->Pos += 1;
                unset($this->Str); // Since we're finished parsing the string, we don't need to store it anymore. Benchmarked - this makes the parser run way faster.

                return;
            }

            // Decode the bencoded element.
            // This function changes $this->Pos and $this->Val, so you don't have to.
            $this->decode($Type, $Key);
            ++ $Key;
        }

        return true;
    }
}
