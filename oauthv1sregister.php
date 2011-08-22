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
//define('MENUITEM', 'configusers/oauthv1sregister');
define('MENUITEM', 'configextensions/oauthv1sregister');

require(dirname(dirname(dirname(__FILE__))) . '/init.php');
define('TITLE', get_string('oauthv1sregister', 'artefact.webservice'));
require_once('pieforms/pieform.php');
require_once(dirname(__FILE__).'/libs/oauth-php/OAuthServer.php');
require_once(dirname(__FILE__).'/libs/oauth-php/OAuthStore.php');
OAuthStore::instance('Mahara');

$server_id  = param_variable('edit', 0);
$dbserver = get_record('oauth_server_registry', 'id', $server_id);
error_log('server: '.var_export($dbserver, true));

$institutions = get_records_array('institution');
$iopts = array();
foreach ($institutions as $institution) {
    $iopts[trim($institution->name)] = $institution->displayname;
}

$services = get_records_array('external_services', 'restrictedusers', 0);
$sopts = array();
foreach ($services as $service) {
    $sopts[$service->id] = $service->name;
}

// we have an edit form
if (!empty($dbserver)) {
    $form = webservice_server_edit_form($dbserver, $sopts, $iopts);
}
// else we have just the standard list
else {
    $form = webservice_server_list_form($sopts, $iopts);
}



                                
function webservices_add_application_validate(Pieform $form, $values) {
    global $SESSION;
    error_log('validate application: '.var_export($values, true));
//    $form->set_error('application', 'bad person');
//    $SESSION->add_error_msg(get_string('existingtokens', 'artefact.webservice'));
}


                                
function webservices_add_application_submit(Pieform $form, $values) {
    global $SESSION;
    
    error_log('add application: '.var_export($values, true));
    
    $dbuser = get_record('usr', 'username', $values['username']);
    if (empty($dbuser)) {
        $SESSION->add_error_msg(get_string('erroruser', 'artefact.webservice'));
        redirect('/artefact/webservice/oauthv1sregister.php');       
    }
    $store = OAuthStore::instance();
    
    $new_app = array(
                'application_title' => $values['application'],
                'application_uri'   => 'http://example.com',
                'requester_name'    => $dbuser->firstname.' '.$dbuser->lastname,
                'requester_email'   => $dbuser->email,
                'callback_uri'      => 'http://example.com',
                'institution'       => $values['institution'],
                'externalserviceid' => $values['service'],
      );
    $key = $store->updateConsumer($new_app, $dbuser->id, true);
    $c = (object) $store->getConsumer($key, $dbuser->id);
    if (empty($c)) {
        $SESSION->add_error_msg(get_string('errorregister', 'artefact.webservice'));
        redirect('/artefact/webservice/oauthv1sregister.php');
    }
    else {
        redirect('/artefact/webservice/oauthv1sregister.php?edit='.$c->id);
    }
    
}


                                
                                
function webservices_server_submit(Pieform $form, $values) {
    global $USER, $SESSION;
    $store = OAuthStore::instance();
    $is_admin = ($USER->get('admin') ||defined('ADMIN') || defined('INSTITUTIONALADMIN') || $USER->is_institutional_admin() ? true : false);
    $dbserver = get_record('oauth_server_registry', 'id', $values['token']);
    if ($dbserver) {
        if ($values['action'] == 'delete') {
            $store->deleteServer($dbserver->consumer_key, $dbserver->userid, $is_admin);
            $SESSION->add_ok_msg(get_string('oauthserverdeleted', 'artefact.webservice'));
        }
        else if ($values['action'] == 'edit') {
            redirect('/artefact/webservice/oauthv1sregister.php?edit='.$values['token']);
        }
    }
    redirect('/artefact/webservice/oauthv1sregister.php');
}


function webservice_oauth_server_submit(Pieform $form, $values) {
    global $USER, $SESSION;
    $store = OAuthStore::instance();
    $is_admin = ($USER->get('admin') ||defined('ADMIN') || defined('INSTITUTIONALADMIN') || $USER->is_institutional_admin() ? true : false);
    $dbserver = get_record('oauth_server_registry', 'id', $values['id']);
    if ($dbserver) {
        
       $app = array(
                    'application_title' => $values['application_title'],
                    'application_uri'   => $values['application_uri'],
                    'requester_name'    => $dbserver->requester_name,
                    'requester_email'   => $dbserver->requester_email,
                    'callback_uri'      => $values['callback_uri'],
                    'institution'       => $values['institution'],
                    'externalserviceid' => $values['service'],
                    'consumer_key'      => $dbserver->consumer_key,
                    'consumer_secret'   => $dbserver->consumer_secret,
                    'id'                => $values['id'],
       );        
        $key = $store->updateConsumer($app, $values['userid'], true);
        $c = (object) $store->getConsumer($key, $values['userid']);
        if (empty($c)) {
            $SESSION->add_error_msg(get_string('errorregister', 'artefact.webservice'));
            redirect('/artefact/webservice/oauthv1sregister.php');
        }
        else {
            redirect('/artefact/webservice/oauthv1sregister.php?edit='.$c->id);
        }
    }
    
    $SESSION->add_error_msg(get_string('errorupdate', 'artefact.webservice'));
    redirect('/artefact/webservice/oauthv1sregister.php');
}



$form = pieform($form);

$smarty = smarty(array(), array('<link rel="stylesheet" type="text/css" href="' . get_config('wwwroot') . 'artefact/webservice/theme/raw/static/style/style.css">',));
$smarty->assign('form', $form);
$smarty->assign('PAGEHEADING', TITLE);
$smarty->display('artefact:webservice:tokenconfig.tpl');

                                
function webservice_main_submit(Pieform $form, $values) {
    error_log('main: '.var_export($values, true));
    
}


function webservice_server_edit_form($dbserver, $sopts, $iopts) {
    
    $server_details =
        array(
            'name'             => 'webservice_oauth_server',
            'successcallback'  => 'webservice_oauth_server_submit',
            'jsform'           => true,
            'renderer'         => 'multicolumntable',
            'elements'   => array(
                            'id' => array(
                                'type'  => 'hidden',
                                'value' => $dbserver->id,
                            ),
                            'userid' => array(
                                'type'  => 'hidden',
                                'value' => $dbserver->userid,
                            ),                            
                            'consumer_key' => array(
                                'type'  => 'hidden',
                                'value' => $dbserver->consumer_key,
                            ),                            
                    ),
            );
            
    $server_details['elements']['consumer_secret'] = array(
        'title'        => get_string('consumer_secret', 'artefact.webservice'),
        'value'        =>  $dbserver->consumer_secret,
        'type'         => 'html',
    );
            
    $server_details['elements']['application_title'] = array(
        'title'        => get_string('application_title', 'artefact.webservice'),
        'defaultvalue'        =>  $dbserver->application_title,
        'type'         => 'text',
    );
            
    $server_details['elements']['user'] = array(
        'title'        => get_string('serviceuser', 'artefact.webservice'),
        'value'        =>  get_field('usr', 'username', 'id', $dbserver->userid),
        'type'         => 'html',
    );
                           
    $server_details['elements']['application_uri'] = array(
        'title'        => get_string('application_uri', 'artefact.webservice'),
        'value'        =>  $dbserver->application_uri,
        'type'         => 'text',
    );
                            
    $server_details['elements']['callback_uri'] = array(
        'title'        => get_string('callback', 'artefact.webservice'),
        'value'        =>  $dbserver->callback_uri,
        'type'         => 'text',
    );
    
    $server_details['elements']['institution'] = array(
        'type'         => 'select',
        'title'        => get_string('institution'),
        'options'      => $iopts,
        'defaultvalue' => trim($dbserver->institution),
    );
    
    $server_details['elements']['service'] = array(
        'type'         => 'select',
        'title'        => get_string('servicename', 'artefact.webservice'),
        'options'      => $sopts,
        'defaultvalue' => $dbserver->externalserviceid,
    );
    
    $server_details['elements']['enabled'] = array(
        'title'        => get_string('enabled', 'artefact.webservice'),
        'defaultvalue' => (($dbserver->enabled == 1) ? 'checked' : ''),
        'type'         => 'checkbox',
        'disabled'     => true,
    );
    
    $functions = get_records_array('external_services_functions', 'externalserviceid', $dbserver->externalserviceid);
    $function_list = array();
    if ($functions) {
        foreach ($functions as $function) {
            $dbfunction = get_record('external_functions', 'name', $function->functionname);
            $function_list[]= '<a href="'.get_config('wwwroot').'artefact/webservice/wsdoc.php?id='.$dbfunction->id.'">'.$function->functionname.'</a>';
        }
    }
    $server_details['elements']['functions'] = array(
        'title'        => get_string('functions', 'artefact.webservice'),
        'value'        =>  implode(', ', $function_list),
        'type'         => 'html',
    );
    
    $server_details['elements']['submit'] = array(
        'type'  => 'submitcancel',
        'value' => array(get_string('save'), get_string('cancel')),
        'goto'  => get_config('wwwroot') . 'artefact/webservice/oauthv1sregister.php',
    );
    
    $elements = array(
            // fieldset for managing service function list
            'token_details' => array(
                                'type' => 'fieldset',
                                'legend' => get_string('serverkey', 'artefact.webservice', $dbserver->consumer_key),
                                'elements' => array(
                                    'sflist' => array(
                                        'type'         => 'html',
                                        'value' =>     pieform($server_details),
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
        'successcallback' => 'webservice_server_edit_submit',
        'elements' => $elements,
    );

    return $form;
}

function webservice_server_list_form($sopts, $iopts) {
    global $USER, $THEME;
    
    $dbconsumers = get_records_sql_assoc('
            SELECT  osr.id              as id,
                    userid              as userid,
                    institution         as institution,
                    externalserviceid   as externalserviceid,
                    u.username          as username,
                    u.email             as email,
                    consumer_key        as consumer_key,
                    consumer_secret     as consumer_secret,
                    enabled             as enabled,
                    status              as status,
                    issue_date          as issue_date,
                    application_uri     as application_uri,
                    application_title   as application_title,
                    application_descr   as application_descr,
                    requester_name      as requester_name,
                    requester_email     as requester_email,
                    callback_uri        as callback_uri
            FROM {oauth_server_registry} osr
            JOIN {usr} u
            ON osr.userid = u.id
            ORDER BY application_title, username
            ', array());    
    $form = '';
    if (!empty($dbconsumers)) {
        $form = array(
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
                            'username' => array(
                                'title' => ' ',
                                'class' => 'header',
                                'type'  => 'html',
                                'value' => get_string('username', 'artefact.webservice'),
                            ),                            
                            'consumer_key' => array(
                                'title' => ' ',
                                'class' => 'header',
                                'type'  => 'html',
                                'value' => get_string('consumer_key', 'artefact.webservice'),
                            ),
                            'consumer_secret' => array(
                                'title' => ' ',
                                'class' => 'header',
                                'type'  => 'html',
                                'value' => get_string('consumer_secret', 'artefact.webservice'),
                            ),
                            'enabled' => array(
                                'title' => ' ',
                                'class' => 'header',
                                'type'  => 'html',
                                'value' => get_string('enabled', 'artefact.webservice'),
                            ),
                            'calback_uri' => array(
                                'title' => ' ',
                                'class' => 'header',
                                'type'  => 'html',
                                'value' => get_string('callback', 'artefact.webservice'),
                            ),
                            'consumer_secret' => array(
                                'title' => ' ',
                                'class' => 'header',
                                'type'  => 'html',
                                'value' => get_string('consumer_secret', 'artefact.webservice'),
                            ),
                        ),
            );
        foreach ($dbconsumers as $consumer) {
            $form['elements']['id'. $consumer->id . '_application'] = array(
                'value'        =>  $consumer->application_title,
                'type'         => 'html',
                'key'        => $consumer->consumer_key,
            );
            $form['elements']['id'. $consumer->id . '_username'] = array(
                'value'        =>  $consumer->username,
                'type'         => 'html',
                'key'        => $consumer->consumer_key,
            );            
            $form['elements']['id'. $consumer->id . '_consumer_key'] = array(
                'value'        =>  $consumer->consumer_key,
                'type'         => 'html',
                'key'        => $consumer->consumer_key,
            );
            $form['elements']['id'. $consumer->id . '_consumer_secret'] = array(
                'value'        =>  $consumer->consumer_secret,
                'type'         => 'html',
                'key'        => $consumer->consumer_key,
            );
            $form['elements']['id'. $consumer->id . '_enabled'] = array(
                'defaultvalue' => (($consumer->enabled == 1) ? 'checked' : ''),
                'type'         => 'checkbox',
                'disabled'     => true,
                'key'        => $consumer->consumer_key,
            );
            $form['elements']['id'. $consumer->id . '_calback_uri'] = array(
                'value'        =>  $consumer->callback_uri,
                'type'         => 'html',
                'key'        => $consumer->consumer_key,
            );
    
            // edit and delete buttons
            $form['elements']['id'. $consumer->id . '_actions'] = array(
                'value'        => '<span class="actions inline">'.
                                pieform(array(
                                    'name'            => 'webservices_server_edit_'.$consumer->id,
                                    'renderer'        => 'div',
                                    'elementclasses'  => false,
                                    'successcallback' => 'webservices_server_submit',
                                    'class'           => 'oneline inline',
                                    'jsform'          => true,
                                    'elements' => array(
                                        'token'      => array('type' => 'hidden', 'value' => $consumer->id),
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
                                    'name'            => 'webservices_server_delete_'.$consumer->id,
                                    'renderer'        => 'div',
                                    'elementclasses'  => false,
                                    'successcallback' => 'webservices_server_submit',
                                    'class'           => 'oneline inline',
                                    'jsform'          => true,
                                    'elements' => array(
                                        'token'      => array('type' => 'hidden', 'value' => $consumer->id),
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
                'key'        => $consumer->consumer_key,
                'class'        => 'actions',
            );
        }
        $form = pieform($form);
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
    $form = '<tr><td colspan="2">'.
            $form.'</td></tr><tr><td colspan="2">'.
                                pieform(array(
                                    'name'            => 'webservices_token_generate',
                                    'renderer'        => 'div',
                                    'validatecallback' => 'webservices_add_application_validate',
                                    'successcallback' => 'webservices_add_application_submit',
                                    'class'           => 'oneline inline',
                                    'jsform'          => false,
                                    'action'          => get_config('wwwroot') . 'artefact/webservice/oauthv1sregister.php',
                                    'elements' => array(
                                        'application'  => array(
                                                               'type'        => 'text',
                                                               'title'       => get_string('application', 'artefact.webservice'),
                                                           ),
                                                           
                                         'institution' => array(
                                                                'type'         => 'select',
                                                                'options'      => $iopts,
                                                            ),

                                        'service' => array(
                                                            'type'         => 'select',
                                                            'options'      => $sopts,
                                        ),     
                                        'username'  => array(
                                                               'type'        => 'text',
                                                               'title'       => get_string('username'),
                                                               'value'       => $username,
                                                           ),
                                        'usersearch'  => array(
                                                               'type'        => 'html',
                                                               'value'       => '&nbsp;<a href="'.get_config('wwwroot') .'artefact/webservice/search.php?ouid=add"><img src="'.$searchicon.'" id="usersearch"/></a> &nbsp; ',
                                                           ),
                                        'action'     => array('type' => 'hidden', 'value' => 'add'),
                                        'submit'     => array(
                                                'type'  => 'submit',
                                                'class' => 'linkbtn',
                                                'value' => get_string('add', 'artefact.webservice'),
                                            ),
                                    ),
                                )).
             '</td></tr>';
    
    $elements = array(
            // fieldset for managing service function list
            'register_server' => array(
                                'type' => 'fieldset',
                                'legend' => get_string('userapplications', 'artefact.webservice'),
                                'elements' => array(
                                    'sflist' => array(
                                        'type'         => 'html',
                                        'value' =>     $form,
                                    )
                                ),
                                'collapsible' => false,
                            ),
        );
    
    $form = array(
        'renderer' => 'table',
        'type' => 'div',
        'id' => 'maintable',
        'name' => 'maincontainer',
        'dieaftersubmit' => false,
        'successcallback' => 'webservice_main_submit',
        'elements' => $elements,
    );

    return $form;
}
