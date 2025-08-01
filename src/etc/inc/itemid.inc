<?php
/*
 * itemid.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2009-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2025 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2009 Janne Enberg <janne.enberg@lietu.net>
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

require_once("filter.inc");

/****f* itemid/delete_id
 * NAME
 *   delete_id - delete an item with ['associated-rule-id'] = $id from $array
 * INPUTS
 *   $id       - int: The ID to delete
 *   $array    - array to delete the item from
 * RESULT
 *   boolean   - true if item was found and deleted
 ******/
function delete_id($id, $update_associated_rules = true, array &$changes = []) {
	$array = get_filter_rules_list();

	// Search for the item in the array
	$delete_index = get_id($id, $array);

	// If we found the item, unset it
	if ($delete_index === false) {
		return;
	}

	$if = $array[$delete_index]['interface'];
	remove_filter_rules($delete_index, $if, $update_associated_rules, $changes);
}

/****f* itemid/toggle_id
 * NAME
 *   toggle_id - enable/disable item with ['associated-rule-id'] = $id from $array
 * INPUTS
 *   $id       - int: The ID to delete
 *   $array    - array to delete the item from
 *   $status   - true to enable item and false to disable
 * RESULT
 *   boolean   - true if item was found and set
 ******/
function toggle_id($id, $status, array &$changes = []) {
	$array = get_filter_rules_list();

	// Search for the item in the array
	$toggle_index = get_id($id, $array);

	// If we found the item, unset it
	if ($toggle_index === false) {
		return;
	}

	if ($status) {
		unset($array[$toggle_index]['disabled']);
	} else {
		$array[$toggle_index]['disabled'] = true;
	}

	set_filter_rules_list($array);
	$changes['filter_rules'] = true;
}

/****f* itemid/get_id
 * NAME
 *   get_id - Get an item id with ['associated-rule-id'] = $id from $array
 * INPUTS
 *   $id     - string: The ID to get
 *   $array  - array to get the item from
 * RESULT
 *   mixed   - The id, false if not found
 ******/
function get_id($id, $array) {
	// Use $foo = get_id('id', array('id'=>'value'));

	if (!is_array($array)) {
		return false;
	}

	// Search for the item in the array
	foreach ($array as $key => $item) {
		// If this item is the one we want to delete
		if (isset($item['associated-rule-id']) &&
		    $item['associated-rule-id'] == $id) {
			return $key;
		}
	}

	return false;
}

/****f* itemid/get_unique_id
 * NAME
 *   get_unique_id - get a unique identifier
 * RESULT
 *   string     - unique id
 ******/
function get_unique_id() {

	return uniqid("nat_", true);
}

?>
