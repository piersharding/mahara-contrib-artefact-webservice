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
 * @subpackage artefact-internal
 * @author     Catalyst IT Ltd
 * @author     Piers Harding
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2006-2009 Catalyst IT Ltd http://catalyst.net.nz
 *
 */

defined('INTERNAL') || die();


$path = get_config('docroot').'artefact/webservice/libs/zend';
set_include_path($path . PATH_SEPARATOR . get_include_path());

require_once(get_config('docroot')."/artefact/webservice/locallib.php");

/**
 * Delete all service and external functions information defined in the specified component.
 * @param string $component name of component (mahara, local, etc.)
 * @return void
 */
function external_delete_descriptions($component) {

    $params = array($component);

    delete_records_select('external_services_users', "externalserviceid IN (SELECT id FROM {external_services} WHERE component = ?)", $params);
    delete_records_select('external_services_functions', "externalserviceid IN (SELECT id FROM {external_services} WHERE component = ?)", $params);
    delete_records('external_services', 'component', $component);
    delete_records('external_functions', 'component', $component);
}

/**
 * Upgrade the webservice descriptions for all plugins
 *
 * @return bool true = success
 */

function external_upgrade_webservices() {

    $ignored = array('CVS', '_vti_cnf', 'simpletest', 'db', 'yui', 'phpunit');

    foreach (array('webservice', 'admin', 'api', 'local') as $component) {
        // are there service plugins
        $basepath = get_component_directory($component).'/serviceplugins';

        // are there any plugin directories
        $plugindirs = array();
        if (!file_exists($basepath)) {
            continue;
        }
        error_log('basepath: '.$basepath);

        $items = new DirectoryIterator($basepath);
        foreach ($items as $item) {
            if ($item->isDot() or !$item->isDir()) {
                continue;
            }
            $pluginname = $item->getFilename();
            if (in_array($pluginname, $ignored)) {
                continue;
            }
            if ($pluginname !== clean_param($pluginname, PARAM_SAFEDIR)) {
                // better ignore plugins with problematic names here
                continue;
            }
            $plugindirs[]= $pluginname;
            unset($item);
        }
        unset($items);

        foreach ($plugindirs as $plugin) {
            $module = $component.'/'.$plugin; // needs to be specific to plugin

            $defpath = $basepath.'/'.$plugin.'/services.php';
            if (!file_exists($defpath)) {
                external_delete_descriptions($module);
                continue;
            }

            // load new info
            $functions = array();
            $services = array();
            include($defpath);

            // update all function first
            $dbfunctions = get_records_array('external_functions', 'component', $module);
            if (!empty($dbfunctions)) {
                foreach ($dbfunctions as $dbfunction) {
                    if (empty($functions[$dbfunction->name])) {
                        // the functions is nolonger available for use
                        delete_records('external_services_functions', 'functionname', $dbfunction->name);
                        delete_records('external_functions', 'id', $dbfunction->id);
                        continue;
                    }
                    $function = $functions[$dbfunction->name];
                    unset($functions[$dbfunction->name]);
                    $function['classpath'] = empty($function['classpath']) ? null : $function['classpath'];

                    $update = false;
                    if ($dbfunction->classname != $function['classname']) {
                        $dbfunction->classname = $function['classname'];
                        $update = true;
                    }
                    if ($dbfunction->methodname != $function['methodname']) {
                        $dbfunction->methodname = $function['methodname'];
                        $update = true;
                    }
                    if ($dbfunction->classpath != $function['classpath']) {
                        $dbfunction->classpath = $function['classpath'];
                        $update = true;
                    }
                    if ($update) {
                        update_record('external_functions', $dbfunction);
                    }
                }
            }

            foreach ($functions as $fname => $function) {
                $dbfunction = new stdClass();
                $dbfunction->name       = $fname;
                $dbfunction->classname  = $function['classname'];
                $dbfunction->methodname = $function['methodname'];
                $dbfunction->classpath  = empty($function['classpath']) ? null : $function['classpath'];
                $dbfunction->component  = $module;
                $dbfunction->id = insert_record('external_functions', $dbfunction);
            }
            unset($functions);

            // now deal with services
            $dbservices = get_records_array('external_services', 'component', $module);

            if (!empty($dbservices)) {
                foreach ($dbservices as $dbservice) {
                    if (empty($services[$dbservice->name])) {
                        delete_records('external_services_functions', 'externalserviceid', $dbservice->id);
                        delete_records('external_services_users', 'externalserviceid', $dbservice->id);
                        delete_records('external_tokens', 'externalserviceid', $dbservice->id);
                        delete_records('external_services', 'id', $dbservice->id);
                        continue;
                    }
                    $service = $services[$dbservice->name];
                    unset($services[$dbservice->name]);
                    $service['enabled'] = empty($service['enabled']) ? 0 : $service['enabled'];
                    $service['restrictedusers'] = ((isset($service['restrictedusers']) && $service['restrictedusers'] == 1) ? 1 : 0);

                    $update = false;
                    if ($dbservice->enabled != $service['enabled']) {
                        $dbservice->enabled = $service['enabled'];
                        $update = true;
                    }
                    if ($dbservice->restrictedusers != $service['restrictedusers']) {
                        $dbservice->restrictedusers = $service['restrictedusers'];
                        $update = true;
                    }
                    if ($update) {
                        update_record('external_services', $dbservice);
                    }

                    $functions = get_records_array('external_services_functions', 'externalserviceid', $dbservice->id);
                    if (!empty($functions)) {
                        foreach ($functions as $function) {
                            $key = array_search($function->functionname, $service['functions']);
                            if ($key === false) {
                                delete_records('external_services_functions', 'id', $function->id);
                            } else {
                                unset($service['functions'][$key]);
                            }
                        }
                    }
                    foreach ($service['functions'] as $fname) {
                        $newf = new stdClass();
                        $newf->externalserviceid = $dbservice->id;
                        $newf->functionname      = $fname;
                        insert_record('external_services_functions', $newf);
                    }
                    unset($functions);
                }
            }
            foreach ($services as $name => $service) {
                $dbservice = new stdClass();
                $dbservice->name               = $name;
                $dbservice->enabled            = empty($service['enabled']) ? 0 : $service['enabled'];
                $dbservice->restrictedusers    = ((isset($service['restrictedusers']) && $service['restrictedusers'] == 1) ? 1 : 0);
                $dbservice->component          = $module;
                $dbservice->timecreated        = time();
                $dbservice->id = insert_record('external_services', $dbservice, 'id', true);
                foreach ($service['functions'] as $fname) {
                    $newf = new stdClass();
                    $newf->externalserviceid = $dbservice->id;
                    $newf->functionname      = $fname;
                    insert_record('external_services_functions', $newf);
                }
            }
        }
    }

    return true;
}


/* pieforms callback for activate_webservices for
 */
function activate_webservices_submit(Pieform $form, $values) {

    $enabled = $values['enabled'] ? 0 : 1;
    set_config_plugin('artefact', 'webservice', 'enabled', $enabled);
    redirect('/admin/extensions/pluginconfig.php?plugintype=artefact&pluginname=webservice&type=webservice');
}

function activate_webservice_proto_submit(Pieform $form, $values) {

    $enabled = $values['enabled'] ? 0 : 1;
    $proto = $values['protocol'];
    set_config_plugin('artefact', 'webservice', $proto.'_enabled', $enabled);
    redirect('/admin/extensions/pluginconfig.php?plugintype=artefact&pluginname=webservice&type=webservice');
}


function webservices_function_groups_submit(Pieform $form, $values) {
    global $SESSION;

    error_log('form: '.var_export($values, true));
    if ($values['action'] == 'add') {
        $service = preg_replace('/[^a-zA-Z0-9_ ]+/', '', $values['service']);
        $service = trim($service);
        if (empty($service) || record_exists('external_services', 'name', $service)) {
            $SESSION->add_error_msg(get_string('invalidinput', 'artefact.webservice'));
        }
        else {
            $service = array('name' => $service, 'restrictedusers' => 0, 'enabled' => 1, 'component' => 'webservice', 'timecreated' => time());
            insert_record('external_services', $service);
            $SESSION->add_ok_msg(get_string('configsaved', 'artefact.webservice'));
        }
    }
    else {
        $service = get_record('external_services', 'id', $values['service']);
        if (!empty($service)) {
            if ($values['action'] == 'edit') {
                redirect('/artefact/webservice/serviceconfig.php?service='.$values['service']);
            }
            else if ($values['action'] == 'delete') {
                // remove everything associated with a service
                $params = array($values['service']);
                delete_records_select('external_tokens', "externalserviceid  = ?", $params);
                delete_records_select('external_services_users', "externalserviceid  = ?", $params);
                delete_records_select('external_services_functions', "externalserviceid  = ?", $params);
                delete_records('external_services', 'id', $values['service']);
                $SESSION->add_ok_msg(get_string('configsaved', 'artefact.webservice'));
            }
        }
    }

    // default back to where we came from
    redirect('/artefact/webservice/pluginconfig.php');
}



function webservices_token_submit(Pieform $form, $values) {
    global $SESSION, $USER;

    if ($values['action'] == 'generate') {
        if (isset($values['username'])) {
            $dbuser = get_record('usr', 'username', $values['username']);
            if (!empty($dbuser)) {
                $services = get_records_array('external_services', 'restrictedusers', 0);
                if (empty($services)) {
                    $SESSION->add_error_msg(get_string('noservices', 'artefact.webservice'));
                }
                else {
                    $service = array_shift($services); // just pass the first one for the moment
                    $token = external_generate_token(EXTERNAL_TOKEN_PERMANENT, $service, $dbuser->id);
                    $dbtoken = get_record('external_tokens', 'token', $token);
                    redirect('/artefact/webservice/tokenconfig.php?token='.$dbtoken->id);
                }
            }
            else {
                $SESSION->add_error_msg(get_string('invaliduserselected', 'artefact.webservice'));
            }
        }
        else {
            $SESSION->add_error_msg(get_string('nouser', 'artefact.webservice'));
        }

    }
    else {
        $token = get_record('external_tokens', 'id', $values['token']);
        if (!empty($token)) {
            if ($values['action'] == 'edit') {
                redirect('/artefact/webservice/tokenconfig.php?token='.$values['token']);
            }
            else if ($values['action'] == 'delete') {
                // remove everything associated with a service
                $params = array($values['token']);
                delete_records_select('external_tokens', "id = ?", $params);
                $SESSION->add_ok_msg(get_string('configsaved', 'artefact.webservice'));
            }
        }
    }

    // default back to where we came from
    redirect('/artefact/webservice/pluginconfig.php');
}


function webservices_user_submit(Pieform $form, $values) {
    global $SESSION, $USER;

    if ($values['action'] == 'add') {
        if (isset($values['username'])) {
            $dbuser = get_record('usr', 'username', $values['username']);
            if ($auth_instance = webservices_validate_user($dbuser)) {
                // make sure that this account is not already in use
                $existing = get_record('external_services_users', 'userid', $dbuser->id);
                if (empty($existing)) {
                    $services = get_records_array('external_services', 'restrictedusers', 1);
                    if (empty($services)) {
                      $SESSION->add_error_msg(get_string('noservices', 'artefact.webservice'));
                    }
                    else {
                        $service = array_shift($services); // just pass the first one for the moment
                        $dbserviceuser = (object) array('externalserviceid' => $service->id,
                                        'userid' => $dbuser->id,
                                        'institution' => $auth_instance->institution,
                                        'timecreated' => time());
                        $dbserviceuser->id = insert_record('external_services_users', $dbserviceuser, 'id', true);
                        redirect('/artefact/webservice/userconfig.php?suid='.$dbserviceuser->id);
                    }
                }
                else {
                    $SESSION->add_error_msg(get_string('duplicateuser', 'artefact.webservice'));
                }
            }
            else {
                $SESSION->add_error_msg(get_string('invaliduserselected', 'artefact.webservice'));
            }
        }
        else {
            $SESSION->add_error_msg(get_string('nouser', 'artefact.webservice'));
        }
    }
    else {
        $dbserviceuser = get_record('external_services_users', 'id', $values['suid']);
        if (!empty($dbserviceuser)) {
            if ($values['action'] == 'edit') {
                redirect('/artefact/webservice/userconfig.php?suid='.$values['suid']);
            }
            else if ($values['action'] == 'delete') {
                // remove everything associated with a service
                $params = array($values['suid']);
                delete_records_select('external_services_users', "id = ?", $params);
                $SESSION->add_ok_msg(get_string('configsaved', 'artefact.webservice'));
            }
        }
    }

    // default back to where we came from
    redirect('/artefact/webservice/pluginconfig.php');
}


class PluginArtefactWebservice extends PluginArtefact {

    public static function get_artefact_types() {
        return array(
            'webservice',
        );
    }

    public static function get_block_types() {
        return array();
    }

    public static function get_plugin_name() {
        return 'webservice';
    }

    public static function menu_items() {
        return array(
        );
    }

    public static function postinst($prevversion) {

        if ($prevversion == 0) {
            // force the upgrade to get the intial services loaded
            external_upgrade_webservices();
        }
    }

    public static function can_be_disabled() {
        return true;
    }

}

class ArtefactTypeWebservice extends ArtefactType {

    public static function get_icon($options=null) {

    }

    public static function is_singular() {
        return true;
    }

    public static function has_config() {
        return true;
    }


    /**
     * Redirect config page to custom webservices plugin config page
     */
    public static function get_config_options() {
        redirect('/artefact/webservice/pluginconfig.php');
    }


    /**
     *  Check that the webservice plugin is active or not
     *
     *  @return bool - true = active
     */
    public static function plugin_is_active() {
        $active = false;
        $plugins = plugins_installed('artefact');
        if (isset($plugins['webservice'])) {
            $active = $plugins['webservice']->active;
        }
        return $active;
    }

    /**
     * Form layout for webservices master switch fieldset
     *
     * @return pieforms $element array
     */
    public static function webservices_master_switch_form() {
        // enable/disable webservices completely
        $enabled = (get_config_plugin('artefact', 'webservice', 'enabled') || 0);
        $elements =
                array(
                    'funnylittleform' =>
                        array(
                                'type' => 'html',
                                'value' =>
                                     pieform(
                                             array(
                                                    'name'            => 'activate_webservices',
                                                    'renderer'        => 'oneline',
                                                    'elementclasses'  => false,
                                                    'successcallback' => 'activate_webservices_submit',
                                                    'class'           => 'oneline inline',
                                                    'jsform'          => false,
                                                    'elements' => array(
                                                        'label'      => array('type' => 'html', 'value' => get_string('control_webservices', 'artefact.webservice'),),
                                                        'plugintype' => array('type' => 'hidden', 'value' => 'artefact'),
                                                        'type'       => array('type' => 'hidden', 'value' => 'webservice'),
                                                        'pluginname' => array('type' => 'hidden', 'value' => 'webservice'),
                                                        'enabled'    => array('type' => 'hidden', 'value' => $enabled),
                                                        'enable'     => array('type' => 'hidden', 'value' => $enabled-1),
                                                        'submit'     => array(
                                                            'type'  => 'submit',
                                                            'class' => 'linkbtn',
                                                            'value' => $enabled ? get_string('disable') : get_string('enable')
                                                        ),
                                                        'state'     => array('type' => 'html', 'value' => '['.($enabled ? get_string('enabled', 'artefact.webservice') : get_string('disabled', 'artefact.webservice')).']',),
                                                    ),
                                                )
                                            ),
                                    ),
                            );

        return $elements;
    }


    /**
     * Form layout for webservices protocol switch fieldset
     *
     * @return pieforms $element array
     */
    public static function webservices_protocol_switch_form() {

        // enable/disable separate protocols of SOAP/XML-RPC/REST
        $elements = array();
        $elements['label'] = array('title' => ' ', 'class' => 'header', 'type' => 'html', 'value' => get_string('protocol', 'artefact.webservice'),);

        foreach (array('soap', 'xmlrpc', 'rest') as $proto) {
            $enabled = (get_config_plugin('artefact', 'webservice', $proto.'_enabled') || 0);
            $elements[$proto] =  array(
                'class' => 'header',
                'title' => ' ',
                'type' => 'html',
                'value' =>
            pieform(array(
                'name'            => 'activate_webservice_protos_'.$proto,
                'renderer'        => 'oneline',
                'elementclasses'  => false,
                'successcallback' => 'activate_webservice_proto_submit',
                'class'           => 'oneline inline',
                'jsform'          => false,
                'elements' => array(
                    'label'      => array('type' => 'html', 'value' => get_string($proto, 'artefact.webservice'),),
                    'plugintype' => array('type' => 'hidden', 'value' => 'artefact'),
                    'type'       => array('type' => 'hidden', 'value' => 'webservice'),
                    'pluginname' => array('type' => 'hidden', 'value' => 'webservice'),
                    'protocol'   => array('type' => 'hidden', 'value' => $proto),
                    'enabled'    => array('type' => 'hidden', 'value' => $enabled),
                    'submit'     => array(
                        'type'  => 'submit',
                        'class' => 'linkbtn',
                        'value' => $enabled ? get_string('disable') : get_string('enable')
                    ),
                    'state'     => array('type' => 'html', 'value' => '['.($enabled ? get_string('enabled', 'artefact.webservice') : get_string('disabled', 'artefact.webservice')).']',),
                ),
            )));
        }

        return $elements;
    }



    /**
     * Service Function Groups edit form
     *
     * @return html
     */
    public static function service_fg_edit_form() {

        $form = array(
            'name'            => 'webservices_function_groups',
            'elementclasses'  => false,
            'successcallback' => 'webservices_function_groups_submit',
            'renderer'   => 'multicolumntable',
            'elements'   => array(
                            'component' => array(
                                'title' => ' ',
                                'class' => 'header',
                                'type'  => 'html',
                                'value' => get_string('component', 'artefact.webservice'),
                            ),
                            'enabled' => array(
                                'title' => ' ',
                                'class' => 'header',
                                'type'  => 'html',
                                'value' => get_string('enabled', 'artefact.webservice'),
                            ),
                            'restricted' => array(
                                'title' => ' ',
                                'class' => 'header',
                                'type'  => 'html',
                                'value' => get_string('restrictedusers', 'artefact.webservice'),
                            ),
                            'functions' => array(
                                'title' => ' ',
                                'class' => 'header',
                                'type'  => 'html',
                                'value' => get_string('functions', 'artefact.webservice'),
                            ),
                        ),
            );

        $dbservices = get_records_array('external_services', null, null, 'name');
        foreach ($dbservices as $service) {
            $form['elements']['id'. $service->id . '_component'] = array(
                'value'        =>  $service->component,
                'type'         => 'html',
                'title'        => $service->name,
            );
            $form['elements']['id'. $service->id . '_enabled'] = array(
                'defaultvalue' => (($service->enabled == 1) ? 'checked' : ''),
                'type'         => 'checkbox',
                'disabled'     => true,
                'title'        => $service->name,
            );
            $form['elements']['id'. $service->id . '_restricted'] = array(
                'defaultvalue' => (($service->restrictedusers == 1) ? 'checked' : ''),
                'type'         => 'checkbox',
                'disabled'     => true,
                'title'        => $service->name,
            );

            $functions = get_records_array('external_services_functions', 'externalserviceid', $service->id);
            $function_list = array();
            if ($functions) {
                foreach ($functions as $function) {
                    $function_list[]= $function->functionname;
                }
            }
            $form['elements']['id'. $service->id . '_functions'] = array(
                'value'        =>  implode(', ', $function_list),
                'type'         => 'html',
                'title'        => $service->name,
            );

            // edit and delete buttons
            $form['elements']['id'. $service->id . '_actions'] = array(
                'value'        => '<span class="actions inline">'.
                                pieform(array(
                                    'name'            => 'webservices_function_groups_edit_'.$service->id,
                                    'renderer'        => 'div',
                                    'elementclasses'  => false,
                                    'successcallback' => 'webservices_function_groups_submit',
                                    'class'           => 'oneline inline',
                                    'jsform'          => false,
                                    'action'          => get_config('wwwroot') . 'artefact/webservice/pluginconfig.php',
                                    'elements' => array(
                                        'service'    => array('type' => 'hidden', 'value' => $service->id),
                                        'action'     => array('type' => 'hidden', 'value' => 'edit'),
                                        'submit'     => array(
                                                'type'  => 'submit',
                                                'class' => 'linkbtn inline',
                                                'value' => get_string('edit')
                                            ),
                                    ),
                                ))
                                .
                                pieform(array(
                                    'name'            => 'webservices_function_groups_delete_'.$service->id,
                                    'renderer'        => 'div',
                                    'elementclasses'  => false,
                                    'successcallback' => 'webservices_function_groups_submit',
                                    'class'           => 'oneline inline',
                                    'jsform'          => false,
                                    'action'          => get_config('wwwroot') . 'artefact/webservice/pluginconfig.php',
                                    'elements' => array(
                                        'service'    => array('type' => 'hidden', 'value' => $service->id),
                                        'action'     => array('type' => 'hidden', 'value' => 'delete'),
                                        'submit'     => array(
                                                'type'  => 'submit',
                                                'class' => 'linkbtn inline',
                                                'value' => get_string('delete')
                                            ),
                                    ),
                                )) . '</span>'
                                ,
                'type'         => 'html',
                'title'        => $service->name,
                'class'        => 'actions',
            );
        }

        return '<tr><td colspan="2">'.
            pieform($form).'</td></tr><tr><td colspan="2">'.
                                pieform(array(
                                    'name'            => 'webservices_function_groups_add',
                                    'renderer'        => 'oneline',
                                    'successcallback' => 'webservices_function_groups_submit',
                                    'class'           => 'oneline inline',
                                    'jsform'          => false,
                                    'action'          => get_config('wwwroot') . 'artefact/webservice/pluginconfig.php',
                                    'elements' => array(
                                        'service'    => array('type' => 'text'),
                                        'action'     => array('type' => 'hidden', 'value' => 'add'),
                                        'submit'     => array(
                                                'type'  => 'submit',
                                                'class' => 'linkbtn',
                                                'value' => get_string('add')
                                            ),
                                    ),
                                )).
             '</td></tr>';
    }


    /**
     * Service Tokens Groups edit form
     *
     * @return html
     */
    public static function service_tokens_edit_form() {
        global $THEME, $USER;

        $form = array(
            'name'            => 'webservices_tokens',
            'elementclasses'  => false,
            'successcallback' => 'webservices_tokens_submit',
            'renderer'   => 'multicolumntable',
            'elements'   => array(
                            'institution' => array(
                                'title' => ' ',
                                'class' => 'header',
                                'type'  => 'html',
                                'value' => get_string('institution'),
                            ),
                            'username' => array(
                                'title' => ' ',
                                'class' => 'header',
                                'type'  => 'html',
                                'value' => get_string('username', 'artefact.webservice'),
                            ),
                            'servicename' => array(
                                'title' => ' ',
                                'class' => 'header',
                                'type'  => 'html',
                                'value' => get_string('servicename', 'artefact.webservice'),
                            ),
                            'enabled' => array(
                                'title' => ' ',
                                'class' => 'header',
                                'type'  => 'html',
                                'value' => get_string('enabled', 'artefact.webservice'),
                            ),
                            'functions' => array(
                                'title' => ' ',
                                'class' => 'header',
                                'type'  => 'html',
                                'value' => get_string('functions', 'artefact.webservice'),
                            ),
                        ),
            );

        $dbtokens = get_records_sql_array('SELECT et.id as tokenid, et.externalserviceid as externalserviceid, et.institution as institution, u.id as userid, u.username as username, et.token as token, es.name as name, es.enabled as enabled FROM external_tokens AS et LEFT JOIN usr AS u ON et.userid = u.id LEFT JOIN external_services AS es ON et.externalserviceid = es.id ORDER BY u.username', false);
        if (!empty($dbtokens)) {
            foreach ($dbtokens as $token) {
                $dbinstitution = get_record('institution', 'name', $token->institution);
                $form['elements']['id'. $token->tokenid . '_institution'] = array(
                    'value'        =>  $dbinstitution->displayname,
                    'type'         => 'html',
                    'title'        => $token->token,
                );
                if ($USER->is_admin_for_user($token->userid)) {
                    $user_url = get_config('wwwroot').'admin/users/edit.php?id='.$token->userid;
                }
                else {
                    $user_url = get_config('wwwroot').'user/view.php?id='.$token->userid;
                }
                $form['elements']['id'. $token->tokenid . '_username'] = array(
                    'value'        =>  '<a href="'.$user_url.'">'.$token->username.'</a>',
                    'type'         => 'html',
                    'title'        => $token->token,
                );
                $form['elements']['id'. $token->tokenid . '_servicename'] = array(
                    'value'        =>  $token->name,
                    'type'         => 'html',
                    'title'        => $token->token,
                );
                $form['elements']['id'. $token->tokenid . '_enabled'] = array(
                    'defaultvalue' => (($token->enabled == 1) ? 'checked' : ''),
                    'type'         => 'checkbox',
                    'disabled'     => true,
                    'title'        => $token->token,
                );

                $functions = get_records_array('external_services_functions', 'externalserviceid', $token->externalserviceid);
                $function_list = array();
                if ($functions) {
                    foreach ($functions as $function) {
                        $function_list[]= $function->functionname;
                    }
                }
                $form['elements']['id'. $token->tokenid . '_functions'] = array(
                    'value'        =>  implode(', ', $function_list),
                    'type'         => 'html',
                    'title'        => $token->token,
                );

                // edit and delete buttons
                $form['elements']['id'. $token->tokenid . '_actions'] = array(
                    'value'        => '<span class="actions inline">'.
                                    pieform(array(
                                        'name'            => 'webservices_token_edit_'.$token->tokenid,
                                        'renderer'        => 'div',
                                        'elementclasses'  => false,
                                        'successcallback' => 'webservices_token_submit',
                                        'class'           => 'oneline inline',
                                        'jsform'          => true,
                                        'elements' => array(
                                            'token'      => array('type' => 'hidden', 'value' => $token->tokenid),
                                            'action'     => array('type' => 'hidden', 'value' => 'edit'),
                                            'submit'     => array(
                                                    'type'  => 'submit',
                                                    'class' => 'linkbtn inline',
                                                    'value' => get_string('edit')
                                                ),
                                        ),
                                    ))
                                    .
                                    pieform(array(
                                        'name'            => 'webservices_token_delete_'.$token->tokenid,
                                        'renderer'        => 'div',
                                        'elementclasses'  => false,
                                        'successcallback' => 'webservices_token_submit',
                                        'class'           => 'oneline inline',
                                        'jsform'          => true,
                                        'elements' => array(
                                            'token'      => array('type' => 'hidden', 'value' => $token->tokenid),
                                            'action'     => array('type' => 'hidden', 'value' => 'delete'),
                                            'submit'     => array(
                                                    'type'  => 'submit',
                                                    'class' => 'linkbtn inline',
                                                    'value' => get_string('delete')
                                                ),
                                        ),
                                    )) . '</span>'
                                    ,
                    'type'         => 'html',
                    'title'        => $token->token,
                    'class'        => 'actions',
                );
            }
        }

        $username = '';
        if ($user  = param_integer('user', 0)) {
            $dbuser = get_record('usr', 'id', $user);
            if (!empty($dbuser)) {
                $username = $dbuser->username;
            }
        }
        else {
            $username = param_alphanum('username', '');
        }
        $searchicon = $THEME->get_url('images/btn-search.gif');
        return '<tr><td colspan="2">'.
            pieform($form).'</td></tr><tr><td colspan="2">'.
                                pieform(array(
                                    'name'            => 'webservices_token_generate',
                                    'renderer'        => 'oneline',
                                    'successcallback' => 'webservices_token_submit',
                                    'class'           => 'oneline inline',
                                    'jsform'          => false,
                                    'action'          => get_config('wwwroot') . 'artefact/webservice/pluginconfig.php',
                                    'elements' => array(
                                        'username'  => array(
                                                               'type'        => 'text',
                                                               'title'       => get_string('username'),
                                                               'value'       => $username,
                                                           ),
                                        'usersearch'  => array(
                                                               'type'        => 'html',
                                                               'value'       => '&nbsp;<a href="'.get_config('wwwroot') .'/artefact/webservice/search.php?token=add"><img src="'.$searchicon.'" id="usersearch"/></a> &nbsp; ',
                                                           ),

                                        'action'     => array('type' => 'hidden', 'value' => 'generate'),
                                        'submit'     => array(
                                                'type'  => 'submit',
                                                'class' => 'linkbtn',
                                                'value' => get_string('generate', 'artefact.webservice')
                                            ),
                                    ),
                                )).
             '</td></tr>';

    }



    /**
     * Service Users edit form
     *
     * @return html
     */
    public static function service_users_edit_form() {
        global $THEME, $USER;

        $form = array(
            'name'            => 'webservices_users',
            'elementclasses'  => false,
            'successcallback' => 'webservices_users_submit',
            'renderer'   => 'multicolumntable',
            'elements'   => array(
                            'institution' => array(
                                'title' => ' ',
                                'class' => 'header',
                                'type'  => 'html',
                                'value' => get_string('institution'),
                            ),
                            'username' => array(
                                'title' => ' ',
                                'class' => 'header',
                                'type'  => 'html',
                                'value' => get_string('username', 'artefact.webservice'),
                            ),
                            'servicename' => array(
                                'title' => ' ',
                                'class' => 'header',
                                'type'  => 'html',
                                'value' => get_string('servicename', 'artefact.webservice'),
                            ),
                            'enabled' => array(
                                'title' => ' ',
                                'class' => 'header',
                                'type'  => 'html',
                                'value' => get_string('enabled', 'artefact.webservice'),
                            ),
                            'functions' => array(
                                'title' => ' ',
                                'class' => 'header',
                                'type'  => 'html',
                                'value' => get_string('functions', 'artefact.webservice'),
                            ),
                        ),
            );

        $dbusers = get_records_sql_array('SELECT eu.id as id, eu.userid as userid, eu.externalserviceid as externalserviceid, eu.institution as institution, u.username as username, es.name as name, es.enabled as enabled FROM external_services_users AS eu LEFT JOIN usr AS u ON eu.userid = u.id LEFT JOIN external_services AS es ON eu.externalserviceid = es.id ORDER BY eu.id', false);
        if (!empty($dbusers)) {
            foreach ($dbusers as $user) {
                $dbinstitution = get_record('institution', 'name', $user->institution);
                $form['elements']['id'. $user->id . '_institution'] = array(
                    'value'        =>  $dbinstitution->displayname,
                    'type'         => 'html',
                    'title'        => $user->id,
                );
                if ($USER->is_admin_for_user($user->id)) {
                    $user_url = get_config('wwwroot').'admin/users/edit.php?id='.$user->userid;
                }
                else {
                    $user_url = get_config('wwwroot').'user/view.php?id='.$user->userid;
                }
                $form['elements']['id'. $user->id . '_username'] = array(
                    'value'        =>  '<a href="'.$user_url.'">'.$user->username.'</a>',
                    'type'         => 'html',
                    'title'        => $user->id,
                );
                $form['elements']['id'. $user->id . '_servicename'] = array(
                    'value'        =>  $user->name,
                    'type'         => 'html',
                    'title'        => $user->id,
                );
                $form['elements']['id'. $user->id . '_enabled'] = array(
                    'defaultvalue' => (($user->enabled == 1) ? 'checked' : ''),
                    'type'         => 'checkbox',
                    'disabled'     => true,
                    'title'        => $user->id,
                );

                $functions = get_records_array('external_services_functions', 'externalserviceid', $user->externalserviceid);
                $function_list = array();
                if ($functions) {
                    foreach ($functions as $function) {
                        $function_list[]= $function->functionname;
                    }
                }
                $form['elements']['id'. $user->id . '_functions'] = array(
                    'value'        =>  implode(', ', $function_list),
                    'type'         => 'html',
                    'title'        => $user->id,
                );

                // edit and delete buttons
                $form['elements']['id'. $user->id . '_actions'] = array(
                    'value'        => '<span class="actions inline">'.
                                    pieform(array(
                                        'name'            => 'webservices_user_edit_'.$user->id,
                                        'renderer'        => 'div',
                                        'elementclasses'  => false,
                                        'successcallback' => 'webservices_user_submit',
                                        'class'           => 'oneline inline',
                                        'jsform'          => true,
                                        'elements' => array(
                                            'suid'       => array('type' => 'hidden', 'value' => $user->id),
                                            'action'     => array('type' => 'hidden', 'value' => 'edit'),
                                            'submit'     => array(
                                                    'type'  => 'submit',
                                                    'class' => 'linkbtn inline',
                                                    'value' => get_string('edit')
                                                ),
                                        ),
                                    ))
                                    .
                                    pieform(array(
                                        'name'            => 'webservices_user_delete_'.$user->id,
                                        'renderer'        => 'div',
                                        'elementclasses'  => false,
                                        'successcallback' => 'webservices_user_submit',
                                        'class'           => 'oneline inline',
                                        'jsform'          => true,
                                        'elements' => array(
                                            'suid'       => array('type' => 'hidden', 'value' => $user->id),
                                            'action'     => array('type' => 'hidden', 'value' => 'delete'),
                                            'submit'     => array(
                                                    'type'  => 'submit',
                                                    'class' => 'linkbtn inline',
                                                    'value' => get_string('delete')
                                                ),
                                        ),
                                    )) . '</span>'
                                    ,
                    'type'         => 'html',
                    'title'        => $user->id,
                    'class'        => 'actions',
                );
            }
        }

        $username = '';
        if ($user  = param_integer('user', 0)) {
            $dbuser = get_record('usr', 'id', $user);
            if (!empty($dbuser)) {
                $username = $dbuser->username;
            }
        }
        else {
            $username = param_alphanum('username', '');
        }
        $searchicon = $THEME->get_url('images/btn-search.gif');
        return '<tr><td colspan="2">'.
            pieform($form).'</td></tr><tr><td colspan="2">'.
                                pieform(array(
                                    'name'            => 'webservices_user_generate',
                                    'renderer'        => 'oneline',
                                    'successcallback' => 'webservices_user_submit',
                                    'class'           => 'oneline inline',
                                    'jsform'          => false,
                                    'action'          => get_config('wwwroot') . 'artefact/webservice/pluginconfig.php',
                                    'elements' => array(
                                        'username'  => array(
                                                               'type'        => 'text',
                                                               'title'       => get_string('username'),
                                                               'value'       => $username,
                                                           ),
                                        'usersearch'  => array(
                                                               'type'        => 'html',
                                                               'value'       => '&nbsp;<a href="'.get_config('wwwroot') .'/artefact/webservice/search.php?suid=add"><img src="'.$searchicon.'" id="usersearch"/></a> &nbsp; ',
                                                           ),

                                        'action'     => array('type' => 'hidden', 'value' => 'add'),
                                        'submit'     => array(
                                                'type'  => 'submit',
                                                'class' => 'linkbtn',
                                                'value' => get_string('add')
                                            ),
                                    ),
                                )).
             '</td></tr>';
    }

    /**
     *  Custom webservices config page
     *  - activate/deactivate webservices comletely
     *  - activate/deactivat protocols - SOAP/XML-RPC/REST
     *  - manage service clusters
     *  - manage users and access tokens
     *
     *  @return pieforms $element array
     */
    public static function get_config_options_extended() {

        $protos =
            pieform(array(
                'name'            => 'activate_webservice_protos',
                'renderer'        => 'multicolumntable',
                'elements'        => self::webservices_protocol_switch_form(),
                ));

        $currentwidth = get_config_plugin('artefact', 'file', 'profileiconwidth');
        $currentheight = get_config_plugin('artefact', 'file', 'profileiconheight');

        $elements = array(
                // fieldset of master switch
                'webservicesmaster' => array(
                    'type' => 'fieldset',
                    'legend' => get_string('masterswitch', 'artefact.webservice'),
                    'elements' =>  self::webservices_master_switch_form(),
                    'collapsible' => true,
                    'collapsed'   => false,
                ),

                // fieldset of protocol switches
                'protocolswitches' => array(
                                    'type' => 'fieldset',
                                    'legend' => get_string('protocolswitches', 'artefact.webservice'),
                                    'elements' =>  array(
                                                    'protos_help' =>  array(
                                                    'type' => 'html',
                                                    'value' => get_string('manage_protocols', 'artefact.webservice'),
                                                    ),
                                                    'enablewebserviceprotos' =>  array(
                                                    'type' => 'html',
                                                    'value' => $protos,
                                                    ),
                                                ),
                                    'collapsible' => true,
                                    'collapsed'   => false,
                                ),


                // fieldset for managing service function groups
                'servicefunctiongroups' => array(
                                    'type' => 'fieldset',
                                    'legend' => get_string('servicefunctiongroups', 'artefact.webservice'),
                                    'elements' => array(
                                        'sfgdescription' => array(
                                            'value' => '<tr><td colspan="2">' . get_string('sfgdescription', 'artefact.webservice') . '</td></tr>'
                                        ),
                                        'webservicesservicecontainer' => array(
                                            'type'         => 'html',
                                            'value' => self::service_fg_edit_form(),
                                        )
                                    ),
                                    'collapsible' => true,
                                    'collapsed'   => true,
                                ),


                // fieldset for managing service tokens
                'servicetokens' => array(
                                    'type' => 'fieldset',
                                    'legend' => get_string('servicetokens', 'artefact.webservice'),
                                    'elements' => array(
                                        'stdescription' => array(
                                            'value' => '<tr><td colspan="2">' . get_string('stdescription', 'artefact.webservice') . '</td></tr>'
                                        ),
                                        'webservicestokenscontainer' => array(
                                            'type'         => 'html',
                                            'value' => self::service_tokens_edit_form(),
                                        )
                                    ),
                                    'collapsible' => true,
                                    'collapsed'   => false,
                                ),

                // fieldset for managing service tokens
                'serviceusers' => array(
                                    'type' => 'fieldset',
                                    'legend' => get_string('manageserviceusers', 'artefact.webservice'),
                                    'elements' => array(
                                        'sudescription' => array(
                                            'value' => '<tr><td colspan="2">' . get_string('sudescription', 'artefact.webservice') . '</td></tr>'
                                        ),
                                        'webservicesuserscontainer' => array(
                                            'type'         => 'html',
                                            'value' => self::service_users_edit_form(),
                                        )
                                    ),
                                    'collapsible' => true,
                                    'collapsed'   => false,
                                ),
    );

        $form = array(
            'renderer' => 'table',
            'type' => 'div',
            'elements' => $elements,
        );

        return $form;
    }

    public function save_config_options($values) {
        $mandatory = '';
        $public = '';
        foreach ($values as $field => $value) {
            if (($value == 'on' || $value == 'checked')
                && preg_match('/([a-zA-Z]+)_(mandatory|public)/', $field, $matches)) {
                if (empty($$matches[2])) {
                    $$matches[2] = $matches[1];
                }
                else {
                    $$matches[2] .= ',' . $matches[1];
                }
            }
        }
        set_config_plugin('artefact', 'internal', 'profilepublic', $public);
        set_config_plugin('artefact', 'internal', 'profilemandatory', $mandatory);
    }
    public static function get_links($id) {
        $wwwroot = get_config('wwwroot');

        return array(
            '_default' => $wwwroot . 'artefact/webservice/',
        );
    }

    public function in_view_list() {
        return false;
    }

    public function display_title($maxlen=null) {
        return get_string($this->get('artefacttype'), 'artefact.webservice');
    }
}


?>
