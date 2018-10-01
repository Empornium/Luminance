<?php

namespace Luminance\Legacy;

class Feed
{
    public $UseSSL = true; // If we're using SSL for blog and news links

    public function open_feed()
    {
        header("Content-type: application/xml; charset=UTF-8");
        echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n","<rss version=\"2.0\" xmlns:dc=\"http://purl.org/dc/elements/1.1/\">\n\t<channel>\n";
    }

    public function close_feed()
    {
        echo "\t</channel>\n</rss>";
    }

    public function channel($Title, $Description, $Section='')
    {
        $Site = $this->UseSSL ? 'https://'.SSL_SITE_URL : 'http://'.NONSSL_SITE_URL;
        echo "\t\t<title>$Title :: ". SITE_NAME. "</title>\n";
        echo "\t\t<link>$Site/$Section</link>\n";
        echo "\t\t<description>$Description</description>\n";
        echo "\t\t<language>en-us</language>\n";
        echo "\t\t<lastBuildDate>". date('r'). "</lastBuildDate>\n";
        echo "\t\t<docs>http://blogs.law.harvard.edu/tech/rss</docs>\n";
        echo "\t\t<generator>Gazelle Feed Class</generator>\n\n";
    }

    public function item($Title, $Description, $Page, $Creator, $Comments='', $Category='', $Date='')
    {
        $Timestamp = !empty($Date) ? strtotime($Date) : time();
        $Date      = date("r", $Timestamp);

        // Parameters that need special escaping (CData)
        $Title       = $this->cdata($Title);
        $Description = $this->cdata($Description);
        $Category    = $this->cdata($Category);

        $Site = $this->UseSSL ? 'https://'.SSL_SITE_URL : 'http://'.NONSSL_SITE_URL;
        $Item = "\t\t<item>\n";
        $Item .= "\t\t\t<title>$Title</title>\n";
        $Item .= "\t\t\t<description>$Description</description>\n";
        $Item .= "\t\t\t<pubDate>$Date</pubDate>\n";
        $Item .= "\t\t\t<link>$Site/$Page</link>\n";
        $Item .= "\t\t\t<guid>$Site/$Page</guid>\n";
        if ($Comments != '') {
            $Item .= "\t\t\t<comments>$Site/$Comments</comments>\n";
        }
        if ($Category != '') {
            $Item .= "\t\t\t<category>$Category</category>\n";
        }
        $Item .= "\t\t\t<dc:creator>$Creator</dc:creator>\n\t\t</item>\n";

        return $Item;
    }

    // Specialised creator function for torrent items
    public function torrent($Title, $Description, $Page, $DownLink, $InfoHash, $TorrentName, $TorrentSize, $ContentSize, $ContentSizeHR, $Creator, $Domain, $Category='', $Tags='', $FreeTorrent=0)
    {
        $Date = date("r");

        // Parameters that need special escaping (CData)
        $Title       = $this->cdata($Title);
        $Category    = $this->cdata($Category);
        $Description = $this->cdata($Description);
        $Tags        = $this->cdata($Tags);
        $TorrentName = $this->cdata($TorrentName);
        $InfoHash    = $this->cdata($InfoHash);

        $Site = $this->UseSSL ? 'https://'.SSL_SITE_URL : 'http://'.NONSSL_SITE_URL;
        $Item = "\t\t<item>\n";

        $Item .= "\t\t\t<title>$Title</title>\n";
        $Item .= "\t\t\t<link>$Site/$Page</link>\n";
        $Item .= "\t\t\t<category domain=\"$Site/$Domain\">$Category</category>\n";
        $Item .= "\t\t\t<pubDate>$Date</pubDate>\n";
        $Item .= "\t\t\t<description>$Description</description>\n";
        $Item .= "\t\t\t<tags>$Tags</tags>\n";
        $Item .= "\t\t\t<dc:creator>$Creator</dc:creator>\n";
        $Item .= "\t\t\t<enclosure url=\"$Site/$DownLink\" length=\"$TorrentSize\" type=\"application/x-bittorrent\" />\n";
        $Item .= "\t\t\t<comments>$Site/$Page</comments>\n";
        $Item .= "\t\t\t<guid>$Site/$Page</guid>\n";

        $Item .= "\t\t\t<torrent xmlns=\"http://xmlns.ezrss.it/0.1/\">\n";
        $Item .= "\t\t\t\t<fileName>$TorrentName</fileName>\n";
        $Item .= "\t\t\t\t<infoHash>$InfoHash</infoHash>\n";
        $Item .= "\t\t\t\t<contentLength>$ContentSize</contentLength>\n";
        $Item .= "\t\t\t\t<contentLengthHR>$ContentSizeHR</contentLengthHR>\n";
        $Item .= "\t\t\t</torrent>\n";

        $Item .= "\t\t</item>\n";

        return $Item;
    }

    public function retrieve($CacheKey,$AuthKey,$PassKey)
    {
        global $Cache;
        $Entries = $Cache->get_value($CacheKey);
        if (!$Entries) {
            $Entries = array();
        } else {
            foreach ($Entries as $Item) {
                echo str_replace(array('[[PASSKEY]]','[[AUTHKEY]]'),array(display_str($PassKey),display_str($AuthKey)),$Item);
            }
        }
    }

    public function populate($CacheKey,$Item)
    {
        global $Cache;
        $Entries = $Cache->get_value($CacheKey,true);
        if (!$Entries) {
            $Entries = array();
        } else {
            if (count($Entries)>=50) {
                array_pop($Entries);
            }
        }
        array_unshift($Entries, $Item);
        $Cache->cache_value($CacheKey, $Entries, 0); //inf cache
    }

    /**
     * Returns a value as CDATA
     *
     * Inspired by the DOMCdataSection class, it looks for possible closing CDATA tags
     * and creates a new one in order to avoid XML injection
     *
     * @param string $Value
     * @return string
     */
    private function cdata($Value)
    {
        $Escaped = str_replace(']]>', ']]]]><![CDATA[>', preg_replace('/[\x00-\x1F\x7F]/', '', $Value));
        return "<![CDATA[$Escaped]]>";
    }
}
