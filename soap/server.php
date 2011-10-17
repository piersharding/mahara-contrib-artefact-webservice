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
 */

/**
 * SOAP web service entry point. The authentication is done via tokens.
 *
 * @package   webservice
 * @copyright 2009 Moodle Pty Ltd (http://moodle.com)
 * @copyright  Copyright (C) 2011 Catalyst IT Ltd (http://www.catalyst.net.nz)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Piers Harding
 */

define('INTERNAL', 1);
define('PUBLIC', 1);
define('XMLRPC', 1);
define('TITLE', '');

require(dirname(dirname(dirname(dirname(__FILE__)))) . '/api/xmlrpc/lib.php');

// Catch anything that goes wrong in init.php
ob_start();
    require(dirname(dirname(dirname(dirname(__FILE__)))) . '/init.php');
    $errors = trim(ob_get_contents());
ob_end_clean();
require_once(get_config('docroot') . '/artefact/webservice/soap/locallib.php');

if (!webservice_protocol_is_enabled('soap')) {
    header("HTTP/1.0 404 Not Found");
    die;
}

$server = new webservice_soap_server(WEBSERVICE_AUTHMETHOD_PERMANENT_TOKEN);
$server->run();
die;
