<?php
namespace Luminance\Services;

use Luminance\Core\Service;

class Woff extends Service {

    private $glyphs;

    private const UINT8        =  1;
    private const  INT8        =  2;
    private const UINT16       =  3;
    private const  INT16       =  4;
    private const UINT24       =  5;
    private const UINT32       =  6;
    private const  INT32       =  7;
    private const SHORTFRAC    =  8;
    private const FIXED        =  9;
    private const  FWORD       = 10;
    private const UFWORD       = 11;
    private const F2DOT14      = 12;
    private const LONGDATETIME = 13;
    private const TAG          = 14;
    private const OFFSET16     = 15;
    private const OFFSET32     = 16;


    # header
    protected $def = array(
      "sfntVersion"   => self::UINT32,
      "numTables"     => self::UINT16,
      "searchRange"   => self::UINT16,
      "entrySelector" => self::UINT16,
      "rangeShift"    => self::UINT16,
    );

    # Offset table (header)
    #  Table Record
    #
    #  BASE
    #

    private function convertGlyph($data) {}

    public function assemble($path) {
        foreach (glob($path."*.svg") as $glyph) {}
    }

    public function addGlyph($offset, $data) {}

    public function removeGlyph($offset) {}

    public function writeWoff($path) {}
}
