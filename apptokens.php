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
define('MENUITEM', 'settings/apps');

require(dirname(dirname(dirname(__FILE__))) . '/init.php');
define('TITLE', get_string('apptokens', 'artefact.webservice'));
require_once('pieforms/pieform.php');

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
$form = get_string('notokens', 'artefact.webservice');
if (!empty($dbtokens)) {
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
                        'last_access' => array(
                            'title' => ' ',
                            'class' => 'header',
                            'type'  => 'html',
                            'value' => get_string('last_access', 'artefact.webservice'),
                        ),
                    ),
        );
    foreach ($dbtokens as $token) {
        $form['elements']['id'. $token->id . '_application'] = array(
            'value'        =>  $token->application_title,
            'type'         => 'html',
            'key'        => $token->id,
        );
        $form['elements']['id'. $token->id . '_service_name'] = array(
            'value'        =>  $token->service_name,
            'type'         => 'html',
            'key'        => $token->id,
        );
        $form['elements']['id'. $token->id . '_token'] = array(
            'value'        =>  $token->token,
            'type'         => 'html',
            'key'        => $token->id,
        );
        $form['elements']['id'. $token->id . '_last_access'] = array(
            'value'        =>  date("F j, Y H:i", strtotime($token->timestamp)),
            'type'         => 'html',
            'key'        => $token->id,
        );

        // edit and delete buttons
        $form['elements']['id'. $token->id . '_actions'] = array(
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
    $form = pieform($form);
}

$elements = array(
        // fieldset for managing service function list
        'auth_tokens' => array(
                            'type' => 'fieldset',
                            'legend' => get_string('accesstokens', 'artefact.webservice'),
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
    'renderer' => 'div',
    'type' => 'div',
    'id' => 'maintable',
    'name' => 'maincontainer',
    'dieaftersubmit' => false,
    'successcallback' => 'webservice_main_submit',
    'elements' => $elements,
);

                                
                                
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
