<?php
/*
Plugin Name: Gravatars
Plugin URI: http://www.skippy.net/blog/2005/03/24/gravatars/
Description: This plugin provides an administrative interface to control default gravatar options.  Registered users can also (optionally) define local gravatar images that will override their gravatar.com default.  Copyright 2005 Scott Merrill; Licensed under the terms of the <a href="http://www.gnu.org/licenses/gpl.html">GPL</a>.
Version: 2.3
Author: Scott Merrill
Author URI: http://www.skippy.net/

based upon the original Gravatar plugin:
http://gravatar.com/implement.php#section_2_2
*/

/// MAIN PROGRAM
add_action ('admin_menu', 'gravatar_menu');
if ( ($grav_options = get_option('gravatar_options')) && ('1' == $grav_options['gravatar_in_posts']) ) {
	add_filter('the_content', 'gravatar_in_posts');
}

/// FUNCTIONS
////////////////////////
function gravatar_menu() {
global $grav_options;
	add_options_page('Gravatars', 'Gravatars', 9, __FILE__, 'gravatar_manage');
	if ('1' == $grav_options['gravatar_allow_local']) {
		add_submenu_page('profile.php', 'Gravatar Selection', 'Gravatar', 1, __FILE__, 'gravatar_profile');
	}
} // gravatar_menu

////////////////////////////
function gravatar_defaults($action = '', $rating = '', $size = '', $border = '', $default = '', $expire = '604800', $posts = '0', $local = '1', $gravatar_cache = '1') {

// let's check whether allow_url_fopen works here
if (! ini_get('allow_url_fopen')) {
	$gravatar_cache = 0;
} elseif (! isset($gravatar_cache)) {
	$gravatar_cache = 1;
}

if ( (FALSE === get_option('gravatar_options')) || ('reset' == $action) ) {
	$grav_options = array('gravatar_rating' => 'PG',
		'gravatar_size' => '80',
		'gravatar_border' => '',
		'gravatar_default' => 'wp-content/gravatars/blank_gravatar.png',
		'gravatar_expire' => '604800',
		'gravatar_in_posts' => '1',
		'gravatar_allow_local' => '1',
		'gravatar_cache' => $gravatar_cache);
	update_option('gravatar_options', $grav_options);
} elseif ('update' == $action) {
	$grav_options = array('gravatar_rating' => $rating,
		'gravatar_size' => $size,
		'gravatar_border' => $border,
		'gravatar_default' => $default,
		'gravatar_expire' => $expire,
		'gravatar_in_posts' => $posts,
		'gravatar_allow_local' => $local,
		'gravatar_cache' => $gravatar_cache);
	update_option('gravatar_options', $grav_options);
}

} // gravatar_defaults

///////////////////////////
function gravatar_profile() {
load_plugin_textdomain('gravatars');
global $user_email;

if ('' == $user_email) {
	get_currentuserinfo();
}

$cached = FALSE;
$gravpath = '';
$message = '';
if (isset($_POST['gravpath'])) {
	if ('' != $_POST['gravpath']) {
		if (stristr("http://", $_POST['gravpath'])) {
			// it's a remote URL; let's try to copy it.
			$filename = md5($user_email);
			$cached = copy ($_POST['gravpath'], ABSPATH . "wp-content/gravatars/$filename");
			if (! $cached) {
				// error, give them the default
				$gravpath = '';
				$message = __("There was an error copying the gravatar.  The system default has been used instead.") . "<br />";
			} else {
				$gravpath = ABSPATH . "wp-content/gravatars/$filename";
			}
		} else {
			// it's a local path
			$gravpath = $_POST['gravpath'];
		}
		$gravatar_local = get_option('gravatar_local');
		$gravatar_local[$user_email] = $gravpath;
	} else {
		// empty gravpath, so delete the local record
		$old_local = get_option('gravatar_local');
		foreach ($old_local as $key => $value) {
			if ($user_name != $key) {
				$gravatar_local[$key] = $value;
			}
		}
	}
	update_option("gravatar_local", $gravatar_local);
	$message .= __('Gravatar Updated.', 'gravatars');
}

$gravwhere = get_settings('blogname') . __(' is using your <strong>local</strong> gravatar', 'gravatars') . ":";
if (! isset($gravatar_local)) {
	$gravatar_local = get_option('gravatar_local');
}
$user_gravatar = $gravatar_local[$user_email];
if ('' == $user_gravatar) {
	$gravwhere = get_settings('blogname') . __(' is using your <strong>default</strong> gravatar', 'gravatars');
	// use their cached gravatar, or give them the system default
	$user_gravatar = wp_gravatar($user_email);
}
if ('' != $message) {
	echo "<div class='updated'><p><strong>$message</strong></p></div>";
}
echo "<div class='wrap'>";
echo "<p>$gravwhere:<br /><img src='$user_gravatar' /></p>";
echo "<p>";
_e('You can assign a specific gravatar for use on ', 'gravatars');
echo get_settings('blogname') . "; ";
_e ('enter the path (local or remote) below', 'gravatars');
echo ':</p>';
echo "<p><form method='POST'><input type='text' size='50' name='gravpath' /> ";
echo "<input type='hidden' name='user' value='" . $user_email . "'><input type='submit' name='submit' value='" . __('Submit', 'gravatars') . "'></p>";
echo "<p><em>";
_e('A blank submission will remove your locally defined gravatar', 'gravatars');
echo '.</em></fieldset></div>';
} // gravatar_profile

/////////////////////////
function gravatar_manage() {
load_plugin_textdomain('gravatars');
global $wpdb;

if (! is_writable(ABSPATH . "wp-content/gravatars")) {
	echo "<div class='updated'><p align='center'>";
	_e('WARNING! WARNING! WARNING!', 'gravatars');
	echo '<br /><strong><code>' . ABSPATH . 'wp-content/gravatars</code></strong><br />';
	_e('is not writable', 'gravatars');
	echo '.</p>';
	echo "<p align='center'>";
	_e('Gravatar caching will be disabled until this directory is made writable', 'gravatars');
	echo '.</p></div>';
	if (isset($_POST['gravatar_cache']))
		$_POST['gravatar_cache'] = 0;
		$NOCACHE = TRUE;
}

if (! ini_get('allow_url_fopen')) {
	echo "<div class='updated'><p align='center'>";
	_e('WARNING! WARNING! WARNING!', 'gravatars');
	echo "<br /><a href='http://us4.php.net/manual/en/ref.filesystem.php#ini.allow-url-fopen'><code>allow_url_fopen</code></a>";
	_e('is <strong>DISABLED</strong> on this host', 'gravatars');
	echo ".</p><p align='center'> ";
	_e('Gravatar caching has been disabled', 'gravatars');
	echo '.</p></div>';
	if (isset($_POST['gravatar_cache'])) {
		$_POST['gravatar_cache'] = 0;
	}
	$NOCACHE = TRUE;
}

if ( isset($_POST['reset']) && ('RESET' == $_POST['reset']) ) {
	// reset the defaults
	gravatar_defaults('reset');
} elseif ( isset($_POST['grav_options']) && ('update' == $_POST['grav_options']) ) {
	// update the defaults
	gravatar_defaults ('update', $_POST['rating'],  $_POST['size'],  $_POST['gravatar_border'],  $_POST['default'],  $_POST['expire'], $_POST['gravatar_in_posts'], $_POST['gravatar_allow_local'], $_POST['gravatar_cache']);
} elseif (isset($_POST['delete_local'])) {
	// delete local gravatar
	$who = $_POST['delete_local'];
	if ('ALL_LOCAL' == $who) {
		// delete all local gravatars
		update_option('gravatar_local', '');
	} else {
		// delete just one local gravatar
		$old_local = get_option('gravatar_local');
		foreach ($old_local as $key => $value) {
			if ($who != $key) {
				$gravatar_local[$key] = $value;
			}
		}
		update_option('gravatar_local', $gravatar_local);
	}
} elseif ( (isset($_POST['delete_cache'])) && (FALSE == $NOCACHE) ) {
	// delete cached gravatar
	$who = $_POST['delete_cache'];
	if ('ALL_CACHE' == $who) {
		// delete all cached gravatars
		update_option('gravatar_expire', '');
		if ($d = opendir(ABSPATH . "wp-content/gravatars/")) {
			while (($file = readdir($d)) !== false) {
				if (is_file(ABSPATH . "wp-content/gravatars/$file")) {
					unlink(ABSPATH . "wp-content/gravatars/$file");
				}
			}
			closedir($d);
		}
	} else {
		// delete just one cached gravatar
		$old_expire = get_option('gravatar_expire');
		$new_expire = array();
		foreach ($old_expire as $key => $value) {
			if ($who != $key) {
				$new_expire[$key] = $value;
			}
		}
		update_option('gravatar_expire', $new_expire);
		if (is_file(ABSPATH . 'wp-content/gravatars/' . md5($who))) {
			unlink(ABSPATH . 'wp-content/gravatars/' . md5($who));
		}
	}
}
gravatar_defaults();
if (! isset($gravatar_expire)) {
	$gravatar_expire = get_option('gravatar_expire');
}
$grav_options = get_option('gravatar_options');
if ($NOCACHE) {
	$grav_options['gravatar_cache'] = '0';
}

echo "<div class='wrap'><h2>";
_e('Gravatar Options', 'gravatars');
echo "</h2>\r\n<fieldset class='options'>";
echo "<table width='100%' cellspacing='2' cellpadding='5' class='editform'><tr><td align='center'>";
echo "<form method='POST'>";
echo "<input type='hidden' name='grav_options' value='update'>";
_e('Default gravatar rating', 'gravatars');
echo ":<br /> <select name='rating'>";
$ratings = array ("G", "PG", "R", "X");
foreach ($ratings as $r) {
	echo "<option value='$r'";
	if ($r == $grav_options['gravatar_rating']) { echo " selected"; }
	echo ">$r</option>";
}
echo "</select></td>\r\n";
echo "<td align='center'>";
_e('Default gravatar size', 'gravatars');
echo ": <br /> <select name='size'>";
for ($i = 1; $i <= 80; $i++) {
	echo "<option value='$i'";
	if ($i == $grav_options['gravatar_size']) { echo " selected"; }
	echo ">$i</option>";
}
echo "</select></td>\r\n";
echo "<td align='center'>";
_e('Border Color', 'gravatars');
echo ":<br /> <input type='text' name='gravatar_border' size='10' value='" . $grav_options['gravatar_border'] . "' /></td></tr>";
echo "<tr class='alternate'>";
echo "<tr><td colspan='3' align='left' class='alternate'>";
_e('Default gravatar image', 'gravatars');
echo ": <input type='text' name='default' value='" . $grav_options['gravatar_default'] . "' size='80' /><br />";
_e('You may enter: ', 'gravatars');
echo '<br /><ul><li>';
_e('a local filename: ', 'gravatars');
echo '(<code>/images/foo.png</code>)';
echo ',</li><li>';
_e('a directory containing a collection of gravatars from which to randomly select: ', 'gravatars');
echo '(<code>/wp-content/gravatars/random/</code>)</li><li>';
_e('or a remote URI ', 'gravatars');
echo '(<code>http://example.com/foo.png</code>)</li></ul>';
_e('<strong>Please read the documentation for more information about valid options.</strong>', 'gravatars');
echo '</td></tr>';
echo "<td align='center'>";
_e('Cache gravatars', 'gravatars');
echo ': <br />';
if ($NOCACHE) {
	_e('<strong>DISABLED</strong>');
} else {
	echo "<input type='radio' name='gravatar_cache' value='1'";
	if ('1' == $grav_options['gravatar_cache']) {
		echo " checked='checked'";
	}
	echo '>';
	_e('Yes', 'gravatars');
	echo "&nbsp;<input type='radio' name='gravatar_cache' value='0'";
	if ('0' == $grav_options['gravatar_cache']) {
		echo " checked='checked'";
	}
	echo ">";
	_e('No', 'gravatars');
	echo '</td>';
}
echo "</td>";
echo "<td align='center'>";
_e('How long (in seconds) to cache gravatars', 'gravatars');
echo ":<br /> <input type='text' size='10' name='expire' value='" . $grav_options['gravatar_expire'] . "' /></td>";
echo "<td align='center'>";
_e('Allow local gravatars', 'gravatars');
echo "?<br /> <input type='radio' name='gravatar_allow_local' value='1'";
if ('1' == $grav_options['gravatar_allow_local']) {
	echo " checked='checked'";
}
echo ">";
_e('Yes', 'gravatars');
echo "&nbsp;<input type='radio' name='gravatar_allow_local' value='0'";
if ('0' == $grav_options['gravatar_allow_local']) {
	echo " checked='checked'";
}
echo '> ';
_e('No', 'gravatars');
echo '</td></tr>';
echo "<tr><td class='alternate' colspan='4' align='center'>";
_e('Replace <code>&lt;gravatar foo@bar.com&gt;</code> in posts', 'gravatars');
echo "? <input type='radio' name='gravatar_in_posts' value='1'";
if ('1' == $grav_options['gravatar_in_posts']) {
	echo " checked='checked'";
}
echo '>';
_e('Yes', 'gravatars');
echo " &nbsp;<input type='radio' name='gravatar_in_posts' value='0'";
if ('0' == $grav_options['gravatar_in_posts']) {
        echo " checked='checked'";
}
echo '> ';
_e('No', 'gravatars');
echo '</td>';
echo "<tr'><td align='left' colspan='2'><input type='submit' name='submit' value='" . __('Submit', 'gravatars') . "'</td>\r\n";
echo "<td align='right'><input type='submit' name='reset' value='RESET'></td></tr>\r\n</table></form></fieldset>\r\n";


// first, collect all the people who have commented
$commenters = $wpdb->get_results("SELECT DISTINCT comment_author, comment_author_email, comment_author_url, COUNT(*) as count FROM $wpdb->comments WHERE $wpdb->comments.comment_approved = '1' AND $wpdb->comments.comment_type = '' AND $wpdb->comments.comment_author_url != '' GROUP BY comment_author_email ORDER BY count DESC, comment_author DESC");

$locals = array();
$cached = array();
$gravatar_local = get_option('gravatar_local');
foreach ($commenters as $commenter) {
	if ('' == $commenter->comment_author_email) { continue; }
	$gravatar = '';
	$filename = md5($commenter->comment_author_email);
	if ('' != $gravatar_local[$commenter->comment_author_email]) {
		$gravatar = $gravatar_local[$commenter->comment_author_email];
		$foo = "<div style='float: left; text-align: center; margin: 2px; padding: 2px; border: 2px solid #9cf;'>";
		$foo .= "<form method='POST'><a href='$commenter->comment_author_url' title='$commenter->comment_author_url'><img src='$gravatar' alt='$commenter->comment_author' /></a><br />$commenter->comment_author<br />$commenter->count comments<br /><input type='hidden' name='delete_local' value='$commenter->comment_author_email)' /><input type='submit' name='gravatar_admin' value='[ X ]' /></form></div>\r\n";
		$locals[] = $foo;
	} elseif (is_file(ABSPATH . "wp-content/gravatars/$filename"))  {
		$gravatar = get_settings('siteurl') . "/wp-content/gravatars/$filename";
		// let's make these into relative links
		$gravatar = str_replace("http://" . $_SERVER['SERVER_NAME'], '', $gravatar);
		$foo = "<div style='float: left; text-align: center; margin: 2px; padding: 2px; border: 2px solid #f90;'>";
		$foo .= "<form method='POST'><a href='$commenter->comment_author_url' title='$commenter->comment_author_url'><img src='$gravatar' alt='$commenter->comment_author' /></a><br />$commenter->comment_author<br />$commenter->count comments<br /><input type='hidden' name='delete_cache' value='$commenter->comment_author_email' /><input type='submit' name='gravatar_admin' value='[ X ]' /></form></div>\r\n";
		$cached[] = $foo;
	}
}

echo '<h2>';
_e('Local Gravatars', 'gravatars');
echo "</h2><fieldset class='options'>\r\n<p>";
_e("Deleting a local gravatar causes your blog to use that user's global gravatar (if they have one) or your site's default gravatar", 'gravatars');
echo '.</p>';
if (count($locals) > 0) {
	foreach ($locals as $local) {
		echo $local;
	}
	echo "<div style='clear: both;'>&nbsp;</div>";
	echo "<form method='POST'><table width='100%' class='editform'><tr><td class='alternate' align='right'>Delete all local gravatars: <input type='hidden' name='delete_local' value='ALL_LOCAL' /><input type='submit' name='gravatar_admin' value='DELETE' /></td></tr></table></form>";
} else {
	echo "<p align='center'><strong>";
	_e('No local gravatars', 'gravatars');
	echo '</strong></p>';
}
echo "</fieldset>";

echo '<h2>';
_e('Cached Gravatars', 'gravatars');
echo "</h2><fieldset class='options'>\r\n<p>";
_e('Deleting a cached gravatar will force your blog to request the latest version (if any) from <code>gravatar.com</code>', 'gravatars');
echo '.</p>';
if (count($cached) > 0) {
	foreach ($cached as $cache) {
		echo $cache;
	}
	echo "<div style='clear: both;'>&nbsp;</div>";
	echo "<form method='POST'><table width='100%' class='editform'><tr><td class='alternate' align='right'>Delete all cached gravatars: <input type='hidden' name='delete_cache' value='ALL_CACHE' /><input type='submit' name='gravatar_admin' value='DELETE' /></td></tr></table></form>";
} else {
	echo "<p align='center'><strong>";
	_e('No cached gravatars', 'gravatars');
	echo '</strong></p>';
}
echo "</fieldset>\r\n";
echo "<div style='clear: both;'>&nbsp;</div>";
echo "</div>";
include (ABSPATH . 'wp-admin/admin-footer.php');
// just to be sure
die;

} // gravatar_manage

/////////////////////////////////////////
function gravatar_in_posts($content = '') {

if ('' == $content) { return; }

$matches = array();
$replacement = array();
$counter = 0;

// look for all instances of <gravatar ... > in the content
preg_match_all("/<gravatar ([^>]+) \/>/", $content, $matches);
// for each instance, let's try to parse it
foreach ($matches['0'] as $match) {
	list( ,$foo, ) = explode(' ', $match);
	$replacement[$counter] = "<img class='postgrav' src='" . wp_gravatar($foo) . "' />";
	$counter++;
}
for ($i = 0; $i <= $counter; $i++) {
	$content = str_replace($matches[0][$i], $replacement[$i], $content);
}
return $content;
} // gravatar_in_posts

////////////////////////////////////
function wp_gravatar_info($md5 = '') {
if ('' == $md5) { return false; }

$r = array();
$foo = file("http://www.gravatar.com/info/md5/$md5");
array_shift($foo); // strip leading <xml ...> declaration
array_shift($foo); // strip opening <gravatar>
array_pop($foo);   // strip closing <gravatar>
foreach ($foo as $bar) {
	$matched = array();
	preg_match_all("/([^<>])+/", $bar, $matched);
	$r[$matched[0][1]] = $matched[0][2];
}
return $r;
}

///////////////////////////
function random_gravatar() {
// select a random gravatar 
global $random_gravatars, $grav_options;

if ('/' != substr($grav_options['gravatar_default'], -1, 1)) {
	return FALSE;
}

if (! is_dir(ABSPATH . $grav_options['gravatar_default'])) {
	return FALSE;
}

if (! isset($random_gravatars)) {
	// largely cribbed from photomatt:
	// http://photomatt.net/scripts/randomimage
	$random_gravatars = array();
	$handle = opendir(ABSPATH . $grav_options['gravatar_default']);
	while (false !== ($file = readdir($handle))) {
		 if ('.' != substr($file, 0, 1)) { 
			$random_gravatars[] = $file;
		}
	}
	closedir($handle);
}
mt_srand((double)microtime()*1000000); // seed for PHP < 4.2
$rand = mt_rand(0, (count($random_gravatars) - 1));
return $grav_options['gravatar_default'] . $random_gravatars[$rand];
} // random_gravatar

///////////////////////////////////////////////////////
function gravatar_query ($md5 = '', $default = '') {
global $grav_options;

if ('' == $md5) return FALSE;

if ('' == $default) {
	if ('/' == substr($grav_options['gravatar_default'], -1, 1)) {
		$default = random_gravatar();
	} else {
		$default = $grav_options['gravatar_default'];
	}
}

// prepare the query to gravatar.com
$gravatar = "http://www.gravatar.com/avatar.php?gravatar_id=$md5";
if ('' != $grav_options['gravatar_rating'])
        $gravatar .= "&rating=" . $grav_options['gravatar_rating'];
if ('' != $grav_options['gravatar_size'])
        $gravatar .= "&size=" . $grav_options['gravatar_size'];
if ( ('' != $default) && ('NONE' != $default) ) {
        $gravatar .= "&default=";
        if (! stristr($default, "http://")) {
                $gravatar .= "http://" . $_SERVER['SERVER_NAME'];
        }
	$gravatar .= $default;
}
if ('' != $grav_options['gravatar_border'])
        $gravatar .= "&border=" . $grav_options['gravatar_border'];

return $gravatar;
} // gravatar_query

/////////////////////////////////
function gravatar_cache($md5 = '') {
global $grav_options;

$cached = FALSE;

if ('' == $md5) return $cached;

$gravatar = gravatar_query ($md5, 'NONE');

if ( (is_writeable(ABSPATH . 'wp-content/gravatars/')) && (ini_get('allow_url_fopen')) ) {
	$cached = copy ($gravatar, ABSPATH . "wp-content/gravatars/$md5.TMP");
	if (! $cached) {
		// looks like the copy failed, delete the TMP
		unlink(ABSPATH . "wp-content/gravatars/$md5.TMP");
	} else {
		// we copied successfully
	rename(ABSPATH . "wp-content/gravatars/$md5.TMP", ABSPATH . "wp-content/gravatars/$md5");
	}
}

return $cached;
} // gravatar_cache

///////////////////////////////////
function wp_gravatar ($who = '', $default = '') {

// use globals to hopefully speed up subsequent iterations
global $grav_options, $gravatar_expire, $gravatar_local;
$cached = FALSE;

if (! isset($grav_options)) {
	gravatar_defaults();
	$grav_options = get_option('gravatar_options');
}
if (! isset($gravatar_expire)) {
	$gravatar_expire = get_option('gravatar_expire');
}
if (! isset($gravatar_local)) {
	$gravatar_local = get_option('gravatar_local');
}

// does this address have a local gravatar?
if ( ('' != $who) && (! empty($gravatar_local)) && ('' != $gravatar_local[$who]) ) {
        return $gravatar_local[$who];
}
if (! isset($now)) {
	$now = time();
}

if ( ('' != $who) && (stristr($who, "http://")) ) {
	// were we handed a URL?
	global $wpdb;
	// let's see if we know who owns this URL
	$parsed = parse_url($who);
	$email = $wpdb->get_var("SELECT DISTINCT comment_author_email FROM $wpdb->comments where comment_author_url='http://" . $parsed['host'] . "' LIMIT 1");
	if (is_email($email)) {
		$who = $email;
	} else {
		$who = '';
	}
} elseif (! is_email($who)) {
	$who = '';
}

if ('0' == $grav_options['gravatar_cache']) {
	// we're not using local cache, so give the gravatar.com URL
	$gravatar = gravatar_query(md5($who), $default);
	$gravatar = str_replace("&", "&amp;", $gravatar);
	return $gravatar;
}

if ('' == $who) {
	// dummy step, to make the rest easier
	$cached = FALSE;
} elseif ('' != $gravatar_expire[$who]) {
	// we have this gravatar -- check the time stamp
	$cached = TRUE;
	if ( (! function_exists('wp_cron_gravcache')) && ($gravatar_expire[$who] < ($now - $grav_options['gravatar_expire'])) ) {
		// it's past the expiration time, so grab the latest version
		$cached = gravatar_cache(md5($who));
		if ($cached) {
			// update the expiration
			$gravatar_expire[$who] = $now;
			update_option('gravatar_expire', $gravatar_expire);
		} else {
			// reset to use the old cached version
			$cached = TRUE;
		}
	}
} else {
	// we don't know about this gravatar yet, let's look for it
	$response = wp_gravatar_info(md5($who));
	if ('200' == $response[code]) {
		// it's not an error, so let's make a local copy
		$cached = gravatar_cache (md5($who));
		if ($cached) {
			// we copied successfully, so set the expiration
			$gravatar_expire[$who] = $now;
			update_option('gravatar_expire', $gravatar_expire);
		}
	}
}

if (FALSE === $cached) {
	if ('/' == substr($grav_options['gravatar_default'], -1, 1)) {
		return random_gravatar();
	} else {
		return $grav_options['gravatar_default'];
	}
} else {
	// we want to return a relative URI
	$url = parse_url(get_settings('siteurl') . '/wp-content/gravatars/' . md5($who));
	return $url['path'];
}
} // wp_gravatar

///////////////////////////
// this is simply a wrapper for wp_gravatar
function gravatar($who = '', $default = '') {
	echo wp_gravatar($who, $default);
}

?>
