<?php
/*
 * system.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2025 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * originally based on m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
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

##|+PRIV
##|*IDENT=page-system-generalsetup
##|*NAME=System: General Setup
##|*DESCR=Allow access to the 'System: General Setup' page.
##|*MATCH=system.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("system.inc");

$pconfig['hostname'] = config_get_path('system/hostname');
$pconfig['domain'] = config_get_path('system/domain');
$pconfig['dnsserver'] = config_get_path('system/dnsserver');

$arr_gateways = get_gateways();

// set default columns to two if unset
if (!is_numericint(config_get_path('system/webgui/dashboardcolumns'))) {
	config_set_path('system/webgui/dashboardcolumns', 2);
}

// set default language if unset
if (!config_path_enabled('system', 'language')) {
	config_set_path('system/language', g_get('language'));
}

$dnshost_counter = 1;

while (config_path_enabled("system", "dns{$dnshost_counter}host")) {
	$pconfig_dnshost_counter = $dnshost_counter - 1;
	$pconfig["dnshost{$pconfig_dnshost_counter}"] = config_get_path("system/dns{$dnshost_counter}host");
	$dnshost_counter++;
}

$dnsgw_counter = 1;

while (config_get_path("system/dns{$dnsgw_counter}gw") !== null) {
	$pconfig_dnsgw_counter = $dnsgw_counter - 1;
	$pconfig["dnsgw{$pconfig_dnsgw_counter}"] = config_get_path("system/dns{$dnsgw_counter}gw");
	$dnsgw_counter++;
}

$pconfig['dnsallowoverride'] = config_path_enabled('system', 'dnsallowoverride');
$pconfig['timezone'] = config_get_path('system/timezone');
$pconfig['timeservers'] = config_get_path('system/timeservers');
$pconfig['language'] = config_get_path('system/language');
$pconfig['webguicss'] = config_get_path('system/webgui/webguicss');
$pconfig['logincss'] = config_get_path('system/webgui/logincss');
$pconfig['webguifixedmenu'] = config_get_path('system/webgui/webguifixedmenu');
$pconfig['dashboardcolumns'] = config_get_path('system/webgui/dashboardcolumns');
$pconfig['interfacessort'] = config_path_enabled('system/webgui', 'interfacessort');
$pconfig['webguileftcolumnhyper'] = config_path_enabled('system/webgui', 'webguileftcolumnhyper');
$pconfig['disablealiaspopupdetail'] = config_path_enabled('system/webgui', 'disablealiaspopupdetail');
$pconfig['dashboardavailablewidgetspanel'] = config_path_enabled('system/webgui', 'dashboardavailablewidgetspanel');
$pconfig['systemlogsfilterpanel'] = config_path_enabled('system/webgui', 'systemlogsfilterpanel');
$pconfig['systemlogsmanagelogpanel'] = config_path_enabled('system/webgui', 'systemlogsmanagelogpanel');
$pconfig['statusmonitoringsettingspanel'] = config_path_enabled('system/webgui', 'statusmonitoringsettingspanel');
$pconfig['webguihostnamemenu'] = config_get_path('system/webgui/webguihostnamemenu');
$pconfig['dnslocalhost'] = config_get_path('system/dnslocalhost');
//$pconfig['dashboardperiod'] = isset($config['widgets']['period']) ? $config['widgets']['period']:"10";
$pconfig['roworderdragging'] = config_path_enabled('system/webgui', 'roworderdragging');
$pconfig['loginshowhost'] = config_path_enabled('system/webgui', 'loginshowhost');
$pconfig['login_message'] = htmlentities(base64_decode(config_get_path('system/login_message', '')));
$pconfig['requirestatefilter'] = config_path_enabled('system/webgui', 'requirestatefilter');
$pconfig['requirefirewallinterface'] = config_path_enabled('system/webgui', 'requirefirewallinterface');

if (!$pconfig['timezone']) {
	if (isset($g['default_timezone']) && !empty(g_get('default_timezone'))) {
		$pconfig['timezone'] = g_get('default_timezone');
	} else {
		$pconfig['timezone'] = "Etc/UTC";
	}
}

if (!$pconfig['timeservers']) {
	$pconfig['timeservers'] = "pool.ntp.org";
}

$changedesc = gettext("System") . ": ";
$changecount = 0;

function is_timezone($elt) {
	return !preg_match("/\/$/", $elt);
}

if ($pconfig['timezone'] <> $_POST['timezone']) {
	filter_pflog_start();
}

$timezonelist = system_get_timezone_list();
$timezonedesc = $timezonelist;

/*
 * Etc/GMT entries work the opposite way to what people expect.
 * Ref: https://github.com/eggert/tz/blob/master/etcetera and Redmine issue 7089
 * Add explanatory text to entries like:
 * Etc/GMT+1 and Etc/GMT-1
 * but not:
 * Etc/GMT or Etc/GMT+0
 */
foreach ($timezonedesc as $idx => $desc) {
	if (substr($desc, 0, 7) != "Etc/GMT" || substr($desc, 8, 1) == "0") {
		continue;
	}

	$direction = substr($desc, 7, 1);

	switch ($direction) {
	case '-':
		$direction_str = gettext('AHEAD of');
		break;
	case '+':
		$direction_str = gettext('BEHIND');
		break;
	default:
		continue 2;
	}

	$hr_offset = substr($desc, 8);
	$timezonedesc[$idx] = $desc . " " .
	    sprintf(ngettext('(%1$s hour %2$s GMT)', '(%1$s hours %2$s GMT)', intval($hr_offset)), $hr_offset, $direction_str);
}

$multiwan = 0;
$multiwan6 = 0;
foreach ($arr_gateways as $gw) {
	if ($gw['ipprotocol'] == 'inet') {
		$multiwan++;
		if ($multiwan > 1) {
			break;
		}
	} else {
		$multiwan6++;
		if ($multiwan6 > 1) {
			break;
		}
	}
}

if ($_POST) {

	$changecount++;

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "hostname domain");
	$reqdfieldsn = array(gettext("Hostname"), gettext("Domain"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if ($_POST['hostname']) {
		if (!is_hostname($_POST['hostname'])) {
			$input_errors[] = gettext("The hostname can only contain the characters A-Z, 0-9 and '-'. It may not start or end with '-'.");
		} else {
			if (!is_unqualified_hostname($_POST['hostname'])) {
				$input_errors[] = gettext("A valid hostname is specified, but the domain name part should be omitted");
			}
		}
	}
	if ($_POST['domain'] && (!is_domain($_POST['domain'], false, false))) {
		$input_errors[] = gettext("The domain may only contain the characters a-z, 0-9, '-' and '.', and it cannot start with '.' or '-'.");
	}
	validate_webguicss_field($input_errors, $_POST['webguicss']);
	validate_webguifixedmenu_field($input_errors, $_POST['webguifixedmenu']);
	validate_webguihostnamemenu_field($input_errors, $_POST['webguihostnamemenu']);
	validate_dashboardcolumns_field($input_errors, $_POST['dashboardcolumns']);

	$dnslist = $ignore_posted_dnsgw = array();

	$dnscounter = 0;
	$dnsname = "dns{$dnscounter}";

	while (isset($_POST[$dnsname])) {
		$dnsgwname = "dnsgw{$dnscounter}";
		$dnshostname = "dnshost{$dnscounter}";
		$dnslist[] = $_POST[$dnsname];

		if (($_POST[$dnsname] && !is_ipaddr($_POST[$dnsname]))) {
			$input_errors[] = sprintf(gettext("A valid IP address must be specified for DNS server %s."), $dnscounter+1);
		} else {
			if (!empty($_POST[$dnshostname]) && !is_hostname($_POST[$dnshostname])) {
				$input_errors[] = sprintf(gettext('The hostname provided for DNS server "%1$s" is not valid.'), $_POST[$dnsname]);
			}
			if (($_POST[$dnsgwname] <> "") && ($_POST[$dnsgwname] <> "none")) {
				// A real gateway has been selected.
				if (is_ipaddr($_POST[$dnsname])) {
					if ((is_ipaddrv4($_POST[$dnsname])) && (validate_address_family($_POST[$dnsname], $_POST[$dnsgwname]) === false)) {
						$input_errors[] = sprintf(gettext('The IPv6 gateway "%1$s" can not be specified for IPv4 DNS server "%2$s".'), $_POST[$dnsgwname], $_POST[$dnsname]);
					}
					if ((is_ipaddrv6($_POST[$dnsname])) && (validate_address_family($_POST[$dnsname], $_POST[$dnsgwname]) === false)) {
						$input_errors[] = sprintf(gettext('The IPv4 gateway "%1$s" can not be specified for IPv6 DNS server "%2$s".'), $_POST[$dnsgwname], $_POST[$dnsname]);
					}
				} else {
					// The user selected a gateway but did not provide a DNS address. Be nice and set the gateway back to "none".
					$ignore_posted_dnsgw[$dnsgwname] = true;
				}
			}
		}
		$dnscounter++;
		$dnsname = "dns{$dnscounter}";
	}

	if (count(array_filter($dnslist)) != count(array_unique(array_filter($dnslist)))) {
		$input_errors[] = gettext('Each configured DNS server must have a unique IP address. Remove the duplicated IP.');
	}

	$dnscounter = 0;
	$dnsname = "dns{$dnscounter}";

	$direct_networks_list = explode(" ", filter_get_direct_networks_list());
	while (isset($_POST[$dnsname])) {
		$dnsgwname = "dnsgw{$dnscounter}";
		if ($_POST[$dnsgwname] && ($_POST[$dnsgwname] <> "none")) {
			foreach ($direct_networks_list as $direct_network) {
				if (ip_in_subnet($_POST[$dnsname], $direct_network)) {
					$input_errors[] = sprintf(gettext("A gateway cannot be specified for %s because that IP address is part of a directly connected subnet %s. To use that nameserver, change its Gateway to `none`."), $_POST[$dnsname], $direct_network);
				}
			}
		}
		$dnscounter++;
		$dnsname = "dns{$dnscounter}";
	}

	# it's easy to have a little too much whitespace in the field, clean it up for the user before processing.
	$_POST['timeservers'] = preg_replace('/[[:blank:]]+/', ' ', $_POST['timeservers']);
	$_POST['timeservers'] = trim($_POST['timeservers']);
	foreach (explode(' ', $_POST['timeservers']) as $ts) {
		if (!is_domain($ts) && (!is_ipaddr($ts))) {
			$input_errors[] = gettext("NTP Time Server names must be valid domain names, IPv4 addresses, or IPv6 addresses");
		}
	}

	if ($input_errors) {
		// Put the user-entered list back into place so it will be redisplayed for correction.
		$pconfig['dnsserver'] = $dnslist;
	} else {
		// input validation passed, so we can proceed with removing static routes for dead DNS gateways
		if (is_array(config_get_path('system/dnsserver'))) {
		  	$dns_servers_arr = config_get_path('system/dnsserver');
	 		foreach ($dns_servers_arr as $arr_index => $this_dnsserver) {
				$i = (int)$arr_index + 1;
				$this_dnsgw = config_get_path("system/dns{$i}gw");
				unset($gatewayip);
				unset($inet6);
				if ((!empty($this_dnsgw)) && ($this_dnsgw != 'none') && (!empty($this_dnsserver))) {
					$gatewayip = lookup_gateway_ip_by_name($this_dnsgw);
					$inet6 = is_ipaddrv6($gatewayip) ? '-inet6 ' : '';
					mwexec("/sbin/route -q delete -host {$inet6}{$this_dnsserver} " . escapeshellarg($gatewayip));
				}
			}
		}

		$system_config = config_get_path('system');
		update_if_changed("hostname", $system_config['hostname'], $_POST['hostname']);
		update_if_changed("domain", $system_config['domain'], $_POST['domain']);
		update_if_changed("timezone", $system_config['timezone'], $_POST['timezone']);
		update_if_changed("NTP servers", $system_config['timeservers'], strtolower($_POST['timeservers']));
		config_set_path('system', $system_config);

		if ($_POST['language'] && $_POST['language'] != config_get_path('system/language')) {
			config_set_path('system/language', $_POST['language']);
			set_language();
		}

		config_del_path('system/webgui/interfacessort');
		config_set_path('system/webgui/interfacessort', $_POST['interfacessort'] ? true : false);

		config_del_path('system/webgui/webguileftcolumnhyper');
		config_set_path('system/webgui/webguileftcolumnhyper', $_POST['webguileftcolumnhyper'] ? true : false);

		config_del_path('system/webgui/disablealiaspopupdetail');
		config_set_path('system/webgui/disablealiaspopupdetail', $_POST['disablealiaspopupdetail'] ? true : false);

		config_del_path('system/webgui/dashboardavailablewidgetspanel');
		config_set_path('system/webgui/dashboardavailablewidgetspanel', $_POST['dashboardavailablewidgetspanel'] ? true : false);

		config_del_path('system/webgui/systemlogsfilterpanel');
		config_set_path('system/webgui/systemlogsfilterpanel', $_POST['systemlogsfilterpanel'] ? true : false);

		config_del_path('system/webgui/systemlogsmanagelogpanel');
		config_set_path('system/webgui/systemlogsmanagelogpanel', $_POST['systemlogsmanagelogpanel'] ? true : false);

		config_del_path('system/webgui/statusmonitoringsettingspanel');
		config_set_path('system/webgui/statusmonitoringsettingspanel', $_POST['statusmonitoringsettingspanel'] ? true : false);

//		if ($_POST['dashboardperiod']) {
//			$config['widgets']['period'] = $_POST['dashboardperiod'];
//		}

		if ($_POST['webguicss']) {
			config_set_path('system/webgui/webguicss', $_POST['webguicss']);
		} else {
			config_del_path('system/webgui/webguicss');
		}

		config_set_path('system/webgui/roworderdragging', $_POST['roworderdragging'] ? true:false);

		if ($_POST['logincss']) {
			config_set_path('system/webgui/logincss', $_POST['logincss']);
		} else {
			config_del_path('system/webgui/logincss');
		}

		config_set_path('system/webgui/loginshowhost', $_POST['loginshowhost'] ? true:false);

		if (isset($_POST['login_message']) && (strlen(strval($_POST['login_message'])) > 0)) {
			$login_message = strip_tags(strval($pconfig['login_message']));
			$pconfig['login_message'] = $login_message;
			config_set_path('system/login_message', base64_encode($login_message));
		} else {
			config_del_path('system/login_message');
		}

		if ($_POST['webguifixedmenu']) {
			config_set_path('system/webgui/webguifixedmenu', $_POST['webguifixedmenu']);
		} else {
			config_del_path('system/webgui/webguifixedmenu');
		}

		if ($_POST['webguihostnamemenu']) {
			config_set_path('system/webgui/webguihostnamemenu', $_POST['webguihostnamemenu']);
		} else {
			config_del_path('system/webgui/webguihostnamemenu');
		}

		if ($_POST['dashboardcolumns']) {
			config_set_path('system/webgui/dashboardcolumns', $_POST['dashboardcolumns']);
		} else {
			config_del_path('system/webgui/dashboardcolumns');
		}

		config_set_path('system/webgui/requirestatefilter', $_POST['requirestatefilter'] ? true : false);
		config_set_path('system/webgui/requirefirewallinterface', $_POST['requirefirewallinterface'] ? true : false);

		/* XXX - billm: these still need updating after figuring out how to check if they actually changed */
		$olddnsservers = config_get_path('system/dnsserver');
		config_del_path('system/dnsserver');

		$dnscounter = 0;
		$dnsname = "dns{$dnscounter}";

		while (isset($_POST[$dnsname])) {
			if ($_POST[$dnsname]) {
				config_set_path('system/dnsserver/', $_POST[$dnsname]);
			}
			$dnscounter++;
			$dnsname = "dns{$dnscounter}";
		}

		// Remember the new list for display also.
		$pconfig['dnsserver'] = config_get_path('system/dnsserver');

		$olddnsallowoverride = config_get_path('system/dnsallowoverride');

		config_del_path('system/dnsallowoverride');
		config_set_path('system/dnsallowoverride', $_POST['dnsallowoverride'] ? true : false);

		if ($_POST['dnslocalhost']) {
			config_set_path('system/dnslocalhost', $_POST['dnslocalhost']);
		} else {
			config_del_path('system/dnslocalhost');
		}

		/* which interface should the dns servers resolve through? */
		$dnscounter = 0;
		// The $_POST array key of the DNS IP (starts from 0)
		$dnsname = "dns{$dnscounter}";
		$outdnscounter = 0;
		while (isset($_POST[$dnsname])) {
			// The $_POST array key of the corresponding gateway (starts from 0)
			$dnsgwname = "dnsgw{$dnscounter}";
			$dnshostname = "dnshost{$dnscounter}";
			// The numbering of DNS GW/host entries in the config starts from 1
			$dnsgwconfigcounter = $dnscounter + 1;
			$dnshostconfigcounter = $dnscounter + 1;
			// So this is the array key of the DNS GW entry in $config['system']
			$dnsgwconfigname = "dns{$dnsgwconfigcounter}gw";
			$dnshostconfigname = "dns{$dnshostconfigcounter}host";

			$olddnsgwname = config_get_path("system/{$dnsgwconfigname}");
			$olddnshostname = config_get_path("system/{$dnshostconfigname}");

			if ($ignore_posted_dnsgw[$dnsgwname]) {
				$thisdnsgwname = "none";
			} else {
				$thisdnsgwname = $pconfig[$dnsgwname];
			}
			$thisdnshostname = $pconfig[$dnshostname];

			// "Blank" out the settings for this index, then we set them below using the "outdnscounter" index.
			config_set_path("system/{$dnsgwconfigname}", 'none');
			$pconfig[$dnsgwname] = "none";
			config_set_path("system/{$dnshostconfigname}", '');
			$pconfig[$dnshostname] = "";
			$pconfig[$dnsname] = "";

			if ($_POST[$dnsname]) {
				// Only the non-blank DNS servers were put into the config above.
				// So we similarly only add the corresponding gateways sequentially to the config (and to pconfig), as we find non-blank DNS servers.
				// This keeps the DNS server IP and corresponding gateway "lined up" when the user blanks out a DNS server IP in the middle of the list.

				// The $pconfig array key of the DNS IP (starts from 0)
				$outdnsname = "dns{$outdnscounter}";
				// The $pconfig array key of the corresponding gateway (starts from 0)
				$outdnsgwname = "dnsgw{$outdnscounter}";
				// The $pconfig array key of the corresponding hostname (starts from 0)
				$outdnshostname = "dnshost{$outdnscounter}";

				// The numbering of DNS GW/host entries in the config starts from 1
				$outdnsgwconfigcounter = $outdnscounter + 1;
				$outdnshostconfigcounter = $outdnscounter + 1;
				// So this is the array key of the output DNS GW entry in $config['system']
				$outdnsgwconfigname = "dns{$outdnsgwconfigcounter}gw";
				$outdnshostconfigname = "dns{$outdnshostconfigcounter}host";

				$pconfig[$outdnsname] = $_POST[$dnsname];
				if ($_POST[$dnsgwname]) {
					config_set_path("system/{$outdnsgwconfigname}", $thisdnsgwname);
					$pconfig[$outdnsgwname] = $thisdnsgwname;
				} else {
					// Note: when no DNS GW name is chosen, the entry is set to "none", so actually this case never happens.
					config_del_path("system/{$outdnsgwconfigname}");
					$pconfig[$outdnsgwname] = "";
				}
				if ($_POST[$dnshostname]) {
					config_set_path("system/{$outdnshostconfigname}", $thisdnshostname);
					$pconfig[$outdnshostname] = $thisdnshostname;
				} else {
					// Note: when no DNS hostname is chosen, unset the value.
					config_del_path("system/{$outdnshostconfigname}");
					$pconfig[$outdnshostname] = "";
				}
				$outdnscounter++;
			}

			$dnscounter++;
			// The $_POST array key of the DNS IP (starts from 0)
			$dnsname = "dns{$dnscounter}";
		}

		// clean up dnsgw orphans
		$oldgwcounter = 1;
		$olddnsgwconfigname = "dns{$oldgwcounter}gw";
		while (config_get_path("system/{$olddnsgwconfigname}") !== null) {
			if (empty(config_get_path('system/dnsserver/' . ($oldgwcounter - 1)))) {
				config_del_path("system/{$olddnsgwconfigname}");
			}
			$oldgwcounter++;
			$olddnsgwconfigname = "dns{$oldgwcounter}gw";
		}
		unset($oldgwcounter);
		unset($olddnsgwconfigname);

		if ($changecount > 0) {
			write_config($changedesc);
		}

		$changes_applied = true;
		$retval = 0;
		$retval |= system_hostname_configure();
		$retval |= system_hosts_generate();
		$retval |= system_resolvconf_generate();
		if (config_path_enabled('dnsmasq')) {
			$retval |= services_dnsmasq_configure();
		} elseif (config_path_enabled('unbound')) {
			$retval |= services_unbound_configure();
		}
		$retval |= system_timezone_configure();
		$retval |= system_ntp_configure();

		if ($olddnsallowoverride != config_get_path('system/dnsallowoverride')) {
			$retval |= send_event("service reload dns");
		}

		// Reload the filter - plugins might need to be run.
		$retval |= filter_configure();
	}

	unset($ignore_posted_dnsgw);
}

$pgtitle = array(gettext("System"), gettext("General Setup"));
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($changes_applied) {
	print_apply_result_box($retval);
}
?>
<div id="container">
<?php

$form = new Form;
$section = new Form_Section('System');
$section->addInput(new Form_Input(
	'hostname',
	'*Hostname',
	'text',
	$pconfig['hostname'],
	['placeholder' => 'pfSense']
))->setHelp('Name of the firewall host, without domain part.');

$section->addInput(new Form_Input(
	'domain',
	'*Domain',
	'text',
	$pconfig['domain'],
	['placeholder' => 'home.arpa, example.com, home, office, private, etc.']
))->setHelp('Domain name for the firewall.%1$s%1$s' .
	'Do not end the domain name with \'.local\' as the final part (Top Level Domain, TLD). ' .
	'The \'local\' TLD is %2$swidely used%3$s by mDNS (e.g. Avahi, Bonjour, Rendezvous, Airprint, Airplay) ' .
	'and some Windows systems and networked devices. ' .
	'These will not network correctly if the router uses \'local\' as its TLD. ' .
	'Alternatives such as \'home.arpa\', \'local.lan\', or \'mylocal\' are safe.',
	'<br/>',
	'<a target="_blank" href="https://www.unbound.net/pipermail/unbound-users/2011-March/001735.html">',
	'</a>'
);

$form->add($section);

$section = new Form_Section('DNS Server Settings');

if (!is_array($pconfig['dnsserver'])) {
	$pconfig['dnsserver'] = array();
}

$dnsserver_count = count($pconfig['dnsserver']);
$dnsserver_num = 0;
$dnsserver_help = gettext("Address") . '<br/>' . gettext("Enter IP addresses to be used by the system for DNS resolution.") . " " .
	gettext("These are also used for the DHCP service, DNS Forwarder and DNS Resolver when it has DNS Query Forwarding enabled.");
$dnshost_help = gettext("Hostname") . '<br/>' . gettext("Enter the DNS Server Hostname for TLS Verification in the DNS Resolver (optional).");
$dnsgw_help = gettext("Gateway") . '<br/>'. gettext("Optionally select the gateway for each DNS server.") . " " .
	gettext("When using multiple WAN connections there should be at least one unique DNS server per gateway.");

// If there are no DNS servers, make an empty entry for initial display.
if ($dnsserver_count == 0) {
	$pconfig['dnsserver'][] = '';
}

foreach ($pconfig['dnsserver'] as $dnsserver) {

	$is_last_dnsserver = (($dnsserver_num == $dnsserver_count - 1) || $dnsserver_count == 0);
	$group = new Form_Group($dnsserver_num == 0 ? 'DNS Servers':'');
	$group->addClass('repeatable');

	$group->add(new Form_Input(
		'dns' . $dnsserver_num,
		'DNS Server',
		'text',
		$dnsserver
	))->setHelp(($is_last_dnsserver) ? $dnsserver_help:null);

	$group->add(new Form_Input(
		'dnshost' . $dnsserver_num,
		'DNS Hostname',
		'text',
		$pconfig['dnshost' . $dnsserver_num]
	))->setHelp(($is_last_dnsserver) ? $dnshost_help:null);

	if (($multiwan > 1) || ($multiwan6 > 1)) {
		$options = array('none' => 'none');

		foreach ($arr_gateways as $gwname => $gwitem) {
			if ((is_ipaddrv4(lookup_gateway_ip_by_name($pconfig[$dnsgw])) && (is_ipaddrv6($gwitem['gateway'])))) {
				continue;
			}

			if ((is_ipaddrv6(lookup_gateway_ip_by_name($pconfig[$dnsgw])) && (is_ipaddrv4($gwitem['gateway'])))) {
				continue;
			}

			$options[$gwname] = $gwname.' - '.$gwitem['friendlyiface'].' - '.$gwitem['gateway'];
		}

		$group->add(new Form_Select(
			'dnsgw' . $dnsserver_num,
			'Gateway',
			$pconfig['dnsgw' . $dnsserver_num],
			$options
		))->setWidth(4)->setHelp(($is_last_dnsserver) ? $dnsgw_help:null);
	}

	$group->add(new Form_Button(
		'deleterow' . $dnsserver_num,
		'Delete',
		null,
		'fa-solid fa-trash-can'
	))->setWidth(2)->addClass('btn-warning');

	$section->add($group);
	$dnsserver_num++;
}

$section->addInput(new Form_Button(
	'addrow',
	'Add DNS Server',
	null,
	'fa-solid fa-plus'
))->addClass('btn-success addbtn');

$section->addInput(new Form_Checkbox(
	'dnsallowoverride',
	'DNS Server Override',
	'Allow DNS server list to be overridden by DHCP/PPP on WAN or remote OpenVPN server',
	$pconfig['dnsallowoverride']
))->setHelp('If this option is set, %s will use DNS servers '.
	'assigned by a DHCP/PPP server on WAN or a remote OpenVPN server (if Pull DNS ' .
	'option is enabled) for its own purposes (including the DNS Forwarder/DNS Resolver). '.
        'However, they will not be assigned to DHCP clients.', g_get('product_label'));

$section->addInput(new Form_Select(
	'dnslocalhost',
	'DNS Resolution Behavior',
	$pconfig['dnslocalhost'],
	array(
		''       => 'Use local DNS (127.0.0.1), fall back to remote DNS Servers (Default)',
		'local'  => 'Use local DNS (127.0.0.1), ignore remote DNS Servers',
		'remote' => 'Use remote DNS Servers, ignore local DNS',
	)
))->setHelp('By default the firewall will use local DNS service (127.0.0.1, DNS '.
	'Resolver or Forwarder) as the first DNS server when possible, and it '.
	'will fall back to remote DNS servers otherwise. Use this option to '.
	'choose alternate behaviors.');

$form->add($section);

$section = new Form_Section('Localization');

$section->addInput(new Form_Select(
	'timezone',
	'*Timezone',
	$pconfig['timezone'],
	array_combine($timezonelist, $timezonedesc)
))->setHelp('Select a geographic region name (Continent/Location) to determine the timezone for the firewall. %1$s' .
	'Choose a special or "Etc" zone only in cases where the geographic zones do not properly handle the clock offset required for this firewall.', '<br/>');

$section->addInput(new Form_Input(
	'timeservers',
	'Timeservers',
	'text',
	$pconfig['timeservers']
))->setHelp('Use a space to separate multiple hosts (only one required). '.
	'Remember to set up at least one DNS server if a host name is entered here!');

$section->addInput(new Form_Select(
	'language',
	'*Language',
	$pconfig['language'],
	get_locale_list()
))->setHelp('Choose a language for the webConfigurator');

$form->add($section);

$section = new Form_Section('webConfigurator');

gen_webguicss_field($section, $pconfig['webguicss']);
gen_webguifixedmenu_field($section, $pconfig['webguifixedmenu']);
gen_webguihostnamemenu_field($section, $pconfig['webguihostnamemenu']);
gen_dashboardcolumns_field($section, $pconfig['dashboardcolumns']);
gen_interfacessort_field($section, $pconfig['interfacessort']);
gen_associatedpanels_fields(
	$section,
	$pconfig['dashboardavailablewidgetspanel'],
	$pconfig['systemlogsfilterpanel'],
	$pconfig['systemlogsmanagelogpanel'],
	$pconfig['statusmonitoringsettingspanel']);
gen_requirestatefilter_field($section, $pconfig['requirestatefilter']);
gen_requirefirewallinterface_field($section, $pconfig['requirefirewallinterface']);
gen_webguileftcolumnhyper_field($section, $pconfig['webguileftcolumnhyper']);
gen_disablealiaspopupdetail_field($section, $pconfig['disablealiaspopupdetail']);

$section->addInput(new Form_Checkbox(
	'roworderdragging',
	'Disable dragging',
	'Disable dragging of firewall/NAT rules',
	$pconfig['roworderdragging']
))->setHelp('Disables dragging rows to allow selecting and copying row contents and avoid accidental changes.');

$section->addInput(new Form_Select(
	'logincss',
	'Login page color',
	$pconfig['logincss'],
	["1e3f75;" => gettext("Dark Blue"), "003300" => gettext("Dark green"), "770101" => gettext("Crimson red"),
	 "4b1263" => gettext("Purple"), "424142" => gettext("Gray"), "333333" => gettext("Dark gray"),
	 "000000" => gettext("Black"), "633215" => gettext("Dark brown"), "bf7703" => gettext("Brown"), 
	 "008000" => gettext("Green"), "007faa" => gettext("Light Blue"), "dc2a2a" => gettext("Red"),
	 "9b59b6" => gettext("Violet")]
))->setHelp('Choose a color for the login page');

$section->addInput(new Form_Checkbox(
	'loginshowhost',
	'Login hostname',
	'Show hostname on login banner',
	$pconfig['loginshowhost']
));

$section->addInput(new Form_Textarea(
	'login_message',
	'Login Message',
	$pconfig['login_message']
))->setHelp('Text message to display on the login page, such as a warning or disclaimer. ' .
	'Plain text only - tags, e.g. HTML and PHP, are automatically removed.'
);

/*
$section->addInput(new Form_Input(
	'dashboardperiod',
	'Dashboard update period',
	'number',
	$pconfig['dashboardperiod'],
	['min' => '5', 'max' => '600']
))->setHelp('Time in seconds between dashboard widget updates. Small values cause ' .
			'more frequent updates but increase the load on the web server. ' .
			'Minimum is 5 seconds, maximum 600 seconds');
*/
$form->add($section);

print $form;

$csswarning = sprintf(gettext("%sUser-created themes are unsupported, use at your own risk."), "<br />");

?>
</div>

<script type="text/javascript">
//<![CDATA[
events.push(function() {

	function setThemeWarning() {
		if ($('#webguicss').val().startsWith("pfSense")) {
			$('#csstxt').html("").addClass("text-default");
		} else {
			$('#csstxt').html("<?=$csswarning?>").addClass("text-danger");
		}
	}

	$('#webguicss').change(function() {
		setThemeWarning();
	});

	setThemeWarning();

	// Suppress "Delete row" button if there are fewer than two rows
	checkLastRow();
});
//]]>
</script>

<?php
include("foot.inc");
?>
