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
class Bencode
{
    public $Val; // Decoded array
    public $Pos = 1; // Pointer that indicates our position in the string
    public $Str = ''; // Torrent string

    public function __construct($Val, $IsParsed = false)
    {
        if (!$IsParsed) {
            $this->Str = $Val;
            $this->dec();
        } else {
            $this->Val = $Val;
        }
    }

    // Decode an element based on the type. The type is really just an indicator.
    public function decode($Type, $Key)
    {
        if (is_number($Type)) { // Element is a string
            // Get length of string
            $StrLen = $Type;

            while ($this->Str[$this->Pos+1]!=':') {
                $this->Pos++;
                $StrLen.=$this->Str[$this->Pos];
            }

            $this->Val[$Key] = substr($this->Str, $this->Pos+2, $StrLen);
            $this->Pos+=$StrLen;
            $this->Pos+=2;

        } elseif ($Type == 'i') { // Element is an int
            $this->Pos++;

            // Find end of integer (first occurance of 'e' after position)
            $End = strpos($this->Str, 'e', $this->Pos);

            // Get the integer, and - IMPORTANT - cast it as an int, so we know later that it's an int and not a string
            $this->Val[$Key] = (int) substr($this->Str, $this->Pos, $End-$this->Pos);
            $this->Pos = $End+1;

        } elseif ($Type == 'l') { // Element is a list
            $this->Val[$Key] = new BencodeList(substr($this->Str, $this->Pos));
            $this->Pos += $this->Val[$Key]->Pos;

        } elseif ($Type == 'd') { // Element is a dictionary
            $this->Val[$Key] = new BencodeDict(substr($this->Str, $this->Pos));
            $this->Pos += $this->Val[$Key]->Pos;
            // Sort by key to respect spec
            ksort($this->Val[$Key]->Val);

        } else {
            die('Invalid torrent file');
        }
    }

    public function encode($Val)
    {
        if (is_int($Val)) { // Integer

            return 'i'.$Val.'e';
        } elseif (is_string($Val)) {
            return strlen($Val).':'.$Val;
        } elseif (is_object($Val)) {
            return $Val->enc();
        } else {
            return 'fail';
        }
    }
}
