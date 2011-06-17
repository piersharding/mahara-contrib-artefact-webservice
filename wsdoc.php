<?php

/**
 * Mahara: Electronic portfolio, weblog, resume builder and social networking
 * Copyright (C) 2009 Moodle Pty Ltd (http://moodle.com)
 * Copyright (C) 2011 Catalyst IT Ltd (http://www.catalyst.net.nz)
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
 * @author     Piers Harding
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  Copyright (C) 2011 Catalyst IT Ltd (http://www.catalyst.net.nz)
 */

define('INTERNAL', 1);
define('ADMIN', 1);
define('MENUITEM', 'configextensions/pluginadminwebservices');
require(dirname(dirname(dirname(__FILE__))) . '/init.php');
define('TITLE', get_string('pluginadmin', 'admin'));
require_once('pieforms/pieform.php');

require($CFG->dirroot . '/artefact/webservice/lib.php');

//require_login();
//require_sesskey();

//$usercontext = get_context_instance(CONTEXT_USER, $USER->id);
$tokenid = required_param('id', PARAM_INT);

//PAGE settings
$PAGE->set_context($usercontext);
$PAGE->set_url('/user/wsdoc.php');
$PAGE->set_title(get_string('documentation', 'webservice'));
$PAGE->set_heading(get_string('documentation', 'webservice'));
$PAGE->set_pagelayout('standard');

//nav bar
$PAGE->navbar->ignore_active(true);
$PAGE->navbar->add(get_string('usercurrentsettings'));
$PAGE->navbar->add(get_string('securitykeys', 'webservice'),
        new moodle_url('/user/managetoken.php',
                array('id' => $tokenid, 'sesskey' => sesskey())));
$PAGE->navbar->add(get_string('documentation', 'webservice'));

//check web service are enabled
if (empty($CFG->enablewsdocumentation)) {
    echo get_string('wsdocumentationdisable', 'webservice');
    die;
}

//check that the current user is the token user
$webservice = new webservice();
$token = $webservice->get_token_by_id($tokenid);
if (empty($token) or empty($token->userid) or empty($USER->id)
        or ($token->userid != $USER->id)) {
    throw new mahara_ws_exception('docaccessrefused', 'webservice');
}

// get the list of all functions related to the token
$functions = $webservice->get_external_functions(array($token->externalserviceid));

// get all the function descriptions
$functiondescs = array();
foreach ($functions as $function) {
    $functiondescs[$function->name] = external_function_info($function);
}

//get activated protocol
$activatedprotocol = array();
$activatedprotocol['rest'] = webservice_protocol_is_enabled('rest');
$activatedprotocol['xmlrpc'] = webservice_protocol_is_enabled('xmlrpc');

/// Check if we are in printable mode
$printableformat = false;
if (isset($_REQUEST['print'])) {
    $printableformat = $_REQUEST['print'];
}

/// OUTPUT
echo $OUTPUT->header();

$renderer = $PAGE->get_renderer('core', 'webservice');
echo $renderer->documentation_html($functiondescs,
        $printableformat, $activatedprotocol, array('id' => $tokenid));

/// trigger browser print operation
if (!empty($printableformat)) {
    $PAGE->requires->js_function_call('window.print', array());
}

echo $OUTPUT->footer();
