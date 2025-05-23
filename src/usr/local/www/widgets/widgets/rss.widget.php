<?php
/*
 * rss.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2025 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");

/*
 * Validate the "widgetkey" value.
 * When this widget is present on the Dashboard, $widgetkey is defined before
 * the Dashboard includes the widget. During other types of requests, such as
 * saving settings or AJAX, the value may be set via $_POST or similar.
 */
if ($_POST['widgetkey'] || $_GET['widgetkey']) {
	$rwidgetkey = isset($_POST['widgetkey']) ? $_POST['widgetkey'] : (isset($_GET['widgetkey']) ? $_GET['widgetkey'] : null);
	if (is_valid_widgetkey($rwidgetkey, $user_settings, __FILE__)) {
		$widgetkey = $rwidgetkey;
	} else {
		print gettext("Invalid Widget Key");
		exit;
	}
}

/* bring in the Composer autoloader */
require_once('vendor/autoload.php');

use SimplePie\SimplePie;

if ($_POST['widgetkey']) {
	set_customwidgettitle($user_settings);

	if ($_POST['rssfeed']) {
		$validfeeds = [];
		/* Allow feeds separated by comma or newline */
		$feeds = preg_split('/[,\n]/', $_POST['rssfeed']);
		foreach ($feeds as $feed) {
			/* Trim any extra whitespace as the submitted value may have \r at the end. */
			$feed = trim($feed);
			if (is_URL($feed)) {
				$validfeeds[] = $feed;
			}
		}
		$user_settings['widgets'][$_POST['widgetkey']]['rssfeed'] = htmlspecialchars(implode(",", $validfeeds), ENT_QUOTES | ENT_HTML401);
	}

	if (is_numeric($_POST['rssmaxitems'])) {
		$user_settings['widgets'][$_POST['widgetkey']]['rssmaxitems'] = $_POST['rssmaxitems'];
	} else {
		unset($user_settings['widgets'][$_POST['widgetkey']]['rssmaxitems']);
	}
	if (is_numeric($_POST['rsswidgetheight'])) {
		$user_settings['widgets'][$_POST['widgetkey']]['rsswidgetheight'] = $_POST['rsswidgetheight'];
	} else {
		unset($user_settings['widgets'][$_POST['widgetkey']]['rsswidgetheight']);
	}
	if (is_numeric($_POST['rsswidgettextlength'])) {
		$user_settings['widgets'][$_POST['widgetkey']]['rsswidgettextlength'] = $_POST['rsswidgettextlength'];
	} else {
		unset($user_settings['widgets'][$_POST['widgetkey']]['rsswidgettextlength']);
	}

	save_widget_settings($_SESSION['Username'], $user_settings["widgets"], gettext("Saved RSS Widget feed via Dashboard."));
	header("Location: /");
}

// Use saved feed and max items
if ($user_settings['widgets'][$widgetkey]['rssfeed']) {
	$rss_feed_s = explode(",", $user_settings['widgets'][$widgetkey]['rssfeed']);
}

if ($user_settings['widgets'][$widgetkey]['rssmaxitems']) {
	$max_items =  (int) $user_settings['widgets'][$widgetkey]['rssmaxitems'];
}

if (is_numeric($user_settings['widgets'][$widgetkey]['rsswidgetheight'])) {
	$rsswidgetheight = (int) $user_settings['widgets'][$widgetkey]['rsswidgetheight'];
}

if (is_numeric($user_settings['widgets'][$widgetkey]['rsswidgettextlength'])) {
	$rsswidgettextlength = (int) $user_settings['widgets'][$widgetkey]['rsswidgettextlength'];
}

// Set a default feed if none exists
if (!$rss_feed_s) {
	$rss_feed_s = "https://www.netgate.com/blog/";
	if ($widgetkey != "") {
		$user_settings['widgets'][$widgetkey]['rssfeed'] = $rss_feed_s;
	}
}

if (!$max_items || !is_numeric($max_items)) {
	$max_items = 10;
}

if (!$rsswidgetheight || !is_numeric($rsswidgetheight)) {
	$rsswidgetheight = 300;
}

if (!$rsswidgettextlength || !is_numeric($rsswidgettextlength)) {
	$rsswidgettextlength = 140; // oh twitter, how do we love thee?
}

if ($user_settings['widgets'][$widgetkey]['rssfeed']) {
	$textarea_txt =	 str_replace(",", "\n", $user_settings['widgets'][$widgetkey]['rssfeed']);
} else {
	$textarea_txt = "";
}

?>
<div class="list-group" style="height: <?=$rsswidgetheight?>px; overflow:scroll;">
<?php
	if (!is_dir("/tmp/simplepie")) {
		mkdir("/tmp/simplepie");
		mkdir("/tmp/simplepie/cache");
	}
	exec("/bin/chmod a+rw /tmp/simplepie/.");
	exec("/bin/chmod a+rw /tmp/simplepie/cache/.");
	if (!function_exists('textLimit')) {
		function textLimit($string, $length, $replacer = '...') {
			if (strlen($string) > $length) {
				return (preg_match('/^(.*)\W.*$/', substr($string, 0, $length+1), $matches) ? $matches[1] : substr($string, 0, $length)) . $replacer;
			}
			return $string;
		}
	}
	$feed = new SimplePie();
	$feed->set_cache_location("/tmp/simplepie/");
	$feed->set_feed_url($rss_feed_s);
	$feed->init();
	$feed->handle_content_type();
	$counter = 1;
	foreach ($feed->get_items(0, $max_items) as $item) {
		$feed = $item->get_feed();
		$feed->strip_htmltags();
		$content = $item->get_content();
		$content = strip_tags($content);
?>
	<a href="<?=$item->get_permalink()?>" target="_blank" class="list-group-item">
		<h4 class="list-group-item-heading">
			<?=$item->get_title()?>
		</h4>
		<p class="list-group-item-text">
			<?=textLimit($content, $rsswidgettextlength)?>
			<br />
		</p>
	</a>
<?php
	}
?>

</div>

<!-- close the body we're wrapped in and add a configuration-panel -->
</div><div id="<?=$widget_panel_footer_id?>" class="panel-footer collapse">

<form action="/widgets/widgets/rss.widget.php" method="post" class="form-horizontal">
	<input type="hidden" name="widgetkey" value="<?=htmlspecialchars($widgetkey); ?>">
	<?=gen_customwidgettitle_div($widgetconfig['title']); ?>
	<div class="form-group">
		<label for="rssfeed" class="col-sm-4 control-label"><?=gettext('Feeds')?></label>
		<div class="col-sm-6">
			<textarea id="rssfeed" name="rssfeed" class="form-control"><?=$textarea_txt;?></textarea>
		</div>
	</div>

	<div class="form-group">
		<label for="rssmaxitems" class="col-sm-4 control-label"><?=gettext('# Stories')?></label>
		<div class="col-sm-6">
			<input type="number" id="rssmaxitems" name="rssmaxitems" value="<?=$max_items?>" min="1" max="100" class="form-control" />
		</div>
	</div>

	<div class="form-group">
		<label for="rsswidgetheight" class="col-sm-4 control-label"><?=gettext('Widget height')?></label>
		<div class="col-sm-6">
			<input type="number" id="rsswidgetheight" name="rsswidgetheight" value="<?=$rsswidgetheight?>" min="100" max="2500" step="100" class="form-control" />
		</div>
	</div>

	<div class="form-group">
		<label for="rsswidgettextlength" class="col-sm-4 control-label"><?=gettext('Content limit')?></label>
		<div class="col-sm-6">
			<input type="number" id="rsswidgettextlength" name="rsswidgettextlength" value="<?=$rsswidgettextlength?>" min="100" max="5000" step="10" class="form-control" />
		</div>
	</div>

	<div class="form-group">
		<div class="col-sm-offset-3 col-sm-6">
			<button type="submit" class="btn btn-primary"><i class="fa-solid fa-save icon-embed-btn"></i><?=gettext('Save')?></button>
		</div>
	</div>
</form>
