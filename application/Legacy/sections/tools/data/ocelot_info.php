<?php
if (!check_perms('users_mod')) {
	error(403);
}

if (isset($_GET['userid']) && !is_number($_GET['userid'])) {
    error(0);
}

if (isset($_GET['userid'])) {
	$UserHeavyInfo = user_heavy_info($_GET['userid']);
	if (isset($UserHeavyInfo['torrent_pass'])) {
		$TorrentPass = $UserHeavyInfo['torrent_pass'];
		$UserPeerStats = $master->tracker->userStats($TorrentPass);
		$UserInfo = user_info($_GET['userid']);
		$UserLevel = $Classes[$UserInfo['PermissionID']]['Level'];
		if (!check_paranoia('leeching+', $UserInfo['Paranoia'], $UserLevel, $_GET['userid'])) {
			$UserPeerStats['seeding'] = false;
		}
		if (!check_paranoia('seeding+', $UserInfo['Paranoia'], $UserLevel, $_GET['userid'])) {
			$UserPeerStats['leeching'] = false;
		}
	} else {
		$UserPeerStats = false;
	}
} else if (isset($_GET['dbinfo']) && $_GET['dbinfo']=='1') {
	$MainStats = $master->tracker->dbInfo();
} else if (isset($_GET['domaininfo']) && $_GET['domaininfo']=='1') {
	$MainStats = $master->tracker->domainInfo();
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
        [<a href="?action=<?=$_GET['action']?>&amp;dbinfo=1" class="brackets" />DB stats</a>]
        [<a href="?action=<?=$_GET['action']?>&amp;domaininfo=1" class="brackets" />Domain stats</a>]
	</div>
	<div class="sidebar">
		<div class="box box2">
			<div class="head"><strong>User stats</strong></div>
			<div class="pad">
				<form method="get" action="">
					<input type="hidden" name="action" value="ocelot_info" />
					<span class="label">Get stats for user</span><br />
					<input type="text" name="userid" placeholder="User ID" value="<?form('userid')?>" />
					<input type="submit" value="Go" />
				</form>
			</div>
		</div>
	</div>
	<div class="main_column">
		<div class="box box2">
			<div class="head"><strong>Numbers and such</strong></div>
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
		if (is_numeric($Value)) {
			if (substr($Key, 0, 6) === "bytes ") {
				$Value = get_size($Value);
				$Key = substr($Key, 6);
			} else {
				$Value = number_format($Value);
			}
		}
?>
				<?="$Value $Key<br />\n"?>
<?php
	}
} elseif (isset($TorrentPass)) {
?>
				Failed to get stats for user <?=$_GET['userid']?>
<?php
} elseif (isset($_GET['userid'])) {
?>
				User <?=$_GET['userid']?> doesn't exist
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
