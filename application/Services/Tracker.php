<?php
namespace Luminance\Services;

use Luminance\Core\Master;
use Luminance\Core\Service;
use Luminance\Errors\ConfigurationError;
use Luminance\Errors\SystemError;

class Tracker extends Service {

    const STATS_MAIN   = 0;
    const STATS_USER   = 1;
    const STATS_DB     = 2;
    const STATS_DOMAIN = 3;

    protected $Requests = array();

    protected static $useServices = [
        'cache'    => 'Cache',
        'options'  => 'Options',
        'settings' => 'Settings',
    ];

    // only include options here that can be passed to the tracker
    // (other site based tracker options can be inserted into options manager display with section = 'tracker' param)
    protected static $defaultOptions = [

        'EnableIPv6Tracker'            => ['value' => false,
                                           'section' => 'tracker',
                                           'displayRow' => 1,
                                           'displayCol' => 1,
                                           'type' => 'bool',
                                           'updateTracker' => true,
                                           'description' => 'Track IPv6 Peers'],

        'SitewideFreeleechStartTime'   => ['value' => '0000-00-00 00:00:00',
                                           'section' => 'tracker',
                                           'displayRow' => 2,
                                           'displayCol' => 1,
                                           'type' => 'date',
                                           'updateTracker' => true,
                                           'description' => 'Sitewide Freeleech Start Time'],

        'SitewideFreeleechEndTime'     => ['value' => '0000-00-00 00:00:00',
                                           'section' => 'tracker',
                                           'displayRow' => 2,
                                           'displayCol' => 2,
                                           'type' => 'date',
                                           'updateTracker' => true,
                                           'description' => 'Sitewide Freeleech End Time'],

        'SitewideFreeleechMode'        => ['value' => 'off',
                                           'section' => 'tracker',
                                           'displayRow' => 2,
                                           'displayCol' => 3,
                                           'type' => 'enum',
                                           'validation' => ['inarray' => ['off', 'timed', 'perma']],
                                           'updateTracker' => true,
                                           'description' => 'Sitewide Freeleech Mode'],

        'SitewideDoubleseedStartTime'  => ['value' => '0000-00-00 00:00:00',
                                           'section' => 'tracker',
                                           'displayRow' => 3,
                                           'displayCol' => 1,
                                           'type' => 'date',
                                           'updateTracker' => true,
                                           'description' => 'Sitewide Doubleseed Start Time'],

        'SitewideDoubleseedEndTime'    => ['value' => '0000-00-00 00:00:00',
                                           'section' => 'tracker',
                                           'displayRow' => 3,
                                           'displayCol' => 2,
                                           'type' => 'date',
                                           'updateTracker' => true,
                                           'description' => 'Sitewide Doubleseed End Time'],

        'SitewideDoubleseedMode'       => ['value' => 'off',
                                           'section' => 'tracker',
                                           'displayRow' => 3,
                                           'displayCol' => 3,
                                           'type' => 'enum',
                                           'validation' => ['inarray' => ['off', 'timed', 'perma']],
                                           'updateTracker' => true,
                                           'description' => 'Sitewide Doubleseed Mode'],
    ];


    public function options($name, $value) {
        if (!array_key_exists($name, self::$defaultOptions)) return false;

        switch (self::$defaultOptions[$name]['type']) {
            case 'date':  // actually a timestamp by the time it gets to here
                if (!is_number($value)) return false;
                break;
            case 'bool':
                if (!is_bool($value)) return false;
                break;
            case 'enum':
                if (!in_array($value, self::$defaultOptions[$name]['validation']['inarray'])) {
                    //debug only
                    //write_log("Failed to validate option: $name -> $value");
                    return false;
                }
                break;
            default:
                return false;
        }
        $params = ['set' => $name, 'value' => $value];
        return $this->update('options', $params);
    }



    public function validateParams($params) {
        foreach ($params as $key => $value) {
            switch ($key) {
                case 'id':
                case 'userid':
                case 'new_announce_interval':
                    // > 0
                    if (!$value || !is_number($value)) return false;
                    break;
                case 'info_hash':
                    // 40 digit hex (when unpacked)
                    $unp = unpack("H*", $value)[1];
                    if (!preg_match('/^[0-9a-f]{40}$/i', $unp)) {
                        //debug only
                        //write_log("Failed to validate infohash: $unp");
                        return false;
                    }
                    break;
                case 'info_hashes':
                    // 40 digit hex * 1->many (when unpacked)
                    $unp = unpack("H*", $value)[1];
                    if (!preg_match('/^([0-9a-f]{40})+$/i', $unp)) {
                        //debug only
                        //write_log("Failed to validate infohashes: $unp");
                        return false;
                    }
                    break;
                case 'freetorrent':
                    if (!in_array($value, [0,1,2])) return false;
                    break;
                case 'doubletorrent':
                case 'can_leech':
                case 'visible':
                case 'track_ipv6':
                    if (!in_array($value, [0,1])) return false;
                    break;
                case 'time':
                    // >= 0
                    if (!is_number($value)) return false;
                    break;
                case 'passkey':
                case 'oldpasskey':
                case 'newpasskey':
                    // 32 digit alphanumeric
                    if (!preg_match('/^[0-9a-z]{32}$/i', $value)) {
                        //debug only
                        //write_log("Failed to validate passkey: $value");
                        return false;
                    }
                    break;
                case 'passkeys':
                    // 32 digit alphanumeric * 1->many
                    if (!preg_match('/^([0-9a-z]{32})+$/i', $value)) {
                        //debug only
                        //write_log("Failed to validate passkeys: $value");
                        return false;
                    }
                    break;
                case 'peer_id':
                case 'old_peer_id':
                case 'new_peer_id':
                    // string up to 20 bytes
                    if (strlen($value)>20) return false;
                    break;
                default:
                    return false;
            }
        }
        return true;
    }

    /**
     * Send a request to add a torrent to the tracker
     * @param $torrentID   int    The torrent ID of the torrent to add
     * @param $infohash    string The torrent info hash (should not be rawurlencoded first)
     * @param $freetorrent int    FL status, 0 == none, 1 == freeleech, 2 == neutralleech
     * @param $doubleseed  int    DS status, 0 == none, 1 == doubleseed
     * @return             bool   Whether the request was successfully sent
     */
    public function addTorrent($torrentID, $infohash, $freetorrent = 0, $doubleseed = 0) {
        $params = ['id'             => $torrentID,
                   'info_hash'      => $infohash,
                   'freetorrent'    => $freetorrent,
                   'doubletorrent'  => $doubleseed];

        if ($this->validateParams($params)) {
            $params['info_hash'] = rawurlencode($infohash);
            return $this->update('add_torrent', $params);
        }
        return false;
    }

    /**
     * Send a request to update a torrents status to the tracker
     * @param $infohash    string The torrent info hash (should not be rawurlencoded first)
     * @param $freetorrent int    FL status, 0 == none, 1 == freeleech, 2 == neutralleech
     * @param $doubleseed  int    DS status, 0 == none, 1 == doubleseed
     * @return             bool   Whether the request was successfully sent
     */
    public function updateTorrent($infohash, $freetorrent = 0, $doubleseed = 0) {
        $params = ['info_hash'      => $infohash,
                   'freetorrent'    => $freetorrent,
                   'doubletorrent'  => $doubleseed];

        if ($this->validateParams($params)) {
            $params['info_hash'] = rawurlencode($infohash);
            return $this->update('update_torrent', $params);
        }
        return false;
    }

    /**
     * Send a request to update multiple torrents status to the tracker
     * @param $infohash    string The torrent info hashes appended end to end, no separators (should not be rawurlencoded first)
     * @param $freetorrent int    FL status, 0 == none, 1 == freeleech, 2 == neutralleech, will be set for all torrents
     * @param $doubleseed  int    DS status, 0 == none, 1 == doubleseed, will be set for all torrents
     * @return             bool   Whether the request was successfully sent
     */
    public function updateTorrents($infohashes, $freetorrent = 0, $doubleseed = 0) {
        $params = ['info_hashes'    => $infohashes,
                   'freetorrent'    => $freetorrent,
                   'doubletorrent'  => $doubleseed];

        if ($this->validateParams($params)) {
            $params['info_hashes'] = rawurlencode($infohashes);
            return $this->update('update_torrents', $params);
        }
        return false;
    }

    /**
     * Send a request to the tracker to add a FreeLeech token for a user
     * @param $infohash string The torrent info hash (should not be rawurlencoded first)
     * @param $userid   int    The userID of the user this token is for
     * @param $time     int    A utc timestamp for the datetime the token ends
     * @return          bool   Whether the request was successfully sent
     */
    public function addTokenFreeleech($infohash, $userid, $time) {
        $params = ['info_hash'  => $infohash,
                   'userid'     => $userid,
                   'time'       => $time];

        if ($this->validateParams($params)) {
            $params['info_hash'] = rawurlencode($infohash);
            return $this->update('add_token_fl', $params);
        }
        return false;
    }

    /**
     * Send a request to the tracker to add a DoubleSeed token for a user
     * @param $infohash string The torrent info hash (should not be rawurlencoded first)
     * @param $userid   int    The userID of the user this token is for
     * @param $time     int    A utc timestamp for the datetime the token ends
     * @return          bool   Whether the request was successfully sent
     */
    public function addTokenDoubleseed($infohash, $userid, $time) {
        $params = ['info_hash'  => $infohash,
                   'userid'     => $userid,
                   'time'       => $time];

        if ($this->validateParams($params)) {
            $params['info_hash'] = rawurlencode($infohash);
            return $this->update('add_token_ds', $params);
        }
        return false;
    }

    /**
     * Send a request to the tracker to remove tokens for a user
     * @param $infohash string The torrent info hash (should not be rawurlencoded first)
     * @param $userid   int    The userID of the user to remove tokens for
     * @return          bool   Whether the request was successfully sent
     */
    public function removeTokens($infohash, $userid) {
        $params = ['info_hash'  => $infohash,
                   'userid'     => $userid];

        if ($this->validateParams($params)) {
            $params['info_hash'] = rawurlencode($infohash);
            return $this->update('remove_tokens', $params);
        }
        return false;
    }

    /**
     * Send a request to the tracker to remove a torrent
     * @param $infohash string The torrent info hash (should not be rawurlencoded first)
     * @return          bool   Whether the request was successfully sent
     */
    public function deleteTorrent($infohash) {
        // @param $reason int The reason for deleting
        // a code for the delete reason - atm the codes defined in the tracker are whatcd related
        // ['reason'  => $reason];
        $params = ['info_hash'  => $infohash];

        if ($this->validateParams($params)) {
            $params['info_hash'] = rawurlencode($infohash);
            return $this->update('delete_torrent', $params);
        }
        return false;
    }

    /**
     * Send a request to the tracker to add a user
     * @param $passkey string(32) The passkey hash for this user
     * @param $userid  int        The userID of the user to add
     * @return         bool       Whether the request was successfully sent
     */
    public function addUser($passkey, $userid) {
        $params = ['passkey'  => $passkey,
                   'id'       => $userid];

        if ($this->validateParams($params)) {
            return $this->update('add_user', $params);
        }
        return false;
    }

    /**
     * Send a request to the tracker to change a users passkey
     * @param $oldpasskey string(32) The current passkey hash for this user
     * @param $newpasskey string(32) The new passkey hash for this user
     * @return            bool       Whether the request was successfully sent
     */
    public function changePasskey($oldpasskey, $newpasskey) {
        $params = ['oldpasskey'  => $oldpasskey,
                   'newpasskey'  => $newpasskey];

        if ($this->validateParams($params)) {
            return $this->update('change_passkey', $params);
        }
        return false;
    }

    /**
     * Send a request to the tracker to update user options
     * @param $passkey     string(32) The passkey hash for this user
     * @param $canleech    int        Can this user leech, 0 == no, 1 == yes
     * @param $visible     int        Include user in swarms 0 == no, 1 == yes
     * @param $track_ipv6  int        Track user's IPv6 address 0 == no, 1 == yes
     * @return             bool       Whether the request was successfully sent
     */
    public function updateUser($passkey, $canleech = null, $visible = null, $trackipv6 = null) {
        $params = ['passkey'    => $passkey,
                   'can_leech'  => $canleech,
                   'visible'    => $visible,
                   'track_ipv6' => $trackipv6];

        // Filter out unspecified parameters
        foreach ($params as $key => $value) {
            if (is_null($value)) unset($params[$key]);
        }

        if ($this->validateParams($params)) {
            return $this->update('update_user', $params);
        }
        return false;
    }

    /**
     * Send a request to the tracker to remove a user
     * @param $passkey string(32) The passkey hash for this user
     * @return         bool       Whether the request was successfully sent
     */
    public function removeUser($passkey) {
        $params = ['passkey'  => $passkey];

        if ($this->validateParams($params)) {
            return $this->update('remove_user', $params);
        }
        return false;
    }

    /**
     * Send a request to the tracker to remove a user
     * @param $passkey string(32) The passkey hash for this user
     * @return         bool       Whether the request was successfully sent
     */
    public function removeUsers($passkeys) {
        $params = ['passkeys'  => $passkeys];

        if ($this->validateParams($params)) {
            return $this->update('remove_users', $params);
        }
        return false;
    }

    /**
     * Send a request to the tracker to set PFL for a user
     * @param $passkey  string(32)  The passkey hash for this user
     * @param $time     int         A utc timestamp for the datetime the PFL ends
     * @return          bool        Whether the request was successfully sent
     */
    public function setPersonalFreeleech($passkey, $time) {
        $params = ['passkey'  => $passkey,
                   'time'     => $time];

        if ($this->validateParams($params)) {
            return $this->update('set_personal_freeleech', $params);
        }
        return false;
    }

    /**
     * Send a request to the tracker to set PDS for a user
     * @param $passkey  string(32)  The passkey hash for this user
     * @param $time     int         A utc timestamp for the datetime the PDS ends
     * @return          bool        Whether the request was successfully sent
     */
    public function setPersonalDoubleseed($passkey, $time) {
        $params = ['passkey'  => $passkey,
                   'time'     => $time];

        if ($this->validateParams($params)) {
            return $this->update('set_personal_doubleseed', $params);
        }
        return false;
    }

    /**
     * Send a request to the tracker to add a peerid to the client blacklist
     * @param $peerid int   The peerid to add
     * @return        bool  whether the request was successfully sent
     */
    public function addBlacklist($peerid) {
        $params = ['peer_id'  => $peerid];

        if ($this->validateParams($params)) {
            return $this->update('add_blacklist', $params);
        }
        return false;
    }

    /**
     * Send a request to the tracker to remove a peerid from the client blacklist
     * @param $peerid int   The peerid to remove
     * @return        bool  whether the request was successfully sent
     */
    public function removeBlacklist($peerid) {
        $params = ['peer_id'  => $peerid];

        if ($this->validateParams($params)) {
            return $this->update('remove_blacklist', $params);
        }
        return false;
    }

    /**
     * Send a request to the tracker to change a peerid in the client blacklist
     * @param $oldpeerid  int   The peerid to remove
     * @param $newpeerid  int   The peerid to add
     * @return            bool  whether the request was successfully sent
     */
    public function editBlacklist($oldpeerid, $newpeerid) {
        $params = ['old_peer_id'  => $oldpeerid,
                   'new_peer_id'  => $newpeerid];

        if ($this->validateParams($params)) {
            return $this->update('edit_blacklist', $params);
        }
        return false;
    }

    /**
     * Send a request to the tracker to remove tokens for a user
     * @param $announceInterval int  The announce interval for the tracker
     * @return                  bool  whether the request was successfully sent
     */
    public function updateAnnounceInterval($announceInterval) {
        $params = ['new_announce_interval'  => $announceInterval];

        if ($this->validateParams($params)) {
            return $this->update('update_announce_interval', $params);
        }
        return false;
    }

    /**
     * Send a request to the tracker to remove tokens for a user
     * @param $infohash string  The torrent info hash (should not be rawurlencoded first)
     * @return          bool    whether the request was successfully sent
     */
    public function infoTorrent($infohash) {
        $params = ['info_hash'  => $infohash];

        if ($this->validateParams($params)) {
            $params['info_hash'] = rawurlencode($infohash);
            return $this->update('info_torrent', $params);
        }
        return false;
    }


    public function globalPeerCount() {
        $Stats = $this->getStats(self::STATS_MAIN);
        if (isset($Stats['leechers tracked']) && isset($Stats['seeders tracked'])) {
            $Leechers = $Stats['leechers tracked'];
            $Seeders = $Stats['seeders tracked'];
        } else {
            return false;
        }
        return array($Leechers, $Seeders);
    }

    public function userPeerCount($TorrentPass) {
        $Stats = $this->getStats(self::STATS_USER, array('key' => $TorrentPass));
        if ($Stats === false) {
            return false;
        }
        if (isset($Stats['leeching']) && isset($Stats['seeding'])) {
            $Leeching = $Stats['leeching'];
            $Seeding = $Stats['seeding'];
        } else {
            // User doesn't exist, but don't tell anyone
            $Leeching = $Seeding = 0;
        }
        return array($Leeching, $Seeding);
    }

    public function userStats($TorrentPass) {
        return $this->getStats(self::STATS_USER, array('key' => $TorrentPass));
    }

    public function info() {
        return $this->getStats(self::STATS_MAIN);
    }

    public function dbInfo() {
        return $this->getStats(self::STATS_DB);
    }

    public function domainInfo() {
        return $this->getStats(self::STATS_DOMAIN);
    }

    public function update($Action, $Updates) {
        $Get = $this->settings->tracker->secret . "/update?action=$Action";
        foreach ($Updates as $Key => $Value) {
            $Get .= "&$Key=$Value";
        }

        if ($this->settings->site->debug_mode) {
            $MaxAttempts = 1;
        } else {
            $MaxAttempts = 3;
        }
        $Err = false;
        if ($this->send($Get, $MaxAttempts, $Err) === false) {
            if ($this->cache->get_value('tracker_error_reported') === false) {
                // nope, get can include passkeys
                //write_log("Tracker error: Failed to update radiance: $Err : $Get");
                $this->cache->cache_value('tracker_error_reported', true, $this->options->ErrorLoggingFreqS);
            }
            return false;
        }
        return true;
    }

    protected function getStats($Type, $Params = false) {
        $Get = $this->settings->tracker->reportkey . '/report?';
        if ($Type === self::STATS_MAIN) {
            $Get .= 'get=stats';
        } elseif ($Type === self::STATS_USER && !empty($Params['key'])) {
            $Get .= "get=user&key=$Params[key]";
        } elseif ($Type === self::STATS_DB) {
            $Get .= 'get=db';
        } elseif ($Type === self::STATS_DOMAIN) {
            $Get .= 'get=domain';
        } else {
            return false;
        }
        $Response = $this->send($Get);
        if ($Response === false) {
            return false;
        }
        $Stats = (array)json_decode($Response, true);
        /* $Stats = array();
        foreach (explode("\n", $Response) as $Stat) {
            list($Val, $Key) = explode(" ", $Stat, 2);
            $Stats[$Key] = $Val;
        } */
        return $Stats;
    }

    protected function send($Get, $MaxAttempts = 1, &$Err = false) {
        $Attempts = 0;
        $Sleep = 0;
        $Success = false;
        $StartTime = microtime(true);
        while (!$Success && $Attempts++ < $MaxAttempts) {
            if ($Sleep) {
                sleep($Sleep);
            }
            $Sleep = 6;
            // Only support http tracker comms for now
            $Response = '';
            $Response = @file_get_contents('http://' . $this->settings->tracker->host . ':' . $this->settings->tracker->port . '/' . $Get);

            // $http_response_header is auto-populated by file_get_contents
            if (empty($http_response_header)) {
                $Success = false;
            } else {
                $ResponseCode = substr($http_response_header[0], 9, 3);

                // Check to see if we got a response and whether or not the response was
                // a 2xx sucess response. Tracker supports 200, 204 and 500 for now.
                if (!($Response === false) && $ResponseCode >= 200 && $ResponseCode < 300) {
                    $Success = true;
                }
            }
        }
        $Request = array(
            'path' => substr($Get, strpos($Get, '/')),
            'response' => ($Success ? $Response : ''),
            'status' => ($Success ? 'ok' : 'failed'),
            'time' => 1000 * (microtime(true) - $StartTime)
        );
        $this->Requests[] = $Request;
        if ($Success) {
            return $Response;
        }
        return false;
    }
}
