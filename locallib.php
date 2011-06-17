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
$path = get_config('docroot').'artefact/webservice/libs/zend';
set_include_path($path . PATH_SEPARATOR . get_include_path());



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
 * validate the user for webservices access
 * the account must use the webservice auth plugin
 * the account must have membership for the selected auth_instance
 *
 * @param object $dbuser
 */
function webservices_validate_user($dbuser) {
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


/** // FIXME
 * Constructs IN() or = sql fragment
 * @param mixed $items single or array of values
 * @param int $type bound param type SQL_PARAMS_QM or SQL_PARAMS_NAMED
 * @param string named param placeholder start
 * @param bool true means equal, false not equal
 * @return array - $sql and $params
 */
function get_in_or_equal($items, $type=SQL_PARAMS_QM, $start='param0000', $equal=true) {
    if (is_array($items) and empty($items)) {
        throw new coding_exception('get_in_or_equal() does not accept empty arrays');
    }
    if (count($items) == 1) {
        return array('= '.array_shift($items), NULL);
    }
    else {
        $parms = ' IN ('.implode(',', $items).')';
        return array($parms, NULL);
    }
}



require_once(get_config('docroot').'/artefact/webservice/libs/externallib.php');
require_once(get_config('docroot').'/artefact/webservice/libs/moodlelib.php');
require_once(get_config('docroot').'/artefact/webservice/libs/weblib.php');

define('WEBSERVICE_AUTHMETHOD_USERNAME', 0);
define('WEBSERVICE_AUTHMETHOD_PERMANENT_TOKEN', 1);
define('WEBSERVICE_AUTHMETHOD_SESSION_TOKEN', 2);

/// Debug levels ///
/** no warnings at all */
define('DEBUG_NONE', 0);
/** E_ERROR | E_PARSE */
define('DEBUG_MINIMAL', 5);
/** E_ERROR | E_PARSE | E_WARNING | E_NOTICE */
define('DEBUG_NORMAL', 15);
/** E_ALL without E_STRICT for now, do show recoverable fatal errors */
define('DEBUG_ALL', 6143);
///** DEBUG_ALL with extra Moodle debug messages - (DEBUG_ALL | 32768) */
//define('DEBUG_DEVELOPER', 38911);

/** Remove any memory limits */
define('MEMORY_UNLIMITED', -1);
/** Standard memory limit for given platform */
define('MEMORY_STANDARD', -2);
/**
 * Large memory limit for given platform - used in cron, upgrade, and other places that need a lot of memory.
 * Can be overridden with $CFG->extramemorylimit setting.
 */
define('MEMORY_EXTRA', -3);
/** Extremely large memory limit - not recommended for standard scripts */
define('MEMORY_HUGE', -4);

/**
 * Extracts file argument either from file parameter or PATH_INFO
 * Note: $scriptname parameter is not needed anymore
 *
 * @global string
 * @uses $_SERVER
 * @uses PARAM_PATH
 * @return string file path (only safe characters)
 */
function get_file_argument() {
    global $SCRIPT;

    $relativepath = optional_param('file', FALSE, PARAM_PATH);

    if ($relativepath !== false and $relativepath !== '') {
        return $relativepath;
    }
    $relativepath = false;

    // then try extract file from the slasharguments
    if (stripos($_SERVER['SERVER_SOFTWARE'], 'iis') !== false) {
        // NOTE: ISS tends to convert all file paths to single byte DOS encoding,
        //       we can not use other methods because they break unicode chars,
        //       the only way is to use URL rewriting
        if (isset($_SERVER['PATH_INFO']) and $_SERVER['PATH_INFO'] !== '') {
            // check that PATH_INFO works == must not contain the script name
            if (strpos($_SERVER['PATH_INFO'], $SCRIPT) === false) {
                $relativepath = clean_param(urldecode($_SERVER['PATH_INFO']), PARAM_PATH);
            }
        }
    } else {
        // all other apache-like servers depend on PATH_INFO
        if (isset($_SERVER['PATH_INFO'])) {
            if (isset($_SERVER['SCRIPT_NAME']) and strpos($_SERVER['PATH_INFO'], $_SERVER['SCRIPT_NAME']) === 0) {
                $relativepath = substr($_SERVER['PATH_INFO'], strlen($_SERVER['SCRIPT_NAME']));
            } else {
                $relativepath = $_SERVER['PATH_INFO'];
            }
            $relativepath = clean_param($relativepath, PARAM_PATH);
        }
    }


    return $relativepath;
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
        global $CFG;
        $sql = " SELECT u.id as id, esu.id as serviceuserid, u.email as email, u.firstname as firstname,
                        u.lastname as lastname,
                        esu.iprestriction as iprestriction, esu.validuntil as validuntil,
                        esu.timecreated as timecreated
                   FROM {usr} u, {external_services_users} esu
                  WHERE u.id <> ".$CFG->siteguest." AND u.deleted = 0 AND u.confirmed = 1
                        AND esu.userid = u.id
                        AND esu.externalserviceid = ".$serviceid;

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
        global $CFG;
        $sql = " SELECT u.id as id, esu.id as serviceuserid, u.email as email, u.firstname as firstname,
                        u.lastname as lastname,
                        esu.iprestriction as iprestriction, esu.validuntil as validuntil,
                        esu.timecreated as timecreated
                   FROM {usr} u, {external_services_users} esu
                  WHERE u.id <> ".$CFG->siteguest." AND u.deleted = 0 AND u.confirmed = 1
                        AND esu.userid = u.id
                        AND esu.externalserviceid = ".$serviceid."
                        AND u.id = ".$userid;
        $user = get_record_sql($sql, $params);
        return $user;
    }

    /**
     * Generate all ws token needed by a user
     * @param int $userid
     */
    public function generate_user_ws_tokens($userid) {
        global $CFG;

//        /// generate a token for non admin if web service are enable and the user has the capability to create a token
//        /// for every service than the user is authorised on, create a token (if it doesn't already exist)

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
                    t.userid=".$userid." AND t.tokentype = ".EXTERNAL_TOKEN_PERMANENT." AND s.id = t.externalserviceid AND t.userid = u.id";
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
                        t.creatorid=".$userid." AND t.id=".$tokenid." AND t.tokentype = "
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
     * @throws mahara_ws_exception if there is multiple result
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
        if (!empty($serviceids)) {
            list($serviceids, $params) = get_in_or_equal($serviceids);
            $sql = "SELECT f.*
                      FROM {external_functions} f
                     WHERE f.name IN (SELECT sf.functionname
                                        FROM {external_services_functions} sf
                                       WHERE sf.externalserviceid $serviceids)";
            $functions = get_records_sql_array($sql, array());
        } else {
            $functions = array();
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
                                 WHERE s.externalserviceid = ".$serviceid."
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
 * Exception indicating access control problem in web service call
 * @author Petr Skoda (skodak)
 */
class webservice_access_exception extends mahara_ws_exception {
    /**
     * Constructor
     */
    function __construct($debuginfo) {
        parent::__construct('accessexception', 'artefact.webservice', '', null, $debuginfo);
    }
}

/**
 * Is protocol enabled?
 * @param string $protocol name of WS protocol
 * @return bool
 */
function webservice_protocol_is_enabled($protocol) {
    global $CFG;

    if (!get_config_plugin('artefact', 'webservice', 'enabled')) {
        return false;
    }
    $plugin = get_record('artefact_installed', 'name', 'webservice');
    if (empty($plugin) || $plugin->active != 1) {
        return false;
    }

    return get_config_plugin('artefact', 'webservice', $protocol.'_enabled');
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
        global $CFG, $USER, $SESSION, $WEBSERVICE_INSTITUTION;

        if ($this->authmethod == WEBSERVICE_AUTHMETHOD_USERNAME) {

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
            require_once(get_config('docroot')."/auth/webservice/lib.php");

            // get the user
            $user = get_record('usr', 'username', $this->username);
//            error_log('user is: '.var_export($user, true));
            if (empty($user)) {
                throw new webservice_access_exception(get_string('wrongusernamepassword', 'artefact.webservice'));
            }

            // user account is nolonger validly configured
            if (!$auth_instance = webservices_validate_user($user)) {
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
                ws_add_to_log(0, 'webservice', get_string('simpleauthlog', 'artefact.webservice'), '' , get_string('failedtolog', 'artefact.webservice').": ".$this->username."/".$this->password." - ".getremoteaddr() , 0);
                throw new webservice_access_exception(get_string('wrongusernamepassword', 'artefact.webservice'));
            }

        } else if ($this->authmethod == WEBSERVICE_AUTHMETHOD_PERMANENT_TOKEN){
            $user = $this->authenticate_by_token(EXTERNAL_TOKEN_PERMANENT);
        } else {
            $user = $this->authenticate_by_token(EXTERNAL_TOKEN_EMBEDDED);
        }

        // now fake user login, the session is completely empty too
        //session_set_user($user); // FIXME - this is where you login with token user and check authorisation
        // Note: All copied from LiveUser::authenticate
        // this is to construct the user as per normal logged in session
        $defaults = array(
            'logout_time'      => 0,
            'id'               => 0,
            'username'         => '',
            'password'         => '',
            'salt'             => '',
            'passwordchange'   => 0,
            'active'           => 1,
            'deleted'          => 0,
            'expiry'           => null,
            'expirymailsent'   => 0,
            'lastlogin'        => null,
            'lastlastlogin'    => null,
            'lastaccess'       => null, /* Is not necessarily updated every request, see accesstimeupdatefrequency config variable */
            'inactivemailsent' => 0,
            'staff'            => 0,
            'admin'            => 0,
            'firstname'        => '',
            'lastname'         => '',
            'studentid'        => '',
            'preferredname'    => '',
            'email'            => '',
            'profileicon'      => null,
            'suspendedctime'   => null,
            'suspendedreason'  => null,
            'suspendedcusr'    => null,
            'quota'            => null,
//            'quotaused'        => 0,
            'authinstance'     => 1,
            'sessionid'        => '', /* The real session ID that PHP knows about */
            'accountprefs'     => array(),
            'activityprefs'    => array(),
            'institutions'     => array(),
            'grouproles'       => array(),
            'theme'            => null,
            'admininstitutions' => array(),
            'staffinstitutions' => array(),
            'parentuser'       => null,
            'loginanyway'       => false,
            'sesskey'          => '',
            'ctime'            => null,
            'views'            => array(),
            'showhomeinfo'     => 1
        );
        while(list($key, ) = each($defaults)) {
            if (property_exists($user, $key)) {
                $USER->__set($key, $user->{$key});
            }
        }
        $USER->id = $user->id; // FIXME - fingers crossed ...
        $USER->username = $user->username;
        $this->userid = $user->id;
        session_regenerate_id(true);
        $USER->lastlastlogin      = $USER->lastlogin;
        $USER->lastlogin          = time();
        $USER->lastaccess         = time();
        $USER->sessionid          = session_id();
        $USER->logout_time        = time() + get_config('session_timeout');
        $USER->sesskey            = get_random_key();

        // We need a user->id before we load_c*_preferences
        if (empty($user->id)) $USER->commit();
        $USER->activityprefs      = load_activity_preferences($user->id);
        $USER->accountprefs       = load_account_preferences($user->id);

        // If user has chosen a language while logged out, save it as their lang pref.
        $sessionlang = $SESSION->get('lang');
        if (!empty($sessionlang) && $sessionlang != 'default'
            && (empty($this->accountprefs['lang']) || $sessionlang != $USER->accountprefs['lang'])) {
            $USER->set_account_preference('lang', $sessionlang);
        }

        // Set language for the current request
        if (!empty($USER->accountprefs['lang'])) {
            current_language($USER->accountprefs['lang']);
        }

        $USER->reset_institutions();
        $USER->reset_grouproles();
        $sessionid = $USER->get('sessionid');
        delete_records('usr_session', 'session', $sessionid);
        insert_record('usr_session', (object) array(
            'usr' => $USER->get('id'),
            'session' => $sessionid,
            'ctime' => db_format_timestamp(time()),
        ));

        $USER->commit();
    }

    protected function authenticate_by_token($tokentype){
        global $WEBSERVICE_INSTITUTION;

        if (!$token = get_record('external_tokens', 'token', $this->token, 'tokentype', $tokentype)) {
            // log failed login attempts
            ws_add_to_log(0, 'webservice', get_string('tokenauthlog', 'artefact.webservice'), '' , get_string('failedtolog', 'artefact.webservice').": ".$this->token. " - ".getremoteaddr() , 0);
            throw new webservice_access_exception(get_string('invalidtoken', 'artefact.webservice'));
        }

        if ($token->validuntil and $token->validuntil < time()) {
            delete_records('external_tokens', 'token', $this->token, 'tokentype', $tokentype);
            throw new webservice_access_exception(get_string('invalidtimedtoken', 'artefact.webservice'));
        }

        if ($token->sid){//assumes that if sid is set then there must be a valid associated session no matter the token type
            $session = session_get_instance();
            if (!$session->session_exists($token->sid)){
                delete_records('external_tokens', 'sid', $token->sid);
                throw new webservice_access_exception(get_string('invalidtokensession', 'artefact.webservice'));
            }
        }

        if ($token->iprestriction and !address_in_subnet(getremoteaddr(), $token->iprestriction)) {
            ws_add_to_log(0, 'webservice', get_string('tokenauthlog', 'artefact.webservice'), '' , get_string('failedtolog', 'artefact.webservice').": ".getremoteaddr() , 0);
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
            // standard auth
            if (!isset($_REQUEST['wsusername']) && $this->authmethod == WEBSERVICE_AUTHMETHOD_USERNAME) {
                // wsse auth
                $xml = file_get_contents('php://input');
                $dom = new DOMDocument();
                if(strlen($xml) == 0 || !$dom->loadXML($xml)) {
                    require_once 'Zend/Soap/Server/Exception.php';
                    throw new Zend_Soap_Server_Exception('Invalid XML');
                }
                else {
                    // now hunt for the user and password from the headers
                    $xpath = new DOMXpath($dom);
                    $xpath->registerNamespace('wsse', 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd');
                    if ($q = $xpath->query("//wsse:Security/wsse:UsernameToken/wsse:Username/text()", $dom)) {
                        if ($q->item(0)) {
                            $this->username = (string) $q->item(0)->data;
                            $this->password = (string) $xpath->query("//wsse:Security/wsse:UsernameToken/wsse:Password/text()", $dom)->item(0)->data;
                            error_log('username/password is wsse: '.$this->username.'/'.$this->password);
                        }
                    }
                }
            }
            // this sets up $USER and $SESSION and context restrictions
            $this->authenticate_user();
        }

        // make a list of all functions user is allowed to excecute
        $this->init_service_class();

        // tell server what functions are available
        $this->zend_server->setClass($this->service_class);

        //log the web service request
        ws_add_to_log(0, 'webservice', '', '' , $this->zend_class." ".getremoteaddr() , 0, $this->userid);

        // execute and return response, this sends some headers too
        $response = $this->zend_server->handle($xml);

        // session cleanup
        $this->session_cleanup();

        // allready all done if we were doing wsdl
        if (optional_param('wsdl', 0, PARAM_BOOL)) {
            die;
        }

        //finally send the result
        $this->send_headers();
        echo $response;
        die;
    }

    /**
     * Load virtual class needed for Zend api
     * @return void
     */
    protected function init_service_class() {
        global $USER;

        // first ofall get a complete list of services user is allowed to access

        if ($this->restricted_serviceid) {
            $params = array('sid1'=>$this->restricted_serviceid, 'sid2'=>$this->restricted_serviceid); // FIXME
            $wscond1 = 'AND s.id = '.$this->restricted_serviceid;
            $wscond2 = 'AND s.id = '.$this->restricted_serviceid;
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
                  JOIN {external_services_users} su ON (su.externalserviceid = s.id AND su.userid = ".$USER->id.")
                 WHERE s.enabled = 1 AND su.validuntil IS NULL OR su.validuntil < ".time()." $wscond2";

        $params = array_merge($params);

        $serviceids = array();
        $rs = get_recordset_sql($sql);

        // now make sure user may access at least one service
        $remoteaddr = getremoteaddr();
        $allowed = false;
        foreach ($rs as $service) {
            $service = (object)$service; // FIXME - had to cast to object
            if (isset($serviceids[$service->id])) {
                continue;
            }
            if ($service->iprestriction and !address_in_subnet($remoteaddr, $service->iprestriction)) {
                continue; // wrong request source ip, sorry
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
 * Virtual class web services for user id '.$USER->id.'
 */
class '.$classname.' {
'.$methods.'

    public function Header ($data) {
        return true;
    }

    public function Security ($data) {
        //error_log("username: ".$data->UsernameToken->Username);
        //error_log("password: ".$data->UsernameToken->Password);
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
        global $CFG;

        $function = external_function_info($function);

        //arguments in function declaration line with defaults.
        $paramanddefaults      = array();
        //arguments used as parameters for external lib call.
        $params      = array();
        $params_desc = array();
        foreach ($function->parameters_desc->keys as $name=>$keydesc) {
            $param = '$'.$name;
            $paramanddefault = $param;
            //need to generate the default if there is any
            if ($keydesc instanceof external_value) {
                if ($keydesc->required == VALUE_DEFAULT) {
                    if ($keydesc->default===null) {
                        $paramanddefault .= '=null';
                    } else {
                        switch($keydesc->type) {
                            case PARAM_BOOL:
                                $paramanddefault .= '='.$keydesc->default; break;
                            case PARAM_INT:
                                $paramanddefault .= '='.$keydesc->default; break;
                            case PARAM_FLOAT;
                                $paramanddefault .= '='.$keydesc->default; break;
                            default:
                                $paramanddefault .= '=\''.$keydesc->default.'\'';
                        }
                    }
                } else if ($keydesc->required == VALUE_OPTIONAL) {
                    //it does make sens to declare a parameter VALUE_OPTIONAL
                    //VALUE_OPTIONAL is used only for array/object key
                    throw new mahara_ws_exception('parametercannotbevalueoptional');
                }
            } else { //for the moment we do not support default for other structure types
                 if ($keydesc->required == VALUE_DEFAULT) {
                     //accept empty array as default
                     if (isset($keydesc->default) and is_array($keydesc->default)
                             and empty($keydesc->default)) {
                         $paramanddefault .= '=array()';
                     } else {
                        throw new mahara_ws_exception('errornotemptydefaultparamarray', 'artefact.webservice', '', $name);
                     }
                 }
                 if ($keydesc->required == VALUE_OPTIONAL) {
                     throw new mahara_ws_exception('erroroptionalparamarray', 'artefact.webservice', '', $name);
                 }
            }
            $params[] = $param;
            $paramanddefaults[] = $paramanddefault;
            $type = 'string';
            if ($keydesc instanceof external_value) {
                switch($keydesc->type) {
                    case PARAM_BOOL: // 0 or 1 only for now
                    case PARAM_INT:
                        $type = 'int'; break;
                    case PARAM_FLOAT;
                        $type = 'double'; break;
                    default:
                        $type = 'string';
                }
            } else if ($keydesc instanceof external_single_structure) {
                $type = 'object|struct';
            } else if ($keydesc instanceof external_multiple_structure) {
                $type = 'array';
            }
            $params_desc[] = '     * @param '.$type.' $'.$name.' '.$keydesc->desc;
        }
        $params                = implode(', ', $params);
        $paramanddefaults      = implode(', ', $paramanddefaults);
        $params_desc           = implode("\n", $params_desc);

        $serviceclassmethodbody = $this->service_class_method_body($function, $params);

        if (is_null($function->returns_desc)) {
            $return = '     * @return void';
        } else {
            $type = 'string';
            if ($function->returns_desc instanceof external_value) {
                switch($function->returns_desc->type) {
                    case PARAM_BOOL: // 0 or 1 only for now
                    case PARAM_INT:
                        $type = 'int'; break;
                    case PARAM_FLOAT;
                        $type = 'double'; break;
                    default:
                        $type = 'string';
                }
            } else if ($function->returns_desc instanceof external_single_structure) {
                $type = 'object|struct'; //only 'object' is supported by SOAP, 'struct' by XML-RPC MDL-23083
            } else if ($function->returns_desc instanceof external_multiple_structure) {
                $type = 'array';
            }
            $return = '     * @return '.$type.' '.$function->returns_desc->desc;
        }

        // now crate the virtual method that calls the ext implementation

        $code = '
    /**
     * '.$function->description.'
     *
'.$params_desc.'
'.$return.'
     */
    public function '.$function->name.'('.$paramanddefaults.') {
'.$serviceclassmethodbody.'
    }
';
        return $code;
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
                '=webservice_zend_server::cast_objects_to_array('.$paramtocast.');';
            }

        }

        $descriptionmethod = $function->methodname.'_returns()';
        $callforreturnvaluedesc = $function->classname.'::'.$descriptionmethod;
        return $castingcode . '    if ('.$callforreturnvaluedesc.' == null)  {'.
                        $function->classname.'::'.$function->methodname.'('.$params.');
                        return null;
                    }
                    return external_api::clean_returnvalue('.$callforreturnvaluedesc.', '.$function->classname.'::'.$function->methodname.'('.$params.'));';
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
        header('Expires: '. gmdate('D, d M Y H:i:s', 0) .' GMT');
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

        //log the web service request
        ws_add_to_log(0, 'webservice', $this->functionname, '' , getremoteaddr() , 0, $this->userid);

        // finally, execute the function - any errors are catched by the default exception handler
        $this->execute();

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
        global $USER, $CFG;

        if (empty($this->functionname)) {
            throw new invalid_parameter_exception('Missing function name');
        }

        // function must exist
        $function = external_function_info($this->functionname);
        if (!$function) {
            throw new webservice_access_exception('Access to external function not configured');
        }

        if ($this->restricted_serviceid) {
            $wscond1 = 'AND s.id = '.$this->restricted_serviceid;
            $wscond2 = 'AND s.id = '.$this->restricted_serviceid;
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
                  JOIN {external_services_functions} sf ON (sf.externalserviceid = s.id AND s.restrictedusers = 0 AND sf.functionname = '".$function->name."')
                 WHERE s.enabled = 1 $wscond1

                 UNION

                SELECT s.*, su.iprestriction
                  FROM {external_services} s
                  JOIN {external_services_functions} sf ON (sf.externalserviceid = s.id AND s.restrictedusers = 1 AND sf.functionname = '".$function->name."')
                  JOIN {external_services_users} su ON (su.externalserviceid = s.id AND su.userid = ".$USER->id.")
                 WHERE s.enabled = 1 AND su.validuntil IS NULL OR su.validuntil < ".time()." $wscond2";

        $rs = get_recordset_sql($sql);
        // now make sure user may access at least one service
        $remoteaddr = getremoteaddr();
        $allowed = false;
        foreach ($rs as $service) {
            if ($service['iprestriction'] and !address_in_subnet($remoteaddr, $service['iprestriction'])) {
                continue; // wrong request source ip, sorry
            }
            $allowed = true;
            break; // one service is enough, no need to continue
        }
        $rs->close();
        if (!$allowed) {
            throw new webservice_access_exception('Access to external function not allowed');
        }

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


