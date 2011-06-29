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
require_once('pieforms/pieform.php');

$suid  = param_variable('suid', '');
// lookup user cancelled
if ($suid == 'add') {
    redirect('/artefact/webservice/pluginconfig.php');
}

$dbserviceuser = get_record('external_services_users', 'id', $suid);
if (empty($dbserviceuser)) {
    $SESSION->add_error_msg(get_string('invalidserviceuser', 'artefact.webservice'));
    redirect('/artefact/webservice/pluginconfig.php');
}

$services = get_records_array('external_services', 'restrictedusers', 1);
$sopts = array();
foreach ($services as $service) {
    $sopts[$service->id] = $service->name;
}

$dbuser = get_record('usr', 'id', $dbserviceuser->userid);
$function_list = array();
if (isset($dbserviceuser->externalserviceid)) {
    $dbservice = get_record('external_services', 'id', $dbserviceuser->externalserviceid);
    $defaultserviceid = $dbserviceuser->externalserviceid;
    $serviceenabled = $dbservice->enabled;
    $restrictedusers = $dbservice->restrictedusers;
    $functions = get_records_array('external_services_functions', 'externalserviceid', $dbserviceuser->externalserviceid);
    if ($functions) {
        foreach ($functions as $function) {
            $dbfunction = get_record('external_functions', 'name', $function->functionname);
            $function_list[]= '<a href="'.get_config('wwwroot').'artefact/webservice/wsdoc.php?id='.$dbfunction->id.'">'.$function->functionname.'</a>';
        }
    }
}
else {
    $serviceenabled = 0;
    $defaultserviceid = array_pop(array_keys($sopts));
    $restrictedusers = 0;
}

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


$serviceuser_details =
    array(
        'name'             => 'allocate_webservice_users',
        'successcallback'  => 'allocate_webservice_users_submit',
        'validatecallback' => 'allocate_webservice_users_validate',
        'jsform'           => true,
        'renderer'         => 'multicolumntable',
        'elements'   => array(
                        'suid' => array(
                            'type'  => 'hidden',
                            'value' => $dbserviceuser->id,
                        ),
                ),
        );

$dbinstitution = get_record('institution', 'name', $dbserviceuser->institution);
$serviceuser_details['elements']['institution'] = array(
    'type'         => 'html',
    'title'        => get_string('institution'),
    'value'        => $dbinstitution->displayname,
);

$searchicon = $THEME->get_url('images/btn-search.gif');

if ($USER->is_admin_for_user($dbuser->id)) {
    $user_url = get_config('wwwroot').'admin/users/edit.php?id='.$dbuser->id;
}
else {
    $user_url = get_config('wwwroot').'user/view.php?id='.$dbuser->id;
}
$serviceuser_details['elements']['username'] = array(
    'type'        => 'html',
    'title'       => get_string('username'),
    'value'       =>  '<a href="'.$user_url.'">'.$dbuser->username.'</a>',
);

$serviceuser_details['elements']['user'] = array(
    'type'        => 'hidden',
    'value'       => $dbuser->id,
);

$services = get_records_array('external_services');
$sopts = array();
foreach ($services as $service) {
    $sopts[$service->id] = $service->name;
}

$serviceuser_details['elements']['service'] = array(
    'type'         => 'select',
    'title'        => get_string('servicename', 'artefact.webservice'),
    'options'      => $sopts,
    'defaultvalue' => $defaultserviceid,
//        'rules'        => array('required' => true),
);

$serviceuser_details['elements']['enabled'] = array(
    'title'        => get_string('enabled', 'artefact.webservice'),
    'defaultvalue' => (($serviceenabled == 1) ? 'checked' : ''),
    'type'         => 'checkbox',
    'disabled'     => true,
);

$serviceuser_details['elements']['restricted'] = array(
    'title'        => get_string('restrictedusers', 'artefact.webservice'),
    'defaultvalue' => (($restrictedusers == 1) ? 'checked' : ''),
    'type'         => 'checkbox',
    'disabled'     => true,
);

$serviceuser_details['elements']['functions'] = array(
    'title'        => get_string('functions', 'artefact.webservice'),
    'value'        =>  implode(', ', $function_list),
    'type'         => 'html',
);

$serviceuser_details['elements']['submit'] = array(
    'type'  => 'submitcancel',
    'value' => array(get_string('save'), get_string('cancel')),
    'goto'  => get_config('wwwroot') . 'artefact/webservice/pluginconfig.php',
);

$elements = array(
        // fieldset for managing service function list
        'serviceusers_details' => array(
                            'type' => 'fieldset',
                            'legend' => get_string('serviceuser', 'artefact.webservice').': '.$dbuser->username,
                            'elements' => array(
                                'sflist' => array(
                                    'type'         => 'html',
                                    'value' =>     pieform($serviceuser_details),
                                )
                            ),
                            'collapsible' => false,
                        ),
    );

$form = array(
    'renderer' => 'table',
    'type' => 'div',
    'id' => 'maintable',
    'name' => 'tokenconfig',
    'jsform' => true,
    'successcallback' => 'allocate_webservice_users_submit',
    'validatecallback' => 'allocate_webservice_users_validate',
    'elements' => $elements,
);


$form = pieform($form);

$smarty = smarty(array(), array('<link rel="stylesheet" type="text/css" href="' . get_config('wwwroot') . 'artefact/webservice/theme/raw/static/style/style.css">',));
$smarty->assign('suid', $dbserviceuser->id);
$smarty->assign('form', $form);
$smarty->assign('plugintype', $plugintype);
$smarty->assign('pluginname', $pluginname);
$heading = get_string('pluginadmin', 'admin') . ': ' . $plugintype . ': ' . $pluginname;
$smarty->assign('PAGEHEADING', $heading);
$smarty->display('artefact:webservice:userconfig.tpl');


function allocate_webservice_users_submit(Pieform $form, $values) {
    global $SESSION;
    $dbserviceuser = get_record('external_services_users', 'id', $values['suid']);
    if (empty($dbserviceuser)) {
        $SESSION->add_error_msg(get_string('invalidserviceuser', 'artefact.webservice'));
        redirect('/artefact/webservice/pluginconfig.php');
        return;
    }

    if ($dbserviceuser->externalserviceid != $values['service']) {
        $dbservice = get_record('external_services', 'id', $values['service']);
        if ($dbservice) {
            $dbserviceuser->externalserviceid = $values['service'];
        }
    }
    update_record('external_services_users', $dbserviceuser);

    $SESSION->add_ok_msg(get_string('configsaved', 'artefact.webservice'));
    redirect('/artefact/webservice/userconfig.php?suid='.$dbserviceuser->id);
}

function allocate_webservice_users_validate(PieForm $form, $values) {
    global $SESSION;
    return true;
}
