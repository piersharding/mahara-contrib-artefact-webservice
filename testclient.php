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
require_once(get_config('docroot') . '/artefact/webservice/lib.php');

define('TITLE', get_string('pluginadmin', 'admin'));
require_once('pieforms/pieform.php');

$protocol  = param_alpha('protocol', '');
$authtype  = param_alpha('authtype', '');
$service  = param_integer('service', 0);
if ($service != 0) {
    $dbs = get_record('external_services', 'id', $service);
}
$function  = param_integer('function', 0);
if ($function != 0) {
    $dbsf = get_record('external_services_functions', 'id', $function);
}

$elements = array();

// check for web services call results
global $SESSION;
if ($result = $SESSION->get('ws_call_results')) {
    $SESSION->set('ws_call_results', false);
    $result = unserialize($result);
//    error_log("ws call results are: ".var_export($result, true));
    $elements['wsresults'] = array('type' => 'html', 'value' => '<h3>Results:</h3><pre>'.var_export($result, true).'</pre><br/>');
}

// add protocol choice
$popts = array();
foreach (array('soap', 'xmlrpc', 'rest') as $proto) {
    $enabled = (get_config_plugin('artefact', 'webservice', $proto.'_enabled') || 0);
    if ($enabled) {
        $popts[$proto] = get_string($proto, 'artefact.webservice');
    }
}
$default_protocol = (empty($protocol) ?  array_shift(array_keys($popts)) : $protocol);
$elements['protocol'] = array(
    'type'         => 'select',
    'title'        => get_string('protocol', 'artefact.webservice'),
    'options'      => $popts,
    'defaultvalue' => trim($default_protocol),
    'disabled'     => (!empty($protocol)),
);

// add auth method
$aopts = array();
foreach (array('token', 'user') as $auth) {
    $aopts[$auth] = get_string($auth.'auth', 'artefact.webservice');
}
$default_authtype = (empty($authtype) ? 'token' : $authtype);
$elements['authtype'] = array(
    'type'         => 'select',
    'title'        => get_string('authtype', 'artefact.webservice'),
    'options'      => $aopts,
    'defaultvalue' => trim($default_authtype),
    'disabled'     => (!empty($authtype)),
);

$nextaction = get_string('next');
$iterations = 0;

$params = array('protocol='.$protocol, 'authtype='.$authtype);
if (!empty($service)) {
    $params[]= 'service='.$service;
}
if (!empty($function)) {
    $params[]= 'function='.$function;
}

if (!empty($authtype)) {
    // add service group
    $dbservices = get_records_select_array('external_services', 'enabled = ? AND restrictedusers = ?', array(1, ($authtype == 'token' ? 0 : 1)));
    $sopts = array();
    if (!empty($dbservices)) {
        foreach ($dbservices as $dbservice) {
            $sopts[$dbservice->id] = $dbservice->name.' ('.($dbservice->restrictedusers ? get_string('userauth', 'artefact.webservice') : get_string('tokenauth', 'artefact.webservice')).')';
        }
    }
    $default_service = ($service == 0 ? array_shift(array_keys($sopts)) : $service);
    $elements['service'] = array(
        'type'         => 'select',
        'title'        => get_string('servicename', 'artefact.webservice'),
        'options'      => $sopts,
        'defaultvalue' => $default_service,
        'disabled'     => (!empty($service)),
    );


    // finally add function choice
    if ($service != 0 && !empty($dbs)) {
        $dbfunctions = get_records_array('external_services_functions', 'externalserviceid', $dbs->id);
        $fopts = array();
        if (!empty($dbfunctions)) {
            foreach ($dbfunctions as $dbfunction) {
                $fopts[$dbfunction->id] = $dbfunction->functionname;
            }
        }
        $default_function = ($function == 0 ? array_shift(array_keys($fopts)) : $function);
        $elements['function'] = array(
            'type'         => 'select',
            'title'        => get_string('functions', 'artefact.webservice'),
            'options'      => $fopts,
            'defaultvalue' => $default_function,
            'disabled'     => (!empty($function)),
        );
    }

    // we are go - build the form for function parameters
    if ($function != 0 && !empty($dbsf)) {
        $vars = testclient_get_interface($dbsf->functionname);
        $elements['spacer'] = array('type' => 'html', 'value' => '<br/><h3>'.get_string('enterparameters', 'artefact.webservice').'</h3>');
        for ($i=0;$i<=$iterations; $i++) {
            foreach ($vars as $var) {
                $name = preg_replace('/NUM/', $i, $var['name']);
                $elements[$name] = array('title' => $name, 'type' => 'text',);
            }
        }
        if ($authtype == 'user') {
            $username = param_alphanum('wsusername', '');
            $password = param_alphanum('wspassword', '');
            $elements['wsusername'] = array('title' => 'wsusername', 'type' => 'text', 'value' => $username);
            $elements['wspassword'] = array('title' => 'wspassword', 'type' => 'text', 'value' => $password);
            if ($username) {
                $params[]= 'wsusername='.$username;
            }
            if ($password) {
                $params[]= 'wspassword='.$password;
            }
        }
        else {
            $wstoken = param_alphanum('wstoken', '');
            $elements['wstoken'] = array('title' => 'wstoken', 'type' => 'text', 'value' => $wstoken);
            if ($wstoken) {
                $params[]= 'wstoken='.$wstoken;
            }
        }
//        $html = var_export($vars, true);
//        $elements['parameters'] = array('type' => 'html', 'value' => $html);
        $nextaction = get_string('execute', 'artefact.webservice');
    }
}

$elements['submit'] = array(
            'type'  => 'submitcancel',
            'value' => array($nextaction, get_string('cancel')),
            'goto'  => get_config('wwwroot') . 'artefact/webservice/testclient.php',
        );

$form = pieform(array(
    'name'            => 'testclient',
    'renderer'        => 'table',
    'type'            => 'div',
    'successcallback' => 'testclient_submit',
    'elements'        => $elements,
));

$smarty = smarty(array(), array('<link rel="stylesheet" type="text/css" href="' . get_config('wwwroot') . '/artefact/webservice/theme/raw/static/style/style.css">',));
$smarty->assign('form', $form);
$heading = get_string('testclient', 'artefact.webservice');
$smarty->assign('PAGEHEADING', $heading);
$smarty->display('artefact:webservice:testclient.tpl');

die;



/**
 * get the interface definition for the function
 *
 * @param string $functionname
 */
function testclient_get_interface($functionname) {
    $fdesc = external_function_info($functionname);
    $strs = explode('|', testclient_parameters($fdesc->parameters_desc, ''));
    $vars = array();
    foreach ($strs as $str) {
        if (empty($str)) continue;
        list($name, $type) = explode('=', $str);
        $name = preg_replace('/\]\[/', '_', $name);
        $name = preg_replace('/[\]\[]/', '', $name);
        $vars[]= array('name' => $name, 'type' => $type);
    }
    return $vars;
}

/**
 * Return indented REST param description
 * @param object $paramdescription
 * @param string $indentation composed by space only
 * @return string the html to diplay
 */
function testclient_parameters($paramdescription, $paramstring) {
    $brakeline = '|';
    /// description object is a list
    if ($paramdescription instanceof external_multiple_structure) {
        $paramstring = $paramstring . '[NUM]';
        $return = testclient_parameters($paramdescription->content, $paramstring);
        return $return;
    } else if ($paramdescription instanceof external_single_structure) {
        /// description object is an object
        $singlestructuredesc = "";
        $initialparamstring = $paramstring;
        foreach ($paramdescription->keys as $attributname => $attribut) {
            $paramstring = $initialparamstring . '[' . $attributname . ']';
            $singlestructuredesc .= testclient_parameters(
                            $paramdescription->keys[$attributname], $paramstring);
        }
        return $singlestructuredesc;
    } else {
        /// description object is a primary type (string, integer)
        $paramstring = $paramstring . '=';
        switch ($paramdescription->type) {
            case PARAM_BOOL: // 0 or 1 only for now
            case PARAM_INT:
                $type = 'int';
                break;
            case PARAM_FLOAT;
                $type = 'double';
                break;
            default:
                $type = 'string';
        }
        return $paramstring . " " . $type . $brakeline;
    }
}

/**
 * recurse into structured parameter names to figure out PHP hash structure
 *
 * @param array ref $inputs
 * @param array $parts
 * @param string $value
 */
function testclient_build_inputs(&$inputs, $parts, $value) {
    $part = array_shift($parts);
    if (empty($parts)) {
        if (!empty($value)) {
            $inputs[$part] = $value;
        }
        return;
    }
    // eg: users_0_id
    if (preg_match('/^\d+$/', $part)) {
        // we have a real array
        $part = (int)$part;
    }
    if (!isset($inputs[$part])) {
        $inputs[$part] = array();
    }
    testclient_build_inputs($inputs[$part], $parts, $value);
}

/**
 * submit callback
 *
 * @param Pieform $form
 * @param array $values
 */
function testclient_submit(Pieform $form, $values) {
    global $SESSION, $params, $iterations, $function, $dbsf;

//    error_log('submit: '.var_export($values, true));
    if (($values['authtype'] == 'token' && !empty($values['wstoken'])) ||
        ($values['authtype'] == 'user' && !empty($values['wsusername']) && !empty($values['wspassword']))) {
        $vars = testclient_get_interface($dbsf->functionname);
        $inputs = array();
        for ($i=0;$i<=$iterations; $i++) {
            foreach ($vars as $var) {
                $name = preg_replace('/NUM/', $i, $var['name']);
                $parts = explode('_', $name);
                testclient_build_inputs($inputs, $parts, $values[$name]);
            }
        }
//        error_log('inputs built: '.var_export($inputs, true));

        if ($values['authtype'] == 'token') {
           // check token
           $dbtoken = get_record('external_tokens', 'token', $values['wstoken']);
           if (empty($dbtoken)) {
               $SESSION->add_error_msg(get_string('invalidtoken', 'artefact.webservice'));
               redirect('/artefact/webservice/testclient.php?'.implode('&', $params));
           }
        }
        else {
            // check user is a valid web services account
           $dbuser = get_record('usr', 'username', $values['wsusername']);
           if (empty($dbuser)) {
               $SESSION->add_error_msg(get_string('invaliduser', 'artefact.webservice'));
               redirect('/artefact/webservice/testclient.php?'.implode('&', $params));
           }
            // special web service login
            require_once(get_config('docroot')."/auth/webservice/lib.php");

           // do password auth
            $ext_user = get_record('external_services_users', 'userid', $dbuser->id);
            if (empty($ext_user)) {
               $SESSION->add_error_msg(get_string('invaliduser', 'artefact.webservice'));
               redirect('/artefact/webservice/testclient.php?'.implode('&', $params));
            }
            // determine the internal auth instance
            $auth_instance = get_record('auth_instance', 'institution', $ext_user->institution, 'authname', 'webservice');
            if (empty($auth_instance)) {
               $SESSION->add_error_msg(get_string('invaliduser', 'artefact.webservice'));
               redirect('/artefact/webservice/testclient.php?'.implode('&', $params));
            }
            // authenticate the user
            $auth = new AuthWebservice($auth_instance->id);
            if (!$auth->authenticate_user_account($dbuser, $values['wspassword'], 'webservice')) {
                // log failed login attempts
               $SESSION->add_error_msg(get_string('invaliduser', 'artefact.webservice'));
               redirect('/artefact/webservice/testclient.php?'.implode('&', $params));
            }
        }
        // now build the test call
        $type = ($values['authtype'] == 'token' ? 'server' : 'simpleserver');
        switch ($values['protocol']){
            case 'rest':
                error_log('creating REST client');
                require_once(get_config('docroot') . "/artefact/webservice/rest/lib.php");
                $client = new webservice_rest_client(get_config('wwwroot')
                                . '/artefact/webservice/rest/'.$type.'.php',
                                 ($type == 'server' ? array('wstoken' => $values['wstoken']) :
                                                      array('wsusername' => $values['wsusername'], 'wspassword' => $values['wspassword'])), $type);

                break;

            case 'xmlrpc':
                error_log('creating XML-RPC client');
                require_once(get_config('docroot') . "/artefact/webservice/xmlrpc/lib.php");
                $client = new webservice_xmlrpc_client(get_config('wwwroot')
                        . '/artefact/webservice/xmlrpc/'.$type.'.php',
                         ($type == 'server' ? array('wstoken' => $values['wstoken']) :
                                              array('wsusername' => $values['wsusername'], 'wspassword' => $values['wspassword'])));
                break;

            case 'soap':
                error_log('creating SOAP client');
                require_once(get_config('docroot') . "/artefact/webservice/soap/lib.php");
                $client = new webservice_soap_client(get_config('wwwroot') . 'artefact/webservice/soap/'.$type.'.php',
                                ($type == 'server' ? array('wstoken' => $values['wstoken']) :
                                                     array('wsusername' => $values['wsusername'], 'wspassword' => $values['wspassword'])),
                                array("features" => SOAP_WAIT_ONE_WAY_CALLS)); //force SOAP synchronous mode
                $client->setWsdlCache(false);
                break;
        }

        try {
            $results = $client->call($dbsf->functionname, $inputs, true);
        } catch (Exception $e) {
             $results = "exception: ".$e->getMessage();
        }

        $SESSION->set('ws_call_results', serialize($results));
        $SESSION->add_ok_msg(get_string('executed', 'artefact.webservice'));
    }

    redirect('/artefact/webservice/testclient.php?'.implode('&', $params));
}

