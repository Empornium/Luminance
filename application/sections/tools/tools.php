<?php
show_header('Staff Tools');
?>
<div class="permissions">
    <div class="permission_container">
            <div class="head">Managers</div>
        <table>
<?php    if (check_perms('admin_manage_articles')) { ?>
            <tr><td><a href="tools.php?action=articles">Articles</a></td></tr>
<?php  } if (check_perms('site_manage_awards')) { ?>
            <tr><td><a href="tools.php?action=awards_auto">Automatic Awards</a></td></tr>
<?php  } if (check_perms('site_manage_badges')) { ?>
            <tr><td><a href="tools.php?action=badges_list">Badges</a></td></tr>
<?php  } if (check_perms('site_manage_shop')) { ?>
            <tr><td><a href="tools.php?action=shop_list">Bonus Shop</a></td></tr>
<?php  } if (check_perms('admin_manage_categories')) { ?>
            <tr><td><a href="tools.php?action=categories">Categories</a></td></tr>
<?php  } if (check_perms('admin_whitelist')) { ?>
            <tr><td><a href="tools.php?action=client_blacklist">Client Blacklist</a></td></tr>
<?php  } if (check_perms('admin_dnu')) { ?>
            <tr><td><a href="tools.php?action=dnu">Do not upload list</a></td></tr>
<?php  } if (check_perms('admin_email_blacklist')) { ?>
            <tr><td><a href="tools.php?action=email_blacklist">Email Blacklist</a></td></tr>
<?php  } if (check_perms('admin_manage_forums')) { ?>
            <tr><td><a href="tools.php?action=forum">Forums</a></td></tr>
<?php  } if (check_perms('admin_imagehosts')) { ?>
            <tr><td><a href="tools.php?action=imghost_whitelist">Imagehost Whitelist</a></td></tr>
<?php  } if (check_perms('admin_manage_ipbans')) { ?>
            <tr><td><a href="tools.php?action=ip_ban">IP Bans</a></td></tr>

<?php  } if (check_perms('admin_login_watch') || check_perms('admin_manage_ipbans')) { ?>
            <tr><td><a href="tools.php?action=login_watch">Login Watch</a></td></tr>
<?php  } if (check_perms('users_mod')) { ?>
            <tr><td><a href="tools.php?action=tokens">Manage freeleech tokens</a></td></tr>
<?php  } if (check_perms('torrents_review')) { ?>
            <tr><td><a href="tools.php?action=marked_for_deletion">Marked for Deletion</a></td></tr>

<?php  } if (check_perms('admin_manage_news')) { ?>
            <tr><td><a href="tools.php?action=news">News</a></td></tr>
<?php  } if (check_perms('site_manage_tags')) { ?>
            <tr><td><a href="tools.php?action=official_tags">Official Tags Manager</a></td></tr>
<?php  } if (check_perms('site_convert_tags')) { ?>
            <tr><td><a href="tools.php?action=official_synonyms">Official Synonyms Manager</a></td></tr>
<?php  } if (check_perms('users_fls')) { ?>
            <tr><td><a href="torrents.php?action=allcomments">Recent Torrent Comments</a></td></tr>
<?php  } if (check_perms('users_fls')) { ?>
            <tr><td><a href="requests.php?action=allcomments">Recent Request Comments</a></td></tr>
<?php  } if (check_perms('users_fls')) { ?>
            <tr><td><a href="forums.php?action=allposts">Recent Posts</a></td></tr>
<?php  } if (check_perms('users_manage_cheats')) { ?>
            <tr><td><a href="tools.php?action=speed_cheats">Speed Cheats</a></td></tr>
<?php  } if (check_perms('users_manage_cheats')) { ?>
            <tr><td><a href="tools.php?action=speed_records">Speed Reports</a></td></tr>
<?php  } if (check_perms('admin_manage_languages')) { ?>
            <tr><td><a href="tools.php?action=languages">Site Languages</a></td></tr>
<?php  } if (check_perms('admin_manage_site_options')) { ?>
                        <tr><td><a href="tools.php?action=site_options">Site Options</a></td></tr>
<?php  } if (check_perms('admin_manage_permissions')) { ?>
            <tr><td><a href="tools.php?action=permissions">User Classes<!--Permissions--></a></td></tr>
<?php  } if (check_perms('users_groups')) { ?>
            <tr><td><a href="groups.php">User Groups</a></td></tr>
<?php  } ?>
        </table>
    </div>
    <div class="permission_container">
            <div class="head">Data</div>
        <table>
<?php
if (check_perms('admin_donor_addresses')) { ?>
            <tr><td><a href="tools.php?action=btc_address_input" title="Input freshly generated bitcoin addresses for users to donate to">Bitcoin addresses</a></td></tr>
<?php  } if (check_perms('admin_donor_log')) { ?>
            <tr><td><a href="tools.php?action=donation_log" title="View bitcoin donation log">Donation Log</a></td></tr>
<?php  } if (check_perms('admin_donor_drives')) { ?>
            <tr><td><a href="tools.php?action=donation_drives" title="Manage Donation Drives">Donation Drives</a></td></tr>

<?php  } if (check_perms('users_view_ips') && check_perms('users_view_email')) { ?>
            <tr><td><a href="tools.php?action=registration_log">Registration Log</a></td></tr>
<?php  } if (check_perms('users_view_invites')) { ?>
            <tr><td><a href="tools.php?action=invite_pool">Invite Pool</a></td></tr>
<?php  } if (check_perms('site_view_flow')) { ?>
            <tr><td><a href="tools.php?action=upscale_pool">Upscale Pool</a></td></tr>
            <tr><td><a href="tools.php?action=user_flow">User Flow</a></td></tr>
            <tr><td><a href="tools.php?action=torrent_stats">Torrent Stats</a></td></tr>
            <tr><td><a href="tools.php?action=economic_stats">Economic Stats</a></td></tr>
<?php  } if (check_perms('site_debug')) { ?>
            <tr><td><a href="tools.php?action=opcode_stats">Opcode Stats</a></td></tr>
            <tr><td><a href="tools.php?action=service_stats">Service Stats</a></td></tr>
<?php  } if (check_perms('admin_manage_permissions')) { ?>
            <tr><td><a href="tools.php?action=special_users">Special Users</a></td></tr>
<?php  } if (check_perms('admin_data_viewer')) { ?>
            <tr><td><a href="tools.php?action=data_viewer">Data Viewer</a></td></tr>

<?php  } ?>
        </table>
    </div>
    <div class="permission_container">
            <div class="head">Misc</div>
        <table>

<?php  if (check_perms('users_mod')) { ?>
            <tr><td><a href="tools.php?action=manipulate_tree">Manipulate Tree</a></td></tr>
<?php  }
if (check_perms('admin_update_geoip')) {
    ?>
            <tr><td><a href="tools.php?action=repair_geoip">Repair GeoIP </a></td></tr>
<?php  } if (check_perms('admin_create_users')) { ?>
            <tr><td><a href="tools.php?action=create_user">Create User</a></td></tr>
<?php  } if (check_perms('admin_clear_cache')) { ?>
            <tr><td><a href="tools.php?action=clear_cache">Clear/view a cache key</a></td></tr>
<?php  } if (check_perms('users_view_ips')) { ?>
            <tr><td><a href="tools.php?action=dupe_ips">Duplicate IPs</a></td></tr>
            <tr><td><a href="tools.php?action=banned_ip_users">Returning Dupe IPs</a></td></tr>

            <tr><td><a href="tools.php?action=dupe_ips_old">Old Duplicate IPs</a></td></tr>
<?php  } if (check_perms('site_debug')) { ?>
            <tr><td><a href="schedule.php?auth=<?=$LoggedUser['AuthKey']?>" onClick="return confirm('Are you sure you want to run the site schedule (may take minutes to complete)?');">Schedule</a></td></tr>
            <tr><td><a href="tools.php?action=branches">Git branches</a></td></tr>
<?php  }
 // screw these stupid sandbox links... if a debugger really needs to use one they can use the url manually
?>
            <tr><td><a href="tools.php?action=sandbox1" title="Preview bonus credit forumlas (safe to press)">Sandbox (1)</a></td></tr>

<?php  if (check_perms('site_debug')) { ?>

            <tr><td><a href="tools.php?action=sandbox2" title="GeoIP condenser - you will need to edit the code to make this useable">Sandbox (2)</a></td></tr>
            <tr><td><a href="tools.php?action=sandbox3" title="torrent title fixer (safe)">Sandbox (3)</a></td></tr>

<?php  }   ?>
        </table>
    </div>
    <div class="clear"></div>
</div>
<?php
show_footer();
