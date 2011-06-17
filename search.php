<?php

/**
 * Mahara: Electronic portfolio, weblog, resume builder and social networking
 * Copyright (C) 2006-2011 Catalyst IT Ltd and others; see:
 *                         http://wiki.mahara.org/Contributors
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    mahara
 * @subpackage admin
 * @author     Catalyst IT Ltd
 * @author     Piers Harding
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2006-2011 Catalyst IT Ltd http://catalyst.net.nz
 *
 */

define('INTERNAL', 1);
define('ADMIN', 1);
define('MENUITEM', 'configextensions/pluginadminwebservices');
require(dirname(dirname(dirname(__FILE__))) . '/init.php');
define('TITLE', get_string('pluginadmin', 'admin'));
$plugintype = 'artefact';
$pluginname = 'webservice';
define('SECTION_PLUGINTYPE', $plugintype);
define('SECTION_PLUGINNAME', $pluginname);
define('SECTION_PAGE', 'pluginconfig');

require_once('webservicessearchlib.php');

/**
 * override menu layout for WebServices
 *
 * @param arrayref $menu
 */
function local_main_nav_update(&$menu) {
    $menu[]=
    array(
      'path' =>  'configextensions/pluginadminwebservices',
      'url' => 'artefact/webservice/pluginconfig.php',
      'title' => 'WebServices Administration',
      'weight' => 30);
}

// validate the incoming token
$token  = param_alpha('token', '');
$suid   = param_alpha('suid', '');

// did the user cancel
if (param_alpha('cancel_submit', 'empty') != 'empty') {
    if ($suid) {
        redirect('/artefact/webservice/userconfig.php?suid='.$suid);
    }
    else {
        redirect('/artefact/webservice/tokenconfig.php?token='.$token);
    }
}

$search = (object) array(
    'query'       => trim(param_variable('query', '')),
    'f'           => param_alpha('f', null), // first initial
    'l'           => param_alpha('l', null), // last initial
);

$sortby  = param_alpha('sortby', 'firstname');
$sortdir = param_alpha('sortdir', 'asc');
$offset  = param_integer('offset', 0);
$limit   = param_integer('limit', 10);

if ($USER->get('admin')) {
    $institutions = get_records_array('institution', '', '', 'displayname');
    $search->institution = param_alphanum('institution', 'all');
} else {
    $institutions = get_records_select_array('institution', "name IN ('" . join("','", array_keys($USER->get('admininstitutions'))) . "')", null, 'displayname');
    $search->institution_requested = param_alphanum('institution_requested', 'all');
}

$smarty = smarty(array('artefact/webservice/js/usersearch.js'));
$smarty->assign('token_id', $token);
$smarty->assign('token', $token);
$smarty->assign('suid', $suid);
$smarty->assign('search', $search);
$smarty->assign('alphabet', explode(',', get_string('alphabet')));
$smarty->assign('cancel', get_string('cancel'));
$smarty->assign('institutions', $institutions);
$smarty->assign('results', build_webservice_user_search_results($search, $offset, $limit, $sortby, $sortdir));
if ($token) {
    $heading = get_string('headingusersearchtoken', 'artefact.webservice');
}
else {
    $heading = get_string('headingusersearchuser', 'artefact.webservice');
}
$smarty->assign('PAGEHEADING', $heading);
$smarty->display('artefact:webservice:search.tpl');


