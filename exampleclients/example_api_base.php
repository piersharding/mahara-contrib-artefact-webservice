<?php

/**
 * Mahara: Electronic portfolio, weblog, resume builder and social networking
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
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 */

/**
 * Test the different web service protocols.
 *
 * @author     Piers Harding
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package web service
 * @copyright  Copyright (C) 2011 Catalyst IT Ltd (http://www.catalyst.net.nz)
 */

// must be run from the command line
if (isset($_SERVER['REMOTE_ADDR']) || isset($_SERVER['GATEWAY_INTERFACE'])){
    die('Direct access to this script is forbidden.');
}

$path = realpath('../libs/zend');
set_include_path($path . PATH_SEPARATOR . get_include_path());

include ("Console/Getopt.php");


include 'Zend/Loader/Autoloader.php';
Zend_Loader_Autoloader::autoload('Zend_Loader');

// disable the WSDL cache
ini_set("soap.wsdl_cache_enabled", "0");

// get the WSSE SOAP client class
require_once('wsse_soap_client.php');


//fetch arguments
$args = Console_Getopt::readPHPArgv();
//checking errors for argument fetching
if (PEAR::isError($args)) {
    error_log('Invalid arguments (1)');
    exit(1);
}

// remove stderr/stdout redirection args
$args = preg_grep('/2>&1/', $args, PREG_GREP_INVERT);
$console_opt = Console_Getopt::getOpt($args, 'u:p:l:s:', array('username=', 'password=', 'url=', 'servicegroup='));

// must supply at least one arg for the action to perform
if (count($args) <= 1) {
    error_log('Invalid arguments (2)');
    exit(1);
}

/**
 * Get and check interactive parameters
 *
 * @param string $param
 * @param string $default
 */
function get_param_console($param, $default) {
    if ($tmp = trim(readline("Enter Mahara $param ($default): "))) {
        $default = $tmp;
    }
    if (empty($default)) {
        die("You must have a $param for executing web services\n");
    }
    return $default;
}

// defaults
global $url, $servicegroup, $username, $password;

$url = 'http://mahara.local.net/maharadev/artefact/webservice/soap/simpleserver.php';
if (empty($servicegroup)) {
    $servicegroup = 'Simple User Provisioning';
}
if (empty($username)) {
    $username = false;
}
if (empty($password)) {
    $password = false;
}

// parse back the options
$opts = $console_opt[0];
if (sizeof($opts) > 0) {
    // if at least one option is present
    foreach ($opts as $o) {
        switch ($o[0]) {
            // handle the size option
            case 'u':
            case '--username':
                $username = $o[1];
                break;
            case 'p':
            case '--password':
                $password = $o[1];
                break;
            case 'l':
            case '--url':
                $url = $o[1];
                break;
            case 's':
            case '--servicegroup':
                $servicegroup = $o[1];
                break;
        }
    }
}


// ensure that we have the necessary options
$username = get_param_console('username', $username);
$password = get_param_console('password', $password);
$servicegroup = get_param_console('servicegroup', $servicegroup);
$url = get_param_console('Mahara Web Services URL', $url);

// declare what we are running with
print "web services url: $url\n";
print "service group: $servicegroup\n";
print "username; $username\n";
print "password: $password\n";
$wsdl = $url. '?wsservice=' . $servicegroup.'&wsdl=1';
print "WSDL URL: $wsdl \n";

// keep looping until user exits
while (1==1) {
    // now select a function to execute
    print "Select one of functions to execute:\n";
    $cnt = 0;
    $function_list = array_keys($functions);
    foreach ($function_list as $rfunction) {
        print "$cnt. $rfunction\n";
        $cnt++;
    }
    $function_choice = trim(readline("Enter your choice (0..".(count($function_list) - 1)." or x for exit):"));
    if (in_array($function_choice, array('x', 'X', 'q', 'Q'))) {
        break;
    }
    if (!preg_match('/^\d+$/', $function_choice) || (int)$function_choice > (count($function_list) - 1)) {
        print "Incorrect choice - aborting\n";
        exit(0);
    }
    $function = $function_list[$function_choice];
    print "Chosen function: $function\n";
    print "Parameters used for execution are: ".var_export($functions[$function], true)."\n";

    // build the client for execution
    $client = new WSSE_Soap_Client($wsdl, $username, $password);

    //make the web service call
    try {
        $result = $client->call($function, $functions[$function]);
        print "Results are: ".var_export($result, true)."\n";
    } catch (Exception $e) {
         print "exception: ".var_export($e, true)."\n";
    }
}

exit(0);