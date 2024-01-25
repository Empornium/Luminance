<?php
if (!check_perms('site_debug')) { error(403); }
if (isset($_POST['global_flush'])) {
    authorize();
    $master->cache->flush();
}
$DBStats = $master->db->rawQuery('SHOW GLOBAL STATUS')->fetchAll(\PDO::FETCH_UNIQUE);
$MemStats = $master->cache->getStats();

show_header("Service Stats");
?>
<div class="linkbox">
    [<a href="/tools.php?action=database_specifics">Database Specifics</a>]
</div>
<div class="permissions" style="overflow-y: visible; overflow-x: visible">
    <div class="permission_container">
        <div class="head">Service</div>
        <table>
            <tr><td colspan="2"><strong>Threads (Active)</strong></td></tr>
            <tr>
                <td>Cache:</td>
                <td><?=number_format($MemStats['threads'])?> <span style="float:right;">(100.000%)</span></td>
            </tr>
            <tr>
                <td<?php  if ($DBStats['Threads_connected']['Value']/$DBStats['Threads_created']['Value'] > 0.7) { echo ' class="invalid" '; } ?>>Database:</td>
                <td><?=number_format($DBStats['Threads_created']['Value'])?> <span style="float:right;">(<?=number_format(($DBStats['Threads_connected']['Value']/$DBStats['Threads_created']['Value'])*100,3)?>%)</span></td>
            </tr>
            <tr><td colspan="2"></td></tr>
            <tr><td colspan="2"><strong>Connections</strong></td></tr>
            <tr>
                <td>Cache:</td>
                <td><?=number_format($MemStats['total_connections'])?></td>
            </tr>
            <tr>
                <td>Database:</td>
                <td><?=number_format($DBStats['Connections']['Value'])?></td>
            </tr>
            <tr><td colspan="2"></td></tr>
            <tr><td colspan="2"><strong>Special</strong></td></tr>
            <tr>
                <td>Cache Current Index:</td>
                <td><?=number_format($MemStats['curr_items'])?></span></td>
            </tr>
            <tr>
                <td>Cache Total Index:</td>
                <td><?=number_format($MemStats['total_items'])?></span></td>
            </tr>
            <tr>
                <td<?php  if ($MemStats['bytes']/$MemStats['limit_maxbytes'] > 0.85) { echo ' class="invalid" title="Evictions begin when storage exceeds 85%" '; } ?>>Cache Storage:</td>
                <td><?=get_size($MemStats['bytes'])?> <span style="float:right;">(<?=number_format(($MemStats['bytes']/$MemStats['limit_maxbytes'])*100, 3);?>%)</span></td>
            </tr>
            <tr><td colspan="2"></td></tr>
            <tr><td colspan="2"><strong>Utilities</strong></td></tr>
            <tr>
                <td>Cache:</td>
                <td>
                    <form action="" method="post">
                        <input type="hidden" name="action" value="service_stats" />
                        <input type="hidden" name="auth" value="<?=$activeUser['AuthKey']?>" />
                        <input type="hidden" name="global_flush" value="1" />
                        <input type="submit" value="Flush" />
                    </form>
                </td>
            </tr>
        </table>
    </div>
    <div class="permission_container">
        <div class="head">Activity</div>
        <table>
            <tr><td colspan="2"><strong>Total Reads</strong></td></tr>
            <tr>
                <td>Cache:</td>
                <td><?=number_format($MemStats['cmd_get'])?></td>
            </tr>
            <tr>
                <td>Database:</td>
                <td><?=number_format($DBStats['Com_select']['Value'])?></td>
            </tr>
            <tr><td colspan="2"><strong>Total Writes</strong></td></tr>
            <tr>
                <td>Cache:</td>
                <td><?=number_format($MemStats['cmd_set'])?></td>
            </tr>
            <tr>
                <td>Database:</td>
                <td><?=number_format($DBStats['Com_insert']['Value']+$DBStats['Com_update']['Value'])?></td>
            </tr>
            <tr><td colspan="2"></td></tr>
            <tr><td colspan="2"><strong>Get/Select (Success)</strong></td></tr>
            <tr>
                <td<?php  if ($MemStats['get_hits']/$MemStats['cmd_get'] < 0.7) { echo ' class="invalid" '; } ?>>Cache:</td>
                <td><?=number_format($MemStats['get_hits'])?> <span style="float:right;">(<?=number_format(($MemStats['get_hits']/$MemStats['cmd_get'])*100, 3);?>%)</span></td>
            </tr>
            <tr>
                <td>Database:</td>
                <td><?=number_format($DBStats['Com_select']['Value'])?> <span style="float:right;">(100.000%)</span></td>
            </tr>
            <tr><td colspan="2"><strong>Set/Insert (Success)</strong></td></tr>
            <tr>
                <td>Cache:</td>
                <td><?=number_format($MemStats['cmd_set'])?> <span style="float:right;">(100.000%)</span></td>
            </tr>
            <tr>
                <td>Database:</td>
                <td><?=number_format($DBStats['Com_insert']['Value'])?> <span style="float:right;">(100.000%)</span></td>
            </tr>
            <tr><td colspan="2"><strong>Increment/Decrement (Success)</strong></td></tr>
            <tr>
                <td<?php  if ($MemStats['incr_hits']/($MemStats['incr_hits']+$MemStats['incr_misses']) < 0.7) { echo ' class="invalid" '; } ?>>Cache Increment:</td>
                <td><?=number_format($MemStats['incr_hits'])?> <span style="float:right;">(<?=number_format(($MemStats['incr_hits']/($MemStats['incr_hits']+$MemStats['incr_misses']))*100, 3);?>%)</span></td>
            </tr>
            <tr>
                <td<?php  if ($MemStats['decr_hits']/($MemStats['decr_hits']+$MemStats['decr_misses']) < 0.7) { echo ' class="invalid" '; } ?>>Cache Decrement:</td>
                <td><?=number_format($MemStats['decr_hits'])?> <span style="float:right;">(<?=number_format(($MemStats['decr_hits']/($MemStats['decr_hits']+$MemStats['decr_misses']))*100, 3);?>%)</span></td>
            </tr>
            <tr><td colspan="2"><strong>CAS/Update (Success)</strong></td></tr>
            <tr>
                <td<?php  if ($MemStats['cas_hits'] > 0 && $MemStats['cas_hits']/($MemStats['cas_hits']+$MemStats['cas_misses']) < 0.7) { echo ' class="invalid" title="More than 30% of the issued CAS commands were unnecessary wasting time and resources." '; } elseif ($MemStats['cas_hits'] == 0) { echo ' class="notice" title="Disable CAS with the -C parameter and save resources since it is not used." '; } ?>>Cache:</td>
                <td><?=number_format($MemStats['cas_hits'])?> <span style="float:right;">(<?php  if ($MemStats['cas_hits'] > 0) { echo number_format(($MemStats['cas_hits']/($MemStats['cas_hits']+$MemStats['cas_misses']))*100, 3); } else { echo '0.000'; }?>%)</span></td>
            </tr>
            <tr>
                <td>Database:</td>
                <td><?=number_format($DBStats['Com_update']['Value'])?> <span style="float:right;">(100.000%)</span></td>
            </tr>
            <tr><td colspan="2"><strong>Deletes (Success)</strong></td></tr>
            <tr>
                <td<?php  if ($MemStats['delete_hits']/($MemStats['delete_hits']+$MemStats['delete_misses']) < 0.7) { echo ' class="invalid" title="More than 30% of the issued delete commands were unnecessary wasting time and resources." '; } ?>>Cache:</td>
                <td><?=number_format($MemStats['delete_hits'])?> <span style="float:right;">(<?=number_format(($MemStats['delete_hits']/($MemStats['delete_hits']+$MemStats['delete_misses']))*100, 3);?>%)</span></td>
            </tr>
            <tr>
                <td>Database:</td>
                <td><?=number_format($DBStats['Com_delete']['Value'])?> <span style="float:right;">(100.000%)</span></td>
            </tr>
            <tr><td colspan="2"></td></tr>
            <tr><td colspan="2"><strong>Special</strong></td></tr>
            <tr>
                <td<?php  if ($MemStats['cmd_flush'] > $MemStats['uptime']/7*24*3600) { echo ' class="invalid" title="Flushing the cache on a regular basis defeats the benefits of it, look into using cache transactions, or deletes instead of global flushing where possible." '; } ?>>Cache Flushes:</td>
                <td><?=number_format($MemStats['cmd_flush'])?></td>
            </tr>
            <tr>
                <td<?php  if ($MemStats['evictions'] > 0) { echo ' class="invalid" '; } ?>>Cache Evicted:</td>
                <td><?=number_format($MemStats['evictions'])?></td>
            </tr>
            <tr>
                <td<?php  if ($DBStats['Slow_queries']['Value'] > $DBStats['Questions']['Value']/7500) { echo ' class="invalid" title="1/7500 queries is allowed to be slow to minimize performance impact." '; } ?>>Database Slow:</td>
                <td><?=number_format($DBStats['Slow_queries']['Value'])?></td>
            </tr>
            <tr><td colspan="2"></td></tr>
            <tr><td colspan="2"><strong>Data Read</strong></td></tr>
            <tr>
                <td>Cache:</td>
                <td><?=get_size($MemStats['bytes_read'])?></td>
            </tr>
            <tr>
                <td>Database:</td>
                <td><?=get_size($DBStats['Bytes_received']['Value'])?></td>
            </tr>
            <tr><td colspan="2"><strong>Data Write</strong></td></tr>
            <tr>
                <td>Cache:</td>
                <td><?=get_size($MemStats['bytes_written'])?></td>
            </tr>
            <tr>
                <td>Database:</td>
                <td><?=get_size($DBStats['Bytes_sent']['Value'])?></td>
            </tr>
        </table>
    </div>
    <div class="permission_container">
        <div class="head">Concurrency</div>
        <table>
            <tr><td colspan="2"><strong>Total Reads</strong></td></tr>
            <tr>
                <td<?php  if (($MemStats['cmd_get']/$MemStats['uptime'])*5 < $DBStats['Com_select']['Value']/$DBStats['Uptime']['Value']) { echo ' class="invalid" '; } ?>>Cache:</td>
                <td><?=number_format($MemStats['cmd_get']/$MemStats['uptime'],5)?>/s</td>
            </tr>
            <tr>
                <td>Database:</td>
                <td><?=number_format($DBStats['Com_select']['Value']/$DBStats['Uptime']['Value'],5)?>/s</td>
            </tr>
            <tr><td colspan="2"><strong>Total Writes</strong></td></tr>
            <tr>
                <td<?php  if (($MemStats['cmd_set']/$MemStats['uptime'])*5 < ($DBStats['Com_insert']['Value']+$DBStats['Com_update']['Value'])/$DBStats['Uptime']['Value']) { echo ' class="invalid" '; } ?>>Cache:</td>
                <td><?=number_format($MemStats['cmd_set']/$MemStats['uptime'],5)?>/s</td>
            </tr>
            <tr>
                <td>Database:</td>
                <td><?=number_format(($DBStats['Com_insert']['Value']+$DBStats['Com_update']['Value'])/$DBStats['Uptime']['Value'],5)?>/s</td>
            </tr>
            <tr><td colspan="2"></td></tr>
            <tr><td colspan="2"><strong>Get/Select</strong></td></tr>
            <tr>
                <td>Cache:</td>
                <td><?=number_format($MemStats['get_hits']/$MemStats['uptime'],5)?>/s</td>
            </tr>
            <tr>
                <td>Database:</td>
                <td><?=number_format($DBStats['Com_select']['Value']/$DBStats['Uptime']['Value'],5)?>/s</td>
            </tr>
            <tr><td colspan="2"><strong>Set/Insert</strong></td></tr>
            <tr>
                <td>Cache:</td>
                <td><?=number_format($MemStats['cmd_set']/$MemStats['uptime'],5)?>/s</td>
            </tr>
            <tr>
                <td>Database:</td>
                <td><?=number_format($DBStats['Com_insert']['Value']/$DBStats['Uptime']['Value'],5)?>/s</td>
            </tr>
            <tr><td colspan="2"><strong>Increment/Decrement</strong></td></tr>
            <tr>
                <td>Cache Increment:</td>
                <td><?=number_format($MemStats['incr_hits']/$MemStats['uptime'],5)?>/s</td>
            </tr>
            <tr>
                <td>Cache Decrement:</td>
                <td><?=number_format($MemStats['decr_hits']/$MemStats['uptime'],5)?>/s</td>
            </tr>
            <tr><td colspan="2"><strong>CAS/Updates</strong></td></tr>
            <tr>
                <td>Cache:</td>
                <td><?=number_format($MemStats['cas_hits']/$MemStats['uptime'],5)?>/s</td>
            </tr>
            <tr>
                <td>Database:</td>
                <td><?=number_format($DBStats['Com_update']['Value']/$DBStats['Uptime']['Value'],5)?>/s</td>
            </tr>
            <tr><td colspan="2"><strong>Deletes</strong></td></tr>
            <tr>
                <td>Cache:</td>
                <td><?=number_format($MemStats['delete_hits']/$MemStats['uptime'],5)?>/s</td>
            </tr>
            <tr>
                <td>Database:</td>
                <td><?=number_format($DBStats['Com_delete']['Value']/$DBStats['Uptime']['Value'],5)?>/s</td>
            </tr>
            <tr><td colspan="2"></td></tr>
            <tr><td colspan="2"><strong>Special</strong></td></tr>
            <tr>
                <td>Cache Flushes:</td>
                <td><?=number_format($MemStats['cmd_flush']/$MemStats['uptime'],5)?>/s</td>
            </tr>
            <tr>
                <td>Cache Evicted:</td>
                <td><?=number_format($MemStats['evictions']/$MemStats['uptime'],5)?>/s</td>
            </tr>
            <tr>
                <td>Database Slow:</td>
                <td><?=number_format($DBStats['Slow_queries']['Value']/$DBStats['Uptime']['Value'],5)?>/s</td>
            </tr>
            <tr><td colspan="2"></td></tr>
            <tr><td colspan="2"><strong>Data Read</strong></td></tr>
            <tr>
                <td>Cache:</td>
                <td><?=get_size($MemStats['bytes_read']/$MemStats['uptime'])?>/s</td>
            </tr>
            <tr>
                <td>Database:</td>
                <td><?=get_size($DBStats['Bytes_received']['Value']/$DBStats['Uptime']['Value'])?>/s</td>
            </tr>
            <tr><td colspan="2"><strong>Data Write</strong></td></tr>
            <tr>
                <td>Cache:</td>
                <td><?=get_size($MemStats['bytes_written']/$MemStats['uptime'])?>/s</td>
            </tr>
            <tr>
                <td>Database:</td>
                <td><?=get_size($DBStats['Bytes_sent']['Value']/$DBStats['Uptime']['Value'])?>/s</td>
            </tr>
        </table>
    </div>
    <div class="clear"></div>
</div>
<?php
show_footer();
?>
