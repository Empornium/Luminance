<?php
if (!check_perms('admin_data_viewer')) { error(403); }


show_header('Data Viewer','dataviewer,jquery');

require(SERVER_ROOT.'/Legacy/sections/tools/data/data_viewer_queries.php');

if (isset($_REQUEST['query']) && array_key_exists($_REQUEST['query'], $data_viewer_queries)) {
    $selected_query = $_REQUEST['query'];
} else {
    $selected_query = null;
}

?>
<div class="thin">
    <h2>Data Viewer</h2>

    <div class="head">Select query</div>
    <div class="box">
        <table class="pad shadow">
            <tr>
                <form action="tools.php" method="post">
                    <input type="hidden" name="action" value="data_viewer" />
                    <td width="10%" class="label nobr">Query:</td>
                    <td>
                        <select name="query" style="font-family: monospace;" onchange="changeQuery(this)" >
                            <option value=""></option>
<?php
            foreach ($data_viewer_queries as $query_name => $query_data) {
                $title = $query_data['title'];
                $desc = $query_data['description'];
                $selected = ($query_name == $selected_query) ? ' selected="selected"' : '';
                echo "<option value=\"{$query_name}\"{$selected} desc=\"{$desc}\">{$title}&nbsp;</option>";
            }
?>
                        </select>
                    </td>
                    <td width="50%">
                        <input type="submit" name="submit" value="show selected query">
                    </td>
                </form>
            </tr>
            <tr id="querydesc" class="hide">
                <td class="label nobr">Description:</td>
                <td colspan="2"></td>
            </tr>
        </table>
    </div>

<?php
define('ROWS_PER_PAGE', 100);
list($Page,$Limit) = page_limit(ROWS_PER_PAGE);

if ($selected_query) {
    $sql = $data_viewer_queries[$selected_query]['sql'];

    $master->db->raw_query("SET group_concat_max_len=16777216");
    $results = $master->db->raw_query("{$sql} LIMIT $Limit")->fetchAll(\PDO::FETCH_ASSOC);
    $total = $master->db->raw_query('SELECT FOUND_ROWS()')->fetchColumn();

?>
    <br />
    <h2>Results for <?=$data_viewer_queries[$selected_query]['title']?></h2>
    <div class="head">Query description</div>
    <div class="box pad">
<?php
        echo $data_viewer_queries[$selected_query]['description'];
?>
    </div>
<?php

    if ($total>0) {
?>
        <div class="linkbox">
<?php
        $Pages=get_pages($Page, $total, ROWS_PER_PAGE, 11, "&amp;action=data_viewer&amp;query={$selected_query}") ;
        echo $Pages;
        $rowidx = 0;
        foreach($results as $row) {
            $rowstyle = ($rowidx % 2) ? 'a' : 'b';
            if ($rowidx == 0) {
?>
        </div>
        <table width="100%">
            <tr class="head">
                <td colspan="100">Results</td>
            </tr>
            <tr class="colhead">
<?php
                foreach (array_keys($row) as $key) {
                    echo "<td>" . str_replace('_', ' ', $key) . "</td>\n";
                }
?>
            </tr>
<?php
            }
?>
            <tr class="row<?=$rowstyle?>">
<?php
                foreach (array_values($row) as $value) {
                    echo "<td>{$value}</td>";
                }
?>
            </tr>
<?php
            $rowidx++;
        }
?>
        </table>
        <div class="linkbox">
<?php       echo $Pages; ?>
        </div>
<?php
    } else { ?>
        <h2 align="center">No results.</h2>
<?php
    }
}
?>
</div>
<?php
show_footer();
