<?php
/********************************************************************************
 ************ Permissions form ********************** user.php and tools.php ****
 ********************************************************************************
 ** This function is used to create both the class permissions form, and the   **
 ** user custom permissions form.					       **
 ********************************************************************************/

function preg_grep_keys($pattern, $input, $flags = 0) {
    return array_intersect_key($input, array_flip(preg_grep($pattern, array_keys($input), $flags)));
}

// Not a class... dafuq it doing in classes?
function permissions_form() {
$PermissionsArray = [
    'site_force_anon_uploaders'          => 'Hide all uploader info from this user',
    'site_leech'                         => 'Can leech (Does this work?).',
    'site_upload'                        => 'Upload torrent access.',
    'site_upload_anon'                   => 'Can upload anonymously',
    'site_edit_torrents'                 => 'Can edit own torrents',
    'site_edit_override_timelock'        => 'Can edit own torrents after edit timelock',
    'site_use_templates'                 => 'Can use templates.',
    'site_make_private_templates'        => 'Can make/delete private upload templates.',
    'site_make_public_templates'         => 'Can make public upload templates.',
    'site_edit_public_templates'         => 'Can edit other\'s public upload templates.',
    'site_delete_any_templates'          => 'Can delete any upload templates.',
    'site_view_stats'                    => 'View the site stats page.',
    'site_stats_advanced'                => 'View selected site stats.',
    'site_vote'                          => 'Can vote on requests.',
    'site_submit_requests'               => 'Can submit requests.',
    'site_see_old_requests'              => 'Can see old requests.',
    'site_staff_page'                    => 'Can see the Staff page.',
    'site_advanced_search'               => 'Can use advanced search.',
    'site_top10'                         => 'Can access top 10.',
    'site_advanced_top10'                => 'Advanced Top 10 access.',
    'site_torrents_notify'               => 'Notifications access.',
    'site_collages_create'               => 'Can create collages.',
    'site_collages_delete'               => 'Can delete collages.',
    'site_collages_subscribe'            => 'Collage subscription access.',
    'site_collages_personal'             => 'Can have a personal collage.',
    'site_collages_renamepersonal'       => 'Can rename own personal collages.',
    'site_make_bookmarks'                => 'Bookmarks access.',
    'site_can_invite_always'             => 'Can invite past user limit.',
    'site_send_unlimited_invites'        => 'Unlimited invites.',
    'site_advanced_tags'                 => 'Advanced bbcode tags.',
    'site_skip_imgwhite'                 => 'Can use Imagehosts not on the Whitelist.',
    'site_edit_own_posts'                => 'Can edit own posts in forum after edit time limit.',
    'site_ignore_floodcheck'             => 'Can post more often than floodcheck allows',
    'site_moderate_requests'             => 'Request moderation access.',
    'site_polls_create'                  => 'Can create polls in the forums.',
    'site_polls_moderate'                => 'Can feature and close polls.',
    'site_moderate_forums'               => 'Forum moderation access.',
    'site_admin_forums'                  => 'Forum administrator access.',
    'site_forums_double_post'            => 'Can double post in the forums.',
    'site_view_flow'                     => 'Can view stats and data pools.',
    'site_view_full_log'                 => 'Can view old log entries.',
    'site_view_torrent_snatchlist'       => 'Can view torrent snatchlists.',
    'site_view_torrent_peerlist'         => 'Can view torrent peerlists.',
    'site_vote_tag'                      => 'Can vote on tags.',
    'site_add_tag'                       => 'Can add tags.',
    'site_add_multiple_tags'             => 'Can add multiple tags at once.',
    'site_delete_tag'                    => 'Can delete tags.',
    'site_vote_tag_enhanced'             => 'Has extra tag voting power (&plusmn;'. ENHANCED_VOTE_POWER . ')',
    'site_disable_ip_history'            => 'Disable IP history.',
    'site_zip_downloader'                => 'Download multiple torrents at once.',
    'site_debug'                         => 'Developer access.',
    'site_proxy_images'                  => 'Image proxy & Anti-Canary.',
    'site_search_many'                   => 'Can go past low limit of search results.',
    'site_give_specialgift'              => 'Can give a special gift.',
    'site_play_slots'                    => 'Can play the slot machine.',
    'site_set_language'                  => 'Can set own user language(s) in settings',
    'site_torrent_signature'             => 'Can set and use a torrent signature',
    'site_collages_manage'               => 'Can manage any collage.',
    'site_collages_recover'              => 'Can recover \'deleted\' collages.',
    'site_project_team'                  => 'Is part of the project team.',

     //-------------------------
    'users_edit_usernames'               => 'Can edit usernames.',
    'users_edit_ratio'                   => 'Can edit other\'s upload/download amounts.',
    'users_edit_own_ratio'               => 'Can edit own upload/download amounts.',
    'users_edit_tokens'                  => 'Can edit other\'s FLTokens (Slots?)',
    'users_edit_own_tokens'              => 'Can edit own FLTokens (Slots?)',
    'users_edit_pfl'                     => 'Can edit other\'s personal freeleech',
    'users_edit_own_pfl'                 => 'Can edit own personal freeleech',
    'users_edit_credits'                 => 'Can edit other\'s Bonus Credits',
    'users_edit_own_credits'             => 'Can edit own Bonus Credits',
    'users_edit_titles'                  => 'Can edit titles.',
    'users_edit_avatars'                 => 'Can edit avatars.',
    'users_edit_badges'                  => 'Can edit other\s badges.',
    'users_edit_own_badges'              => 'Can edit own badges.',
    'users_edit_invites'                 => 'Can edit invite numbers and cancel sent invites.',
    'users_edit_watch_hours'             => 'Can edit contrib watch hours.',
    'users_edit_reset_keys'              => 'Can reset passkey/authkey.',
    'users_edit_profiles'                => 'Can edit anyone\'s profile.',
    'users_view_friends'                 => 'Can view anyone\'s friends.',
    'users_reset_own_keys'               => 'Can reset own passkey/authkey.',
    'users_edit_password'                => 'Can change passwords.',
    'users_edit_email'                   => 'Can change user email address.',
    'users_promote_below'                => 'Can promote users to below current level.',
    'users_promote_to'                   => 'Can promote users up to current level.',
    'users_group_permissions'            => 'Can manage group permissions.',
    'users_view_donor'                   => 'Can view users my donations page.',
    'users_give_donor'                   => 'Can manually give donor status.',
    'users_warn'                         => 'Can warn users.',
    'users_disable_users'                => 'Can disable users.',
    'users_disable_posts'                => 'Can disable users\' posting rights.',
    'users_disable_any'                  => 'Can disable any users\' rights.',
    'users_delete_users'                 => 'Can delete users.',
    'users_view_invites'                 => 'Can view who user has invited.',
    'users_view_seedleech'               => 'Can view what a user is seeding or leeching.',
    'users_view_bonuslog'                => 'Can view bonus logs.',
    'users_view_uploaded'                => 'Can view a user\'s uploads, regardless of privacy level.',
    'users_view_keys'                    => 'Can view passkeys.',
    'users_view_ips'                     => 'Can view IP addresses.',
    'users_view_email'                   => 'Can view email addresses.',
    'users_override_paranoia'            => 'Can override paranoia.',
    'users_logout'                       => 'Can log users out (old?).',
    'users_make_invisible'               => 'Can make users invisible.',
    'users_mod'                          => 'Basic moderator tools.',
    'users_fls'                          => 'Basic support tools.',
    'users_admin_notes'                  => 'Can edit Admin comment.',
    'users_groups'                       => 'Can use Group tools.',
    'users_manage_cheats'                => 'Can manage watchlist.',
    'users_set_suppressconncheck'        => 'Can set Suppress ConnCheck prompt for users.',
    'users_view_language'                => 'Can view user language(s) on user profile',
    'users_view_anon_uploaders'          => 'Can view anonymous uploaders names.',

     //-------------------------
    'torrents_edit_override_timelock'    => 'Can edit own torrents after edit timelock.',
    'torrents_edit'                      => 'Can edit any torrent.',
    'torrents_review'                    => 'Can mark torrents for deletion.',
    'torrents_review_override'           => 'Can overide ongoing marked for deletion process.',
    'torrents_review_manage'             => 'Can set site options for marked for deletion list.',
    'torrents_download_override'         => 'Can download torrents that are marked for deletion.',
    'torrents_delete'                    => 'Can delete torrents.',
    'torrents_delete_fast'               => 'Can delete more than 3 torrents at a time.',
    'torrents_freeleech'                 => 'Can make torrents freeleech.',
    'torrents_search_fast'               => 'Rapid search (for scripts).',
    'torrents_hide_dnu'                  => 'Hide the Do Not Upload list by default.',
    'torrents_hide_imagehosts'           => 'Hide the Imagehost Whitelist list by default.',

     //-------------------------
    'admin_manage_site_options'          => 'Can manage site options',
    'admin_manage_languages'             => 'Can manage the official site languages',
    'admin_email_blacklist'              => 'Can manage the email blacklist',
    'admin_manage_cheats'                => 'Can admin watchlist.',
    'admin_manage_categories'            => 'Can manage categories.',
    'admin_manage_news'                  => 'Can manage news.',
    'admin_manage_articles'              => 'Can manage articles',
    'admin_manage_blog'                  => 'Can manage blog.',
    'admin_manage_polls'                 => 'Can manage polls.',
    'admin_manage_forums'                => 'Can manage forums (add/edit/delete).',
    'admin_manage_fls'                   => 'Can manage FLS.',
    'admin_manage_tags'                  => 'Can manage official tag list and synonyms.',
    'admin_convert_tags'                 => 'Can convert tags to synonyms.',
    'admin_manage_shop'                  => 'Can manage shop.',
    'admin_manage_badges'                => 'Can manage badges.',
    'admin_manage_awards'                => 'Can manage awards schedule.',
    'admin_reports'                      => 'Can access reports system.',
    'admin_advanced_user_search'         => 'Can access advanced user search.',
    'admin_create_users'                 => 'Can create users through an administrative form.',
    'admin_donor_drives'                 => 'Can view and manage donation drives.',
    'admin_donor_log'                    => 'Can view and manage the donor log.',
    'admin_donor_addresses'              => 'Can manage and enter new bitcoin addresses.',
    'admin_manage_ipbans'                => 'Can manage IP bans.',
    'admin_dnu'                          => 'Can manage do not upload list.',
    'admin_imagehosts'                   => 'Can manage Imagehost Whitelist.',
    'admin_clear_cache'                  => 'Can clear cached.',
    'admin_whitelist'                    => 'Can manage the list of allowed clients.',
    'admin_manage_permissions'           => 'Can edit permission classes/user permissions.',
    'admin_schedule'                     => 'Can run the site schedule.',
    'admin_login_watch'                  => 'Can manage login watch.',
    'admin_manage_wiki'                  => 'Can manage wiki access.',
    'admin_update_geoip'                 => 'Can update geoip data.',
    'admin_data_viewer'                  => 'Can access data viewer.',
    'admin_stealth_resolve'              => 'Can stealth resolve.',
    'admin_staffpm_stats'                => 'Can view StaffPM stats.',
];

    $Sections = [];
    foreach($PermissionsArray as $Permission => $Description) {
        preg_match('/^(.*?)_/', $Permission, $Section);

        // Didn't match anything?
        if (empty($Section)) continue;
        $Section = $Section[1];

        // Already enumerated this section?
        if (in_array($Section, $Sections)) continue;

        // New section
        $Sections[] = $Section;
    }

?>
    <div class="permissions">
<?php
    foreach ($Sections as $Section) {
        $Permissions = preg_grep_keys('/^'.$Section.'/', $PermissionsArray);
?>
        <div class="permission_container">
            <table>
                <tr>
                    <td class="colhead"><?=ucfirst($Section)?></td>
                </tr>
                <tr>
                    <td>
<?php
        foreach($Permissions as $Permission => $Description) display_perm($Permission, $Description, "$Description [$Permission]");
?>
                    </td>
                </tr>
            </table>
        </div>
<?php
    }
?>
    </div>
    <div class="submit_container"><input type="submit" name="submit" value="Save Permission Class" /></div>
</div>
<?php }
