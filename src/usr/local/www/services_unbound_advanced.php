<?php
/*
 * services_unbound_advanced.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2025 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2014 Warren Baker (warren@pfsense.org)
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
##|*IDENT=page-services-dnsresolver-advanced
##|*NAME=Services: DNS Resolver: Advanced
##|*DESCR=Allow access to the 'Services: DNS Resolver: Advanced' page.
##|*MATCH=services_unbound_advanced.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("unbound.inc");

$unbound_edns_sizes = array(
	"auto" => "Automatic value based on active interface MTUs",
	"512"  => "512: IPv4 Minimum",
	"1220" => "1220: NSD IPv6 EDNS Minimum",
	"1232" => "1232: IPv6 Minimum",
	"1432" => "1432: 1500 Byte MTU",
	"1480" => "1480: NSD IPv4 EDNS Minimum",
	"4096" => "4096: Unbound Default"
);

$pconfig['hideidentity'] = config_path_enabled('unbound', 'hideidentity');
$pconfig['hideversion'] = config_path_enabled('unbound', 'hideversion');
$pconfig['qname-minimisation'] = config_path_enabled('unbound', 'qname-minimisation');
$pconfig['qname-minimisation-strict'] = config_path_enabled('unbound', 'qname-minimisation-strict');
$pconfig['prefetch'] = config_path_enabled('unbound', 'prefetch');
$pconfig['prefetchkey'] = config_path_enabled('unbound', 'prefetchkey');
$pconfig['dnssecstripped'] = config_path_enabled('unbound', 'dnssecstripped');
$pconfig['dnsrecordcache'] = config_path_enabled('unbound', 'dnsrecordcache');
$pconfig['sock_queue_timeout'] = config_get_path('unbound/sock_queue_timeout', 0);
$pconfig['aggressivensec'] = config_path_enabled('unbound', 'aggressivensec');

$pconfig['msgcachesize'] = config_get_path('unbound/msgcachesize');
$pconfig['outgoing_num_tcp'] = config_get_path('unbound/outgoing_num_tcp', 10);
$pconfig['incoming_num_tcp'] = config_get_path('unbound/incoming_num_tcp', 10);
$pconfig['edns_buffer_size'] = config_get_path('unbound/edns_buffer_size', 'auto');
$pconfig['num_queries_per_thread'] = config_get_path('unbound/num_queries_per_thread');
$pconfig['jostle_timeout'] = config_get_path('unbound/jostle_timeout', 200);
$pconfig['cache_max_ttl'] = config_get_path('unbound/cache_max_ttl', 86400);
$pconfig['cache_min_ttl'] = config_get_path('unbound/cache_min_ttl', 0);

/* default to enabled if not explicitly set */
if (config_get_path('unbound/infra_keep_probing', 'enabled') == "enabled") {
	$pconfig['infra_keep_probing'] = true;
}

$pconfig['infra_host_ttl'] = config_get_path('unbound/infra_host_ttl', 900);
$pconfig['infra_cache_numhosts'] = config_get_path('unbound/infra_cache_numhosts', 10000);
$pconfig['unwanted_reply_threshold'] = config_get_path('unbound/unwanted_reply_threshold', 'disabled');
$pconfig['log_verbosity'] = config_get_path('unbound/log_verbosity', 1);
$pconfig['dns64prefix'] = config_get_path('unbound/dns64prefix', '');
$pconfig['dns64netbits'] = config_get_path('unbound/dns64netbits', 96);

$pconfig['disable_auto_added_access_control'] = config_path_enabled('unbound', 'disable_auto_added_access_control');
$pconfig['disable_auto_added_host_entries'] = config_path_enabled('unbound', 'disable_auto_added_host_entries');
$pconfig['use_caps'] = config_path_enabled('unbound', 'use_caps');
$pconfig['dns64'] = config_path_enabled('unbound', 'dns64');

if ($_POST) {
	if ($_POST['apply']) {
		$retval = 0;
		$retval |= services_unbound_configure();
		if ($retval == 0) {
			clear_subsystem_dirty('unbound');
		}
	} else {
		unset($input_errors);
		$pconfig = $_POST;

		if (isset($_POST['msgcachesize']) && !in_array($_POST['msgcachesize'], array('4', '10', '20', '50', '100', '250', '512'), true)) {
			$input_errors[] = gettext("A valid value for Message Cache Size must be specified.");
		}
		if (isset($_POST['outgoing_num_tcp']) && !in_array($_POST['outgoing_num_tcp'], array('0', '10', '20', '30', '40', '50'), true)) {
			$input_errors[] = gettext("A valid value must be specified for Outgoing TCP Buffers.");
		}
		if (isset($_POST['incoming_num_tcp']) && !in_array($_POST['incoming_num_tcp'], array('0', '10', '20', '30', '40', '50'), true)) {
			$input_errors[] = gettext("A valid value must be specified for Incoming TCP Buffers.");
		}
		if (isset($_POST['edns_buffer_size']) && !array_key_exists($_POST['edns_buffer_size'], $unbound_edns_sizes)) {
			$input_errors[] = gettext("A valid value must be specified for EDNS Buffer Size.");
		}
		if (isset($_POST['num_queries_per_thread']) && !in_array($_POST['num_queries_per_thread'], array('512', '1024', '2048'), true)) {
			$input_errors[] = gettext("A valid value must be specified for Number of Queries per Thread.");
		}
		if (isset($_POST['jostle_timeout']) && !in_array($_POST['jostle_timeout'], array('100', '200', '500', '1000'), true)) {
			$input_errors[] = gettext("A valid value must be specified for Jostle Timeout.");
		}
		if (isset($_POST['cache_max_ttl']) && (!is_numericint($_POST['cache_max_ttl']) || ($_POST['cache_max_ttl'] < 0))) {
			$input_errors[] = gettext("'Maximum TTL for RRsets and Messages' must be a positive integer.");
		}
		if (isset($_POST['cache_min_ttl']) && (!is_numericint($_POST['cache_min_ttl']) || ($_POST['cache_min_ttl'] < 0))) {
			$input_errors[] = gettext("'Minimum TTL for RRsets and Messages' must be a positive integer.");
		}
		if (isset($_POST['infra_host_ttl']) && !in_array($_POST['infra_host_ttl'], array('60', '120', '300', '600', '900'), true)) {
			$input_errors[] = gettext("A valid value must be specified for TTL for Host Cache Entries.");
		}
		if (isset($_POST['infra_cache_numhosts']) && !in_array($_POST['infra_cache_numhosts'], array('1000', '5000', '10000', '20000', '50000', '100000', '200000'), true)) {
			$input_errors[] = gettext("A valid value must be specified for Number of Hosts to Cache.");
		}
		if (isset($_POST['unwanted_reply_threshold']) && !in_array($_POST['unwanted_reply_threshold'], array('disabled', '5000000', '10000000', '20000000', '40000000', '50000000'), true)) {
			$input_errors[] = gettext("A valid value must be specified for Unwanted Reply Threshold.");
		}
		if (isset($_POST['log_verbosity']) && !in_array($_POST['log_verbosity'], array('0', '1', '2', '3', '4', '5'), true)) {
			$input_errors[] = gettext("A valid value must be specified for Log Level.");
		}
		if (isset($_POST['dnssecstripped']) && !config_path_enabled('unbound', 'dnssec')) {
			$input_errors[] = gettext("Harden DNSSEC Data option can only be enabled if DNSSEC support is enabled.");
		}
		if (isset($_POST['dns64']) && !empty($_POST['dns64prefix']) && !is_ipaddrv6($_POST['dns64prefix'])) {
			$input_errors[] = gettext("DNS64 Prefix must be valid IPv6 prefix.");
		}
		if (isset($_POST['dns64']) && isset($_POST['dns64netbits']) && 
		    (($_POST['dns64netbits'] > 96) || ($_POST['dns64netbits'] < 1))) {
			$input_errors[] = gettext("DNS64 subnet must be not more than 96 and not less that 1.");
		}

		if (!$input_errors) {
			if (isset($_POST['hideidentity'])) {
				config_set_path('unbound/hideidentity', true);
			} else {
				config_del_path('unbound/hideidentity');
			}
			if (isset($_POST['hideversion'])) {
				config_set_path('unbound/hideversion', true);
			} else {
				config_del_path('unbound/hideversion');
			}
			if (isset($_POST['qname-minimisation'])) {
				config_set_path('unbound/qname-minimisation', true);
			} else {
				config_del_path('unbound/qname-minimisation');
			}
			if (isset($_POST['qname-minimisation-strict'])) {
				config_set_path('unbound/qname-minimisation-strict', true);
			} else {
				config_del_path('unbound/qname-minimisation-strict');
			}
			if (isset($_POST['prefetch'])) {
				config_set_path('unbound/prefetch', true);
			} else {
				config_del_path('unbound/prefetch');
			}
			if (isset($_POST['prefetchkey'])) {
				config_set_path('unbound/prefetchkey', true);
			} else {
				config_del_path('unbound/prefetchkey');
			}
			if (isset($_POST['dnssecstripped'])) {
				config_set_path('unbound/dnssecstripped', true);
			} else {
				config_del_path('unbound/dnssecstripped');
			}
			if (isset($_POST['dnsrecordcache'])) {
				config_set_path('unbound/dnsrecordcache', true);
			} else {
				config_del_path('unbound/dnsrecordcache');
			}
			config_set_path('unbound/sock_queue_timeout', $_POST['sock_queue_timeout']);
			if (isset($_POST['aggressivensec'])) {
				config_set_path('unbound/aggressivensec', true);
			} else {
				config_del_path('unbound/aggressivensec');
			}
			config_set_path('unbound/msgcachesize', $_POST['msgcachesize']);
			config_set_path('unbound/outgoing_num_tcp', $_POST['outgoing_num_tcp']);
			config_set_path('unbound/incoming_num_tcp', $_POST['incoming_num_tcp']);
			config_set_path('unbound/edns_buffer_size', $_POST['edns_buffer_size']);
			config_set_path('unbound/num_queries_per_thread', $_POST['num_queries_per_thread']);
			config_set_path('unbound/jostle_timeout', $_POST['jostle_timeout']);
			config_set_path('unbound/cache_max_ttl', $_POST['cache_max_ttl']);
			config_set_path('unbound/cache_min_ttl', $_POST['cache_min_ttl']);
			if (isset($_POST['infra_keep_probing'])) {
				config_set_path('unbound/infra_keep_probing', "enabled");
			} else {
				config_set_path('unbound/infra_keep_probing', "disabled");
			}
			config_set_path('unbound/infra_host_ttl', $_POST['infra_host_ttl']);
			config_set_path('unbound/infra_cache_numhosts', $_POST['infra_cache_numhosts']);
			config_set_path('unbound/unwanted_reply_threshold', $_POST['unwanted_reply_threshold']);
			config_set_path('unbound/log_verbosity', $_POST['log_verbosity']);

			if (isset($_POST['disable_auto_added_access_control'])) {
				config_set_path('unbound/disable_auto_added_access_control', true);
			} else {
				config_del_path('unbound/disable_auto_added_access_control');
			}

			if (isset($_POST['disable_auto_added_host_entries'])) {
				config_set_path('unbound/disable_auto_added_host_entries', true);
			} else {
				config_del_path('unbound/disable_auto_added_host_entries');
			}

			if (isset($_POST['use_caps'])) {
				config_set_path('unbound/use_caps', true);
			} else {
				config_del_path('unbound/use_caps');
			}

			if (isset($_POST['dns64'])) {
				config_set_path('unbound/dns64', true);
				config_set_path('unbound/dns64prefix', $_POST['dns64prefix']);
				config_set_path('unbound/dns64netbits', $_POST['dns64netbits']);
			} else {
				config_del_path('unbound/dns64');
			}

			write_config(gettext("DNS Resolver configured."));

			mark_subsystem_dirty('unbound');
		}
	}
}

$pgtitle = array(gettext("Services"), gettext("DNS Resolver"), gettext("Advanced Settings"));
$pglinks = array("", "services_unbound.php", "@self");
$shortcut_section = "resolver";
include_once("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($_POST['apply']) {
	print_apply_result_box($retval);
}

if (is_subsystem_dirty('unbound')) {
	print_apply_box(gettext("The DNS resolver configuration has been changed.") . "<br />" . gettext("The changes must be applied for them to take effect."));
}

$tab_array = array();
$tab_array[] = array(gettext("General Settings"), false, "services_unbound.php");
$tab_array[] = array(gettext("Advanced Settings"), true, "services_unbound_advanced.php");
$tab_array[] = array(gettext("Access Lists"), false, "/services_unbound_acls.php");
display_top_tabs($tab_array, true);

$form = new Form();

$section = new Form_Section('Advanced Privacy Options');

$section->addInput(new Form_Checkbox(
	'hideidentity',
	'Hide Identity',
	'id.server and hostname.bind queries are refused',
	$pconfig['hideidentity']
));

$section->addInput(new Form_Checkbox(
	'hideversion',
	'Hide Version',
	'version.server and version.bind queries are refused',
	$pconfig['hideversion']
));

$section->addInput(new Form_Checkbox(
	'qname-minimisation',
	'Query Name Minimization',
	'Send minimum amount of QNAME/QTYPE information to upstream servers to enhance privacy',
	$pconfig['qname-minimisation']
))->setHelp('Only send minimum required labels of the QNAME and set QTYPE to A when possible. Best effort approach; full QNAME and original QTYPE will be sent when upstream replies with a RCODE other than NOERROR, except when receiving NXDOMAIN from a DNSSEC signed zone. Default is checked (on).%1$s Refer to %2$sRFC 7816%3$s for in-depth information on Query Name Minimization.', '<br/>', '<a href="https://tools.ietf.org/html/rfc7816">', '</a>');

$section->addInput(new Form_Checkbox(
	'qname-minimisation-strict',
	'Strict Query Name Minimization',
	'Do not fall-back to sending full QNAME to potentially broken DNS servers',
	$pconfig['qname-minimisation-strict']
))->setHelp('QNAME minimization in strict mode. %1$sA significant number of domains will fail to resolve when this option in enabled%2$s. Only use if you know what you are doing. This option only has effect when Query Name Minimization is enabled. Default is off.', '<b>', '</b>');

$form->add($section);

$section = new Form_Section('Advanced Resolver Options');

$section->addInput(new Form_Checkbox(
	'prefetch',
	'Prefetch Support',
	'Message cache elements are prefetched before they expire to help keep the cache up to date',
	$pconfig['prefetch']
))->setHelp('When enabled, this option can cause an increase of around 10% more DNS traffic and load on the server, but frequently requested items will not expire from the cache.');

$section->addInput(new Form_Checkbox(
	'prefetchkey',
	'Prefetch DNS Key Support',
	'DNSKEYs are fetched earlier in the validation process when a Delegation signer is encountered',
	$pconfig['prefetchkey']
))->setHelp('This helps lower the latency of requests but does utilize a little more CPU. See: %1$sWikipedia%2$s', '<a href="https://en.wikipedia.org/wiki/List_of_DNS_record_types">', '</a>');

$section->addInput(new Form_Checkbox(
	'dnssecstripped',
	'Harden DNSSEC Data',
	'DNSSEC data is required for trust-anchored zones.',
	$pconfig['dnssecstripped']
))->setHelp('If such data is absent, the zone becomes bogus. If Disabled and no DNSSEC data is received, then the zone is made insecure. ');

$section->addInput(new Form_Checkbox(
	'dnsrecordcache',
	'Serve Expired',
	'Serve cache records even with TTL of 0',
	$pconfig['dnsrecordcache']
))->setHelp('When enabled, allows unbound to serve one query even with a TTL of 0, if TTL is 0 then new record will be requested in the background when the cache is served to ensure cache is updated without latency on service of the DNS request.');

/* Not supported on FreeBSD. See https://redmine.pfsense.org/issues/16299
$section->addInput(new Form_Input(
	'sock_queue_timeout',
	'Drop Old UDP Queries',
	null,
	$pconfig['sock_queue_timeout'],
	array('type' => 'number', 'min' => 0, 'step' => 1)
))->setHelp('Timeout in seconds before dropping UDP queries waiting in the ' .
            'socket buffer. Queries that have waited for a long time don\'t ' .
            'need to be processed and can be dropped. Disabled by default (0).');
*/

$section->addInput(new Form_Checkbox(
	'aggressivensec',
	'Aggressive NSEC',
	'Aggressive Use of DNSSEC-Validated Cache',
	$pconfig['aggressivensec']
))->setHelp('When enabled, unbound uses the DNSSEC NSEC chain to synthesize NXDOMAIN and other denials, ' .
	    'using information from previous NXDOMAINs answers. It helps to reduce the query rate towards ' .
	    'targets that get a very high nonexistent name lookup rate.');

$section->addInput(new Form_Select(
	'msgcachesize',
	'Message Cache Size',
	$pconfig['msgcachesize'],
	array_combine(array("4", "10", "20", "50", "100", "250", "512"), array("4 MB", "10 MB", "20 MB", "50 MB", "100 MB", "250 MB", "512 MB"))
))->setHelp('Size of the message cache. The message cache stores DNS response codes and validation statuses. The Resource Record Set (RRSet) cache will automatically be set to twice this amount. The RRSet cache contains the actual RR data. The default is 4 megabytes.');

$section->addInput(new Form_Select(
	'outgoing_num_tcp',
	'Outgoing TCP Buffers',
	$pconfig['outgoing_num_tcp'],
	array_combine(array("0", "10", "20", "30", "50", "50"), array("0", "10", "20", "30", "50", "50"))
))->setHelp('The number of outgoing TCP buffers to allocate per thread. The default value is 10. If 0 is selected then TCP queries are not sent to authoritative servers.');

$section->addInput(new Form_Select(
	'incoming_num_tcp',
	'Incoming TCP Buffers',
	$pconfig['incoming_num_tcp'],
	array_combine(array("0", "10", "20", "30", "50", "50"), array("0", "10", "20", "30", "50", "50"))
))->setHelp('The number of incoming TCP buffers to allocate per thread. The default value is 10. If 0 is selected then TCP queries are not accepted from clients.');

$section->addInput(new Form_Select(
	'edns_buffer_size',
	'EDNS Buffer Size',
	$pconfig['edns_buffer_size'],
	$unbound_edns_sizes
))->setHelp(
	'Number of bytes size to advertise as the EDNS reassembly buffer size. This is the value that is used in UDP datagrams sent to peers.%1$s' .
	'Auto mode sets optimal buffer size by using the smallest MTU of active interfaces and subtracting the IPv4/IPv6 header size.%1$s' .
	'If fragmentation reassemble problems occur, usually seen as timeouts, then a value of 1432 should help.%1$s' .
	'The 512/1232 values bypasses most IPv4/IPv6 MTU path problems, but it can generate an excessive amount of TCP fallback.', '<br />');

$section->addInput(new Form_Select(
	'num_queries_per_thread',
	'Number of Queries per Thread',
	$pconfig['num_queries_per_thread'],
	array_combine(array("512", "1024", "2048"), array("512", "1024", "2048"))
))->setHelp('The number of queries that every thread will service simultaneously. If more queries arrive that need to be serviced, and no queries can be jostled, then these queries are dropped.');

$section->addInput(new Form_Select(
	'jostle_timeout',
	'Jostle Timeout',
	$pconfig['jostle_timeout'],
	array_combine(array("100", "200", "500", "1000"), array("100", "200", "500", "1000"))
))->setHelp('This timeout is used for when the server is very busy. This protects against denial of service by slow queries or high query rates. The default value is 200 milliseconds. ');

$section->addInput(new Form_Input(
	'cache_max_ttl',
	'Maximum TTL for RRsets and Messages',
	'text',
	$pconfig['cache_max_ttl']
))->setHelp('The Maximum Time to Live for RRsets and messages in the cache. The default is 86400 seconds (1 day). ' .
			'When the internal TTL expires the cache item is expired. This can be configured to force the resolver to query for data more often and not trust (very large) TTL values.');

$section->addInput(new Form_Input(
	'cache_min_ttl',
	'Minimum TTL for RRsets and Messages',
	'text',
	$pconfig['cache_min_ttl']
))->setHelp('The Minimum Time to Live for RRsets and messages in the cache. ' .
			'The default is 0 seconds. If the minimum value kicks in, the data is cached for longer than the domain owner intended, and thus less queries are made to look up the data. ' .
			'The 0 value ensures the data in the cache is as the domain owner intended. High values can lead to trouble as the data in the cache might not match up with the actual data anymore.');

$section->addInput(new Form_Checkbox(
	'infra_keep_probing',
	'Keep Probing',
	'Keep probing servers that are down',
	$pconfig['infra_keep_probing']
))->setHelp('When disabled, it may take up to "TTL for Host Cache Entries" for a server to be used again after being marked as down.');

$mnt = gettext("minutes");
$section->addInput(new Form_Select(
	'infra_host_ttl',
	'TTL for Host Cache Entries',
	$pconfig['infra_host_ttl'],
	array_combine(array("60", "120", "300", "600", "900"), array("1 " . $mnt, "2  " . $mnt, "5  " . $mnt, "10 " . $mnt, "15 " . $mnt))
))->setHelp('Time to Live duration for entries in the infrastructure host cache. The infrastructure host cache contains round trip timing, lameness, and EDNS support information for DNS servers. The default value is 15 minutes.');

$section->addInput(new Form_Select(
	'infra_cache_numhosts',
	'Number of Hosts to Cache',
	$pconfig['infra_cache_numhosts'],
	array_combine(array("1000", "5000", "10000", "20000", "50000", "100000", "200000"), array("1000", "5000", "10000", "20000", "50000", "100000", "200000"))
))->setHelp('Number of infrastructure hosts for which information is cached. The default is 10,000.');

$mln = gettext("million");
$section->addInput(new Form_Select(
	'unwanted_reply_threshold',
	'Unwanted Reply Threshold',
	$pconfig['unwanted_reply_threshold'],
	array_combine(array("disabled", "5000000", "10000000", "20000000", "40000000", "50000000"),
				  array("Disabled", "5 " . $mln, "10 " . $mln, "20 " . $mln, "40 " . $mln, "50 " . $mln))
))->setHelp('If enabled, a total number of unwanted replies is kept track of in every thread. When it reaches the threshold, a defensive action is taken ' .
			'and a warning is printed to the log file. This defensive action is to clear the RRSet and message caches, hopefully flushing away any poison. ' .
			'The default is disabled, but if enabled a value of 10 million is suggested.');

$lvl_word = gettext('Level %s');
$lvl_text = array(
	'0' => 'No logging',
	'1' => 'Basic operational information',
	'2' => 'Detailed operational information',
	'3' => 'Query level information',
	'4' => 'Algorithm level information',
	'5' => 'Client identification for cache misses'
);
foreach ($lvl_text as $k => & $v) {
	$v = sprintf($lvl_word,$k) . ': ' . gettext($v);
}
$section->addInput(new Form_Select(
	'log_verbosity',
	'Log Level',
	$pconfig['log_verbosity'],
	$lvl_text
))->setHelp('Select the level of detail to be logged. Each level also includes the information from previous levels. The default is basic operational information (level 1)');

$section->addInput(new Form_Checkbox(
	'disable_auto_added_access_control',
	'Disable Auto-added Access Control',
	'Disable the automatically-added access control entries',
	$pconfig['disable_auto_added_access_control']
))->setHelp('By default, IPv4 and IPv6 networks residing on internal interfaces of this system are permitted. ' .
			'Allowed networks must be manually configured on the Access Lists tab if the auto-added entries are disabled.');

$section->addInput(new Form_Checkbox(
	'disable_auto_added_host_entries',
	'Disable Auto-added Host Entries',
	'Disable the automatically-added host entries',
	$pconfig['disable_auto_added_host_entries']
))->setHelp('By default, the primary IPv4 and IPv6 addresses of this firewall are added as records for the system domain of this firewall as configured in %1$sSystem: General Setup%2$s. This disables the auto generation of these entries.', '<a href="system.php">', '</a>');

$section->addInput(new Form_Checkbox(
	'use_caps',
	'Experimental Bit 0x20 Support',
	'Use 0x-20 encoded random bits in the DNS query to foil spoofing attempts.',
	$pconfig['use_caps']
))->setHelp('See the implementation %1$sdraft dns-0x20%2$s for more information.', '<a href="https://tools.ietf.org/html/draft-vixie-dnsext-dns0x20-00">', '</a>');

$section->addInput(new Form_Checkbox(
	'dns64',
	'DNS64 Support',
	'Enable DNS64 (RFC 6147)',
	$pconfig['dns64']
))->setHelp('DNS64 is used with an IPv6/IPv4 translator to enable client-server communication between an IPv6-only client and an IPv4-only servers.');

$group = new Form_Group('DNS64 Prefix');
$group->addClass('dns64pr');

$group->add(new Form_IpAddress(
	'dns64prefix',
	'DNS64 Prefix',
	$pconfig['dns64prefix']
))->addMask('dns64netbits', $pconfig['dns64netbits'], 96, 1, false)->setWidth(4);
$group->setHelp('IPv6 prefix used by IPv6 representations of IPv4 addresses. If this field is empty, default 64:ff9b::/96 prefix is used.');

$section->add($group);

$form->add($section);
print($form);

?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	function change_dns64() {
		hideClass('dns64pr', !($('#dns64').prop('checked')));
	}

	 // DNS64
	$('#dns64').change(function () {
		change_dns64();
	});

	// ---------- On initial page load ------------------------------------------------------------

	change_dns64();
});
//]]>
</script>
<?php
include("foot.inc");
