<?php

/**
 * Mahara: Electronic portfolio, weblog, resume builder and social networking
 * Copyright (C) 2006-2009 Catalyst IT Ltd and others; see:
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
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2006-2011 Catalyst IT Ltd http://catalyst.net.nz
 * @author     Piers Harding
 */

define('INTERNAL', 1);
define('ADMIN', 1);
define('MENUITEM', 'configextensions/pluginadminwebservices');
require(dirname(dirname(dirname(__FILE__))) . '/init.php');
define('TITLE', get_string('pluginadmin', 'admin'));
require_once('pieforms/pieform.php');

$plugintype = 'artefact';
$pluginname = 'webservice';

define('SECTION_PLUGINTYPE', $plugintype);
define('SECTION_PLUGINNAME', $pluginname);
define('SECTION_PAGE', 'pluginconfig');

safe_require($plugintype, $pluginname);
$classname = generate_artefact_class_name($pluginname);

if (!call_static_method($classname, 'plugin_is_active')) {
    throw new UserException("Plugin $plugintype $pluginname is disabled");
}

$form = call_static_method($classname, 'get_config_options_extended');

$form['plugintype'] = $plugintype;
$form['pluginname'] = $pluginname;
$form['name'] = 'pluginconfig';
$form['pluginconfigform'] = true;
$form['jsform'] = true;
$form['successcallback'] = 'pluginconfig_submit';
$form['validatecallback'] = 'pluginconfig_validate';
$form = pieform($form);

$smarty = smarty(array(), array('<link rel="stylesheet" type="text/css" href="' . get_config('wwwroot') . 'artefact/webservice/theme/raw/static/style/style.css">',));

$smarty->assign('form', $form);
$smarty->assign('plugintype', $plugintype);
$smarty->assign('pluginname', $pluginname);
$heading = get_string('pluginadmin', 'admin') . ': ' . $plugintype . ': ' . $pluginname;
$smarty->assign('PAGEHEADING', $heading);
$smarty->display('admin/extensions/pluginconfig.tpl');


function pluginconfig_submit(Pieform $form, $values) {

}

function pluginconfig_validate(PieForm $form, $values) {
    global $plugintype, $pluginname, $classname;

    if (method_exists($classname, 'validate_config_options')) {
        call_static_method($classname, 'validate_config_options', $form, $values);
    }
}
