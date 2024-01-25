<?php
if (!check_perms('site_debug')) { error(403); }

if (!isset($_GET['case']) || !$Analysis = $master->cache->getValue('analysis_'.display_str($_GET['case']))) { error(404); }

show_header('Case Analysis');
?>
<h2>Case Analysis (<a href="/<?=display_str($Analysis['url'])?>"><?=display_str($_GET['case'])?></a>)</h2>
<pre id="#debug_report"><?=display_str($Analysis['message'])?></pre>
<?php
$master->debug->flag_table($Analysis['flags']);
$master->debug->include_table($Analysis['includes']);
$master->debug->error_table($Analysis['errors']);
$master->debug->query_table($Analysis['queries']);
$master->debug->cache_table($Analysis['cache']);
$master->debug->class_table();
$master->debug->extension_table();
$master->debug->constant_table();
$master->debug->vars_table($Analysis['vars']);
show_footer();
