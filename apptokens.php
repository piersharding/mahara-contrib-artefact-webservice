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
define('MENUITEM', 'settings/apps');

require(dirname(dirname(dirname(__FILE__))) . '/init.php');
require(dirname(__FILE__) . '/locallib.php');
define('TITLE', get_string('apptokens', 'artefact.webservice'));
require_once('pieforms/pieform.php');


$dbservices = get_records_array('external_services', 'tokenusers', 1);
foreach ($dbservices as $dbservice) {
    $dbtoken = get_record('external_tokens', 'externalserviceid', $dbservice->id, 'userid', $USER->id, 'tokentype', EXTERNAL_TOKEN_USER);
    if ($dbtoken) {
        $dbservice->token = $dbtoken->token;
        $dbservice->timecreated = $dbtoken->timecreated;
        $dbservice->lastaccess = $dbtoken->lastaccess;
        $dbservice->institution = $dbtoken->institution;
    }
}

$userform = get_string('notokens', 'artefact.webservice');
if (!empty($dbservices)) {
    $userform = array(
        'name'            => 'webservices_user_tokens',
        'elementclasses'  => false,
        'successcallback' => 'webservices_user_tokens_submit',
        'renderer'   => 'multicolumntable',
        'elements'   => array(
                        'service_name' => array(
                            'title' => ' ',
                            'class' => 'header',
                            'type'  => 'html',
                            'value' => get_string('serviceaccess', 'artefact.webservice'),
                        ),
                        'enabled' => array(
                            'title' => ' ',
                            'class' => 'header',
                            'type'  => 'html',
                            'value' => get_string('enabled', 'artefact.webservice'),
                        ),
                        'token' => array(
                            'title' => ' ',
                            'class' => 'header',
                            'type'  => 'html',
                            'value' => get_string('token', 'artefact.webservice'),
                        ),
                        'functions' => array(
                            'title' => ' ',
                            'class' => 'header',
                            'type'  => 'html',
                            'value' => get_string('functions', 'artefact.webservice'),
                        ),
                        'last_access' => array(
                            'title' => ' ',
                            'class' => 'header',
                            'type'  => 'html',
                            'value' => get_string('last_access', 'artefact.webservice'),
                        ),
                        'expires' => array(
                            'title' => ' ',
                            'class' => 'header',
                            'type'  => 'html',
                            'value' => get_string('expires', 'artefact.webservice'),
                        ),
                    ),
        );
        foreach ($dbservices as $service) {
        $userform['elements']['id'. $service->id . '_service_name'] = array(
            'value'        =>  $service->name,
            'type'         => 'html',
            'key'        => $service->id,
        );
        $userform['elements']['id'. $service->id . '_enabled'] = array(
            'defaultvalue' => (($service->enabled == 1) ? 'checked' : ''),
            'type'         => 'checkbox',
            'disabled'     => true,
            'key'          => $service->id,
        );
        $userform['elements']['id'. $service->id . '_token'] = array(
            'value'        =>  (empty($service->token) ? get_string('no_token', 'artefact.webservice') : $service->token),
            'type'         => 'html',
            'key'        => $service->id,
        );
        $functions = get_records_array('external_services_functions', 'externalserviceid', $service->id);
        $function_list = array();
        if ($functions) {
            foreach ($functions as $function) {
                $dbfunction = get_record('external_functions', 'name', $function->functionname);
                $function_list[]= '<a href="'.get_config('wwwroot').'artefact/webservice/wsdoc.php?id='.$dbfunction->id.'">'.$function->functionname.'</a>';
            }
        }
        $userform['elements']['id'. $service->id . '_functions'] = array(
            'value'        =>  implode(', ', $function_list),
            'type'         => 'html',
            'key'        => $service->id,
        );
        $userform['elements']['id'. $service->id . '_last_access'] = array(
            'value'        =>  (empty($service->lastaccess) ? ' ' : date("F j, Y H:i", $service->lastaccess)),
            'type'         => 'html',
            'key'        => $service->id,
        );
        $userform['elements']['id'. $service->id . '_expires'] = array(
            'value'        => date("F j, Y H:i", (empty($service->validuntil) ? $service->lastaccess + EXTERNAL_TOKEN_USER_EXPIRES : $service->validuntil)),
            'type'         => 'html',
            'key'        => $service->id,
        );
        // generate button
        // delete button
//        if (!empty($service->token)) {
            $userform['elements']['id'. $service->id . '_actions'] = array(
                'value'        => '<span class="actions inline">'.
                                pieform(array(
                                    'name'            => 'webservices_user_token_generate_'.$service->id,
                                    'renderer'        => 'div',
                                    'elementclasses'  => false,
                                    'successcallback' => 'webservices_user_token_submit',
                                    'class'           => 'oneline inline',
                                    'jsform'          => false,
                                    'action'          => get_config('wwwroot') . 'artefact/webservice/pluginconfig.php',
                                    'elements' => array(
                                        'service'    => array('type' => 'hidden', 'value' => $service->id),
                                        'action'     => array('type' => 'hidden', 'value' => 'generate'),
                                        'submit'     => array(
                                                'type'  => 'submit',
                                                'class' => 'linkbtn inline',
                                                'value' => get_string('gen', 'artefact.webservice')
                                            ),
                                    ),
                                ))
                                .
                                (empty($service->token) ? ' ' :
                                pieform(array(
                                    'name'            => 'webservices_user_token_delete_'.$service->id,
                                    'renderer'        => 'div',
                                    'elementclasses'  => false,
                                    'successcallback' => 'webservices_user_token_submit',
                                    'class'           => 'oneline inline',
                                    'jsform'          => true,
                                    'elements' => array(
                                        'service'    => array('type' => 'hidden', 'value' => $service->id),
                                        'action'     => array('type' => 'hidden', 'value' => 'delete'),
                                        'submit'     => array(
                                                'type'  => 'submit',
                                                'class' => 'linkbtn inline',
                                                'value' => get_string('delete')
                                            ),
                                    ),
                                ))) . '</span>'
                                ,
                'type'         => 'html',
                'key'        => $service->id,
                'class'        => 'actions',
            );
//        }
    }
    $userform = pieform($userform);
}


$dbtokens = get_records_sql_assoc('
        SELECT  ost.id                  as id,
                ost.token               as token,
                ost.timestamp           as timestamp,
                osr.institution         as institution,
                osr.externalserviceid   as externalserviceid,
                es.name                 as service_name,
                osr.consumer_key        as consumer_key,
                osr.consumer_secret     as consumer_secret,
                osr.enabled             as enabled,
                osr.status              as status,
                osr.issue_date          as issue_date,
                osr.application_uri     as application_uri,
                osr.application_title   as application_title,
                osr.application_descr   as application_descr,
                osr.requester_name      as requester_name,
                osr.requester_email     as requester_email,
                osr.callback_uri        as callback_uri
        FROM {oauth_server_token} ost
        JOIN {oauth_server_registry} osr
        ON ost.osr_id_ref = osr.id 
        JOIN {external_services} es
        ON es.id = osr.externalserviceid
        WHERE ost.userid = ? AND
              ost.token_type = \'access\'
        ORDER BY application_title, timestamp desc
        ', array($USER->id));    
$oauthform = get_string('notokens', 'artefact.webservice');
if (!empty($dbtokens)) {
    $oauthform = array(
        'name'            => 'webservices_tokens',
        'elementclasses'  => false,
        'successcallback' => 'webservices_tokens_submit',
        'renderer'   => 'multicolumntable',
        'elements'   => array(
                        'application' => array(
                            'title' => ' ',
                            'class' => 'header',
                            'type'  => 'html',
                            'value' => get_string('application', 'artefact.webservice'),
                        ),
                        'service_name' => array(
                            'title' => ' ',
                            'class' => 'header',
                            'type'  => 'html',
                            'value' => get_string('accessto', 'artefact.webservice'),
                        ),
                        'token' => array(
                            'title' => ' ',
                            'class' => 'header',
                            'type'  => 'html',
                            'value' => get_string('token', 'artefact.webservice'),
                        ),
                        'functions' => array(
                            'title' => ' ',
                            'class' => 'header',
                            'type'  => 'html',
                            'value' => get_string('functions', 'artefact.webservice'),
                        ),
                        'last_access' => array(
                            'title' => ' ',
                            'class' => 'header',
                            'type'  => 'html',
                            'value' => get_string('last_access', 'artefact.webservice'),
                        ),
                    ),
        );
    foreach ($dbtokens as $token) {
        $oauthform['elements']['id'. $token->id . '_application'] = array(
            'value'        =>  $token->application_title,
            'type'         => 'html',
            'key'        => $token->id,
        );
        $oauthform['elements']['id'. $token->id . '_service_name'] = array(
            'value'        =>  $token->service_name,
            'type'         => 'html',
            'key'        => $token->id,
        );
        $oauthform['elements']['id'. $token->id . '_token'] = array(
            'value'        =>  $token->token,
            'type'         => 'html',
            'key'        => $token->id,
        );
        $functions = get_records_array('external_services_functions', 'externalserviceid', $token->externalserviceid);
        $function_list = array();
        if ($functions) {
            foreach ($functions as $function) {
                $dbfunction = get_record('external_functions', 'name', $function->functionname);
                $function_list[]= '<a href="'.get_config('wwwroot').'artefact/webservice/wsdoc.php?id='.$dbfunction->id.'">'.$function->functionname.'</a>';
            }
        }
        $oauthform['elements']['id'. $token->id . '_functions'] = array(
            'value'        =>  implode(', ', $function_list),
            'type'         => 'html',
            'key'        => $token->id,
        );
        $oauthform['elements']['id'. $token->id . '_last_access'] = array(
            'value'        =>  date("F j, Y H:i", strtotime($token->timestamp)),
            'type'         => 'html',
            'key'        => $token->id,
        );

        // edit and delete buttons
        $oauthform['elements']['id'. $token->id . '_actions'] = array(
            'value'        => '<span class="actions inline">'.
                            pieform(array(
                                'name'            => 'webservices_server_delete_'.$token->id,
                                'renderer'        => 'div',
                                'elementclasses'  => false,
                                'successcallback' => 'webservices_server_submit',
                                'class'           => 'oneline inline',
                                'jsform'          => true,
                                'elements' => array(
                                    'token'      => array('type' => 'hidden', 'value' => $token->id),
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
            'key'        => $token->id,
            'class'        => 'actions',
        );
    }
    $oauthform = pieform($oauthform);
}

$elements = array(
        // fieldset for managing service function list
        'user_tokens' => array(
                            'type' => 'fieldset',
                            'legend' => get_string('usertokens', 'artefact.webservice'),
                            'elements' => array(
                                'sflist' => array(
                                    'type'         => 'html',
                                    'value' =>     $userform,
                                )
                            ),
                            'collapsible' => false,
                        ),
        // fieldset for managing service function list
        'oauth_tokens' => array(
                            'type' => 'fieldset',
                            'legend' => get_string('accesstokens', 'artefact.webservice'),
                            'elements' => array(
                                'sflist' => array(
                                    'type'         => 'html',
                                    'value' =>     $oauthform,
                                )
                            ),
                            'collapsible' => false,
                        ),
    );

$form = array(
    'renderer' => 'div',
    'type' => 'div',
    'id' => 'maintable',
    'name' => 'maincontainer',
    'dieaftersubmit' => false,
    'successcallback' => 'webservice_main_submit',
    'elements' => $elements,
);

function webservices_user_token_submit(Pieform $form, $values) {
    global $USER, $SESSION;
    if ($values['action'] == 'generate') {
        delete_records('external_tokens', 'userid', $USER->id, 'externalserviceid', $values['service']);
        $services = get_records_select_array('external_services', 'id = ? AND tokenusers = 1', array($values['service']));
        if (empty($services)) {
            $SESSION->add_error_msg(get_string('noservices', 'artefact.webservice'));
        }
        else {
            $service = array_shift($services); // just pass the first one for the moment
            $authinstance = get_record('auth_instance', 'id', $USER->authinstance);
            $token = external_generate_token(EXTERNAL_TOKEN_USER, $service, $USER->id, $authinstance->institution, (time() + EXTERNAL_TOKEN_USER_EXPIRES));
            $SESSION->add_ok_msg(get_string('token_generated', 'artefact.webservice'));
        }
    }
    else if ($values['action'] == 'delete') {
        delete_records('external_tokens', 'userid', $USER->id, 'externalserviceid', $values['service']);
        $SESSION->add_ok_msg(get_string('oauthtokendeleted', 'artefact.webservice'));
    }
    redirect('/artefact/webservice/apptokens.php');
}

                                
function webservices_server_submit(Pieform $form, $values) {
    global $USER, $SESSION;
    if ($values['action'] == 'delete') {
        delete_records('oauth_server_token', 'id', $values['token'], 'userid', $USER->id);
        $SESSION->add_ok_msg(get_string('oauthtokendeleted', 'artefact.webservice'));
    }
    redirect('/artefact/webservice/apptokens.php');
}


$form = pieform($form);

$smarty = smarty(array(), array('<link rel="stylesheet" type="text/css" href="' . get_config('wwwroot') . 'artefact/webservice/theme/raw/static/style/style.css">',));
$smarty->assign('form', $form);
$smarty->assign('PAGEHEADING', TITLE);
$smarty->display('artefact:webservice:tokenconfig.tpl');

                                
function webservice_main_submit(Pieform $form, $values) {
    error_log('main: '.var_export($values, true));
    
}
