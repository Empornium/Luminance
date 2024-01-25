<?php
function get_timestamp($timeStamp) {
    if ($timeStamp instanceof \DateTime) {
        $timeStamp = $timeStamp->format('Y-m-d H:i:s');
    } elseif ($timeStamp instanceof \DateInterval) {
        $now = new \DateTime;
        $timeStamp = $now->add($timeStamp);
        $timeStamp = $timeStamp->format('Y-m-d H:i:s');
    } elseif (is_integer_string($timeStamp)) {
        $timeStamp = date("Y-m-d H:i:s", $timeStamp);
    }
    return $timeStamp;
}

function time_until($timeStamp) {
    $timeStamp = get_timestamp($timeStamp);
    if (!is_integer_string($timeStamp)) { // Assume that $timeStamp is SQL timestamp
        if ($timeStamp == '0000-00-00 00:00:00' || $timeStamp === null) {
            return false;
        }
        $timeStamp = strtotime($timeStamp);
    }
    if ($timeStamp == 0) {
        return false;
    }

    return $timeStamp - time();
}

function time_ago($timeStamp) {
    $timeStamp = get_timestamp($timeStamp);
    if (!is_integer_string($timeStamp)) { // Assume that $timeStamp is SQL timestamp
        if ($timeStamp == '0000-00-00 00:00:00' || $timeStamp === null) {
            return false;
        }
        $timeStamp = strtotime($timeStamp);
    }
    if ($timeStamp == 0) {
        return false;
    }

    return time() - $timeStamp;
}


function time_span($timeStamp, $levels = 2, $lowerCase = false) {
    if ($timeStamp === false) {
        return 'Never';
    }

    $time = $timeStamp;

    //If the time is negative, then we know that it expires in the future
    if ($time < 0) {
        $time = -$time;
    }

    $years = floor($time / 31556926); // seconds in a year
    $remain = $time - $years * 31556926;

    $months = floor($remain / 2629744); // seconds in a month
    $remain = $remain - $months * 2629744;

    $weeks = floor($remain / 604800); // seconds in a week
    $remain = $remain - $weeks * 604800;

    $days = floor($remain / 86400); // seconds in a day
    $remain = $remain - $days * 86400;

    $hours = floor($remain / 3600);
    $remain = $remain - $hours * 3600;

    $minutes = floor($remain / 60);
    $remain = $remain - $minutes * 60;

    $seconds = $remain;

    $timeSpan = '';

    if ($years > 0 && $levels > 0) {
        if ($years > 1) {
            $timeSpan .= $years . ' years';
        } else {
            $timeSpan .= $years . ' year';
        }
        $levels--;
    }

    if ($months > 0 && $levels > 0) {
        if ($timeSpan != '') {
            $timeSpan.=', ';
        }
        if ($months > 1) {
            $timeSpan.=$months . ' months';
        } else {
            $timeSpan.=$months . ' month';
        }
        $levels--;
    }

    if ($weeks > 0 && $levels > 0) {
        if ($timeSpan != "") {
            $timeSpan.=', ';
        }
        if ($weeks > 1) {
            $timeSpan.=$weeks . ' weeks';
        } else {
            $timeSpan.=$weeks . ' week';
        }
        $levels--;
    }

    if ($days > 0 && $levels > 0) {
        if ($timeSpan != '') {
            $timeSpan.=', ';
        }
        if ($days > 1) {
            $timeSpan.=$days . ' days';
        } else {
            $timeSpan.=$days . ' day';
        }
        $levels--;
    }

    if ($hours > 0 && $levels > 0) {
        if ($timeSpan != '') {
            $timeSpan.=', ';
        }
        if ($hours > 1) {
            $timeSpan.=$hours . ' hours';
        } else {
            $timeSpan.=$hours . ' hour';
        }
        $levels--;
    }

    if ($minutes > 0 && $levels > 0) {
        if ($timeSpan != '') {
            $timeSpan.=' and ';
        }
        if ($minutes > 1) {
            $timeSpan.=$minutes . ' mins';
        } else {
            $timeSpan.=$minutes . ' min';
        }
        $levels--;
    }

    if ($seconds > 0 && $levels > 0) {
        if ($timeSpan != '') {
            $timeSpan.=' and ';
        }
        if ($seconds > 1) {
            $timeSpan.=$seconds . ' secs';
        } else {
            $timeSpan.=$seconds . ' sec';
        }
        $levels--;
    }

    if ($timeSpan == '') {
        $timeSpan = '1 min';
        $timeSpan = '1 sec';
    }

    if ($lowerCase) {
        $timeSpan = strtolower($timeSpan);
    }

    return $timeSpan;
}

function time_diff($timeStamp, $levels = 2, $span = true, $lowerCase = false, $forceFormat = -1) {
    global $master;

    $user = $master->request->user;
    $timeNow = get_timestamp($timeStamp);
    $timeStamp = time_ago($timeStamp);
    if ($timeStamp === false) {
        return 'Forever';
    }

    if (in_array($forceFormat, array(0, 1))) {
        $timeFormat = $forceFormat;
    } elseif ($user instanceof \Luminance\Entities\User) {
        $timeFormat = $user->options('TimeStyle');
    } else {
        $timeFormat = 0;
    }

    if ($user instanceof \Luminance\Entities\User) {
        $timeOffset = (int) $user->timeOffset;
    } else {
        $timeOffset = 0;
    }

    if ($timeFormat == 1 && !$span) { // shortcut if only need plain date time format returned
        return date('M d Y, H:i', strtotime($timeNow) - $timeOffset);
    }

    //If the time is negative, then we know that it expires in the future
    if ($timeStamp < 0) {
        $timeStamp = -$timeStamp;
        $hideAgo = true;
    }

    $timeDiff = time_span($timeStamp, $levels, $lowerCase);

    if ($timeDiff == '') {
        $timeDiff = 'Just now';
    } elseif (!isset($hideAgo)) {
        $timeDiff .= ' ago';
    }

    if ($lowerCase) {
        $timeDiff = strtolower($timeDiff);
    }

    $timeNow = date('M d Y, H:i', strtotime($timeNow) - $timeOffset);

    if ($timeFormat == 1) {
        return '<span class="time" alt="' . $timeDiff . '" title="' . $timeDiff . '">' . $timeNow . '</span>';
    } else {
        if ($span) {
            return '<span class="time" alt="' . $timeNow . '" title="' . $timeNow . '">' . $timeDiff . '</span>';
        } else {
            return $timeDiff;
        }
    }
}

/**   Returns the offset from the origin timezone to the remote timezone, in seconds.
 *    @param string $remote_tz the remote timezone ie. 'Europe/London'
 *    @param string $origin_tz origin timezone. If null the servers current timezone is used as the origin.
 *    @return int;
 */
function get_timezone_offset($remote_tz, $origin_tz = null) {
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
function time_plus($offset, $fuzzy = false) {
    if ($fuzzy) {
        return date('Y-m-d 00:00:00', time() + $offset);
    } else {
        return date('Y-m-d H:i:s', time() + $offset);
    }
}

function time_minus($offset, $fuzzy = false) {
    if ($fuzzy) {
        return date('Y-m-d 00:00:00', time() - $offset);
    } else {
        return date('Y-m-d H:i:s', time() - $offset);
    }
}

function sqltime($timestamp = false) {
    if ($timestamp === false) {
        $timestamp = time();
    }
    if (!is_integer_string($timestamp)) {
        return $timestamp;
    }
    return date('Y-m-d H:i:s', $timestamp);
}

function validDate($dateString) {
    $dateTime = explode(" ", $dateString);
    if (count($dateTime) != 2)
        return false;
    list($date, $time) = $dateTime;
    $splitTime = explode(":", $time);
    if (count($splitTime) != 3)
        return false;
    list($hours, $minutes, $seconds) = $splitTime;
    if ($hours != 0 && !(is_integer_string($hours) && $hours < 24 && $hours >= 0)) {
        return false;
    }
    if ($minutes != 0 && !(is_integer_string($minutes) && $minutes < 60 && $minutes >= 0)) {
        return false;
    }
    if ($seconds != 0 && !(is_integer_string($seconds) && $seconds < 60 && $seconds >= 0)) {
        return false;
    }
    $splitDate = explode("-", $date);
    if (count($splitDate) != 3)
        return false;
    list($years, $months, $days) = $splitDate;

    return checkDate($months, $days, $years);
}

function hoursdays($totalHours) {
    $days = (int) floor($totalHours / 24);
    $days = ($days > 0) ? "$days days" : '';
    $hours = modulos($totalHours, 24.0);

    return "$days $hours hrs";
}

function trimDate($date)
    /**
    * Trim a date to remove the time
    * Convert 2000-01-01 12:00:30 into 2000-01-01
    */
    {
        $maxLength = 10;
        if (strlen($date) > $maxLength) {
            $date = substr($date, 0, strrpos($date, ' '));
        }
        return $date;
    }
