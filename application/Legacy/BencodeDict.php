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
class BencodeDict extends Bencode
{
    public function enc()
    {
        $Str = 'd';
        reset($this->Val);
        // Sort by key to respect spec
        ksort($this->Val);

        foreach ($this->Val as $Key => $Value) {
            $Str.=strlen($Key).':'.$Key.$this->encode($Value);
        }

        return $Str.'e';
    }

    // Decode a dictionary
    public function dec()
    {
        $Length = strlen($this->Str);
        while ($this->Pos<$Length) {

            if ($this->Str[$this->Pos] == 'e') { // End of dictionary
                $this->Pos += 1;
                unset($this->Str); // Since we're finished parsing the string, we don't need to store it anymore. Benchmarked - this makes the parser run way faster.

                return;
            }

            // Get the dictionary key
            // Length of the key, in bytes
            $KeyLen = $this->Str[$this->Pos];

            // Allow for multi-digit lengths
            while ($this->Str[$this->Pos+1]!=':' && $this->Pos+1<$Length) {
                $this->Pos++;
                $KeyLen.=$this->Str[$this->Pos];
            }
            // $this->Pos is now on the last letter of the key length
            // Adding 2 brings it past that character and the ':' to the beginning of the string
            $this->Pos+=2;

            // Get the name of the key
            $Key = substr($this->Str, $this->Pos, $KeyLen);

            // Move the position past the key to the beginning of the element
            $this->Pos+=$KeyLen;
            $Type = $this->Str[$this->Pos];
            // $Type now indicates what type of element we're dealing with
            // It's either an integer (string) , 'i' (an integer), 'l' (a list), 'd' (a dictionary), or 'e' (end of dictionary/list)

            // Decode the bencoded element.
            // This function changes $this->Pos and $this->Val, so you don't have to.
            $this->decode($Type, $Key);

        }

        return true;
    }
}
