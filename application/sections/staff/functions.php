<?php
function get_fls()
{
    global $Cache, $DB;
    if (($FLS = $Cache->get_value('fls')) === false) {
        $DB->query("SELECT
            m.ID,
            p.Level,
            m.Username,
                  m.Title,
            m.Paranoia,
            m.LastAccess,
            i.SupportFor
            FROM users_info AS i
            JOIN users_main AS m ON m.ID=i.UserID
            JOIN permissions AS p ON p.ID=m.PermissionID
            WHERE p.DisplayStaff!='1' AND i.SupportFor!=''
            ORDER BY m.LastAccess ASC");
        $FLS = $DB->to_array(false, MYSQLI_BOTH, array(4, 'Paranoia'));
        $Cache->cache_value('fls', $FLS, 180);
    }

    return $FLS;
}

/* -----------------------------------------------
 * 26th April: changed functions to return Mod Pervs in 'forum staff' (and not in 'staff')
 * this assumes we will not have a layer of FLS (user/helpers) then a layer of 'forum mods' who
 * are not staff then a layer of 'Mods' who are staff then senior staff... can easily change
 * back if we go that way though
 */

function get_staff()
{
    global $Cache, $DB;
    if (($ForumStaff = $Cache->get_value('staff')) === false) {
        $DB->query("SELECT
            m.ID,
            p.Level,
            p.Name,
            m.Username,
                  m.Title,
            m.Paranoia,
            m.LastAccess,
            i.SupportFor
            FROM users_main AS m
            JOIN users_info AS i ON m.ID=i.UserID
            JOIN permissions AS p ON p.ID=m.PermissionID
            WHERE p.DisplayStaff='1'
                AND p.Level >= '" . STAFF_LEVEL . "'
                AND p.Level < '" . ADMIN_LEVEL . "'
            ORDER BY p.Level, m.LastAccess ASC");
        $ForumStaff = $DB->to_array(false, MYSQLI_BOTH, array(5, 'Paranoia'));
        $Cache->cache_value('staff', $ForumStaff, 180);
    }  //	AND p.Level < 700

    return $ForumStaff;
}

function get_admins()
{
    global $Cache, $DB;
    if (($Staff = $Cache->get_value('admins')) === false) {
        $DB->query("SELECT
            m.ID,
            p.Level,
            p.Name,
            m.Username,
                  m.Title,
            m.Paranoia,
            m.LastAccess,
            i.SupportFor
            FROM users_main AS m
            JOIN users_info AS i ON m.ID=i.UserID
            JOIN permissions AS p ON p.ID=m.PermissionID
            WHERE p.DisplayStaff='1'
                AND p.Level >= '" . ADMIN_LEVEL . "'
            ORDER BY p.Level, m.LastAccess ASC");
        $Staff = $DB->to_array(false, MYSQLI_BOTH, array(5, 'Paranoia'));
        $Cache->cache_value('admins', $Staff, 180);
    } //	AND p.Level >= 700

    return $Staff;
}

function get_support()
{
    return array(
        get_fls(),
        get_staff(),
        get_admins()
    );
}

function get_user_languages($UserID)
{
    global $Cache, $DB;

    $Userlangs = $Cache->get_value('user_langs_' . $UserID);
    if ($Userlangs === false) {
        $DB->query("SELECT ul.LangID, l.code, l.flag_cc AS cc, l.language
                              FROM users_languages AS ul
                              JOIN languages AS l ON l.ID=ul.LangID
                             WHERE UserID=$UserID");
        $Userlangs = $DB->to_array('LangID', MYSQL_ASSOC);
        $Cache->cache_value('user_langs_' . $UserID, $Userlangs);
    }
    $Str = '';
    if ($Userlangs) {
        $Str = '<span class="languages" style="float:right;">';
        foreach ($Userlangs as $langresult) {
            if ($langresult['cc'])
                $Str .= '<img style="vertical-align: bottom" alt="['.$langresult['code'].']" title="['.$langresult['code'].'] '.$langresult['language'].'" src="//'. SITE_URL . '/static/common/flags/iso16/'.$langresult['cc'].'.png" />&nbsp;';
            else
                $Str .= '<span class="language" title="['.$langresult['code'].'] '.$langresult['language'].'" >['.$langresult['code'].']&nbsp;';
        }
        $Str .= '</span>';
    }

    return $Str;
}
