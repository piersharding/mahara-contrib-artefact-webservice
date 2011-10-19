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

$path = get_config('docroot') . 'artefact/webservice/libs/zend';
set_include_path($path . PATH_SEPARATOR . get_include_path());

require_once(get_config('docroot') . '/artefact/lib.php');
require_once(get_config('docroot') . '/artefact/webservice/locallib.php');
require_once(get_config('docroot') . 'api/xmlrpc/lib.php');

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

    $ignored = array('CVS', '_vti_cnf', 'tests', 'db', 'yui', 'phpunit');

    foreach (array_keys(get_core_subsystems()) as $component) {
        // are there service plugins
        $basepath = get_component_directory($component) . '/serviceplugins';

        // are there any plugin directories
        $plugindirs = array();
        if (!file_exists($basepath)) {
            continue;
        }
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
            // needs to be specific to plugin
            $module = $component . '/' . $plugin;

            $defpath = $basepath . '/' . $plugin . '/services.php';
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
                    $service['tokenusers'] = ((isset($service['tokenusers']) && $service['tokenusers'] == 1) ? 1 : 0);

                    $update = false;
                    if ($dbservice->enabled != $service['enabled']) {
                        $dbservice->enabled = $service['enabled'];
                        $update = true;
                    }
                    if ($dbservice->restrictedusers != $service['restrictedusers']) {
                        $dbservice->restrictedusers = $service['restrictedusers'];
                        $update = true;
                    }
                    if ($dbservice->tokenusers != $service['tokenusers']) {
                        $dbservice->tokenusers = $service['tokenusers'];
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
                $dbservice->tokenusers         = ((isset($service['tokenusers']) && $service['tokenusers'] == 1) ? 1 : 0);
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

class PluginArtefactWebservice extends PluginArtefact {
    /*
     * cron cleanup service for web service logs
     * set this to go daily at 5 past 1
     */
    public static function get_cron() {
        return array(
            (object)array(
                'callfunction' => 'clean_webservice_logs',
                'hour'         => '01',
                'minute'       => '05',
            ),
        );
    }

    /**
     * The web services cron callback
     * clean out the old records that are N seconds old
     */
    public static function clean_webservice_logs() {
        $LOG_AGE = 8 * 24 * 60 * 60; // 8 days
        delete_records_select('external_services_logs', 'timelogged < ?', array(time() - $LOG_AGE));
    }

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
            array(
              'path' =>  'configextensions/oauthv1sregister',
              'url' => 'artefact/webservice/oauthv1sregister.php',
              'title' => 'OAuth Application Registration',
              'weight' => 99
                ),
            array(
              'path' =>  'configextensions/pluginadminwebservices',
              'url' => 'artefact/webservice/index.php',
              'title' => 'WebServices Administration',
              'weight' => 30
                ),
            array(
              'path' =>  'settings/apps',
              'url' => 'artefact/webservice/apptokens.php',
              'title' => get_string('apptokens', 'artefact.webservice'),
              'weight' => 40
                ),
        );
    }

    public static function postinst($prevversion) {

        if ($prevversion == 0) {
            // force the upgrade to get the intial services loaded
            external_upgrade_webservices();
            // can't do the following as it requires a lot postinst for dependencies on data in core
//             // ensure that we have a webservice auth_instance
//             $authinstance = get_record('auth_instance', 'institution', 'mahara', 'authname', 'webservice');
//             if (empty($authinstance)) {
//                 $authinstance = (object)array(
//                             'instancename' => 'webservice',
//                             'priority'     => 2,
//                             'institution'  => 'mahara',
//                             'authname'     => 'webservice',
//                 );
//                 insert_record('auth_instance', $authinstance);
//             }
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
        redirect('/artefact/webservice/index.php');
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
