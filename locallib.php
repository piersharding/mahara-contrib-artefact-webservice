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
 * Web services utility functions and classes
 *
 * @package   webservice
 * @copyright 2009 Moodle Pty Ltd (http://moodle.com)
 * @copyright  Copyright (C) 2011 Catalyst IT Ltd (http://www.catalyst.net.nz)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Piers Harding
 */
$path = get_config('docroot') . 'artefact/webservice/libs/zend';
set_include_path($path . PATH_SEPARATOR . get_include_path());
require_once(get_config('docroot') . '/artefact/webservice/libs/externallib.php');

/**
 * Security token used for allowing access
 * from external application such as web services.
 * Scripts do not use any session, performance is relatively
 * low because we need to load access info in each request.
 * Scripts are executed in parallel.
 */
define('EXTERNAL_TOKEN_PERMANENT', 0);

/**
 * Security token used for allowing access
 * of embedded applications, the code is executed in the
 * active user session. Token is invalidated after user logs out.
 * Scripts are executed serially - normal session locking is used.
 */
define('EXTERNAL_TOKEN_EMBEDDED', 1);

/**
 * OAuth Token type for registered applications oauth v1
 */
define('EXTERNAL_TOKEN_OAUTH1', 2);

/**
 * OAuth Token type for registered applications oauth v1
 */
define('EXTERNAL_TOKEN_USER', 3);

/**
 * Personal User Tokens expiry time
 */
define('EXTERNAL_TOKEN_USER_EXPIRES', (30 * 24 * 60 * 60));

define('WEBSERVICE_AUTHMETHOD_USERNAME', 0);
define('WEBSERVICE_AUTHMETHOD_PERMANENT_TOKEN', 1);
define('WEBSERVICE_AUTHMETHOD_SESSION_TOKEN', 2);
define('WEBSERVICE_AUTHMETHOD_OAUTH_TOKEN', 3);
define('WEBSERVICE_AUTHMETHOD_USER_TOKEN', 4);

// strictness check
define('MUST_EXIST', 2);

/// Debug levels ///
/** no warnings at all */
define('DEBUG_NONE', 0);
/** E_ERROR | E_PARSE */
define('DEBUG_MINIMAL', 5);
/** E_ERROR | E_PARSE | E_WARNING | E_NOTICE */
define('DEBUG_NORMAL', 15);
/** E_ALL without E_STRICT for now, do show recoverable fatal errors */
define('DEBUG_ALL', 6143);

/** Remove any memory limits */
define('MEMORY_UNLIMITED', -1);
/** Standard memory limit for given platform */
define('MEMORY_STANDARD', -2);
define('MEMORY_EXTRA', -3);
/** Extremely large memory limit - not recommended for standard scripts */
define('MEMORY_HUGE', -4);

/** Get remote addr constant */
define('GETREMOTEADDR_SKIP_HTTP_CLIENT_IP', '1');
/** Get remote addr constant */
define('GETREMOTEADDR_SKIP_HTTP_X_FORWARDED_FOR', '2');

/**
 *  is debugging switched on for Web Services
 */
function ws_debugging() {
    if (defined('DEBUG_DEVELOPER')) {
        return true;
    }
    return false;
}

/**
 * Add an entry to the log table.
 *
 * Add an entry to the log table.  These are "action" focussed rather
 * than web server hits, and provide a way to easily reconstruct what
 * any particular student has been doing.
 *
 * @param    string  $module  The module name - e.g. forum, journal, resource, course, user etc
 * @param    string  $action  'view', 'update', 'add' or 'delete', possibly followed by another word to clarify.
 * @param    string  $url     The file and parameters used to see the results of the action
 * @param    string  $info    Additional description information
 * @param    string  $cm      The course_module->id if there is one
 * @param    string  $user    If log regards $user other than $USER
 * @return void
 */
function ws_add_to_log($module, $action, $url='', $info='', $cm=0, $user=0) {
    error_log("module: $module action: $action ($url) info: $info");
}

/**
 * validate the user for webservices access
 * the account must use the webservice auth plugin
 * the account must have membership for the selected auth_instance
 *
 * @param object $dbuser
 */
function webservice_validate_user($dbuser) {
    global $SESSION;
    if (!empty($dbuser)) {
        $auth_instance = get_record('auth_instance', 'id', $dbuser->authinstance);
        if ($auth_instance->authname == 'webservice') {
            $memberships = count_records('usr_institution', 'usr', $dbuser->id);
            if ($memberships == 0) {
                // auth instance should be a mahara one
                if ($auth_instance->institution == 'mahara') {
                    return $auth_instance;
                }
            }
            else {
                $membership = get_record('usr_institution', 'usr', $dbuser->id, 'institution', $auth_instance->institution);
                if (!empty($membership)) {
                    return $auth_instance;
                }
            }
        }
    }
    return NULL;
}

/**
 * Return exact absolute path to a plugin directory,
 * this method support "simpletest_" prefix designed for unit testing.
 *
 * @param string $component name such as 'moodle', 'mod_forum' or special simpletest value
 * @return string full path to component directory; NULL if not found
 */
function get_component_directory($component) {
    $subsystems = get_core_subsystems();
    if (isset($subsystems[$component])) {
        $path = get_config('docroot') . '/' . $subsystems[$component];
    } else {
        $path = NULL;
    }

    return $path;
}

/**
 * List all core subsystems and their location
 *
 * This is a whitelist of components that are part of the core
 *
 * @return array of (string)name => (string|null)location
 */
function get_core_subsystems() {
    static $info = null;

    if (!$info) {
        $info = array(
            'webservice'  => 'artefact/webservice',
            'admin'       => 'admin',
            'api'         => 'api',
            'local'       => 'local',
        );
    }

    return $info;
}

/**
 * Function to check the passed address is within the passed subnet
 *
 * The parameter is a comma separated string of subnet definitions.
 * Subnet strings can be in one of three formats:
 *   1: xxx.xxx.xxx.xxx/nn or xxxx:xxxx:xxxx:xxxx:xxxx:xxxx:xxxx/nnn          (number of bits in net mask)
 *   2: xxx.xxx.xxx.xxx-yyy or  xxxx:xxxx:xxxx:xxxx:xxxx:xxxx:xxxx::xxxx-yyyy (a range of IP addresses in the last group)
 *   3: xxx.xxx or xxx.xxx. or xxx:xxx:xxxx or xxx:xxx:xxxx.                  (incomplete address, a bit non-technical ;-)
 * Code for type 1 modified from user posted comments by mediator at
 * {@link http://au.php.net/manual/en/function.ip2long.php}
 *
 * @param string $addr    The address you are checking
 * @param string $subnetstr    The string of subnet addresses
 * @return bool
 */
function address_in_subnet($addr, $subnetstr) {

    if ($addr == '0.0.0.0') {
        return false;
    }
    $subnets = explode(',', $subnetstr);
    $found = false;
    $addr = trim($addr);
    // normalise
    $addr = cleanremoteaddr($addr, false);
    if ($addr === null) {
        return false;
    }
    $addrparts = explode(':', $addr);

    $ipv6 = strpos($addr, ':');

    foreach ($subnets as $subnet) {
        $subnet = trim($subnet);
        if ($subnet === '') {
            continue;
        }

        if (strpos($subnet, '/') !== false) {
            ///1: xxx.xxx.xxx.xxx/nn or xxxx:xxxx:xxxx:xxxx:xxxx:xxxx:xxxx/nnn
            list($ip, $mask) = explode('/', $subnet);
            $mask = trim($mask);
            if (!is_number($mask)) {
                // incorect mask number, eh?
                continue;
            }
            // normalise
            $ip = cleanremoteaddr($ip, false);
            if ($ip === null) {
                continue;
            }
            if (strpos($ip, ':') !== false) {
                // IPv6
                if (!$ipv6) {
                    continue;
                }
                if ($mask > 128 or $mask < 0) {
                    // nonsense
                    continue;
                }
                if ($mask == 0) {
                    // any address
                    return true;
                }
                if ($mask == 128) {
                    if ($ip === $addr) {
                        return true;
                    }
                    continue;
                }
                $ipparts = explode(':', $ip);
                $modulo  = $mask % 16;
                $ipnet   = array_slice($ipparts, 0, ($mask-$modulo)/16);
                $addrnet = array_slice($addrparts, 0, ($mask-$modulo)/16);
                if (implode(':', $ipnet) === implode(':', $addrnet)) {
                    if ($modulo == 0) {
                        return true;
                    }
                    $pos     = ($mask-$modulo)/16;
                    $ipnet   = hexdec($ipparts[$pos]);
                    $addrnet = hexdec($addrparts[$pos]);
                    $mask    = 0xffff << (16 - $modulo);
                    if (($addrnet & $mask) == ($ipnet & $mask)) {
                        return true;
                    }
                }

            } else {
                // IPv4
                if ($ipv6) {
                    continue;
                }
                if ($mask > 32 or $mask < 0) {
                    // nonsense
                    continue;
                }
                if ($mask == 0) {
                    return true;
                }
                if ($mask == 32) {
                    if ($ip === $addr) {
                        return true;
                    }
                    continue;
                }
                $mask = 0xffffffff << (32 - $mask);
                if (((ip2long($addr) & $mask) == (ip2long($ip) & $mask))) {
                    return true;
                }
            }

        } else if (strpos($subnet, '-') !== false)  {
            /// 2: xxx.xxx.xxx.xxx-yyy or  xxxx:xxxx:xxxx:xxxx:xxxx:xxxx:xxxx::xxxx-yyyy ...a range of IP addresses in the last group.
            $parts = explode('-', $subnet);
            if (count($parts) != 2) {
                continue;
            }

            if (strpos($subnet, ':') !== false) {
                // IPv6
                if (!$ipv6) {
                    continue;
                }
                // normalise
                $ipstart = cleanremoteaddr(trim($parts[0]), false);
                if ($ipstart === null) {
                    continue;
                }
                $ipparts = explode(':', $ipstart);
                $start = hexdec(array_pop($ipparts));
                $ipparts[] = trim($parts[1]);
                // normalise
                $ipend = cleanremoteaddr(implode(':', $ipparts), false);
                if ($ipend === null) {
                    continue;
                }
                $ipparts[7] = '';
                $ipnet = implode(':', $ipparts);
                if (strpos($addr, $ipnet) !== 0) {
                    continue;
                }
                $ipparts = explode(':', $ipend);
                $end = hexdec($ipparts[7]);

                $addrend = hexdec($addrparts[7]);

                if (($addrend >= $start) and ($addrend <= $end)) {
                    return true;
                }

            } else {
                // IPv4
                if ($ipv6) {
                    continue;
                }
                // normalise
                $ipstart = cleanremoteaddr(trim($parts[0]), false);
                if ($ipstart === null) {
                    continue;
                }
                $ipparts = explode('.', $ipstart);
                $ipparts[3] = trim($parts[1]);
                // normalise
                $ipend = cleanremoteaddr(implode('.', $ipparts), false);
                if ($ipend === null) {
                    continue;
                }

                if ((ip2long($addr) >= ip2long($ipstart)) and (ip2long($addr) <= ip2long($ipend))) {
                    return true;
                }
            }

        } else {
            /// 3: xxx.xxx or xxx.xxx. or xxx:xxx:xxxx or xxx:xxx:xxxx.
            if (strpos($subnet, ':') !== false) {
                // IPv6
                if (!$ipv6) {
                    continue;
                }
                $parts = explode(':', $subnet);
                $count = count($parts);
                if ($parts[$count-1] === '') {
                    // trim trailing :
                    unset($parts[$count-1]);
                    $count--;
                    $subnet = implode('.', $parts);
                }
                // normalise
                $isip = cleanremoteaddr($subnet, false);
                if ($isip !== null) {
                    if ($isip === $addr) {
                        return true;
                    }
                    continue;
                } else if ($count > 8) {
                    continue;
                }
                $zeros = array_fill(0, 8-$count, '0');
                $subnet = $subnet . ':' . implode(':', $zeros) . '/' . ($count*16);
                if (address_in_subnet($addr, $subnet)) {
                    return true;
                }

            } else {
                // IPv4
                if ($ipv6) {
                    continue;
                }
                $parts = explode('.', $subnet);
                $count = count($parts);
                if ($parts[$count-1] === '') {
                    // trim trailing .
                    unset($parts[$count-1]);
                    $count--;
                    $subnet = implode('.', $parts);
                }
                if ($count == 4) {
                    // normalise
                    $subnet = cleanremoteaddr($subnet, false);
                    if ($subnet === $addr) {
                        return true;
                    }
                    continue;
                } else if ($count > 4) {
                    continue;
                }
                $zeros = array_fill(0, 4-$count, '0');
                $subnet = $subnet . '.' . implode('.', $zeros) . '/' . ($count*8);
                if (address_in_subnet($addr, $subnet)) {
                    return true;
                }
            }
        }
    }

    return false;
}

/**
 * Return true if given value is integer or string with integer value
 *
 * @param mixed $value String or Int
 * @return bool true if number, false if not
 */
function is_number($value) {
    if (is_int($value)) {
        return true;
    } else if (is_string($value)) {
        return ((string)(int)$value) === $value;
    } else {
        return false;
    }
}

/**
 * Is current ip in give list?
 *
 * @param string $list
 * @return bool
 */
function remoteip_in_list($list){
    $inlist = false;
    $client_ip = getremoteaddr(null);

    if(!$client_ip){
        // ensure access on cli
        return true;
    }

    $list = explode("\n", $list);
    foreach($list as $subnet) {
        $subnet = trim($subnet);
        if (address_in_subnet($client_ip, $subnet)) {
            $inlist = true;
            break;
        }
    }
    return $inlist;
}

/**
 * Returns most reliable client address
 *
 * @global object
 * @param string $default If an address can't be determined, then return this
 * @return string The remote IP address
 */
function getremoteaddr($default='0.0.0.0') {
    $getremoteaddrconf = get_config('getremoteaddrconf');
    if (empty($getremoteaddrconf)) {
        // This will happen, for example, before just after the upgrade, as the
        // user is redirected to the admin screen.
        $variablestoskip = 0;
    } else {
        $variablestoskip = get_config('getremoteaddrconf');
    }
    if (!($variablestoskip & GETREMOTEADDR_SKIP_HTTP_CLIENT_IP)) {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $address = cleanremoteaddr($_SERVER['HTTP_CLIENT_IP']);
            return $address ? $address : $default;
        }
    }
    if (!($variablestoskip & GETREMOTEADDR_SKIP_HTTP_X_FORWARDED_FOR)) {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $address = cleanremoteaddr($_SERVER['HTTP_X_FORWARDED_FOR']);
            return $address ? $address : $default;
        }
    }
    if (!empty($_SERVER['REMOTE_ADDR'])) {
        $address = cleanremoteaddr($_SERVER['REMOTE_ADDR']);
        return $address ? $address : $default;
    } else {
        return $default;
    }
}

/**
 * Cleans an ip address. Internal addresses are now allowed.
 * (Originally local addresses were not allowed.)
 *
 * @param string $addr IPv4 or IPv6 address
 * @param bool $compress use IPv6 address compression
 * @return string normalised ip address string, null if error
 */
function cleanremoteaddr($addr, $compress=false) {
    $addr = trim($addr);

    //TODO: maybe add a separate function is_addr_public() or something like this

    if (strpos($addr, ':') !== false) {
        // can be only IPv6
        $parts = explode(':', $addr);
        $count = count($parts);

        if (strpos($parts[$count-1], '.') !== false) {
            //legacy ipv4 notation
            $last = array_pop($parts);
            $ipv4 = cleanremoteaddr($last, true);
            if ($ipv4 === null) {
                return null;
            }
            $bits = explode('.', $ipv4);
            $parts[] = dechex($bits[0]).dechex($bits[1]);
            $parts[] = dechex($bits[2]).dechex($bits[3]);
            $count = count($parts);
            $addr = implode(':', $parts);
        }

        if ($count < 3 or $count > 8) {
            // severly malformed
            return null;
        }

        if ($count != 8) {
            if (strpos($addr, '::') === false) {
                // malformed
                return null;
            }
            // uncompress ::
            $insertat = array_search('', $parts, true);
            $missing = array_fill(0, 1 + 8 - $count, '0');
            array_splice($parts, $insertat, 1, $missing);
            foreach ($parts as $key=>$part) {
                if ($part === '') {
                    $parts[$key] = '0';
                }
            }
        }

        $adr = implode(':', $parts);
        if (!preg_match('/^([0-9a-f]{1,4})(:[0-9a-f]{1,4})*$/i', $adr)) {
            // incorrect format - sorry
            return null;
        }

        // normalise 0s and case
        $parts = array_map('hexdec', $parts);
        $parts = array_map('dechex', $parts);

        $result = implode(':', $parts);

        if (!$compress) {
            return $result;
        }

        if ($result === '0:0:0:0:0:0:0:0') {
            // all addresses
            return '::';
        }

        $compressed = preg_replace('/(:0)+:0$/', '::', $result, 1);
        if ($compressed !== $result) {
            return $compressed;
        }

        $compressed = preg_replace('/^(0:){2,7}/', '::', $result, 1);
        if ($compressed !== $result) {
            return $compressed;
        }

        $compressed = preg_replace('/(:0){2,6}:/', '::', $result, 1);
        if ($compressed !== $result) {
            return $compressed;
        }

        return $result;
    }

    // first get all things that look like IPv4 addresses
    $parts = array();
    if (!preg_match('/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/', $addr, $parts)) {
        return null;
    }
    unset($parts[0]);

    foreach ($parts as $key=>$match) {
        if ($match > 255) {
            return null;
        }
        // normalise 0s
        $parts[$key] = (int)$match;
    }

    return implode('.', $parts);
}

function webservice_generate_token($tokentype, $serviceorid, $userid, $institution = 'mahara',  $validuntil=0, $iprestriction=''){
    global $USER;
    // make sure the token doesn't exist (even if it should be almost impossible with the random generation)
    $numtries = 0;
    do {
        $numtries ++;
        $generatedtoken = md5(uniqid(rand(),1));
        if ($numtries > 5){
            throw new mahara_webservice_exception('tokengenerationfailed');
        }
    } while (record_exists('external_tokens', 'token', $generatedtoken));
    $newtoken = new stdClass();
    $newtoken->token = $generatedtoken;
    if (!is_object($serviceorid)){
        $service = get_record('external_services', 'id', $serviceorid);
    } else {
        $service = $serviceorid;
    }
    $newtoken->externalserviceid = $service->id;
    $newtoken->tokentype = $tokentype;
    $newtoken->userid = $userid;
    if ($tokentype == EXTERNAL_TOKEN_EMBEDDED){
        $newtoken->sid = session_id();
    }

    $newtoken->institution = $institution;
    $newtoken->creatorid = $USER->get('id');
    $newtoken->timecreated = time();
    $newtoken->publickeyexpires = time();
    $newtoken->wssigenc = 0;
    $newtoken->publickey = '';
    $newtoken->validuntil = $validuntil;
    if (!empty($iprestriction)) {
        $newtoken->iprestriction = $iprestriction;
    }
    insert_record('external_tokens', $newtoken);
    return $newtoken->token;
}

/**
 * Create and return a session linked token. Token to be used for html embedded client apps that want to communicate
 * with the Moodle server through web services. The token is linked to the current session for the current page request.
 * It is expected this will be called in the script generating the html page that is embedding the client app and that the
 * returned token will be somehow passed into the client app being embedded in the page.
 * @param string $servicename name of the web service. Service name as defined in db/services.php
 * @param int $context context within which the web service can operate.
 * @return int returns token id.
 */
function webservice_create_service_token($servicename, $userid, $institution = 'mahara',  $validuntil=0, $iprestriction=''){
    $service = get_record('external_services', 'name', $servicename, '*');
    return webservice_generate_token(EXTERNAL_TOKEN_EMBEDDED, $service, $userid, $institution,  $validuntil, $iprestriction);
}

/**
 * Returns detailed function information
 * @param string|object $function name of external function or record from external_function
 * @param int $strictness IGNORE_MISSING means compatible mode, false returned if record not found, debug message if more found;
 *                        MUST_EXIST means throw exception if no record or multiple records found
 * @return object description or false if not found or exception thrown
 */
function webservice_function_info($function, $strictness=MUST_EXIST) {
    if (!is_object($function)) {
        if (!$function = get_record('external_functions', 'name', $function, NULL, NULL, NULL, NULL, '*')) {
            return false;
        }
    }

    //first find and include the ext implementation class
    $function->classpath = empty($function->classpath) ? get_component_directory($function->component) : get_config('docroot')  .'/' . $function->classpath;
    if (!file_exists($function->classpath . '/externallib.php')) {
        throw new webservice_coding_exception('Can not find file with external function implementation');
    }
    require_once($function->classpath . '/externallib.php');

    $function->parameters_method = $function->methodname . '_parameters';
    $function->returns_method    = $function->methodname . '_returns';

    // make sure the implementaion class is ok
    if (!method_exists($function->classname, $function->methodname)) {
        throw new webservice_coding_exception('Missing implementation method of ' . $function->classname . '::' . $function->methodname);
    }
    if (!method_exists($function->classname, $function->parameters_method)) {
        throw new webservice_coding_exception('Missing parameters description');
    }
    if (!method_exists($function->classname, $function->returns_method)) {
        throw new webservice_coding_exception('Missing returned values description');
    }

    // fetch the parameters description
    $function->parameters_desc = call_user_func(array($function->classname, $function->parameters_method));
    if (!($function->parameters_desc instanceof external_function_parameters)) {
        throw new webservice_coding_exception('Invalid parameters description');
    }

    // fetch the return values description
    $function->returns_desc = call_user_func(array($function->classname, $function->returns_method));
    // null means void result or result is ignored
    if (!is_null($function->returns_desc) and !($function->returns_desc instanceof external_description)) {
        throw new webservice_coding_exception('Invalid return description');
    }

    //now get the function description
    //TODO: use localised lang pack descriptions, it would be nice to have
    //      easy to understand descriptions in admin UI,
    //      on the other hand this is still a bit in a flux and we need to find some new naming
    //      conventions for these descriptions in lang packs
    $function->description = null;

    $servicesfile = $function->classpath . '/services.php';

    if (file_exists($servicesfile)) {
        $functions = null;
        include($servicesfile);
        if (isset($functions[$function->name]['description'])) {
            $function->description = $functions[$function->name]['description'];
        }
    }

    return $function;
}

/**
 * General web service library
 */
class webservice {

    /**
     * Add a user to the list of authorised user of a given service
     * @param object $user
     */
    public function add_ws_authorised_user($user) {
        $user->timecreated = mktime();
        insert_record('external_services_users', $user);
    }

    /**
     * Remove a user from a list of allowed user of a service
     * @param object $user
     * @param int $serviceid
     */
    public function remove_ws_authorised_user($user, $serviceid) {
        delete_records('external_services_users',
                'externalserviceid', $serviceid, 'userid', $user->id);
    }

    /**
     * Update service allowed user settings
     * @param object $user
     */
    public function update_ws_authorised_user($user) {
        update_record('external_services_users', $user);
    }

    /**
     * Return list of allowed users with their options (ip/timecreated / validuntil...)
     * for a given service
     * @param int $serviceid
     * @return array $users
     */
    public function get_ws_authorised_users($serviceid) {
        $sql = " SELECT u.id as id, esu.id as serviceuserid, u.email as email, u.firstname as firstname,
                        u.lastname as lastname,
                        esu.iprestriction as iprestriction, esu.validuntil as validuntil,
                        esu.timecreated as timecreated
                   FROM {usr} u, {external_services_users} esu
                  WHERE u.id <> " . get_config('siteguest') . " AND u.deleted = 0 AND u.confirmed = 1
                        AND esu.userid = u.id
                        AND esu.externalserviceid = " . $serviceid;

        $users = get_records_sql($sql);
        return $users;
    }

    /**
     * Return a authorised user with his options (ip/timecreated / validuntil...)
     * @param int $serviceid
     * @param int $userid
     * @return object
     */
    public function get_ws_authorised_user($serviceid, $userid) {
        $sql = " SELECT u.id as id, esu.id as serviceuserid, u.email as email, u.firstname as firstname,
                        u.lastname as lastname,
                        esu.iprestriction as iprestriction, esu.validuntil as validuntil,
                        esu.timecreated as timecreated
                   FROM {usr} u, {external_services_users} esu
                  WHERE u.id <> " . get_config('siteguest') . " AND u.deleted = 0 AND u.confirmed = 1
                        AND esu.userid = u.id
                        AND esu.externalserviceid = " . $serviceid . "
                        AND u.id = " . $userid;
        $user = get_record_sql($sql, $params);
        return $user;
    }

    /**
     * Generate all ws token needed by a user
     * @param int $userid
     */
    public function generate_user_ws_tokens($userid) {
       /// generate a token for non admin if web service are enable and the user has the capability to create a token
       /// for every service than the user is authorised on, create a token (if it doesn't already exist)

        ///get all services which are set to all user (no restricted to specific users)
        $norestrictedservices = get_records('external_services', 'restrictedusers', 0);
        $serviceidlist = array();
        foreach ($norestrictedservices as $service) {
            $serviceidlist[] = $service->id;
        }

        //get all services which are set to the current user (the current user is specified in the restricted user list)
        $servicesusers = get_records('external_services_users', 'userid', $userid);
        foreach ($servicesusers as $serviceuser) {
            if (!in_array($serviceuser->externalserviceid,$serviceidlist)) {
                 $serviceidlist[] = $serviceuser->externalserviceid;
            }
        }

        //get all services which already have a token set for the current user
        $usertokens = get_records('external_tokens', 'userid', $userid, 'tokentype', EXTERNAL_TOKEN_PERMANENT);
        $tokenizedservice = array();
        foreach ($usertokens as $token) {
                $tokenizedservice[]  = $token->externalserviceid;
        }

        //create a token for the service which have no token already
        foreach ($serviceidlist as $serviceid) {
            if (!in_array($serviceid, $tokenizedservice)) {
                //create the token for this service
                $newtoken = new stdClass();
                $newtoken->token = md5(uniqid(rand(),1));
                //check that the user has capability on this service
                $newtoken->tokentype = EXTERNAL_TOKEN_PERMANENT;
                $newtoken->userid = $userid;
                $newtoken->externalserviceid = $serviceid;
                $newtoken->creatorid = $userid;
                $newtoken->timecreated = time();
                insert_record('external_tokens', $newtoken);
            }
        }
    }

    /**
     * Return all ws user token
     * @param integer $userid
     * @return array of token
     */
    public function get_user_ws_tokens($userid) {
        //here retrieve token list (including linked users firstname/lastname and linked services name)
        $sql = "SELECT
                    t.id, t.creatorid, t.token, u.firstname, u.lastname, s.name, t.validuntil
                FROM
                    {external_tokens} t, {usr} u, {external_services} s
                WHERE
                    t.userid=" . $userid . " AND t.tokentype = " . EXTERNAL_TOKEN_PERMANENT . " AND s.id = t.externalserviceid AND t.userid = u.id";
        $tokens = get_records_sql($sql);
        return $tokens;
    }

    /**
     * Return a user token that has been created by the user
     * If doesn't exist a exception is thrown
     * @param integer $userid
     * @param integer $tokenid
     * @return object token
     * ->id token id
     * ->token
     * ->firstname user firstname
     * ->lastname
     * ->name service name
     */
    public function get_created_by_user_ws_token($userid, $tokenid) {
        $sql = "SELECT
                        t.id, t.token, u.firstname, u.lastname, s.name
                    FROM
                        {external_tokens} t, {usr} u, {external_services} s
                    WHERE
                        t.creatorid=" . $userid . " AND t.id=" . $tokenid . " AND t.tokentype = "
                . EXTERNAL_TOKEN_PERMANENT
                . " AND s.id = t.externalserviceid AND t.userid = u.id";
        //must be the token creator
        $token = get_record_sql($sql);
        return $token;
    }

    /**
     * Return a token for a given id
     * @param integer $tokenid
     * @return object token
     */
    public function get_token_by_id($tokenid) {
        return get_record('external_tokens', 'id', $tokenid);
    }

    /**
     * Delete a user token
     * @param int $tokenid
     */
    public function delete_user_ws_token($tokenid) {
        delete_records('external_tokens', 'id', $tokenid);
    }

    /**
     * Delete a service - it also delete the functions and users references to this service
     * @param int $serviceid
     */
    public function delete_service($serviceid) {
        delete_records('external_services_users', 'externalserviceid', $serviceid);
        delete_records('external_services_functions', 'externalserviceid', $serviceid);
        delete_records('external_tokens', 'externalserviceid', $serviceid);
        delete_records('external_services', 'id', $serviceid);
    }

    /**
     * Get a user token by token
     * @param string $token
     * @throws mahara_webservice_exception if there is multiple result
     */
    public function get_user_ws_token($token) {
        return get_record('external_tokens', 'token', $token);
    }

    /**
     * Get the list of all functions for given service ids
     * @param array $serviceids
     * @return array functions
     */
    public function get_external_functions($serviceids) {
        global $WS_FUNCTIONS;

        if (!empty($serviceids)) {
            $where = (count($serviceids) == 1 ? ' = '.array_shift($serviceids) : ' IN (' . implode(',', $serviceids) . ')');
            $sql = "SELECT f.*
                      FROM {external_functions} f
                     WHERE f.name IN (SELECT sf.functionname
                                        FROM {external_services_functions} sf
                                       WHERE sf.externalserviceid $where)";
            $functions = get_records_sql_array($sql, array());
        } else {
            $functions = array();
        }

        // stash functions for intro spective RPC calls later
        $WS_FUNCTIONS = array();
        foreach ($functions as $function) {
            $WS_FUNCTIONS[$function->name] = array('id' => $function->id);
        }

        return $functions;
    }

    /**
     * Get the list of all functions not in the given service id
     * @param int $serviceid
     * @return array functions
     */
    public function get_not_associated_external_functions($serviceid) {
        $select = "name NOT IN (SELECT s.functionname
                                  FROM {external_services_functions} s
                                 WHERE s.externalserviceid = " . $serviceid . "
                               )";

        $functions = get_records_select('external_functions',
                        $select);

        return $functions;
    }

    /**
     * Get a external service for a given id
     * @param service id $serviceid
     * @param integer $strictness IGNORE_MISSING, MUST_EXIST...
     * @return object external service
     */
    public function get_external_service_by_id($serviceid, $strictness=IGNORE_MISSING) {
        $service = get_record('external_services',
                        'id', $serviceid);
        return $service;
    }

    /**
     * Get a external function for a given id
     * @param function id $functionid
     * @param integer $strictness IGNORE_MISSING, MUST_EXIST...
     * @return object external function
     */
    public function get_external_function_by_id($functionid, $strictness=IGNORE_MISSING) {
        $function = get_record('external_functions',
                            'id', $functionid);
        return $function;
    }

    /**
     * Add a function to a service
     * @param string $functionname
     * @param integer $serviceid
     */
    public function add_external_function_to_service($functionname, $serviceid) {
        $addedfunction = new stdClass();
        $addedfunction->externalserviceid = $serviceid;
        $addedfunction->functionname = $functionname;
        insert_record('external_services_functions', $addedfunction);
    }

    /**
     * Add a service
     * @param object $service
     * @return serviceid integer
     */
    public function add_external_service($service) {
        $service->timecreated = mktime();
        $serviceid = insert_record('external_services', $service);
        return $serviceid;
    }

     /**
     * Update a service
     * @param object $service
     */
    public function update_external_service($service) {
        $service->timemodified = mktime();
        update_record('external_services', $service);
    }

    /**
     * Test whether a external function is already linked to a service
     * @param string $functionname
     * @param integer $serviceid
     * @return bool true if a matching function exists for the service, else false.
     * @throws dml_exception if error
     */
    public function service_function_exists($functionname, $serviceid) {
        return record_exists('external_services_functions',
                            'externalserviceid', $serviceid,
                                'functionname', $functionname);
    }

    public function remove_external_function_from_service($functionname, $serviceid) {
        delete_records('external_services_functions',
                    'externalserviceid', $serviceid, 'functionname', $functionname);

    }
}

/**
 * Base Mahara WS Exception class
 *
 * Although this class is defined here, you cannot throw a mahara_webservice_exception until
 * after moodlelib.php has been included (which will happen very soon).
 *
 * @package    core
 * @subpackage lib
 * @copyright  2008 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mahara_webservice_exception extends Exception {
    public $errorcode;
    public $module;
    public $a;
    public $link;
    public $debuginfo;

    /**
     * Constructor
     * @param string $errorcode The name of the string from error.php to print
     * @param string $module name of module
     * @param string $link The url where the user will be prompted to continue. If no url is provided the user will be directed to the site index page.
     * @param object $a Extra words and phrases that might be required in the error string
     * @param string $debuginfo optional debugging information
     */
    function __construct($errorcode, $module='', $link='', $a=NULL, $debuginfo=null) {
        if (empty($module) || $module == 'mahara' || $module == 'core') {
            $module = 'error';
        }

        $this->errorcode = $errorcode;
        $this->module    = $module;
        $this->link      = $link;
        $this->a         = $a;
        $this->debuginfo = $debuginfo;
        $message = get_string($errorcode, $module, $a) . $debuginfo;
        parent::__construct($message, 0);
    }
}

/**
 * Exception indicating access control problem in web service call
 * @author Petr Skoda (skodak)
 */
class webservice_access_exception extends mahara_webservice_exception {
    /**
     * Constructor
     */
    function __construct($debuginfo) {
        parent::__construct('accessexception', 'artefact.webservice', '', null, $debuginfo);
    }
}

/**
 * Base class for external api methods.
 */
class external_api {
    private static $contextrestriction;

    /**
     * Set context restriction for all following subsequent function calls.
     * @param stdClass $contex
     * @return void
     */
    public static function set_context_restriction($context) {
        self::$contextrestriction = $context;
    }

    /**
     * This method has to be called before every operation
     * that takes a longer time to finish!
     *
     * @param int $seconds max expected time the next operation needs
     * @return void
     */
    public static function set_timeout($seconds=360) {
        $seconds = ($seconds < 300) ? 300 : $seconds;
        set_time_limit($seconds);
    }

    /**
     * Validates submitted function parameters, if anything is incorrect
     * invalid_parameter_exception is thrown.
     * This is a simple recursive method which is intended to be called from
     * each implementation method of external API.
     * @param external_description $description description of parameters
     * @param mixed $params the actual parameters
     * @return mixed params with added defaults for optional items, invalid_parameters_exception thrown if any problem found
     */
    public static function validate_parameters(external_description $description, $params) {
        if ($description instanceof external_value) {
            if (is_array($params) or is_object($params)) {
                throw new invalid_parameter_exception(get_string('errorscalartype', 'artefact.webservice'));
            }

            if ($description->type == PARAM_BOOL) {
                // special case for PARAM_BOOL - we want true/false instead of the usual 1/0 - we can not be too strict here ;-)
                if (is_bool($params) or $params === 0 or $params === 1 or $params === '0' or $params === '1') {
                    return (bool)$params;
                }
            }
            return validate_param($params, $description->type, $description->allownull, get_string('errorinvalidparamsapi', 'artefact.webservice'));

        } else if ($description instanceof external_single_structure) {
            if (!is_array($params)) {
                throw new invalid_parameter_exception(get_string('erroronlyarray', 'artefact.webservice'));
            }
            $result = array();
            foreach ($description->keys as $key=>$subdesc) {
                if (!array_key_exists($key, $params)) {
                    if ($subdesc->required == VALUE_REQUIRED) {
                        throw new invalid_parameter_exception(get_string('errormissingkey', 'artefact.webservice', $key));
                    }
                    if ($subdesc->required == VALUE_DEFAULT) {
                        try {
                            $result[$key] = self::validate_parameters($subdesc, $subdesc->default);
                        } catch (invalid_parameter_exception $e) {
                            throw new webservice_parameter_exception('invalidextparam', $key);
                        }
                    }
                } else {
                    try {
                        $result[$key] = self::validate_parameters($subdesc, $params[$key]);
                    } catch (invalid_parameter_exception $e) {
                        //it's ok to display debug info as here the information is useful for ws client/dev
                        throw new webservice_parameter_exception('invalidextparam',"key: $key (debuginfo: " . $e->debuginfo.") ");
                    }
                }
                unset($params[$key]);
            }
            if (!empty($params)) {
                //list all unexpected keys
                $keys = '';
                foreach($params as $key => $value) {
                    $keys .= $key . ',';
                }
                throw new invalid_parameter_exception(get_string('errorunexpectedkey', 'artefact.webservice', $keys));
            }
            return $result;

        } else if ($description instanceof external_multiple_structure) {
            if (!is_array($params)) {
                throw new invalid_parameter_exception(get_string('erroronlyarray', 'artefact.webservice'));
            }
            $result = array();
            foreach ($params as $param) {
                $result[] = self::validate_parameters($description->content, $param);
            }
            return $result;

        } else {
            throw new invalid_parameter_exception(get_string('errorinvalidparamsdesc', 'artefact.webservice'));
        }
    }

    /**
     * Clean response
     * If a response attribute is unknown from the description, we just ignore the attribute.
     * If a response attribute is incorrect, invalid_response_exception is thrown.
     * Note: this function is similar to validate parameters, however it is distinct because
     * parameters validation must be distinct from cleaning return values.
     * @param external_description $description description of the return values
     * @param mixed $response the actual response
     * @return mixed response with added defaults for optional items, invalid_response_exception thrown if any problem found
     */
    public static function clean_returnvalue(external_description $description, $response) {
        if ($description instanceof external_value) {
            if (is_array($response) or is_object($response)) {
                throw new invalid_response_exception(get_string('errorscalartype', 'artefact.webservice'));
            }

            if ($description->type == PARAM_BOOL) {
                // special case for PARAM_BOOL - we want true/false instead of the usual 1/0 - we can not be too strict here ;-)
                if (is_bool($response) or $response === 0 or $response === 1 or $response === '0' or $response === '1') {
                    return (bool)$response;
                }
            }
            return validate_param($response, $description->type, $description->allownull, get_string('errorinvalidresponseapi', 'artefact.webservice'));

        } else if ($description instanceof external_single_structure) {
            if (!is_array($response)) {
                throw new invalid_response_exception(get_string('erroronlyarray', 'artefact.webservice'));
            }
            $result = array();
            foreach ($description->keys as $key=>$subdesc) {
                if (!array_key_exists($key, $response)) {
                    if ($subdesc->required == VALUE_REQUIRED) {
                        throw new webservice_parameter_exception('errorresponsemissingkey', $key);
                    }
                    if ($subdesc instanceof external_value) {
                        if ($subdesc->required == VALUE_DEFAULT) {
                            try {
                                $result[$key] = self::clean_returnvalue($subdesc, $subdesc->default);
                            } catch (Exception $e) {
                                throw new webservice_parameter_exception('invalidextresponse',$key . " (" . $e->debuginfo . ")");
                            }
                        }
                    }
                } else {
                    try {
                        $result[$key] = self::clean_returnvalue($subdesc, $response[$key]);
                    } catch (Exception $e) {
                        //it's ok to display debug info as here the information is useful for ws client/dev
                        throw new webservice_parameter_exception('invalidextresponse', $key . " (" . $e->debuginfo . ")");
                    }
                }
                unset($response[$key]);
            }

            return $result;

        } else if ($description instanceof external_multiple_structure) {
            if (!is_array($response)) {
                throw new invalid_response_exception(get_string('erroronlyarray', 'artefact.webservice'));
            }
            $result = array();
            foreach ($response as $param) {
                $result[] = self::clean_returnvalue($description->content, $param);
            }
            return $result;

        } else {
            throw new invalid_response_exception(get_string('errorinvalidresponsedesc', 'artefact.webservice'));
        }
    }
}

/**
 * Common ancestor of all parameter description classes
 */
abstract class external_description {
    /** @property string $description description of element */
    public $desc;
    /** @property bool $required element value required, null not allowed */
    public $required;
    /** @property mixed $default default value */
    public $default;

    /**
     * Contructor
     * @param string $desc
     * @param bool $required
     * @param mixed $default
     */
    public function __construct($desc, $required, $default) {
        $this->desc = $desc;
        $this->required = $required;
        $this->default = $default;
    }
}

/**
 * Scalar alue description class
 */
class external_value extends external_description {
    /** @property mixed $type value type PARAM_XX */
    public $type;
    /** @property bool $allownull allow null values */
    public $allownull;

    /**
     * Constructor
     * @param mixed $type
     * @param string $desc
     * @param bool $required
     * @param mixed $default
     * @param bool $allownull
     */
    public function __construct($type, $desc='', $required=VALUE_REQUIRED,
    $default=null, $allownull=NULL_ALLOWED) {
        parent::__construct($desc, $required, $default);
        $this->type      = $type;
        $this->allownull = $allownull;
    }
}

/**
 * Associative array description class
 */
class external_single_structure extends external_description {
    /** @property array $keys description of array keys key=>external_description */
    public $keys;

    /**
     * Constructor
     * @param array $keys
     * @param string $desc
     * @param bool $required
     * @param array $default
     */
    public function __construct(array $keys, $desc='',
    $required=VALUE_REQUIRED, $default=null) {
        parent::__construct($desc, $required, $default);
        $this->keys = $keys;
    }
}

/**
 * Bulk array description class.
 */
class external_multiple_structure extends external_description {
    /** @property external_description $content */
    public $content;

    /**
     * Constructor
     * @param external_description $content
     * @param string $desc
     * @param bool $required
     * @param array $default
     */
    public function __construct(external_description $content, $desc='',
    $required=VALUE_REQUIRED, $default=null) {
        parent::__construct($desc, $required, $default);
        $this->content = $content;
    }
}

/**
 * Description of top level - PHP function parameters.
 * @author skodak
 *
 */
class external_function_parameters extends external_single_structure {
}

/**
 * Web service parameter exception class
 *
 * This exception must be thrown to the web service client when a web service parameter is invalid
 * The error string is gotten from webservice.php
 */
class webservice_parameter_exception extends mahara_webservice_exception {
    /**
     * Constructor
     * @param string $errorcode The name of the string from webservice.php to print
     * @param string $a The name of the parameter
     */
    function __construct($errorcode=null, $debuginfo = '') {
        parent::__construct($errorcode, 'artefact.webservice', '', '', $debuginfo);
    }
}

/**
 * Exception indicating programming error, must be fixed by a programer. For example
 * a core API might throw this type of exception if a plugin calls it incorrectly.
 *
 * @package    core
 * @subpackage lib
 * @copyright  2008 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class webservice_coding_exception extends mahara_webservice_exception {
    /**
     * Constructor
     * @param string $hint short description of problem
     * @param string $debuginfo detailed information how to fix problem
     */
    function __construct($hint, $debuginfo=null) {
        parent::__construct('codingerror', 'debug', '', $hint, $debuginfo);
    }
}

/**
 * Exception indicating malformed parameter problem.
 * This exception is not supposed to be thrown when processing
 * user submitted data in forms. It is more suitable
 * for WS and other low level stuff.
 *
 * @package    core
 * @subpackage lib
 * @copyright  2009 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class invalid_parameter_exception extends mahara_webservice_exception {
    /**
     * Constructor
     * @param string $debuginfo some detailed information
     */
    function __construct($debuginfo=null) {
        parent::__construct('invalidparameter', 'debug', '', null, $debuginfo);
    }
}

/**
 * Exception indicating malformed response problem.
 * This exception is not supposed to be thrown when processing
 * user submitted data in forms. It is more suitable
 * for WS and other low level stuff.
 */
class invalid_response_exception extends mahara_webservice_exception {
    /**
     * Constructor
     * @param string $debuginfo some detailed information
     */
    function __construct($debuginfo=null) {
        parent::__construct('invalidresponse', 'debug', '', null, $debuginfo);
    }
}

/**
 * An exception that indicates something really weird happened. For example,
 * if you do switch ($context->contextlevel), and have one case for each
 * CONTEXT_... constant. You might throw an invalid_state_exception in the
 * default case, to just in case something really weird is going on, and
 * $context->contextlevel is invalid - rather than ignoring this possibility.
 *
 * @package    core
 * @subpackage lib
 * @copyright  2009 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class invalid_state_exception extends mahara_webservice_exception {
    /**
     * Constructor
     * @param string $hint short description of problem
     * @param string $debuginfo optional more detailed information
     */
    function __construct($hint, $debuginfo=null) {
        parent::__construct('invalidstatedetected', 'debug', '', $hint, $debuginfo);
    }
}

/**
 * Is protocol enabled?
 * @param string $protocol name of WS protocol
 * @return bool
 */
function webservice_protocol_is_enabled($protocol) {
    if (!get_config_plugin('artefact', 'webservice', 'enabled')) {
        return false;
    }
    $plugin = get_record('artefact_installed', 'name', 'webservice');
    if (empty($plugin) || $plugin->active != 1) {
        return false;
    }

    return get_config_plugin('artefact', 'webservice', $protocol . '_enabled');
}

//=== WS classes ===

/**
 * Mandatory interface for all test client classes.
 * @author Petr Skoda (skodak)
 */
interface webservice_test_client_interface {
    /**
     * Execute test client WS request
     * @param string $serverurl
     * @param string $function
     * @param array $params
     * @return mixed
     */
    public function simpletest($serverurl, $function, $params);
}

/**
 * Mandatory interface for all web service protocol classes
 * @author Petr Skoda (skodak)
 */
interface webservice_server_interface {
    /**
     * Process request from client.
     * @return void
     */
    public function run();
}

/**
 * Abstract web service base class.
 * @author Petr Skoda (skodak)
 */
abstract class webservice_server implements webservice_server_interface {

    /** @property string $wsname name of the web server plugin */
    protected $wsname = null;

    /** @property string $username name of local user */
    protected $username = null;

    /** @property string $password password of the local user */
    protected $password = null;

    /** @property string $service service for wsdl look up */
    protected $service = null;

    /** @property int $userid the local user */
    protected $userid = null;

    /** @property integer $authmethod authentication method one of WEBSERVICE_AUTHMETHOD_* */
    protected $authmethod;

    /** @property string $token authentication token*/
    protected $token = null;

    /** @property int restrict call to one service id*/
    protected $restricted_serviceid = null;

    /** @property string info to add to logging*/
    protected $info = null;
    /**
     * Contructor
     * @param integer $authmethod authentication method one of WEBSERVICE_AUTHMETHOD_*
     */
    public function __construct($authmethod) {
        $this->authmethod = $authmethod;
    }


    /**
     * Authenticate user using username+password or token.
     * This function sets up $USER global.
     * It is safe to use has_capability() after this.
     * This method also verifies user is allowed to use this
     * server.
     * @return void
     */
    protected function authenticate_user() {
        global $USER, $SESSION, $WEBSERVICE_INSTITUTION, $WEBSERVICE_OAUTH_USER;

        if ($this->authmethod == WEBSERVICE_AUTHMETHOD_USERNAME) {
            $this->auth = 'USER';
            //we check that authentication plugin is enabled
            //it is only required by simple authentication
            $plugin = get_record('auth_installed', 'name', 'webservice');
            if (empty($plugin) || $plugin->active != 1) {
                throw new webservice_access_exception(get_string('wsauthnotenabled', 'artefact.webservice'));
            }

            if (!$this->username) {
                throw new webservice_access_exception(get_string('missingusername', 'artefact.webservice'));
            }

            if (!$this->password) {
                throw new webservice_access_exception(get_string('missingpassword', 'artefact.webservice'));
            }

            // special web service login
            require_once(get_config('docroot') . '/auth/webservice/lib.php');

            // get the user
            $user = get_record('usr', 'username', $this->username);
            if (empty($user)) {
                throw new webservice_access_exception(get_string('wrongusernamepassword', 'artefact.webservice'));
            }

            // user account is nolonger validly configured
            if (!$auth_instance = webservice_validate_user($user)) {
                throw new webservice_access_exception(get_string('invalidaccount', 'artefact.webservice'));
            }
            // set the global for the web service users defined institution
            $WEBSERVICE_INSTITUTION = $auth_instance->institution;

            // get the institution from the external user
            $ext_user = get_record('external_services_users', 'userid', $user->id);
            if (empty($ext_user)) {
                throw new webservice_access_exception(get_string('wrongusernamepassword', 'artefact.webservice'));
            }
            // determine the internal auth instance
            $auth_instance = get_record('auth_instance', 'institution', $ext_user->institution, 'authname', 'webservice');
            if (empty($auth_instance)) {
                throw new webservice_access_exception(get_string('wrongusernamepassword', 'artefact.webservice'));
            }

            // authenticate the user
            $auth = new AuthWebservice($auth_instance->id);
            if (!$auth->authenticate_user_account($user, $this->password, 'webservice')) {
                // log failed login attempts
                ws_add_to_log('webservice', get_string('simpleauthlog', 'artefact.webservice'), '' , get_string('failedtolog', 'artefact.webservice') . ": " . $this->username . "/" . $this->password . " - " . getremoteaddr() , 0);
                throw new webservice_access_exception(get_string('wrongusernamepassword', 'artefact.webservice'));
            }

        }
        else if ($this->authmethod == WEBSERVICE_AUTHMETHOD_PERMANENT_TOKEN){
            $this->auth = 'TOKEN';
            $user = $this->authenticate_by_token(EXTERNAL_TOKEN_PERMANENT);
        }
        else if ($this->authmethod == WEBSERVICE_AUTHMETHOD_OAUTH_TOKEN){
            //OAuth
            $this->auth = 'OAUTH';
            // special web service login
            require_once(get_config('docroot') . '/auth/webservice/lib.php');

            // get the user - the user that authorised the token
            $user = get_record('usr', 'id', $this->oauth_token_details['user_id']);
            if (empty($user)) {
                throw new webservice_access_exception(get_string('wrongusernamepassword', 'artefact.webservice'));
            }
            // check user is member of configured OAuth institution
            $institutions = array_keys(load_user_institutions($this->oauth_token_details['user_id']));
            $auth_instance = get_record('auth_instance', 'id', $user->authinstance);
            $institutions[]= $auth_instance->institution;
            if (!in_array($this->oauth_token_details['institution'], $institutions)) {
                throw new webservice_access_exception(get_string('institutiondenied', 'artefact.webservice'));
            }

            // set the global for the web service users defined institution
            $WEBSERVICE_INSTITUTION = $this->oauth_token_details['institution'];
            // set the note of the OAuth service owner
            $WEBSERVICE_OAUTH_USER = $this->oauth_token_details['service_user'];
        } else {
            $this->auth = 'OTHER';
            $user = $this->authenticate_by_token(EXTERNAL_TOKEN_USER);
        }

        // now fake user login, the session is completely empty too
        $USER->reanimate($user->id, $user->authinstance);
    }

    protected function authenticate_by_token($tokentype){
        global $WEBSERVICE_INSTITUTION;

        if ($tokentype == EXTERNAL_TOKEN_PERMANENT || $tokentype == EXTERNAL_TOKEN_USER) {
            $token = get_record('external_tokens', 'token', $this->token);
            // trap personal tokens with no valid until time set
            if ($token && $token->tokentype == EXTERNAL_TOKEN_USER && $token->validuntil == 0 && (($token->timecreated - time()) > EXTERNAL_TOKEN_USER_EXPIRES)) {
                delete_records('external_tokens', 'token', $this->token);
                throw new webservice_access_exception(get_string('invalidtimedtoken', 'artefact.webservice'));
            }
        }
        else {
            $token = get_record('external_tokens', 'token', $this->token, 'tokentype', $tokentype);
        }
        if (!$token) {
            // log failed login attempts
            ws_add_to_log('webservice', get_string('tokenauthlog', 'artefact.webservice'), '' . $tokentype , get_string('failedtolog', 'artefact.webservice') . ": " . $this->token . " - " . getremoteaddr() , 0);
            throw new webservice_access_exception(get_string('invalidtoken', 'artefact.webservice'));
        }
        // tidy up the uath method - this could be user token or session token
        if ($token->tokentype != EXTERNAL_TOKEN_PERMANENT) {
            $this->auth = 'OTHER';
        }

        /**
         * check the valid until date
         */
        if ($token->validuntil and $token->validuntil < time()) {
            delete_records('external_tokens', 'token', $this->token, 'tokentype', $tokentype);
            throw new webservice_access_exception(get_string('invalidtimedtoken', 'artefact.webservice'));
        }

        //assumes that if sid is set then there must be a valid associated session no matter the token type
        if ($token->sid){
            $session = session_get_instance();
            if (!$session->session_exists($token->sid)){
                delete_records('external_tokens', 'sid', $token->sid);
                throw new webservice_access_exception(get_string('invalidtokensession', 'artefact.webservice'));
            }
        }

        if ($token->iprestriction and !address_in_subnet(getremoteaddr(), $token->iprestriction)) {
            ws_add_to_log('webservice', get_string('tokenauthlog', 'artefact.webservice'), '' , get_string('failedtolog', 'artefact.webservice') . ": " . getremoteaddr() , 0);
            throw new webservice_access_exception(get_string('invalidiptoken', 'artefact.webservice'));
        }

        $this->restricted_serviceid = $token->externalserviceid;

        $user = get_record('usr', 'id', $token->userid, 'deleted', 0);

        // log token access
        set_field('external_tokens', 'lastaccess', time(), 'id', $token->id);

        // set the global for the web service users defined institution
        $WEBSERVICE_INSTITUTION = $token->institution;

        return $user;
    }
}

/**
 * Special abstraction of our srvices that allows
 * interaction with stock Zend ws servers.
 * @author Petr Skoda (skodak)
 */
abstract class webservice_zend_server extends webservice_server {

    /** @property string name of the zend server class : Zend_XmlRpc_Server, Zend_Soap_Server, Zend_Soap_AutoDiscover, ...*/
    protected $zend_class;

    /** @property object Zend server instance */
    protected $zend_server;

    /** @property string $service_class virtual web service class with all functions user name execute, created on the fly */
    protected $service_class;

    /** @property string $functionname the name of the function that is executed */
    protected $functionname = null;

    /**
     * Contructor
     * @param integer $authmethod authentication method - one of WEBSERVICE_AUTHMETHOD_*
     */
    public function __construct($authmethod, $zend_class) {
        parent::__construct($authmethod);
        $this->zend_class = $zend_class;
    }

    /**
     * Process request from client.
     * @param bool $simple use simple authentication
     * @return void
     */
    public function run() {
        global $WEBSERVICE_FUNCTION_RUN, $USER, $WEBSERVICE_INSTITUTION, $WEBSERVICE_START;
        $WEBSERVICE_START = microtime(true);

        // we will probably need a lot of memory in some functions
        raise_memory_limit(MEMORY_EXTRA);

        // set some longer timeout, this script is not sending any output,
        // this means we need to manually extend the timeout operations
        // that need longer time to finish
        external_api::set_timeout();

        // now create the instance of zend server
        $this->init_zend_server();

        // set up exception handler first, we want to sent them back in correct format that
        // the other system understands
        // we do not need to call the original default handler because this ws handler does everything
        set_exception_handler(array($this, 'exception_handler'));

        // init all properties from the request data
        $this->parse_request();

        // process wsdl only, and without a user
        $xml = null;
        if ($this->service && isset($_REQUEST['wsdl'])) {
            $dbservice = get_record('external_services', 'name', $this->service);
            if (empty($dbservice)) {
                // throw error
                throw new webservice_access_exception(get_string('invalidservice', 'artefact.webservice'));
            }
            $serviceids = array($dbservice->id);
            $this->load_services($serviceids);
        }
        else {
            // Manipulate the payload if necessary
            $xml = $this->modify_payload();

            // this sets up $USER and $SESSION and context restrictions
            $this->authenticate_user();
        }

        // make a list of all functions user is allowed to excecute
        $this->init_service_class();

        // tell server what functions are available
        $this->zend_server->setClass($this->service_class);

        // set additional functions
        $this->fixup_functions();

        //send headers
        $this->send_headers();

        // execute and return response, this sends some headers too
        $response = $this->zend_server->handle($xml);
        // store the info of the error
        if (is_object($response) && get_class($response) == 'Zend_XmlRpc_Server_Fault') {
            $ex = $response->getException();
            $this->info = 'exception: ' . get_class($ex) . ' message: ' . $ex->getMessage() . ' debuginfo: ' . (isset($ex->debuginfo) ? $ex->debuginfo : '');
        }

        // session cleanup
        $this->session_cleanup();

        // allready all done if we were doing wsdl
        if (param_variable('wsdl', 0)) {
            die;
        }

        // modify the result
        $response = $this->modify_result($response);

        $time_end = microtime(true);
        $time_taken = $time_end - $WEBSERVICE_START;

        //log the web service request
        if (!isset($_REQUEST['wsdl']) && !empty($WEBSERVICE_FUNCTION_RUN)) {
            $class = get_class($this);
            if (preg_match('/soap/', $class)) {
                $class = 'SOAP';
            }
            else if (preg_match('/xmlrpc/', $class)) {
                $class = 'XML-RPC';
            }
            $log = (object)  array('timelogged' => time(),
                                   'userid' => $USER->get('id'),
                                   'externalserviceid' => $this->restricted_serviceid,
                                   'institution' => $WEBSERVICE_INSTITUTION,
                                   'protocol' => $class,
                                   'auth' => $this->auth,
                                   'functionname' => $WEBSERVICE_FUNCTION_RUN,
                                   'timetaken' => "" . $time_taken,
                                   'uri' => $_SERVER['REQUEST_URI'],
                                   'info' => ($this->info ? $this->info : ''),
                                   'ip' => getremoteaddr());
            ws_add_to_log('webservice', $WEBSERVICE_FUNCTION_RUN, '', getremoteaddr() , 0, $this->userid);
            insert_record('external_services_logs', $log, 'id', true);
        }
        else {
            // this is WSDL or methodsignature for XML-RPC
        }

        //finally send the result
        // force the content length as this was going wrong
        header('Content-Length: ' . strlen($response));
        echo $response;
        flush();
        die;
    }

    /**
     * Chance for each protocol to modify the function processing list
     *
     */
    protected function fixup_functions() {

        return null;
    }

    /**
     * Chance for each protocol to modify the incoming
     * raw payload - eg: SOAP and auth headers
     *
     * @return content
     */
    protected function modify_payload() {

        return null;
    }

    /**
     * Chance for each protocol to modify the out going
     * raw payload - eg: SOAP encryption and signatures
     *
     * @param string $response The raw response value
     *
     * @return content
     */
    protected function modify_result($response) {

        return $response;
    }

    /**
     * Load virtual class needed for Zend api
     * @return void
     */
    protected function init_service_class() {
        global $USER;

        // first ofall get a complete list of services user is allowed to access

        if ($this->restricted_serviceid) {
            // FIXME
            $params = array('sid1'=>$this->restricted_serviceid, 'sid2'=>$this->restricted_serviceid);
            $wscond1 = 'AND s.id = ' . $this->restricted_serviceid;
            $wscond2 = 'AND s.id = ' . $this->restricted_serviceid;
        } else {
            $params = array();
            $wscond1 = '';
            $wscond2 = '';
        }

        // now make sure the function is listed in at least one service user is allowed to use
        // allow access only if:
        //  1/ entry in the external_services_users table if required
        //  2/ validuntil not reached
        //  3/ has capability if specified in service desc
        //  4/ iprestriction

        $sql = "SELECT s.*, NULL AS iprestriction
                  FROM {external_services} s
                  JOIN {external_services_functions} sf ON (sf.externalserviceid = s.id AND s.restrictedusers = 0)
                 WHERE s.enabled = 1 $wscond1

                 UNION

                SELECT s.*, su.iprestriction
                  FROM {external_services} s
                  JOIN {external_services_functions} sf ON (sf.externalserviceid = s.id AND s.restrictedusers = 1)
                  JOIN {external_services_users} su ON (su.externalserviceid = s.id AND su.userid = " . $USER->get('id') . ")
                 WHERE s.enabled = 1 AND su.validuntil IS NULL OR su.validuntil < " . time() . " $wscond2";

        $params = array_merge($params);

        $serviceids = array();
        $rs = get_recordset_sql($sql);

        // now make sure user may access at least one service
        $remoteaddr = getremoteaddr();
        $allowed = false;
        foreach ($rs as $service) {
            // FIXME - had to cast to object
            $service = (object)$service;
            if (isset($serviceids[$service->id])) {
                continue;
            }
            if ($service->iprestriction and !address_in_subnet($remoteaddr, $service->iprestriction)) {
                // wrong request source ip, sorry
                continue;
            }
            $serviceids[$service->id] = $service->id;
        }
        $rs->close();

        $this->load_services($serviceids);
    }

    /**
     * load service function definitions for service discovery and exectution
     *
     * @param array $serviceids
     */
    protected function load_services($serviceids) {
        global $USER;

        // now get the list of all functions
        $wsmanager = new webservice();
        $functions = $wsmanager->get_external_functions($serviceids);

        // now make the virtual WS class with all the fuctions for this particular user
        $methods = '';
        foreach ($functions as $function) {
            $methods .= $this->get_virtual_method_code($function);
        }

        // let's use unique class name, there might be problem in unit tests
        $classname = 'webservices_virtual_class_000000';
        while(class_exists($classname)) {
            $classname++;
        }

        $code = '
/**
 * Virtual class web services for user id ' . $USER->get('id') . '
 */
class ' . $classname . ' {
' . $methods . '

    public function Header ($data) {
        return true;
    }

    public function Security ($data) {
        //error_log("username: " . $data->UsernameToken->Username);
        //error_log("password: " . $data->UsernameToken->Password);
        //throw new webservice_access_exception(get_string("accessnotallowed", "artefact.webservice"));
        return true;
    }
}
';

        // load the virtual class definition into memory
        eval($code);
        $this->service_class = $classname;
    }

    /**
     * returns virtual method code
     * @param object $function
     * @return string PHP code
     */
    protected function get_virtual_method_code($function) {
        $function = webservice_function_info($function);

        //arguments in function declaration line with defaults.
        $paramanddefaults      = array();
        //arguments used as parameters for external lib call.
        $params      = array();
        $params_desc = array();
        foreach ($function->parameters_desc->keys as $name=>$keydesc) {
            $param = '$' . $name;
            $paramanddefault = $param;
            //need to generate the default if there is any
            if ($keydesc instanceof external_value) {
                if ($keydesc->required == VALUE_DEFAULT) {
                    if ($keydesc->default===null) {
                        $paramanddefault .= '=null';
                    } else {
                        switch($keydesc->type) {
                            case PARAM_BOOL:
                                $paramanddefault .= '=' . $keydesc->default; break;
                            case PARAM_INT:
                                $paramanddefault .= '=' . $keydesc->default; break;
                            case PARAM_FLOAT;
                                $paramanddefault .= '=' . $keydesc->default; break;
                            default:
                                $paramanddefault .= '=\'' . $keydesc->default . '\'';
                        }
                    }
                } else if ($keydesc->required == VALUE_OPTIONAL) {
                    //it does make sens to declare a parameter VALUE_OPTIONAL
                    //VALUE_OPTIONAL is used only for array/object key
                    throw new mahara_webservice_exception('parametercannotbevalueoptional');
                }
            //for the moment we do not support default for other structure types
            } else {
                 if ($keydesc->required == VALUE_DEFAULT) {
                     //accept empty array as default
                     if (isset($keydesc->default) and is_array($keydesc->default)
                             and empty($keydesc->default)) {
                         $paramanddefault .= '=array()';
                     } else {
                        throw new mahara_webservice_exception('errornotemptydefaultparamarray', 'artefact.webservice', '', $name);
                     }
                 }
                 if ($keydesc->required == VALUE_OPTIONAL) {
                     throw new mahara_webservice_exception('erroroptionalparamarray', 'artefact.webservice', '', $name);
                 }
            }
            $params[] = $param;
            $paramanddefaults[] = $paramanddefault;
            $type = $this->get_phpdoc_type($keydesc);
            $params_desc[] = '     * @param ' . $type . ' $' . $name . ' ' . $keydesc->desc;
        }
        $params                = implode(', ', $params);
        $paramanddefaults      = implode(', ', $paramanddefaults);
        $params_desc           = implode("\n", $params_desc);

        $serviceclassmethodbody = $this->service_class_method_body($function, $params);

        if (is_null($function->returns_desc)) {
            $return = '     * @return void';
        } else {
            $type = $this->get_phpdoc_type($function->returns_desc);
            $return = '     * @return ' . $type . ' ' . $function->returns_desc->desc;
        }

        // now crate the virtual method that calls the ext implementation

        $code = '
    /**
     * ' . $function->description . '
     *
' . $params_desc . '
' . $return . '
     */
    public function ' . $function->name . '(' . $paramanddefaults . ') {
        global $WEBSERVICE_FUNCTION_RUN;
        $WEBSERVICE_FUNCTION_RUN = \'' . $function->name . '\';
' . $serviceclassmethodbody . '
    }
';
        return $code;
    }

    protected function get_phpdoc_type($keydesc) {
        if ($keydesc instanceof external_value) {
            switch($keydesc->type) {
                // 0 or 1 only for now
                case PARAM_BOOL:
                case PARAM_INT:
                    $type = 'int'; break;
                case PARAM_FLOAT;
                    $type = 'double'; break;
                default:
                    $type = 'string';
            }

        } else if ($keydesc instanceof external_single_structure) {
            $classname = $this->generate_simple_struct_class($keydesc);
            $type = $classname;

        } else if ($keydesc instanceof external_multiple_structure) {
            $type = 'array';
        }

        return $type;
    }

    protected function generate_simple_struct_class(external_single_structure $structdesc) {
        //only 'object' is supported by SOAP, 'struct' by XML-RPC MDL-23083
        return 'object|struct';
    }

    /**
     * You can override this function in your child class to add extra code into the dynamically
     * created service class. For example it is used in the amf server to cast types of parameters and to
     * cast the return value to the types as specified in the return value description.
     * @param stdClass $function
     * @param array $params
     * @return string body of the method for $function ie. everything within the {} of the method declaration.
     */
    protected function service_class_method_body($function, $params){
        //cast the param from object to array (validate_parameters except array only)
        $castingcode = '';
        if ($params){
            $paramstocast = explode(',', $params);
            foreach ($paramstocast as $paramtocast) {
                //clean the parameter from any white space
                $paramtocast = trim($paramtocast);
                $castingcode .= $paramtocast .
                '=webservice_zend_server::cast_objects_to_array(' . $paramtocast . ');';
            }

        }

        $descriptionmethod = $function->methodname . '_returns()';
        $callforreturnvaluedesc = $function->classname . '::' . $descriptionmethod;
        return $castingcode . '    if (' . $callforreturnvaluedesc . ' == null)  {' .
                        $function->classname . '::' . $function->methodname . '(' . $params . ');
                        return null;
                    }
                    return external_api::clean_returnvalue(' . $callforreturnvaluedesc . ', ' . $function->classname . '::' . $function->methodname . '(' . $params . '));';
    }

    /**
     * Recursive function to recurse down into a complex variable and convert all
     * objects to arrays.
     * @param mixed $param value to cast
     * @return mixed Cast value
     */
    public static function cast_objects_to_array($param){
        if (is_object($param)){
            $param = (array)$param;
        }
        if (is_array($param)){
            $toreturn = array();
            foreach ($param as $key=> $param){
                $toreturn[$key] = self::cast_objects_to_array($param);
            }
            return $toreturn;
        } else {
            return $param;
        }
    }

    /**
     * Set up zend service class
     * @return void
     */
    protected function init_zend_server() {
        $this->zend_server = new $this->zend_class();
    }

    /**
     * This method parses the $_REQUEST superglobal and looks for
     * the following information:
     *  1/ user authentication - username+password or token (wsusername, wspassword and wstoken parameters)
     *
     * @return void
     */
    protected function parse_request() {
        if ($this->authmethod == WEBSERVICE_AUTHMETHOD_USERNAME) {
            //note: some clients have problems with entity encoding :-(
            if (isset($_REQUEST['wsusername'])) {
                $this->username = $_REQUEST['wsusername'];
            }
            if (isset($_REQUEST['wspassword'])) {
                $this->password = $_REQUEST['wspassword'];
            }
            if (isset($_REQUEST['wsservice'])) {
                $this->service = $_REQUEST['wsservice'];
            }
        } else {
            if (isset($_REQUEST['wstoken'])) {
                $this->token = $_REQUEST['wstoken'];
            }
        }
    }

    /**
     * Internal implementation - sending of page headers.
     * @return void
     */
    protected function send_headers() {
        header('Cache-Control: private, must-revalidate, pre-check=0, post-check=0, max-age=0');
        header('Expires: ' . gmdate('D, d M Y H:i:s', 0) . ' GMT');
        header('Pragma: no-cache');
        header('Accept-Ranges: none');
    }

    /**
     * Specialised exception handler, we can not use the standard one because
     * it can not just print html to output.
     *
     * @param exception $ex
     * @return void does not return
     */
    public function exception_handler($ex) {
        // detect active db transactions, rollback and log as error
        db_rollback();

        // some hacks might need a cleanup hook
        $this->session_cleanup($ex);

        // now let the plugin send the exception to client
        $this->send_error($ex);

        // not much else we can do now, add some logging later
        exit(1);
    }

    /**
     * Send the error information to the WS client
     * formatted as XML document.
     * @param exception $ex
     * @return void
     */
    protected function send_error($ex=null) {
        $this->send_headers();
        echo $this->zend_server->fault($ex);
    }

    /**
     * Future hook needed for emulated sessions.
     * @param exception $exception null means normal termination, $exception received when WS call failed
     * @return void
     */
    protected function session_cleanup($exception=null) {
        if ($this->authmethod == WEBSERVICE_AUTHMETHOD_USERNAME) {
            // nothing needs to be done, there is no persistent session
        } else {
            // close emulated session if used
        }
    }

}

/**
 * Web Service server base class, this class handles both
 * simple and token authentication.
 * @author Petr Skoda (skodak)
 */
abstract class webservice_base_server extends webservice_server {

    /** @property array $parameters the function parameters - the real values submitted in the request */
    protected $parameters = null;

    /** @property string $functionname the name of the function that is executed */
    protected $functionname = null;

    /** @property object $function full function description */
    protected $function = null;

    /** @property mixed $returns function return value */
    protected $returns = null;

    /**
     * This method parses the request input, it needs to get:
     *  1/ user authentication - username+password or token
     *  2/ function name
     *  3/ function parameters
     *
     * @return void
     */
    abstract protected function parse_request();

    /**
     * Send the result of function call to the WS client.
     * @return void
     */
    abstract protected function send_response();

    /**
     * Send the error information to the WS client.
     * @param exception $ex
     * @return void
     */
    abstract protected function send_error($ex=null);

    /**
     * Process request from client.
     * @return void
     */
    public function run() {
        global $WEBSERVICE_FUNCTION_RUN, $USER, $WEBSERVICE_INSTITUTION, $WEBSERVICE_START;

        $WEBSERVICE_START = microtime(true);

        // we will probably need a lot of memory in some functions
        raise_memory_limit(MEMORY_EXTRA);

        // set some longer timeout, this script is not sending any output,
        // this means we need to manually extend the timeout operations
        // that need longer time to finish
        external_api::set_timeout();

        // set up exception handler first, we want to sent them back in correct format that
        // the other system understands
        // we do not need to call the original default handler because this ws handler does everything
        set_exception_handler(array($this, 'exception_handler'));

        // init all properties from the request data
        $this->parse_request();

        // authenticate user, this has to be done after the request parsing
        // this also sets up $USER and $SESSION
        $this->authenticate_user();

        // find all needed function info and make sure user may actually execute the function
        $this->load_function_info();

        // finally, execute the function - any errors are catched by the default exception handler
        $this->execute();

        $time_end = microtime(true);
        $time_taken = $time_end - $WEBSERVICE_START;

        //log the web service request
        $log = (object)  array('timelogged' => time(),
                               'userid' => $USER->get('id'),
                               'externalserviceid' => $this->restricted_serviceid,
                               'institution' => $WEBSERVICE_INSTITUTION,
                               'protocol' => 'REST',
                               'auth' => $this->auth,
                               'functionname' => $this->functionname,
                               'timetaken' => "" . $time_taken,
                               'uri' => $_SERVER['REQUEST_URI'],
                               'info' => '',
                               'ip' => getremoteaddr());
        insert_record('external_services_logs', $log, 'id', true);

        // send the results back in correct format
        $this->send_response();

        // session cleanup
        $this->session_cleanup();

        die;
    }

    /**
     * Specialised exception handler, we can not use the standard one because
     * it can not just print html to output.
     *
     * @param exception $ex
     * @return void does not return
     */
    public function exception_handler($ex) {
        global $WEBSERVICE_FUNCTION_RUN, $USER, $WEBSERVICE_INSTITUTION, $WEBSERVICE_START;

        // detect active db transactions, rollback and log as error
        db_rollback();

        $time_end = microtime(true);
        $time_taken = $time_end - $WEBSERVICE_START;

        //log the error on the web service request
        $log = (object)  array('timelogged' => time(),
                               'userid' => $USER->get('id'),
                               'externalserviceid' => $this->restricted_serviceid,
                               'institution' => $WEBSERVICE_INSTITUTION,
                               'protocol' => 'REST',
                               'auth' => $this->auth,
                               'functionname' => ($WEBSERVICE_FUNCTION_RUN ? $WEBSERVICE_FUNCTION_RUN : $this->functionname),
                               'timetaken' => '' . $time_taken,
                               'uri' => $_SERVER['REQUEST_URI'],
                               'info' => 'exception: ' . get_class($ex) . ' message: ' . $ex->getMessage() . ' debuginfo: ' . (isset($ex->debuginfo) ? $ex->debuginfo : ''),
                               'ip' => getremoteaddr());
        insert_record('external_services_logs', $log, 'id', true);

        // some hacks might need a cleanup hook
        $this->session_cleanup($ex);

        // now let the plugin send the exception to client
        $this->send_error($ex);

        // not much else we can do now, add some logging later
        exit(1);
    }

    /**
     * Future hook needed for emulated sessions.
     * @param exception $exception null means normal termination, $exception received when WS call failed
     * @return void
     */
    protected function session_cleanup($exception=null) {
        if ($this->authmethod == WEBSERVICE_AUTHMETHOD_USERNAME) {
            // nothing needs to be done, there is no persistent session
        } else {
            // close emulated session if used
        }
    }

    /**
     * Fetches the function description from database,
     * verifies user is allowed to use this function and
     * loads all paremeters and return descriptions.
     * @return void
     */
    protected function load_function_info() {
        global $USER;

        if (empty($this->functionname)) {
            throw new invalid_parameter_exception('Missing function name');
        }

        // function must exist
        $function = webservice_function_info($this->functionname);
        if (!$function) {
            throw new webservice_access_exception('Access to external function not configured');
        }

        if ($this->restricted_serviceid) {
            $wscond1 = 'AND s.id = ' . $this->restricted_serviceid;
            $wscond2 = 'AND s.id = ' . $this->restricted_serviceid;
        } else {
            $params = array();
            $wscond1 = '';
            $wscond2 = '';
        }

        // now let's verify access control

        // now make sure the function is listed in at least one service user is allowed to use
        // allow access only if:
        //  1/ entry in the external_services_users table if required
        //  2/ validuntil not reached
        //  3/ has capability if specified in service desc
        //  4/ iprestriction

        $sql = "SELECT s.*, NULL AS iprestriction
                  FROM {external_services} s
                  JOIN {external_services_functions} sf ON (sf.externalserviceid = s.id AND (s.restrictedusers = 0 OR s.tokenusers = 1) AND sf.functionname = '" . addslashes($function->name) . "')
                 WHERE s.enabled = 1 $wscond1

                 UNION

                SELECT s.*, su.iprestriction
                  FROM {external_services} s
                  JOIN {external_services_functions} sf ON (sf.externalserviceid = s.id AND s.restrictedusers = 1 AND sf.functionname = '" . addslashes($function->name) . "')
                  JOIN {external_services_users} su ON (su.externalserviceid = s.id AND su.userid = " . $USER->get('id') . ")
                 WHERE s.enabled = 1 AND su.validuntil IS NULL OR su.validuntil < " . time() . " $wscond2";

        $rs = get_recordset_sql($sql);
        // now make sure user may access at least one service
        $remoteaddr = getremoteaddr();
        $allowed = false;
        $serviceids = array();
        foreach ($rs as $service) {
            $serviceids[]= $service['id'];
            if ($service['iprestriction'] and !address_in_subnet($remoteaddr, $service['iprestriction'])) {
                // wrong request source ip, sorry
                continue;
            }
            $allowed = true;
            // one service is enough, no need to continue
            break;
        }
        $rs->close();
        if (!$allowed) {
            throw new webservice_access_exception(get_string('accesstofunctionnotallowed', 'artefact.webservice', $this->functionname));
        }
        // now get the list of all functions - this triggers the stashing of
        // functions in the context
        $wsmanager = new webservice();
        $functions = $wsmanager->get_external_functions($serviceids);

        // we have all we need now
        $this->function = $function;
    }

    /**
     * Execute previously loaded function using parameters parsed from the request data.
     * @return void
     */
    protected function execute() {
        // validate params, this also sorts the params properly, we need the correct order in the next part
        $params = call_user_func(array($this->function->classname, 'validate_parameters'), $this->function->parameters_desc, $this->parameters);

        // execute - yay!
        $this->returns = call_user_func_array(array($this->function->classname, $this->function->methodname), array_values($params));
    }
}
