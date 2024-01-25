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

* decode(Type, $key)
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
class Bencode {
    public $Val;       // Decoded array
    public $Pos = 1;   // Pointer that indicates our position in the string
    public $Str = '';  // Torrent string

    public function __construct($values, $isParsed = false) {
        if (!$isParsed) {
            $this->Str = $values;
            $this->dec();
        } else {
            $this->Val = $values;
        }
    }

    public function dec() {}

    // Decode an element based on the type. The type is really just an indicator.
    public function decode($type, $key) {
        if (is_integer_string($type)) { // Element is a string
            // Get length of string
            $stringLength = $type;

            while ($this->Str[$this->Pos+1]!=':') {
                $this->Pos++;
                $stringLength.=$this->Str[$this->Pos];
            }

            $this->Val[$key] = substr($this->Str, $this->Pos+2, $stringLength);
            $this->Pos+=$stringLength;
            $this->Pos+=2;

        } elseif ($type == 'i') { // Element is an int
            $this->Pos++;

            // Find end of integer (first occurance of 'e' after position)
            $end = strpos($this->Str, 'e', $this->Pos);

            // Get the integer, and - IMPORTANT - cast it as an int, so we know later that it's an int and not a string
            $this->Val[$key] = (int) substr($this->Str, $this->Pos, $end-$this->Pos);
            $this->Pos = $end+1;

        } elseif ($type == 'l') { // Element is a list
            $this->Val[$key] = new BencodeList(substr($this->Str, $this->Pos));
            $this->Pos += $this->Val[$key]->Pos;

        } elseif ($type == 'd') { // Element is a dictionary
            $this->Val[$key] = new BencodeDict(substr($this->Str, $this->Pos));
            $this->Pos += $this->Val[$key]->Pos;
            // Sort by key to respect spec
            ksort($this->Val[$key]->Val);

        } else {
            die('Invalid torrent file');
        }
    }

    public function encode($values) {
        if (is_int($values)) { // Integer
            return 'i'.$values.'e';
        } elseif (is_string($values)) {
            return strlen($values).':'.$values;
        } elseif (is_object($values)) {
            return $values->enc();
        } else {
            return 'fail';
        }
    }
}
