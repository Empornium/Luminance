<?php
namespace Luminance\Core;

use Luminance\Responses\Response;
use Luminance\Legacy\Debug;
use Luminance\Legacy\SphinxSearch;

class LegacyHandler
{

    public $master;

    public function __construct(Master $master)
    {
        $this->master = $master;
    }

    public function handle_legacy_request($section)
    {
        $this->script_start();
        $this->load_section($section);
        $this->script_finish();
        return new Response('');
    }

    public function load_section($section)
    {
        global $AllowedProxies, $ArticleCats, $ArticleSubCats, $AttemptID, $Attempts,
            $AutoAwardTypes, $BadgesArray, $BadgeTypes, $BannedUntil, $Bans, $Browser,
            $Cache, $CaptchaBGs, $CaptchaFonts, $CatsArray, $Class, $Classes,
            $ClassLevels, $ClassNames, $CollageCats, $CollageIcons, $CurIP, $DB,
            $Debug, $Defaults, $Document, $DonateLevels, $DupeResults, $Enabled,
            $ExcludeBytesDupeCheck, $ExcludeForums, $Feed, $Forums, $ForumsDoublePost,
            $ForumsRevealVoters, $HeavyInfo, $imagefiles, $ipcc,
            $Image_FileTypes, $Languages, $LightInfo, $LoggedUser, $LoginCookie,
            $master, $Match, $Media, $Method, $Mobile, $NewCategories, $NewIP,
            $OpenCategories, $OperatingSystem, $OrderBy, $OrderWay, $Paranoia, $Payout, $Permissions,
            $PermissionsArray, $Reel, $Reels, $SessionID, $ShopActions,
            $Sitewide_Freeleech, $Sitewide_Freeleech_On, $Sitewide_Doubleseed, $Sitewide_Doubleseed_On,
            $SpecialChars, $SS, $SSL, $Text, $Time, $TorrentID, $TorrentUserStatus,
            $Tracker, $UA, $UserID, $UserSessions, $UserStats, $Values, $Video_FileTypes,
            $Zip_FileTypes;

        $Document = $section;
        require(SERVER_ROOT . '/Legacy/sections/' . $section . '/index.php');
    }

    public function script_start()
    {
        # This code was originally part of script_start.php

        # First mark a whole lot of vars global since they were previously not inside a class context
        global $SSL, $Debug, $DB, $Cache, $UA, $SS, $Browser, $OperatingSystem,
            $Mobile, $Classes, $ClassLevels, $ClassNames, $NewCategories, $OpenCategories, $LoginCookie, $SessionID,
            $LoggedUser, $UserID, $UserSessions, $Enabled, $UserStats, $LightInfo, $HeavyInfo, $Permissions,
            $ipcc, $TorrentUserStatus, $Tracker, $Document;

        $SSL = $this->master->request->ssl;

        $Debug = $this->master->debug;
        $Debug->handle_errors();
        $Debug->set_flag('Debug constructed');

        $DB = $this->master->olddb;
        $Cache = $this->master->cache;
        $Tracker = $this->master->tracker;
        $SS = $this->master->search;

        $Debug->set_flag('start user handling');

        // Get permissions
        list($Classes, $ClassLevels, $ClassNames) = $Cache->get_value('classes');
        if (!$Classes || !$ClassLevels) {
            $DB->query("SELECT ID, Name, Description, Level, Color, LOWER(REPLACE(Name,' ','')) AS ShortName, IsUserClass, MaxAvatarWidth, MaxAvatarHeight FROM permissions ORDER BY IsUserClass, Level"); //WHERE IsUserClass='1'
            $Classes = $DB->to_array('ID');
            $ClassLevels = $DB->to_array('Level');
            $ClassNames = $DB->to_array('ShortName');
            $Cache->cache_value('classes', array($Classes, $ClassLevels, $ClassNames), 0);
        }
        $Debug->set_flag('Loaded permissions');


        $NewCategories = $Cache->get_value('new_categories');
        if (!$NewCategories) {
            $DB->query('SELECT id, name, image, tag FROM categories ORDER BY name ASC');
            $NewCategories = $DB->to_array('id');
            $Cache->cache_value('new_categories', $NewCategories);
        }
        $OpenCategories = $Cache->get_value('open_categories');
        if (!$OpenCategories) {
            $DB->query("SELECT id, name, image, tag FROM categories WHERE open='1' ORDER BY name ASC");
            $OpenCategories = $DB->to_array('id');
            $Cache->cache_value('open_categories', $OpenCategories);
        }
        $Debug->set_flag('Loaded categories');


        $this->master->auth->setLegacySessionGlobals();

        $Debug->set_flag('end user handling');

        $TorrentUserStatus = $Cache->get_value('torrent_user_status_'.$LoggedUser['ID']);
        if ($TorrentUserStatus === false) {
            $TorrentUserStatus = $this->master->db->raw_query("
                SELECT fid as TorrentID,
                    IF(xbt.remaining >  '0', 'L', 'S') AS PeerStatus
                FROM xbt_files_users AS xbt
                    WHERE active='1' AND uid = :userid", [':userid' => $LoggedUser['ID']])->fetchAll(\PDO::FETCH_UNIQUE | \PDO::FETCH_ASSOC);
            $Cache->cache_value('torrent_user_status_'.$LoggedUser['ID'], $TorrentUserStatus, 600);
        }
    }

    public function script_finish()
    {
        # This code was originally part of script_start.php
        global $Debug;

        $Debug->set_flag('completed module execution');

        if (!headers_sent()) {
            /* Required in the absence of session_start() for providing that pages will change
               upon hit rather than being browser cache'd for changing content. */
            header('Cache-Control: no-cache, must-revalidate, post-check=0, pre-check=0');
            header('Pragma: no-cache');

            $Debug->set_flag('set headers and send to user');
        }

        //Attribute profiling
        $Debug->profile();
    }
}
