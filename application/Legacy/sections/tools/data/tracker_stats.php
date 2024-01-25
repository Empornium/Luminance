<?php
if (!check_perms('users_mod')) {
  error(403);
}

if (isset($_GET['userid']) && !is_integer_string($_GET['userid'])) {
    error(0);
}

if (isset($_GET['torrentid']) && !is_integer_string($_GET['torrentid'])) {
    error(0);
}

if (isset($_GET['userid'])) {
    $UserHeavyInfo = user_heavy_info($_GET['userid']);
    if (isset($UserHeavyInfo['torrent_pass'])) {
        $TorrentPass = $UserHeavyInfo['torrent_pass'];
        $UserPeerStats = $master->tracker->userStats($TorrentPass);
        $UserInfo = user_info($_GET['userid']);
        $UserLevel = $classes[$UserInfo['PermissionID']]['Level'];
        if (!check_paranoia('leeching+', $UserInfo['Paranoia'], $UserLevel, $_GET['userid'])) {
            $UserPeerStats['leeching'] = false;
        }
        if (!check_paranoia('seeding+', $UserInfo['Paranoia'], $UserLevel, $_GET['userid'])) {
            $UserPeerStats['seeding'] = false;
        }
    } else {
        $UserPeerStats = false;
    }
} else if (isset($_GET['torrentid'])) {
    $info_hash = $master->db->rawQuery(
        'SELECT info_hash
           FROM torrents
          WHERE ID = ?',
        [$_GET['torrentid']]
    )->fetchColumn();
    $OtherStats = $master->tracker->torrentStats($info_hash);
} else if (isset($_GET['dbinfo']) && $_GET['dbinfo']=='1') {
    $OtherStats = $master->tracker->dbInfo();
} else if (isset($_GET['perfinfo']) && $_GET['perfinfo']=='1') {
    $OtherStats = $master->tracker->performanceInfo();
} else if (isset($_GET['domaininfo']) && $_GET['domaininfo']=='1') {
    $OtherStats = $master->tracker->domainInfo();
} else {
    $MainStats = $master->tracker->info();
}

show_header('Tracker info');
?>
<div class="thin">
  <div class="header">
    <h2>Tracker info</h2>
  </div>
  <div class="linkbox">
        [<a href="?action=<?=$_GET['action']?>" class="brackets" />Main stats</a>]&nbsp;
        [<a href="?action=<?=$_GET['action']?>&amp;perfinfo=1" class="brackets" />Performance stats</a>]
        [<a href="?action=<?=$_GET['action']?>&amp;dbinfo=1" class="brackets" />DB stats</a>]
        [<a href="?action=<?=$_GET['action']?>&amp;domaininfo=1" class="brackets" />Domain stats</a>]
  </div>
  <div class="sidebar">
    <div class="head"><strong>User stats</strong></div>
    <div class="box box2">
      <div class="pad">
        <form method="get" action="">
          <input type="hidden" name="action" value="tracker_stats" />
          <span class="label">Get stats for user</span><br />
          <input type="text" name="userid" placeholder="User ID" value="<?=form('userid')?>" />
          <input type="submit" value="Go" />
        </form>
      </div>
    </div>
      <div class="head"><strong>Torrent stats</strong></div>
      <div class="box box2">
        <div class="pad">
          <form method="get" action="">
            <input type="hidden" name="action" value="tracker_stats" />
            <span class="label">Get stats for torrent</span><br />
            <input type="text" name="torrentid" placeholder="Torrent ID" value="<?=form('torrentid')?>" />
            <input type="submit" value="Go" />
          </form>
        </div>
      </div>
  </div>
  <div class="main_column">
    <div class="head"><strong>Numbers and such</strong></div>
    <div class="box box2">
      <div class="pad">
<?php
if (!empty($UserPeerStats)) {
?>
        User ID: <?=$_GET['userid']?><br />
        Leeching: <?=$UserPeerStats['leeching'] === false ? "hidden" : number_format($UserPeerStats['leeching'])?><br />
        Seeding: <?=$UserPeerStats['seeding'] === false ? "hidden" : number_format($UserPeerStats['seeding'])?><br />
        Personal Freeleech: <?=$UserPeerStats['personal freeleech'] === false ? "hidden" : time_diff($UserPeerStats['personal freeleech'])?><br />
        Personal Doubleseed: <?=$UserPeerStats['personal doubleseed'] === false ? "hidden" : time_diff($UserPeerStats['personal doubleseed'])?><br />
        Leeching Forbidden: <?=$UserPeerStats['forbidden'] === false ? "hidden" : $UserPeerStats['forbidden']?><br />
        Protected: <?=$UserPeerStats['protected'] === false ? "hidden" : $UserPeerStats['protected']?><br />
        Track IPv6: <?=$UserPeerStats['track ipv6'] === false ? "hidden" : $UserPeerStats['track ipv6']?><br />
<?php
} elseif (!empty($MainStats)) {
  foreach ($MainStats as $Key => $Value) {
    if (is_integer_string($Value)) {
      if (substr($Key, 0, 6) === "bytes ") {
        $Value = get_size($Value);
        $Key = substr($Key, 6);
      } elseif ($Key === "last_flushed") {
        $Value = "{$Key} " . time_diff($Value);
        $Key = '';
      } else {
        $Value = number_format($Value);
      }
    }
        echo "{$Value} {$Key}<br />\n";
  }
} elseif (!empty($OtherStats)) {
    echo "<table><tr>";
    foreach ($OtherStats as $Key => $Value) {
      if (is_integer_string($Value)) {
        if (substr($Key, 0, 6) === "bytes ") {
          $Value = get_size($Value);
          $Key = substr($Key, 6);
        } elseif ($Key === "last flushed") {
          $Value = time_diff($Value);
        } else {
          $Value = number_format($Value);
        }
      }
      echo "<tr><td>{$Key}</td><td>{$Value}</td></tr>\n";
    }
    echo "</tr></table>";
} elseif (isset($TorrentPass)) {
?>
        Failed to get stats for user <?=$_GET['userid']?>
<?php
} elseif (isset($_GET['userid'])) {
?>
        User <?=$_GET['userid']?> doesn't exist
<?php
} elseif (isset($_GET['torrentid'])) {
?>
        Torrent <?=$_GET['torrentid']?> doesn't exist
<?php
} elseif (isset($_GET['dbinfo'])) {
?>
        Failed to get tracker DB info
<?php
} elseif (isset($_GET['domaininfo'])) {
?>
        Failed to get tracker Domain info
<?php
} else {
?>
        Failed to get tracker info
<?php
}
?>
      </div>
    </div>
  </div>
</div>
<?php
show_footer();
