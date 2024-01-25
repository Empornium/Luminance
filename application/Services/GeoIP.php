<?php
namespace Luminance\Services;

use Luminance\Core\Service;
use wapmorgan\UnifiedArchive\UnifiedArchive;

class GeoIP extends Service {

    protected static $useServices = [
        'secretary' => 'Secretary',
        'cache'     => 'Cache',
        'db'        => 'DB',
        'settings'  => 'Settings',
    ];

    private $sqlRangeUpperFunction =
        "CREATE FUNCTION range_upper(network VARCHAR(43))
        RETURNS VARBINARY(16)
        DETERMINISTIC
        BEGIN
            # Declare the variables we need
            DECLARE address VARBINARY(16);
            DECLARE prefixBytes VARBINARY(16);
            DECLARE boundryByte INT;
            DECLARE prefix FLOAT;
            DECLARE addressSize INT;

            # Split CIDR into address and prefix
            SET prefix      = CAST(SUBSTRING_INDEX(network, '/', -1) AS UNSIGNED);
            SET address     = INET6_ATON(SUBSTRING_INDEX(network, '/', 1));
            SET addressSize = LENGTH(address);
            SET prefixBytes = SUBSTR(address, 1, FLOOR(prefix/8.0));

            IF MOD(prefix, 8) != 0 THEN
                # Fucking type madness!
                # We start with a binary string containing the IP address
                # and an ASCII string containing the prefix.

                # Generate the netmask for this byte using the prefix.
                SET boundryByte = 0xFF >> MOD(prefix, 8);

                # Extract the correct byte from the address string and bitwise
                # or it with the netmask from above. 'Cast' to decimal first
                # to allow use of bitwise or operator.
                SET boundryByte = CAST(CONV(HEX(SUBSTR(address, CEIL(prefix/8.0), 1)), 16, 10) AS UNSIGNED) | boundryByte;

                # 'Cast' the boundryByte byte from above back into a blob and
                # concatenate with the unmodified prefix bytes.
                SET prefixBytes = CONCAT(prefixBytes, UNHEX(CONV(boundryByte, 10, 16)));
            END IF;

            RETURN RPAD(prefixBytes, addressSize, x'FF');
        END";

    private $csvFiles = [
        'city' => [
            'url'     => 'https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-City-CSV&suffix=zip',
            'local'   => 'GeoLite2-City.zip',
            'files'   => [
                'GeoLite2-City-Blocks-IPv4.csv' => [
                    'table'   => 'geolite2',
                    'columns' => '(@network,LocationID,@dummy,@dummy,@dummy,@dummy,@postalCode,@lat,@long,AccuracyRadius)
                                   SET StartAddress = INET6_ATON(SUBSTRING_INDEX(@network, \'/\', 1)),
                                       EndAddress   = range_upper(@network),
                                       Coordinates  = POINT(@lat, @long),
                                       PostalCode   = nullif(@postalCode,\'\')',
                ],
                'GeoLite2-City-Blocks-IPv6.csv' => [
                    'table'   => 'geolite2',
                    'columns' => '(@network,LocationID,@dummy,@dummy,@dummy,@dummy,@postalCode,@lat,@long,AccuracyRadius)
                                   SET StartAddress = INET6_ATON(SUBSTRING_INDEX(@network, \'/\', 1)),
                                       EndAddress   = range_upper(@network),
                                       Coordinates  = POINT(@lat, @long),
                                       PostalCode   = nullif(@postalCode,\'\')',
                ],
                'GeoLite2-City-Locations-en.csv' => [
                    'table'   => 'geolite2_locations',
                    'columns' => '(ID,@dummy,@dummy,@dummy,@isoCode,@countryName,@dummy,@regionName,@dummy,@dummy,@cityName,@metroCode,@dummy,@dummy)
                                  SET ISOCode     = nullif(@isoCode,\'\'),
                                      CountryName = nullif(@countryName,\'\'),
                                      RegionName  = nullif(@regionName,\'\'),
                                      CityName    = nullif(@cityName,\'\'),
                                      MetroCode   = nullif(@metroCode,\'\')',
                ],
            ],
        ],
        'country' => [
            'url'     => 'https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-Country-CSV&suffix=zip',
            'local'   => 'GeoLite2-Country.zip',
            'files'   => [
                'GeoLite2-Country-Blocks-IPv4.csv' => [
                    'table'   => 'geolite2',
                    'columns' => '(@network,LocationID,@dummy,@dummy,@dummy,@dummy)
                                   SET StartAddress = INET6_ATON(SUBSTRING_INDEX(@network, \'/\', 1)),
                                       EndAddress   = range_upper(@network)',
                ],
                'GeoLite2-Country-Blocks-IPv6.csv' => [
                    'table'   => 'geolite2',
                    'columns' => '(@network,LocationID,@dummy,@dummy,@dummy,@dummy)
                                   SET StartAddress = INET6_ATON(SUBSTRING_INDEX(@network, \'/\', 1)),
                                       EndAddress   = range_upper(@network)',
                ],
                'GeoLite2-Country-Locations-en.csv' => [
                    'table'   => 'geolite2_locations',
                    'columns' => '(ID,@dummy,@dummy,@dummy,@isoCode,@countryName,@dummy)
                                  SET ISOCode     = nullif(@isoCode,\'\'),
                                      CountryName = nullif(@countryName,\'\')',
                ],
            ],
        ],
        'asn' => [
            'url'     => 'https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-ASN-CSV&suffix=zip',
            'local'   => 'GeoLite2-ASN.zip',
            'files'   => [
                'GeoLite2-ASN-Blocks-IPv4.csv' => [
                    'table'   => 'geolite2_asn',
                    'columns' => '(@network,ASN,ISP)
                                   SET StartAddress = INET6_ATON(SUBSTRING_INDEX(@network, \'/\', 1)),
                                       EndAddress   = range_upper(@network)',
                ],
                'GeoLite2-ASN-Blocks-IPv6.csv' => [
                    'table'   => 'geolite2_asn',
                    'columns' => '(@network,ASN,ISP)
                                   SET StartAddress = INET6_ATON(SUBSTRING_INDEX(@network, \'/\', 1)),
                                       EndAddress   = range_upper(@network)',
                ],
            ],
        ],
    ];

    public function update() {
        # Create the geoip DB directory if necessary
        $geoipDBPath = "{$this->master->resourcePath}/geoip";
        if (!file_exists($geoipDBPath)) {
            mkdir($geoipDBPath);
        }

        # GeoLite provides City and Country databases and we support both,
        # Country is less data for smaller sites to handle but City is more
        # detailed.
        if ($this->settings->site->geoip_city) {
            unset($this->csvFiles['country']);
        } else {
            unset($this->csvFiles['city']);
        }

        foreach ($this->csvFiles as $csvFile) {
            $zipFile = "{$geoipDBPath}/{$csvFile['local']}";
            $url = $csvFile['url'].'&license_key='.$this->settings->site->geoip_license_key;
            if (!$this->secretary->checkRemoteUpdate($url, $zipFile)) {
                continue;
            }

            # If we got here then there's an update available so lets
            # fetch the file to system temp dir and extract the new
            # DB.
            print_r("Fetching {$csvFile['local']}\n");
            $this->secretary->getHttpRemoteFile($url, $zipFile);

            $archive = UnifiedArchive::open($zipFile);
            $archiveFiles = $archive->getFileNames();

            foreach ($csvFile['files'] as $file => $query) {
                print_r("Unpacking {$file}... ");

                # Search for and extract the MaxMind DB file,
                # there should only be a single match to the search,
                # so we just implode and extract.
                $matches = preg_grep("/{$file}/", $archiveFiles);
                $match = implode('', $matches);
                $file = "{$geoipDBPath}/{$file}";
                $result = file_put_contents("{$file}", $archive->getFileContent($match));
                if ($result === false) {
                    print("failed.\n");
                    return;
                } else {
                    print("done.\n");
                }
            }

            # Only get here if we sucessfully unpacked an update, time to load it!
            try {
                $this->db->beginTransaction();
                print("Preparing database tables for import\n");

                foreach ($csvFile['files'] as $file => $query) {
                    $this->db->rawQuery("DROP TABLE IF EXISTS `{$query['table']}_new`");
                    $this->db->rawQuery("DROP TABLE IF EXISTS `{$query['table']}_old`");
                    $this->db->rawQuery("CREATE TABLE IF NOT EXISTS `{$query['table']}_new` LIKE `{$query['table']}`");
                }

                $this->db->exec("DROP FUNCTION IF EXISTS range_upper");
                $this->db->exec($this->sqlRangeUpperFunction);

                foreach ($csvFile['files'] as $file => $query) {
                    print("Loading {$file}\n");
                    $file = "{$geoipDBPath}/{$file}";
                    $this->db->exec("LOAD DATA LOCAL INFILE '{$file}' INTO TABLE `{$query['table']}_new` FIELDS TERMINATED BY ',' ENCLOSED BY '\"' LINES TERMINATED BY '\n' IGNORE 1 LINES {$query['columns']}");
                }

                $this->db->commit();

                print("Applying update\n");
                foreach ($csvFile['files'] as $file => $query) {
                    # Should be safe
                    $this->db->rawQuery("DROP TABLE IF EXISTS `{$query['table']}_old`");
                    $currentTable = $this->db->rawQuery("SHOW TABLES LIKE '{$query['table']}'")->fetchColumn();
                    $newTable     = $this->db->rawQuery("SHOW TABLES LIKE '{$query['table']}_new'")->fetchColumn();
                    if (!empty($newTable) && !empty($currentTable)) {
                        $this->db->rawQuery("RENAME TABLE `{$query['table']}` TO `{$query['table']}_old`, `{$query['table']}_new` TO `{$query['table']}`");
                    }
                }
            } catch (\Exception $e) {
                $this->db->rollback();
                print("Failed to load {$file}\n");
                throw $e;
            } finally {
                print("Cleaning up\n");
                $this->db->exec("DROP FUNCTION IF EXISTS range_upper");
                foreach ($csvFile['files'] as $file => $query) {
                    # safe for this to fail or generate errors.
                    @unlink("{$geoipDBPath}/{$file}");
                    $this->db->rawQuery("DROP TABLE IF EXISTS `{$query['table']}_new`");
                    $this->db->rawQuery("DROP TABLE IF EXISTS `{$query['table']}_old`");
                }
            }
        }
    }
}
