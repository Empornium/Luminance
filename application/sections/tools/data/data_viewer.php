<?php
if (!check_perms('admin_data_viewer')) { error(403); }
show_header('Data Viewer');

require(SERVER_ROOT.'/sections/tools/data/data_viewer_queries.php');

if (isset($_REQUEST['query']) && array_key_exists($_REQUEST['query'], $data_viewer_queries)) {
    $selected_query = $_REQUEST['query'];
} else {
    $selected_query = null;
}

?>
<div class="thin">
    <h2>Data Viewer</h2>
<table>
    <tr class="head">
        <td colspan="6">Select query</td>
    </tr>
    <tr>
        <form action="tools.php" method="post">
            <input type="hidden" name="action" value="data_viewer" />
            <td class="label nobr">Query:</td>
            <td>
                <select name="query">
                    <option value=""></option>
<?php
    foreach ($data_viewer_queries as $query_name => $query_data) {
        $title = $query_data['title'];
        $selected = ($query_name == $selected_query) ? ' selected="selected"' : '';
        echo "<option value=\"{$query_name}\"{$selected}>{$title}</option>";
    }
?>
                </select>
            </td>
            <td>
                <input type="submit" name="submit" value="Submit">
            </td>
        </form>
    </tr>
</tr>
</table>

<?php
define('ROWS_PER_PAGE', 100);
list($Page,$Limit) = page_limit(ROWS_PER_PAGE);

if ($selected_query) {
    $sql = $data_viewer_queries[$selected_query]['sql'];

$DB->query("SET group_concat_max_len=16777216");

$RS = $DB->query("{$sql} LIMIT $Limit");
$DB->query("SELECT FOUND_ROWS()");
list($Results) = $DB->next_record();
$DB->set_query_id($RS);

?>
<br />
<div class="head">Query description</div>
<div class="box pad">
<?php  echo $data_viewer_queries[$selected_query]['description'] ?>
</div>

<?php
if ($DB->record_count()) {
?>
    <div class="linkbox">
<?php
    $Pages=get_pages($Page, $Results, ROWS_PER_PAGE, 11, "&amp;action=data_viewer&amp;query={$selected_query}") ;
    echo $Pages;
    $rowidx = 0;
    while ($row = $DB->next_record(MYSQLI_ASSOC, false)) {
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
<?php  echo $Pages; ?>
    </div>
<?php  } else { ?>
    <h2 align="center">No results.</h2>
<?php  }
}
?>
</div>
<?php
show_footer();
