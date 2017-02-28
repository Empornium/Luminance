<?php
// The "order by x" links on columns headers
function header_link($SortKey, $DefaultWay="desc", $Anchor="")
{
    global $Document, $OrderBy, $OrderWay;
    if ($SortKey==$OrderBy) {
        if ($OrderWay=="desc") {
            $NewWay="asc";
        } else {
            $NewWay="desc";
        }
    } else {
        $NewWay=$DefaultWay;
    }
    return "$Document.php?order_way=$NewWay&amp;order_by=$SortKey&amp;".get_url(array('order_way', 'order_by')).$Anchor;
}

function view_link($View, $ViewKey, $LinkCode)
{
    $Link  = ($View==$ViewKey)? "<b>":"";
    $Link .= "[$LinkCode] &nbsp";
    $Link .= ($View==$ViewKey)? "</b>":"";
    return $Link;
}

// Very useful function from "phpdotnet at m4tt dot co dot uk" http://php.net/manual/en/function.sort.php
function array_sort($array, $on, $order=SORT_ASC)
{
    $new_array = array();
    $sortable_array = array();

    if (count($array) > 0) {
        foreach ($array as $k => $v) {
            if (is_array($v)) {
                foreach ($v as $k2 => $v2) {
                    if ($k2 == $on) {
                        $sortable_array[$k] = $v2;
                    }
                }
            } else {
                $sortable_array[$k] = $v;
            }
        }

        switch ($order) {
            case asc:
                asort($sortable_array);
            break;
            case desc:
                arsort($sortable_array);
            break;
        }

        foreach ($sortable_array as $k => $v) {
            $new_array[$k] = $array[$k];
        }
    }

    return $new_array;
}
?>
