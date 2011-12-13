<?php

/**
 * Web based converter script
 *
 * @copyright (C) 2011 FluxBB (http://fluxbb.org)
 * @license LGPL - GNU Lesser General Public License (http://www.gnu.org/licenses/lgpl.html)
 * @package FluxBB
 */

// Start output buffering
ob_start();

// Start session
session_start();

define('SCRIPT_ROOT', dirname(__FILE__).'/');
require SCRIPT_ROOT.'include/functions_web.php';
require SCRIPT_ROOT.'include/common.php';

define('PUN_DEBUG', 1);
define('PUN_SHOW_QUERIES', 1);

// The number of items to process per page view (lower this if the script times out)
define('PER_PAGE', 500);

// If PUN isn't defined, config.php is missing or corrupt
if (!defined('PUN'))
{
	header('Location: ../install.php');
	exit;
}

$default_style = 'Air';

if (isset($_GET['alert_dupe_users']))
{
	if (empty($_SESSION['converter']['dupe_users']))
		conv_error($lang_convert['Bad request']);

	alert_dupe_users();
	unset($_SESSION['converter']['dupe_users']);
}

// We submited the form, store data in session as we'll redirect to the next page
if (isset($_POST['form_sent']))
{
	$forum_config = array(
		'type'		=> isset($_POST['req_forum']) && isset($forums[$_POST['req_forum']]) ? $_POST['req_forum'] : null,
	);

	$old_db_config = array(
		'type'		=> isset($_POST['req_old_db_type']) ? $_POST['req_old_db_type'] : null,
		'host'		=> isset($_POST['req_old_db_host']) ? trim($_POST['req_old_db_host']) : null,
		'name'		=> isset($_POST['req_old_db_name']) ? trim($_POST['req_old_db_name']) : null,
		'username'	=> isset($_POST['old_db_username']) ? trim($_POST['old_db_username']) : null,
		'password'	=> isset($_POST['old_db_pass']) ? $_POST['old_db_pass'] : '',
		'prefix'	=> isset($_POST['old_db_prefix']) ? trim($_POST['old_db_prefix']) : '',
		'charset'	=> isset($_POST['old_db_charset']) ? trim($_POST['old_db_charset']) : 'UTF-8'
	);

	// Check whether we have all needed data valid
	if (!isset($forum_config['type']))
		conv_error('You have to enter a forum software.');
	if (!isset($old_db_config['type']))
		conv_error('You have to enter database type for old forum.');
	if (!isset($old_db_config['host']))
		conv_error('You have to enter a database host for the old forum.');
	if (!isset($old_db_config['name']))
		conv_error('You have to enter a database name for the old forum.');
	if (!isset($old_db_config['username']))
		conv_error('You have to enter a database username for the old forum.');

	if (!array_key_exists($forum_config['type'], $forums))
		conv_error('You entered an invalid forum software');

	if (!in_array($old_db_config['type'], $engines))
		conv_error('Database type for old forum is invalid.');

	$_SESSION['converter'] = array('forum_config' => $forum_config, 'old_db_config' => $old_db_config, 'lang' => $convert_lang);
}

// Fetch data from session
else if (isset($_SESSION['converter']))
{
	$forum_config = $_SESSION['converter']['forum_config'];
	$old_db_config = $_SESSION['converter']['old_db_config'];
	$convert_lang = $_SESSION['converter']['lang'];
}
else
	$old_db_config = $db_config_default;

if (isset($_POST['form_sent']) || isset($_GET['step']))
{
	if (!isset($forum_config))
		conv_error($lang_convert['Bad request']);

	$step = isset($_GET['step']) ? $_GET['step'] : null;
	$start_at = isset($_GET['start_at']) ? $_GET['start_at'] : 0;

	// Get database configuration from config.php
	$db_config = array(
		'type'			=> $db_type,
		'host'			=> $db_host,
		'name'			=> $db_name,
		'username'		=> $db_username,
		'password'		=> $db_password,
		'prefix'		=> $db_prefix,
	);

	// Check we aren't trying to convert to the same database
	if ($old_db_config == $db_config)
		conv_error('Old and new tables must be different!');

	// Create a wrapper for fluxbb (has easy functions for adding users etc.)
	require SCRIPT_ROOT.'include/fluxbb.class.php';
	$fluxbb = new FluxBB($pun_config);
	$db = $fluxbb->connect_database($db_config);

	// Load the migration script
	require SCRIPT_ROOT.'include/forum.class.php';
	$forum = load_forum($forum_config, $fluxbb);
	$forum->connect_database($old_db_config);

	// Load converter script
	require SCRIPT_ROOT.'include/converter.class.php';
	$converter = new Converter($fluxbb, $forum);

	// Validate only first time we run converter (check whether database configuration is valid)
	if (!isset($step))
		$converter->validate();

	if (!isset($step) || $step != 'results')
	{
		// Start the converter. When it do its work, it redirects to the next page
		$converter->convert($step, $start_at, true);
	}

	if (empty($_SESSION['converter']['dupe_users']))
		unset($_SESSION['converter']);

	// We're done
	$alerts = array($lang_convert['Rebuild search index note']);

	if (!$forum->converts_password())
		$alerts[] = $lang_convert['Password converter mod'];

	$fluxbb->close_database();


?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title><?php echo sprintf($lang_convert['FluxBB converter'], CONVERTER_VERSION) ?></title>
<link rel="stylesheet" type="text/css" href="../style/<?php echo $default_style ?>.css" />
</head>
<body>

<div id="puninstall" class="pun">
<div class="top-box"><div><!-- Top Corners --></div></div>
<div class="punwrap">

<div id="brdheader" class="block">
	<div class="box">
		<div id="brdtitle" class="inbox">
			<h1><span><?php echo sprintf($lang_convert['FluxBB converter'], CONVERTER_VERSION) ?></span></h1>
			<div id="brddesc"><p><?php echo sprintf($lang_convert['Conversion completed in'], round($_SESSION['fluxbb_converter']['time'], 4)) ?></p></div>
		</div>
	</div>
</div>

<div id="brdmain">

<div class="blockform">

<?php if (!empty($_SESSION['converter']['dupe_users'])) : ?>
	<h2><span><?php echo $lang_convert['Username dupes head'] ?></span></h2>
	<div class="box">
		<form method="post" action="index.php?stage=results&alert_dupe_users">
			<div class="inform">
				<div class="forminfo">
					<p style="font-size: 1.1em"><?php echo $lang_convert['Error info 1'] ?></p>
					<p style="font-size: 1.1em"><?php echo $lang_convert['Error info 2'].' '.$lang_convert['Click alert button'] ?></p>
				</div>
			</div>
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang_convert['Username dupes'] ?></legend>
					<div class="infldset">
						<p>
<?php
			foreach ($_SESSION['converter']['dupe_users'] as $id => $cur_user)
				echo sprintf($lang_convert['was renamed to'], '<strong>'.pun_htmlspecialchars($cur_user['old_username']).'</strong>', '<strong>'.pun_htmlspecialchars($cur_user['username']).'</strong>').'<br />'."\n";

?>
						</p>
					</div>
				</fieldset>
			</div>

			<p class="buttons"><input type="submit" name="rename" value="<?php echo $lang_convert['Alert users'] ?>" /></p>
		</form>
	</div>
<?php endif; ?>

	<h2><span><?php echo $lang_convert['Final instructions'] ?></span></h2>
	<div class="box">
		<div class="fakeform">
			<div class="inform">
<?php if (!empty($alerts)): ?>				<div class="forminfo error-info">
					<ul class="error-list">
<?php

foreach ($alerts as $cur_alert)
	echo "\t\t\t\t\t".'<li>'.$cur_alert.'</li>'."\n";
?>
					</ul>
				</div>
<?php endif; ?>
				<div class="forminfo">
					<p><?php printf($lang_convert['Database converted'], '<a href="../index.php">'.$lang_convert['go to forum index'].'</a>') ?></p>
				</div>
			</div>
		</div>
	</div>
</div>

</div>

</div>
<div class="end-box"><div><!-- Bottom Corners --></div></div>
</div>

</body>
</html>
<?php


}
else
{

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en" dir="ltr">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title><?php echo sprintf($lang_convert['FluxBB converter'], CONVERTER_VERSION) ?></title>
<link rel="stylesheet" type="text/css" href="../style/<?php echo $default_style ?>.css" />
<script type="text/javascript">
/* <![CDATA[ */
function process_form(the_form)
{
	var element_names = {
		"req_forum": "<?php echo $lang_convert['Forum software'] ?>",
		"req_old_db_type": "<?php echo $lang_convert['Database type'] ?>",
		"req_old_db_host": "<?php echo $lang_convert['Database server hostname'] ?>",
		"req_old_db_name": "<?php echo $lang_convert['Database name'] ?>",
		"old_db_prefix": "<?php echo $lang_convert['Table prefix'] ?>",
	};
	if (document.all || document.getElementById)
	{
		for (var i = 0; i < the_form.length; ++i)
		{
			var elem = the_form.elements[i];
			if (elem.name && (/^req_/.test(elem.name)))
			{
				if (!elem.value && elem.type && (/^(?:text(?:area)?|password|file)$/i.test(elem.type)))
				{
					alert('"' + element_names[elem.name] + '" <?php echo $lang_convert['Required field'] ?>');
					elem.focus();
					return false;
				}
			}
		}
	}
	return true;
}
/* ]]> */
</script>
</head>
<body onload="document.getElementById('install').req_forum.focus();document.getElementById('install').start.disabled=false;" onunload="">

<div id="puninstall" class="pun">
<div class="top-box"><div><!-- Top Corners --></div></div>
<div class="punwrap">

<div id="brdheader" class="block">
	<div class="box">
		<div id="brdtitle" class="inbox">
			<h1><span><?php echo sprintf($lang_convert['FluxBB converter'], CONVERTER_VERSION) ?></span></h1>
			<div id="brddesc"><p><?php echo $lang_convert['Convert message'] ?></p></div>
		</div>
	</div>
</div>

<div id="brdmain">
<?php if (count($languages) > 1): ?><div class="blockform">
	<h2><span><?php echo $lang_convert['Choose convert language'] ?></span></h2>
	<div class="box">
		<form id="install" method="post" action="index.php">
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang_convert['Convert language'] ?></legend>
					<div class="infldset">
						<p><?php echo $lang_convert['Choose convert language info'] ?></p>
						<label><strong><?php echo $lang_convert['Convert language'] ?></strong>
						<br /><select name="convert_lang">
<?php

		foreach ($languages as $temp)
		{
			if ($temp == $convert_lang)
				echo "\t\t\t\t\t".'<option value="'.$temp.'" selected="selected">'.$temp.'</option>'."\n";
			else
				echo "\t\t\t\t\t".'<option value="'.$temp.'">'.$temp.'</option>'."\n";
		}

?>
						</select>
						<br /></label>
					</div>
				</fieldset>
			</div>
			<p class="buttons"><input type="submit" name="start" value="<?php echo $lang_convert['Change language'] ?>" /></p>
		</form>
	</div>
</div>
<?php endif; ?>

<div class="blockform">
	<h2><span><?php echo $lang_convert['Convert'] ?></span></h2>
	<div class="box">
		<form id="install" method="post" action="index.php" onsubmit="this.start.disabled=true;if(process_form(this)){return true;}else{this.start.disabled=false;return false;}">
		<div><input type="hidden" name="module" value="Convert" /><input type="hidden" name="form_sent" value="1" /><input type="hidden" name="convert_lang" value="<?php echo pun_htmlspecialchars($convert_lang) ?>" /></div>
			<div class="inform">
				<div class="forminfo">
					<h3><?php echo $lang_convert['Convert from'] ?></h3>
					<p><?php echo $lang_convert['Convert info 1'] ?></p>
				</div>
				<fieldset>
				<legend><?php echo $lang_convert['Select software'] ?></legend>
					<div class="infldset">
						<p><?php echo $lang_convert['Convert info 2'] ?></p>
						<label class="required"><strong><?php echo $lang_convert['Forum software'] ?> <span><?php echo $lang_convert['Required'] ?></span></strong>
						<br /><select name="req_forum">
<?php

	foreach ($forums as $value => $name)
	{
		if (isset($forum_config['type']) && $value == $forum_config['type'])
			echo "\t\t\t\t\t\t\t".'<option value="'.$value.'" selected="selected">'.$name.'</option>'."\n";
		else
			echo "\t\t\t\t\t\t\t".'<option value="'.$value.'">'.$name.'</option>'."\n";
	}

?>
						</select>
						<br /></label>
					</div>
				</fieldset>
			</div>
			<div class="inform">
				<fieldset>
				<legend><?php echo $lang_convert['Select old database'] ?></legend>
					<div class="infldset">
						<p><?php echo $lang_convert['Convert info 3'] ?></p>
						<label class="required"><strong><?php echo $lang_convert['Database type'] ?> <span><?php echo $lang_convert['Required'] ?></span></strong>
						<br /><select name="req_old_db_type">
<?php

	foreach ($engines as $temp)
	{
		if ($temp == $old_db_config['type'])
			echo "\t\t\t\t\t\t\t".'<option value="'.$temp.'" selected="selected">'.$temp.'</option>'."\n";
		else
			echo "\t\t\t\t\t\t\t".'<option value="'.$temp.'">'.$temp.'</option>'."\n";
	}

?>
						</select>
						<br /></label>
						<label class="required"><strong><?php echo $lang_convert['Database server hostname'] ?> <span><?php echo $lang_convert['Required'] ?></span></strong><br /><input type="text" name="req_old_db_host" value="<?php echo pun_htmlspecialchars($old_db_config['host']) ?>" size="50" /><br /></label>
						<label class="required"><strong><?php echo $lang_convert['Database name'] ?> <span><?php echo $lang_convert['Required'] ?></span></strong><br /><input type="text" name="req_old_db_name" value="<?php echo pun_htmlspecialchars($old_db_config['name']) ?>" size="30" /><br /></label>
						<label class="conl"><?php echo $lang_convert['Database username'] ?><br /><input type="text" name="old_db_username" value="<?php echo pun_htmlspecialchars($old_db_config['username']) ?>" size="30" /><br /></label>
						<label class="conl"><?php echo $lang_convert['Database password'] ?><br /><input type="password" name="old_db_password" size="30" /><br /></label>
						<label><?php echo $lang_convert['Database charset'] ?> <?php echo $lang_convert['Database charset info'] ?><br /><input type="text" name="old_db_charset" value="<?php echo pun_htmlspecialchars($old_db_config['charset']) ?>" size="20" maxlength="30" /><br /></label>
						<label><?php echo $lang_convert['Table prefix'] ?><br /><input id="db_prefix" type="text" name="old_db_prefix" value="<?php echo pun_htmlspecialchars($old_db_config['prefix']) ?>" size="20" maxlength="30" /><br /></label>
					</div>
				</fieldset>
			</div>
			<p class="buttons"><input type="submit" name="start" value="<?php echo $lang_convert['Start converter'] ?>" /></p>
		</form>
	</div>
</div>
</div>

</div>
<div class="end-box"><div><!-- Bottom Corners --></div></div>
</div>

</body>
</html>
<?php

}

