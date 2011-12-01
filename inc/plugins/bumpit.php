<?php
/**
 *  Plugin  : Bumpit
 *  Author  : Rezz
 *  Version : 1.0
 *  Website : http://openwebnews.com
 *  Contact : rezz@openwebnews.com
 *
 *  This file is part of Bumpit plugin for MyBB.
 *
 *  Bumpit plugin for MyBB is free software; you can
 *  redistribute it and/or modify it under the terms of the GNU General
 *  Public License as published by the Free Software Foundation; either
 *  version 3 of the License, or (at your option) any later version.
 *
 *  Bumpit plugin for MyBB is distributed in the hope that it
 *  will be useful, but WITHOUT ANY WARRANTY; without even the implied
 *  warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See
 *  the GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http:www.gnu.org/licenses/>.
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'bumpit.php');

$templatelist = '';
require_once "./global.php";

// Verify incoming POST request
verify_post_check($mybb->input['my_post_key']);

$lang->load("bumpit");

$tid = intval($mybb->input['tid']);
$query = $db->simple_select("threads", "*", "tid='{$tid}'");
$thread = $db->fetch_array($query);
if(!$thread['tid'])
{
	error($lang->error_invalidthread);
}

$forumpermissions = forum_permissions($thread['fid']);
if($forumpermissions['canview'] == 0 || $forumpermissions['canbumpits'] == 0 || $mybb->usergroup['canbumpits'] == 0)
{
	error_no_permission();
}

// Get forum info
$fid = $thread['fid'];
$forum = get_forum($fid);
if(!$forum)
{
	error($lang->error_invalidforum);
}

// Get forum info
$forum = get_forum($fid);
if(!$forum)
{
	error($lang->error_invalidforum);
}
else
{
	// Is our forum closed?
	if($forum['open'] == 0)
	{
		// Doesn't look like it is
		error($lang->error_closedinvalidforum);
	}
}

// Check if this forum is password protected and we have a valid password
check_forum_password($forum['fid']);

if($forum['allowtratings'] == 0)
{
	error_no_permission();
}
$mybb->input['rating'] = intval($mybb->input['rating']);
if($mybb->input['rating'] < 1 || $mybb->input['rating'] > 5)
{
	error($lang->error_invalidrating);
}
$plugins->run_hooks("bumpit_start");

if($mybb->user['uid'] != 0)
{
	$whereclause = "uid='{$mybb->user['uid']}'";
}
else
{
	$whereclause = "ipaddress='".$db->escape_string($session->ipaddress)."'";
}
$query = $db->simple_select("bumpit", "*", "{$whereclause} AND tid='{$tid}'");
$ratecheck = $db->fetch_array($query);

if($ratecheck['rid'] || $mybb->cookies['mybbbumpit'][$tid])
{
	error($lang->error_alreadyratedthread);
}
else
{
	$plugins->run_hooks("bumpit_process");

	$db->write_query("
		UPDATE ".TABLE_PREFIX."threads
		SET numratings=numratings+1, totalratings=totalratings+'{$mybb->input['rating']}'
		WHERE tid='{$tid}'
	");
	if($mybb->user['uid'] != 0)
	{
		$insertarray = array(
			'tid' => $tid,
			'uid' => $mybb->user['uid'],
			'rating' => $mybb->input['rating'],
			'ipaddress' => $db->escape_string($session->ipaddress)
		);
		$db->insert_query("bumpit", $insertarray);
	}
	else
	{
		$insertarray = array(
			'tid' => $tid,
			'rating' => $mybb->input['rating'],
			'ipaddress' => $db->escape_string($session->ipaddress)
		);
		$db->insert_query("bumpit", $insertarray);
		$time = TIME_NOW;
		my_setcookie("mybbbumpit[{$tid}]", $mybb->input['rating']);
	}
}
$plugins->run_hooks("bumpit_end");

if($mybb->input['ajax'])
{
	echo "<success>{$lang->rating_added}</success>\n";
	$query = $db->simple_select("threads", "totalratings, numratings", "tid='$tid'", array('limit' => 1));
	$fetch = $db->fetch_array($query);
	$width = 0;
	if($fetch['numratings'] >= 0)
	{
		$averagerating = floatval(round($fetch['totalratings']/$fetch['numratings'], 2));
		$width = intval(round($averagerating))*20;
		$fetch['numratings'] = intval($fetch['numratings']);
		$ratingvotesav = $lang->sprintf($lang->rating_votes_average, $fetch['numratings'], $averagerating);
		echo "<average>{$ratingvotesav}</average>\n";
	}
	echo "<width>{$width}</width>";
	exit;
}

redirect(get_thread_link($thread['tid']), $lang->redirect_threadrated);
?>