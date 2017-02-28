<?php
if (!extension_loaded('date')) {
    error('Date Extension not loaded.');
}

function time_ago($TimeStamp)
{
    if (!is_number($TimeStamp)) { // Assume that $TimeStamp is SQL timestamp
        if ($TimeStamp == '0000-00-00 00:00:00') {
            return false;
        }
        $TimeStamp = strtotime($TimeStamp);
    }
    if ($TimeStamp == 0) {
        return false;
    }

    return time() - $TimeStamp;
}

function time_diff($TimeStamp, $Levels=2, $Span=true, $Lowercase=false, $ForceFormat=-1)
{
    global $LoggedUser;

    if (!is_number($TimeStamp)) { // Assume that $TimeStamp is SQL timestamp
        if ($TimeStamp == '0000-00-00 00:00:00') {
            return 'Never';
        }
        $TimeStamp = strtotime($TimeStamp);
    }
    if ($TimeStamp == 0) {
        return 'Never';
    }

    if (in_array($ForceFormat, array(0, 1))) {
        $TimeFormat = $ForceFormat;
    } else {
        $TimeFormat = $LoggedUser['TimeStyle'];
    }

    if ($TimeFormat == 1 && !$Span) { // shortcut if only need plain date time format returned
        $TimeNow = date('M d Y, H:i', $TimeStamp - (int) $LoggedUser['TimeOffset']);

        return $TimeNow;
    }

    $Time = time() - $TimeStamp;

    //If the time is negative, then we know that it expires in the future
    if ($Time < 0) {
        $Time = -$Time;
        $HideAgo = true;
    }

    $Years = floor($Time / 31556926); // seconds in a year
    $Remain = $Time - $Years * 31556926;

    $Months = floor($Remain / 2629744); // seconds in a month
    $Remain = $Remain - $Months * 2629744;

    $Weeks = floor($Remain / 604800); // seconds in a week
    $Remain = $Remain - $Weeks * 604800;

    $Days = floor($Remain / 86400); // seconds in a day
    $Remain = $Remain - $Days * 86400;

    $Hours = floor($Remain / 3600);
    $Remain = $Remain - $Hours * 3600;

    $Minutes = floor($Remain / 60);
    $Remain = $Remain - $Minutes * 60;

    $Seconds = $Remain;

    $TimeAgo = '';

    if ($Years > 0 && $Levels > 0) {
        if ($Years > 1) {
            $TimeAgo .= $Years . ' years';
        } else {
            $TimeAgo .= $Years . ' year';
        }
        $Levels--;
    }

    if ($Months > 0 && $Levels > 0) {
        if ($TimeAgo != '') {
            $TimeAgo.=', ';
        }
        if ($Months > 1) {
            $TimeAgo.=$Months . ' months';
        } else {
            $TimeAgo.=$Months . ' month';
        }
        $Levels--;
    }

    if ($Weeks > 0 && $Levels > 0) {
        if ($TimeAgo != "") {
            $TimeAgo.=', ';
        }
        if ($Weeks > 1) {
            $TimeAgo.=$Weeks . ' weeks';
        } else {
            $TimeAgo.=$Weeks . ' week';
        }
        $Levels--;
    }

    if ($Days > 0 && $Levels > 0) {
        if ($TimeAgo != '') {
            $TimeAgo.=', ';
        }
        if ($Days > 1) {
            $TimeAgo.=$Days . ' days';
        } else {
            $TimeAgo.=$Days . ' day';
        }
        $Levels--;
    }

    if ($Hours > 0 && $Levels > 0) {
        if ($TimeAgo != '') {
            $TimeAgo.=', ';
        }
        if ($Hours > 1) {
            $TimeAgo.=$Hours . ' hours';
        } else {
            $TimeAgo.=$Hours . ' hour';
        }
        $Levels--;
    }

    if ($Minutes > 0 && $Levels > 0) {
        if ($TimeAgo != '') {
            $TimeAgo.=' and ';
        }
        if ($Minutes > 1) {
            $TimeAgo.=$Minutes . ' mins';
        } else {
            $TimeAgo.=$Minutes . ' min';
        }
        $Levels--;
    }

    if ($TimeAgo == '') {
        $TimeAgo = 'Just now';
    } elseif (!isset($HideAgo)) {
        $TimeAgo .= ' ago';
    }

    if ($Lowercase) {
        $TimeAgo = strtolower($TimeAgo);
    }

    if ($TimeFormat == 1) {
        $TimeNow = date('M d Y, H:i', $TimeStamp - (int) $LoggedUser['TimeOffset']);

        return '<span class="time" alt="' . $TimeAgo . '" title="' . $TimeAgo . '">' . $TimeNow . '</span>';
    } else {
        if ($Span) {
            $TimeNow = date('M d Y, H:i', $TimeStamp - (int) $LoggedUser['TimeOffset']);

            return '<span class="time" alt="' . $TimeNow . '" title="' . $TimeNow . '">' . $TimeAgo . '</span>';
        } else {
            return $TimeAgo;
        }
    }
}

/**   Returns the offset from the origin timezone to the remote timezone, in seconds.
 *    @param string $remote_tz the remote timezone ie. 'Europe/London'
 *    @param string $origin_tz origin timezone. If null the servers current timezone is used as the origin.
 *    @return int;
 */
function get_timezone_offset($remote_tz, $origin_tz = null)
{
    if ($origin_tz === null) {
        $origin_tz = "UTC";
    }
    $origin_dtz = new DateTimeZone($origin_tz);
    $remote_dtz = new DateTimeZone($remote_tz);
    $origin_dt = new DateTime("now", $origin_dtz);
    $remote_dt = new DateTime("now", $remote_dtz);
    $offset = $origin_dtz->getOffset($origin_dt) - $remote_dtz->getOffset($remote_dt);

    return $offset;
}

/* SQL utility functions */

function time_plus($Offset)
{
    return date('Y-m-d H:i:s', time() + $Offset);
}

function time_minus($Offset, $Fuzzy = false)
{
    if ($Fuzzy) {
        return date('Y-m-d 00:00:00', time() - $Offset);
    } else {
        return date('Y-m-d H:i:s', time() - $Offset);
    }
}

function sqltime($timestamp = false)
{
    if ($timestamp === false) {
        $timestamp = time();
    }

    return date('Y-m-d H:i:s', $timestamp);
}

function validDate($DateString)
{
    $DateTime = explode(" ", $DateString);
    if (count($DateTime) != 2)
        return false;
    list($Date, $Time) = $DateTime;
    $SplitTime = explode(":", $Time);
    if (count($SplitTime) != 3)
        return false;
    list($H, $M, $S) = $SplitTime;
    if ($H != 0 && !(is_number($H) && $H < 24 && $H >= 0))
        return false;
    if ($M != 0 && !(is_number($M) && $M < 60 && $M >= 0))
        return false;
    if ($S != 0 && !(is_number($S) && $S < 60 && $S >= 0))
        return false;
    $SplitDate = explode("-", $Date);
    if (count($SplitDate) != 3)
        return false;
    list($Y, $M, $D) = $SplitDate;

    return checkDate($M, $D, $Y);
}

function time_span($TimeStamp, $Levels=2, $Lowercase=false)
{
    if (!is_number($TimeStamp)) { // Assume that $TimeStamp is SQL timestamp
        if ($TimeStamp == '0000-00-00 00:00:00') {
            return 'None';
        }
        $TimeStamp = strtotime($TimeStamp);
    }
    if ($TimeStamp == 0) {
        return 'None';
    }

    $Time = $TimeStamp;

    //If the time is negative, then we know that it expires in the future
    if ($Time < 0) {
        $Time = -$Time;
        //$HideAgo = true;
    }

    $Weeks = floor($Time / 604800); // seconds in a week
    $Remain = $Time - $Weeks * 604800;

    $Days = floor($Remain / 86400); // seconds in a day
    $Remain = $Remain - $Days * 86400;

    $Hours = floor($Remain / 3600);
    $Remain = $Remain - $Hours * 3600;

    $Minutes = floor($Remain / 60);
    $Remain = $Remain - $Minutes * 60;

    $Seconds = $Remain;

    $TimeAgo = '';

    if ($Weeks > 0 && $Levels > 0) {
        if ($TimeAgo != "") {
            $TimeAgo.=', ';
        }
        if ($Weeks > 1) {
            $TimeAgo.=$Weeks . ' weeks';
        } else {
            $TimeAgo.=$Weeks . ' week';
        }
        $Levels--;
    }

    if ($Days > 0 && $Levels > 0) {
        if ($TimeAgo != '') {
            $TimeAgo.=', ';
        }
        if ($Days > 1) {
            $TimeAgo.=$Days . ' days';
        } else {
            $TimeAgo.=$Days . ' day';
        }
        $Levels--;
    }

    if ($Hours > 0 && $Levels > 0) {
        if ($TimeAgo != '') {
            $TimeAgo.=', ';
        }
        if ($Hours > 1) {
            $TimeAgo.=$Hours . ' hours';
        } else {
            $TimeAgo.=$Hours . ' hour';
        }
        $Levels--;
    }

    if ($Minutes > 0 && $Levels > 0) {
        if ($TimeAgo != '') {
            $TimeAgo.=' and ';
        }
        if ($Minutes > 1) {
            $TimeAgo.=$Minutes . ' mins';
        } else {
            $TimeAgo.=$Minutes . ' min';
        }
        $Levels--;
    }

    if ($Seconds > 0 && $Levels > 0) {
        if ($TimeAgo != '') {
            $TimeAgo.=' and ';
        }
        if ($Seconds > 1) {
            $TimeAgo.=$Seconds . ' secs';
        } else {
            $TimeAgo.=$Seconds . ' sec';
        }
        $Levels--;
    }

    if ($TimeAgo == '') {
        $TimeAgo = '1 min';
        $TimeAgo = '1 sec';
    }

    if ($Lowercase) {
        $TimeAgo = strtolower($TimeAgo);
    }

    return $TimeAgo;
}

function hoursdays($TotalHours)
{
    $Days = (int) floor($TotalHours / 24);
    $Days = ($Days > 0) ? "$Days days" : '';
    $Hours = modulos($TotalHours, 24.0);

    return "$Days $Hours hrs";
}
