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
 * Support for external API
 *
 * @package    core
 * @subpackage webservice
 * @copyright  2009 Moodle Pty Ltd (http://moodle.com)
 * @copyright  Copyright (C) 2011 Catalyst IT Ltd (http://www.catalyst.net.nz)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Piers Harding
 */

defined('INTERNAL') || die();
define('MUST_EXIST', 2);
/** Bitmask, indicates :name type parameters are supported by db backend. */
define('SQL_PARAMS_NAMED', 1);

/** Bitmask, indicates ? type parameters are supported by db backend. */
define('SQL_PARAMS_QM', 2);

/** Bitmask, indicates $1, $2, ... type parameters are supported by db backend. */
define('SQL_PARAMS_DOLLAR', 4);


/** Normal select query, reading only */
define('SQL_QUERY_SELECT', 1);

/** Insert select query, writing */
define('SQL_QUERY_INSERT', 2);

/** Update select query, writing */
define('SQL_QUERY_UPDATE', 3);

/** Query changing db structure, writing */
define('SQL_QUERY_STRUCTURE', 4);

/** Auxiliary query done by driver, setting connection config, getting table info, etc. */
define('SQL_QUERY_AUX', 5);


/**
 * Add an entry to the log table.
 *
 * Add an entry to the log table.  These are "action" focussed rather
 * than web server hits, and provide a way to easily reconstruct what
 * any particular student has been doing.
 *
 * @global object
 * @global object
 * @global object
 * @uses SITEID
 * @uses DEBUG_DEVELOPER
 * @uses DEBUG_ALL
 * @param    int     $courseid  The course id
 * @param    string  $module  The module name - e.g. forum, journal, resource, course, user etc
 * @param    string  $action  'view', 'update', 'add' or 'delete', possibly followed by another word to clarify.
 * @param    string  $url     The file and parameters used to see the results of the action
 * @param    string  $info    Additional description information
 * @param    string  $cm      The course_module->id if there is one
 * @param    string  $user    If log regards $user other than $USER
 * @return void
 */
function ws_add_to_log($courseid, $module, $action, $url='', $info='', $cm=0, $user=0) {
    error_log("module: $module action: $action ($url) info: $info");
}


/**
 * Returns detailed function information
 * @param string|object $function name of external function or record from external_function
 * @param int $strictness IGNORE_MISSING means compatible mode, false returned if record not found, debug message if more found;
 *                        MUST_EXIST means throw exception if no record or multiple records found
 * @return object description or false if not found or exception thrown
 */
function external_function_info($function, $strictness=MUST_EXIST) {
    global $CFG;

    if (!is_object($function)) {
        if (!$function = get_record('external_functions', 'name', $function, NULL, NULL, NULL, NULL, '*')) {
            return false;
        }
    }

    //first find and include the ext implementation class
//    error_log('component class: '.var_export($function, true));
    $function->classpath = empty($function->classpath) ? get_component_directory($function->component) : $CFG->docroot.'/'.$function->classpath;
    if (!file_exists($function->classpath.'/externallib.php')) {
        throw new coding_exception('Can not find file with external function implementation');
    }
    require_once($function->classpath.'/externallib.php');

    $function->parameters_method = $function->methodname.'_parameters';
    $function->returns_method    = $function->methodname.'_returns';

    // make sure the implementaion class is ok
    if (!method_exists($function->classname, $function->methodname)) {
        throw new coding_exception('Missing implementation method of '.$function->classname.'::'.$function->methodname);
    }
    if (!method_exists($function->classname, $function->parameters_method)) {
        throw new coding_exception('Missing parameters description');
    }
    if (!method_exists($function->classname, $function->returns_method)) {
        throw new coding_exception('Missing returned values description');
    }

    // fetch the parameters description
    $function->parameters_desc = call_user_func(array($function->classname, $function->parameters_method));
    if (!($function->parameters_desc instanceof external_function_parameters)) {
        throw new coding_exception('Invalid parameters description');
    }

    // fetch the return values description
    $function->returns_desc = call_user_func(array($function->classname, $function->returns_method));
    // null means void result or result is ignored
    if (!is_null($function->returns_desc) and !($function->returns_desc instanceof external_description)) {
        throw new coding_exception('Invalid return description');
    }

    //now get the function description
    //TODO: use localised lang pack descriptions, it would be nice to have
    //      easy to understand descriptions in admin UI,
    //      on the other hand this is still a bit in a flux and we need to find some new naming
    //      conventions for these descriptions in lang packs
    $function->description = null;

    //$servicesfile = get_component_directory($function->component).'/db/services.php'; // FIXME
    //$servicesfile = $CFG->docroot.'/'.$function->classpath.'/services.php';
    $servicesfile = $function->classpath.'/services.php';

    if (file_exists($servicesfile)) {
        $functions = null;
        include($servicesfile);
        if (isset($functions[$function->name]['description'])) {
            $function->description = $functions[$function->name]['description'];
//            error_log('function: '.$function->description);
        }
    }

    return $function;
}



/**
 * Base Mahara WS Exception class
 *
 * Although this class is defined here, you cannot throw a mahara_ws_exception until
 * after moodlelib.php has been included (which will happen very soon).
 *
 * @package    core
 * @subpackage lib
 * @copyright  2008 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mahara_ws_exception extends Exception {
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

//        if (get_string_manager()->string_exists($errorcode, $module)) {
            $message = get_string($errorcode, $module, $a).$debuginfo;
//        } else {
//            $message = $module . '/' . $errorcode;
//        }

        parent::__construct($message, 0);
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
//        error_log("params: " . var_export($params, TRUE));
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
                        throw new webservice_parameter_exception('invalidextparam',"key: $key (debuginfo: ".$e->debuginfo.") ");
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
//                error_log('description keys: '.var_export($key, true));
                    if ($subdesc->required == VALUE_REQUIRED) {
                        throw new webservice_parameter_exception('errorresponsemissingkey', $key);
                    }
                    if ($subdesc instanceof external_value) {
                        if ($subdesc->required == VALUE_DEFAULT) {
                            try {
                                    $result[$key] = self::clean_returnvalue($subdesc, $subdesc->default);
                            } catch (Exception $e) {
                                    throw new webservice_parameter_exception('invalidextresponse',$key." (".$e->debuginfo.")");
                            }
                        }
                    }
                } else {
                    try {
                        $result[$key] = self::clean_returnvalue($subdesc, $response[$key]);
                    } catch (Exception $e) {
                        //it's ok to display debug info as here the information is useful for ws client/dev
//                        error_log('ohoh: '.var_export(array('key' => $key, 'subdesc' => $subdesc, 'response' => $response[$key]), true));
                        throw new webservice_parameter_exception('invalidextresponse',$key." (".$e->debuginfo.")");
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

function external_generate_token($tokentype, $serviceorid, $userid, $institution = 'mahara',  $validuntil=0, $iprestriction=''){
    global $USER;
    // make sure the token doesn't exist (even if it should be almost impossible with the random generation)
    $numtries = 0;
    do {
        $numtries ++;
        $generatedtoken = md5(uniqid(rand(),1));
        if ($numtries > 5){
            throw new mahara_ws_exception('tokengenerationfailed');
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
//    if ($tokentype == EXTERNAL_TOKEN_EMBEDDED){
//        $newtoken->sid = session_id();
//    }

    $newtoken->institution = $institution;
    $newtoken->creatorid = $USER->id;
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
function external_create_service_token($servicename, $userid, $institution = 'mahara',  $validuntil=0, $iprestriction=''){
    $service = get_record('external_services', 'name', $servicename, '*');
    return external_generate_token(EXTERNAL_TOKEN_EMBEDDED, $service, $userid, $institution,  $validuntil, $iprestriction);
}

/**
 * Web service parameter exception class
 *
 * This exception must be thrown to the web service client when a web service parameter is invalid
 * The error string is gotten from webservice.php
 */
class webservice_parameter_exception extends mahara_ws_exception {
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
class coding_exception extends mahara_ws_exception {
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
class invalid_parameter_exception extends mahara_ws_exception {
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
class invalid_response_exception extends mahara_ws_exception {
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
class invalid_state_exception extends mahara_ws_exception {
    /**
     * Constructor
     * @param string $hint short description of problem
     * @param string $debuginfo optional more detailed information
     */
    function __construct($hint, $debuginfo=null) {
        parent::__construct('invalidstatedetected', 'debug', '', $hint, $debuginfo);
    }
}




////////////////////////////////////////////////////////////////////////////////

/**
 * Originally from filelib.php - Moodle 2.x
 */

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
 * Functions for file handling.
 *
 * @package    core
 * @subpackage file
 * @copyright  1999 onwards Martin Dougiamas (http://dougiamas.com)
 * @copyright  Copyright (C) 2011 Catalyst IT Ltd (http://www.catalyst.net.nz)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Piers Harding
 */

defined('INTERNAL') || die();

/**
 * class for converting XML document to PHP array
 *
 * @author A K Chauhan <- thanks!
 *
 */
class xml2array {

    function xml2array($xml) {
        if (is_string($xml)) {
            $this->dom = new DOMDocument;
            $this->dom->loadXml($xml);
        }

        return FALSE;
    }

    function _process($node) {
        $occurance = array();
        $result = array();

        if (!empty($node->childNodes)) {
            foreach($node->childNodes as $child) {
                if (empty($occurance[$child->nodeName])) {
                    $occurance[$child->nodeName] = 0;
                }
                $occurance[$child->nodeName]++;
            }
        }

        if($node->nodeType == XML_TEXT_NODE) {
            $result = html_entity_decode(htmlentities($node->nodeValue, ENT_COMPAT, 'UTF-8'),
                                     ENT_COMPAT,'ISO-8859-15');
        }
        else {
            if($node->hasChildNodes()){
                $children = $node->childNodes;

                for($i=0; $i<$children->length; $i++) {
                    $child = $children->item($i);

                    if($child->nodeName != '#text') {
                        if($occurance[$child->nodeName] > 1) {
                            $result[$child->nodeName][] = $this->_process($child);
                        }
                        else {
                            $result[$child->nodeName] = $this->_process($child);
                        }
                    }
                    else if ($child->nodeName == '#text') {
                        $text = $this->_process($child);

                        if (trim($text) != '') {
                            $result[$child->nodeName] = $this->_process($child);
                        }
                    }
                }
            }

            if($node->hasAttributes()) {
                $attributes = $node->attributes;

                if(!is_null($attributes)) {
                    foreach ($attributes as $key => $attr) {
                        $result["@".$attr->name] = $attr->value;
                    }
                }
            }
        }

        return $result;
    }

    function getResult() {
        return $this->_process($this->dom);
    }
}


/**
 * Recursive function formating an array in POST parameter
 * @param array $arraydata - the array that we are going to format and add into &$data array
 * @param string $currentdata - a row of the final postdata array at instant T
 *                when finish, it's assign to $data under this format: name[keyname][][]...[]='value'
 * @param array $data - the final data array containing all POST parameters : 1 row = 1 parameter
 */
function format_array_postdata_for_curlcall($arraydata, $currentdata, &$data) {
        foreach ($arraydata as $k=>$v) {
            $newcurrentdata = $currentdata;
            if (is_object($v)) {
                $v = (array)$v;
            }
            if (is_array($v)) { //the value is an array, call the function recursively
                $newcurrentdata = $newcurrentdata.'['.urlencode($k).']';
                format_array_postdata_for_curlcall($v, $newcurrentdata, $data);
            }  else { //add the POST parameter to the $data array
                $data[] = $newcurrentdata.'['.urlencode($k).']='.urlencode($v);
            }
        }
}

/**
 * Transform a PHP array into POST parameter
 * (see the recursive function format_array_postdata_for_curlcall)
 * @param array $postdata
 * @return array containing all POST parameters  (1 row = 1 POST parameter)
 */
function format_postdata_for_curlcall($postdata) {
        $data = array();
        foreach ($postdata as $k=>$v) {
            if (is_array($v)) {
                $currentdata = urlencode($k);
                format_array_postdata_for_curlcall($v, $currentdata, $data);
            }  else {
                $data[] = urlencode($k).'='.urlencode($v);
            }
        }
        $convertedpostdata = implode('&', $data);
        return $convertedpostdata;
}


/**
 * Fetches content of file from Internet (using proxy if defined). Uses cURL extension if present.
 * Due to security concerns only downloads from http(s) sources are supported.
 *
 * @param string $url file url starting with http(s)://
 * @param array $headers http headers, null if none. If set, should be an
 *   associative array of header name => value pairs.
 * @param array $postdata array means use POST request with given parameters
 * @param bool $fullresponse return headers, responses, etc in a similar way snoopy does
 *   (if false, just returns content)
 * @param int $timeout timeout for complete download process including all file transfer
 *   (default 5 minutes)
 * @param int $connecttimeout timeout for connection to server; this is the timeout that
 *   usually happens if the remote server is completely down (default 20 seconds);
 *   may not work when using proxy
 * @param bool $skipcertverify If true, the peer's SSL certificate will not be checked.
 *   Only use this when already in a trusted location.
 * @param string $tofile store the downloaded content to file instead of returning it.
 * @param bool $calctimeout false by default, true enables an extra head request to try and determine
 *   filesize and appropriately larger timeout based on $CFG->curltimeoutkbitrate
 * @return mixed false if request failed or content of the file as string if ok. True if file downloaded into $tofile successfully.
 */
function download_file_content($url, $headers=null, $postdata=null, $fullresponse=false, $timeout=300, $connecttimeout=20, $skipcertverify=false, $tofile=NULL, $calctimeout=false) {
    global $CFG;

    // some extra security
    $newlines = array("\r", "\n");
    if (is_array($headers) ) {
        foreach ($headers as $key => $value) {
            $headers[$key] = str_replace($newlines, '', $value);
        }
    }
    $url = str_replace($newlines, '', $url);
    if (!preg_match('|^https?://|i', $url)) {
        if ($fullresponse) {
            $response = new stdClass();
            $response->status        = 0;
            $response->headers       = array();
            $response->response_code = 'Invalid protocol specified in url';
            $response->results       = '';
            $response->error         = 'Invalid protocol specified in url';
            return $response;
        } else {
            return false;
        }
    }

    // check if proxy (if used) should be bypassed for this url
    $proxybypass = is_proxybypass($url);

    if (!$ch = curl_init($url)) {
        debugging('Can not init curl.');
        return false;
    }

    // set extra headers
    if (is_array($headers) ) {
        $headers2 = array();
        foreach ($headers as $key => $value) {
            $headers2[] = "$key: $value";
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers2);
    }

    if ($skipcertverify) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    }

    // use POST if requested
    if (is_array($postdata)) {
        $postdata = format_postdata_for_curlcall($postdata);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connecttimeout);

    if (!ini_get('open_basedir') and !ini_get('safe_mode')) {
        // TODO: add version test for '7.10.5'
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    }

    if (!empty($CFG->proxyhost) and !$proxybypass) {
        // SOCKS supported in PHP5 only
        if (!empty($CFG->proxytype) and ($CFG->proxytype == 'SOCKS5')) {
            if (defined('CURLPROXY_SOCKS5')) {
                curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
            } else {
                curl_close($ch);
                if ($fullresponse) {
                    $response = new stdClass();
                    $response->status        = '0';
                    $response->headers       = array();
                    $response->response_code = 'SOCKS5 proxy is not supported in PHP4';
                    $response->results       = '';
                    $response->error         = 'SOCKS5 proxy is not supported in PHP4';
                    return $response;
                } else {
                    debugging("SOCKS5 proxy is not supported in PHP4.", DEBUG_ALL);
                    return false;
                }
            }
        }

        curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, false);

        if (empty($CFG->proxyport)) {
            curl_setopt($ch, CURLOPT_PROXY, $CFG->proxyhost);
        } else {
            curl_setopt($ch, CURLOPT_PROXY, $CFG->proxyhost.':'.$CFG->proxyport);
        }

        if (!empty($CFG->proxyuser) and !empty($CFG->proxypassword)) {
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $CFG->proxyuser.':'.$CFG->proxypassword);
            if (defined('CURLOPT_PROXYAUTH')) {
                // any proxy authentication if PHP 5.1
                curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_BASIC | CURLAUTH_NTLM);
            }
        }
    }

    // set up header and content handlers
    $received = new stdClass();
    $received->headers = array(); // received headers array
    $received->tofile  = $tofile;
    $received->fh      = null;
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, partial('download_file_content_header_handler', $received));
    if ($tofile) {
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, partial('download_file_content_write_handler', $received));
    }

    if (!isset($CFG->curltimeoutkbitrate)) {
        //use very slow rate of 56kbps as a timeout speed when not set
        $bitrate = 56;
    } else {
        $bitrate = $CFG->curltimeoutkbitrate;
    }

    // try to calculate the proper amount for timeout from remote file size.
    // if disabled or zero, we won't do any checks nor head requests.
    if ($calctimeout && $bitrate > 0) {
        //setup header request only options
        curl_setopt_array ($ch, array(
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_NOBODY         => true)
        );

        curl_exec($ch);
        $info = curl_getinfo($ch);
        $err = curl_error($ch);

        if ($err === '' && $info['download_content_length'] > 0) { //no curl errors
            $timeout = max($timeout, ceil($info['download_content_length'] * 8 / ($bitrate * 1024))); //adjust for large files only - take max timeout.
        }
        //reinstate affected curl options
        curl_setopt_array ($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY         => false)
        );
    }

    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    $result = curl_exec($ch);

    // try to detect encoding problems
    if ((curl_errno($ch) == 23 or curl_errno($ch) == 61) and defined('CURLOPT_ENCODING')) {
        curl_setopt($ch, CURLOPT_ENCODING, 'none');
        $result = curl_exec($ch);
    }

    if ($received->fh) {
        fclose($received->fh);
    }

    if (curl_errno($ch)) {
        $error    = curl_error($ch);
        $error_no = curl_errno($ch);
        curl_close($ch);

        if ($fullresponse) {
            $response = new stdClass();
            if ($error_no == 28) {
                $response->status    = '-100'; // mimic snoopy
            } else {
                $response->status    = '0';
            }
            $response->headers       = array();
            $response->response_code = $error;
            $response->results       = false;
            $response->error         = $error;
            return $response;
        } else {
            debugging("cURL request for \"$url\" failed with: $error ($error_no)", DEBUG_ALL);
            return false;
        }

    } else {
        $info = curl_getinfo($ch);
        curl_close($ch);

        if (empty($info['http_code'])) {
            // for security reasons we support only true http connections (Location: file:// exploit prevention)
            $response = new stdClass();
            $response->status        = '0';
            $response->headers       = array();
            $response->response_code = 'Unknown cURL error';
            $response->results       = false; // do NOT change this, we really want to ignore the result!
            $response->error         = 'Unknown cURL error';

        } else {
            $response = new stdClass();;
            $response->status        = (string)$info['http_code'];
            $response->headers       = $received->headers;
            $response->response_code = $received->headers[0];
            $response->results       = $result;
            $response->error         = '';
        }

        if ($fullresponse) {
            return $response;
        } else if ($info['http_code'] != 200) {
            debugging("cURL request for \"$url\" failed, HTTP response code: ".$response->response_code, DEBUG_ALL);
            return false;
        } else {
            return $response->results;
        }
    }
}

/**
 * internal implementation
 */
function download_file_content_header_handler($received, $ch, $header) {
    $received->headers[] = $header;
    return strlen($header);
}

/**
 * internal implementation
 */
function download_file_content_write_handler($received, $ch, $data) {
    if (!$received->fh) {
        $received->fh = fopen($received->tofile, 'w');
        if ($received->fh === false) {
            // bad luck, file creation or overriding failed
            return 0;
        }
    }
    if (fwrite($received->fh, $data) === false) {
        // bad luck, write failed, let's abort completely
        return 0;
    }
    return strlen($data);
}


////////////////////////////////////////////////////////////////////////////////

/**
 * orignally from weblib.php - from Moodle 2.x
 *
 */


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
 * Library of functions for web output
 *
 * Library of all general-purpose Moodle PHP functions and constants
 * that produce HTML output
 *
 * Other main libraries:
 * - datalib.php - functions that access the database.
 * - moodlelib.php - general-purpose Moodle functions.
 *
 * @package    core
 * @subpackage lib
 * @copyright  1999 onwards Martin Dougiamas {@link http://moodle.com}
 * @copyright  Copyright (C) 2011 Catalyst IT Ltd (http://www.catalyst.net.nz)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Piers Harding
 */

defined('INTERNAL') || die();

/// Constants

/// Define text formatting types ... eventually we can add Wiki, BBcode etc

/**
 * Does all sorts of transformations and filtering
 */
define('FORMAT_MOODLE',   '0');   // Does all sorts of transformations and filtering

/**
 * Plain HTML (with some tags stripped)
 */
define('FORMAT_HTML',     '1');   // Plain HTML (with some tags stripped)

/**
 * Plain text (even tags are printed in full)
 */
define('FORMAT_PLAIN',    '2');   // Plain text (even tags are printed in full)

/**
 * Wiki-formatted text
 * Deprecated: left here just to note that '3' is not used (at the moment)
 * and to catch any latent wiki-like text (which generates an error)
 */
define('FORMAT_WIKI',     '3');   // Wiki-formatted text

/**
 * Markdown-formatted text http://daringfireball.net/projects/markdown/
 */
define('FORMAT_MARKDOWN', '4');   // Markdown-formatted text http://daringfireball.net/projects/markdown/

/**
 * A moodle_url comparison using this flag will return true if the base URLs match, params are ignored
 */
define('URL_MATCH_BASE', 0);
/**
 * A moodle_url comparison using this flag will return true if the base URLs match and the params of url1 are part of url2
 */
define('URL_MATCH_PARAMS', 1);
/**
 * A moodle_url comparison using this flag will return true if the two URLs are identical, except for the order of the params
 */
define('URL_MATCH_EXACT', 2);

/**
 * Allowed tags - string of html tags that can be tested against for safe html tags
 * @global string $ALLOWED_TAGS
 * @name $ALLOWED_TAGS
 */
global $ALLOWED_TAGS;
$ALLOWED_TAGS =
'<p><br><b><i><u><font><table><tbody><thead><tfoot><span><div><tr><td><th><ol><ul><dl><li><dt><dd><h1><h2><h3><h4><h5><h6><hr><img><a><strong><emphasis><em><sup><sub><address><cite><blockquote><pre><strike><param><acronym><nolink><lang><tex><algebra><math><mi><mn><mo><mtext><mspace><ms><mrow><mfrac><msqrt><mroot><mstyle><merror><mpadded><mphantom><mfenced><msub><msup><msubsup><munder><mover><munderover><mmultiscripts><mtable><mtr><mtd><maligngroup><malignmark><maction><cn><ci><apply><reln><fn><interval><inverse><sep><condition><declare><lambda><compose><ident><quotient><exp><factorial><divide><max><min><minus><plus><power><rem><times><root><gcd><and><or><xor><not><implies><forall><exists><abs><conjugate><eq><neq><gt><lt><geq><leq><ln><log><int><diff><partialdiff><lowlimit><uplimit><bvar><degree><set><list><union><intersect><in><notin><subset><prsubset><notsubset><notprsubset><setdiff><sum><product><limit><tendsto><mean><sdev><variance><median><mode><moment><vector><matrix><matrixrow><determinant><transpose><selector><annotation><semantics><annotation-xml><tt><code>';

/**
 * Allowed protocols - array of protocols that are safe to use in links and so on
 * @global string $ALLOWED_PROTOCOLS
 */
$ALLOWED_PROTOCOLS = array('http', 'https', 'ftp', 'news', 'mailto', 'rtsp', 'teamspeak', 'gopher', 'mms',
                           'color', 'callto', 'cursor', 'text-align', 'font-size', 'font-weight', 'font-style', 'font-family',
                           'border', 'border-bottom', 'border-left', 'border-top', 'border-right', 'margin', 'margin-bottom', 'margin-left', 'margin-top', 'margin-right',
                           'padding', 'padding-bottom', 'padding-left', 'padding-top', 'padding-right', 'vertical-align',
                           'background', 'background-color', 'text-decoration');   // CSS as well to get through kses


/// Functions

/**
 * Validates an email to make sure it makes sense.
 *
 * @param string $address The email address to validate.
 * @return boolean
 */
function validate_email($address) {

    return (preg_match('#^[-!\#$%&\'*+\\/0-9=?A-Z^_`a-z{|}~]+'.
                 '(\.[-!\#$%&\'*+\\/0-9=?A-Z^_`a-z{|}~]+)*'.
                  '@'.
                  '[-!\#$%&\'*+\\/0-9=?A-Z^_`a-z{|}~]+\.'.
                  '[-!\#$%&\'*+\\./0-9=?A-Z^_`a-z{|}~]+$#',
                  $address));
}


/**
 * Replaces non-standard HTML entities
 *
 * @param string $string
 * @return string
 */
function fix_non_standard_entities($string) {
    $text = preg_replace('/&#0*([0-9]+);?/', '&#$1;', $string);
    $text = preg_replace('/&#x0*([0-9a-fA-F]+);?/', '&#x$1;', $text);
    $text = preg_replace('[\x00-\x08\x0b-\x0c\x0e-\x1f]', '', $text);
    return $text;
}

/**
 * Given raw text (eg typed in by a user), this function cleans it up
 * and removes any nasty tags that could mess up Moodle pages.
 *
 * NOTE: the format parameter was deprecated because we can safely clean only HTML.
 *
 * @param string $text The text to be cleaned
 * @param int $format deprecated parameter, should always contain FORMAT_HTML or FORMAT_MOODLE
 * @return string The cleaned up text
 */
function clean_text($text, $format = FORMAT_HTML) {
    global $ALLOWED_TAGS, $CFG;

    if (empty($text) or is_numeric($text)) {
       return (string)$text;
    }

    if ($format != FORMAT_HTML and $format != FORMAT_HTML) {
        // TODO: we need to standardise cleanup of text when loading it into editor first
        //debugging('clean_text() is designed to work only with html');
    }

    if ($format == FORMAT_PLAIN) {
        return $text;
    }

    if (!empty($CFG->enablehtmlpurifier)) {
        $text = purify_html($text);
    } else {
    /// Fix non standard entity notations
        $text = fix_non_standard_entities($text);

    /// Remove tags that are not allowed
        $text = strip_tags($text, $ALLOWED_TAGS);

    /// Clean up embedded scripts and , using kses
        $text = cleanAttributes($text);

    /// Again remove tags that are not allowed
        $text = strip_tags($text, $ALLOWED_TAGS);

    }

    // Remove potential script events - some extra protection for undiscovered bugs in our code
    $text = preg_replace("~([^a-z])language([[:space:]]*)=~i", "$1Xlanguage=", $text);
    $text = preg_replace("~([^a-z])on([a-z]+)([[:space:]]*)=~i", "$1Xon$2=", $text);

    return $text;
}

/**
 * KSES replacement cleaning function - uses HTML Purifier.
 *
 * @global object
 * @param string $text The (X)HTML string to purify
 */
function purify_html($text) {
    global $CFG;

    // this can not be done only once because we sometimes need to reset the cache
    $cachedir = $CFG->dataroot.'/cache/htmlpurifier';
    check_dir_exists($cachedir);

    static $purifier = false;
    if ($purifier === false) {
        require_once $CFG->libdir.'/htmlpurifier/HTMLPurifier.safe-includes.php';
        $config = HTMLPurifier_Config::createDefault();

        $config->set('HTML.DefinitionID', 'moodlehtml');
        $config->set('HTML.DefinitionRev', 1);
        $config->set('Cache.SerializerPath', $cachedir);
        //$config->set('Cache.SerializerPermission', $CFG->directorypermissions); // it would be nice to get this upstream
        $config->set('Core.NormalizeNewlines', false);
        $config->set('Core.ConvertDocumentToFragment', true);
        $config->set('Core.Encoding', 'UTF-8');
        $config->set('HTML.Doctype', 'XHTML 1.0 Transitional');
        $config->set('URI.AllowedSchemes', array('http'=>true, 'https'=>true, 'ftp'=>true, 'irc'=>true, 'nntp'=>true, 'news'=>true, 'rtsp'=>true, 'teamspeak'=>true, 'gopher'=>true, 'mms'=>true));
        $config->set('Attr.AllowedFrameTargets', array('_blank'));

        if (!empty($CFG->allowobjectembed)) {
            $config->set('HTML.SafeObject', true);
            $config->set('Output.FlashCompat', true);
            $config->set('HTML.SafeEmbed', true);
        }

        $def = $config->getHTMLDefinition(true);
        $def->addElement('nolink', 'Block', 'Flow', array());                       // skip our filters inside
        $def->addElement('tex', 'Inline', 'Inline', array());                       // tex syntax, equivalent to $$xx$$
        $def->addElement('algebra', 'Inline', 'Inline', array());                   // algebra syntax, equivalent to @@xx@@
        $def->addElement('lang', 'Block', 'Flow', array(), array('lang'=>'CDATA')); // old anf future style multilang - only our hacked lang attribute
        $def->addAttribute('span', 'xxxlang', 'CDATA');                             // current problematic multilang

        $purifier = new HTMLPurifier($config);
    }

    $multilang = (strpos($text, 'class="multilang"') !== false);

    if ($multilang) {
        $text = preg_replace('/<span(\s+lang="([a-zA-Z0-9_-]+)"|\s+class="multilang"){2}\s*>/', '<span xxxlang="${2}">', $text);
    }
    $text = $purifier->purify($text);
    if ($multilang) {
        $text = preg_replace('/<span xxxlang="([a-zA-Z0-9_-]+)">/', '<span lang="${1}" class="multilang">', $text);
    }

    return $text;
}

/**
 * This function takes a string and examines it for HTML tags.
 *
 * If tags are detected it passes the string to a helper function {@link cleanAttributes2()}
 * which checks for attributes and filters them for malicious content
 *
 * @param string $str The string to be examined for html tags
 * @return string
 */
function cleanAttributes($str){
    $result = preg_replace_callback(
            '%(<[^>]*(>|$)|>)%m', #search for html tags
            "cleanAttributes2",
            $str
            );
    return  $result;
}

/**
 * This function takes a string with an html tag and strips out any unallowed
 * protocols e.g. javascript:
 *
 * It calls ancillary functions in kses which are prefixed by kses
 *
 * @global object
 * @global string
 * @param array $htmlArray An array from {@link cleanAttributes()}, containing in its 1st
 *              element the html to be cleared
 * @return string
 */
function cleanAttributes2($htmlArray){

    global $CFG, $ALLOWED_PROTOCOLS;
    require_once($CFG->libdir .'/kses.php');

    $htmlTag = $htmlArray[1];
    if (substr($htmlTag, 0, 1) != '<') {
        return '&gt;';  //a single character ">" detected
    }
    if (!preg_match('%^<\s*(/\s*)?([a-zA-Z0-9]+)([^>]*)>?$%', $htmlTag, $matches)) {
        return ''; // It's seriously malformed
    }
    $slash = trim($matches[1]); //trailing xhtml slash
    $elem = $matches[2];    //the element name
    $attrlist = $matches[3]; // the list of attributes as a string

    $attrArray = kses_hair($attrlist, $ALLOWED_PROTOCOLS);

    $attStr = '';
    foreach ($attrArray as $arreach) {
        $arreach['name'] = strtolower($arreach['name']);
        if ($arreach['name'] == 'style') {
            $value = $arreach['value'];
            while (true) {
                $prevvalue = $value;
                $value = kses_no_null($value);
                $value = preg_replace("/\/\*.*\*\//Us", '', $value);
                $value = kses_decode_entities($value);
                $value = preg_replace('/(&#[0-9]+)(;?)/', "\\1;", $value);
                $value = preg_replace('/(&#x[0-9a-fA-F]+)(;?)/', "\\1;", $value);
                if ($value === $prevvalue) {
                    $arreach['value'] = $value;
                    break;
                }
            }
            $arreach['value'] = preg_replace("/j\s*a\s*v\s*a\s*s\s*c\s*r\s*i\s*p\s*t/i", "Xjavascript", $arreach['value']);
            $arreach['value'] = preg_replace("/v\s*b\s*s\s*c\s*r\s*i\s*p\s*t/i", "Xvbscript", $arreach['value']);
            $arreach['value'] = preg_replace("/e\s*x\s*p\s*r\s*e\s*s\s*s\s*i\s*o\s*n/i", "Xexpression", $arreach['value']);
            $arreach['value'] = preg_replace("/b\s*i\s*n\s*d\s*i\s*n\s*g/i", "Xbinding", $arreach['value']);
        } else if ($arreach['name'] == 'href') {
            //Adobe Acrobat Reader XSS protection
            $arreach['value'] = preg_replace('/(\.(pdf|fdf|xfdf|xdp|xfd)[^#]*)#.*$/i', '$1', $arreach['value']);
        }
        $attStr .=  ' '.$arreach['name'].'="'.$arreach['value'].'"';
    }

    $xhtml_slash = '';
    if (preg_match('%/\s*$%', $attrlist)) {
        $xhtml_slash = ' /';
    }
    return '<'. $slash . $elem . $attStr . $xhtml_slash .'>';
}



////////////////////////////////////////////////////////////////////////////////

/**
 * originally from moodlelib.php - from Moodle 2.x
 * 
 */


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
 * moodlelib.php - Moodle main library
 *
 * Main library file of miscellaneous general-purpose Moodle functions.
 * Other main libraries:
 *  - weblib.php      - functions that produce web output
 *  - datalib.php     - functions that access the database
 *
 * @package    core
 * @subpackage lib
 * @copyright  1999 onwards Martin Dougiamas  http://dougiamas.com
 * @copyright  Copyright (C) 2011 Catalyst IT Ltd (http://www.catalyst.net.nz)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Piers Harding
 */

defined('INTERNAL') || die();

/**
 * PARAM_ALPHA - contains only english ascii letters a-zA-Z.
 */
define('PARAM_ALPHA',    'alpha');

/**
 * PARAM_ALPHAEXT the same contents as PARAM_ALPHA plus the chars in quotes: "_-" allowed
 * NOTE: originally this allowed "/" too, please use PARAM_SAFEPATH if "/" needed
 */
define('PARAM_ALPHAEXT', 'alphaext');

/**
 * PARAM_ALPHANUM - expected numbers and letters only.
 */
define('PARAM_ALPHANUM', 'alphanum');

/**
 * PARAM_ALPHANUMEXT - expected numbers, letters only and _-.
 */
define('PARAM_ALPHANUMEXT', 'alphanumext');

/**
 * PARAM_BASE64 - Base 64 encoded format
 */
define('PARAM_BASE64',   'base64');

/**
 * PARAM_BOOL - converts input into 0 or 1, use for switches in forms and urls.
 */
define('PARAM_BOOL',     'bool');

/**
 * PARAM_CLEANHTML - cleans submitted HTML code. use only for text in HTML format. This cleaning may fix xhtml strictness too.
 */
define('PARAM_CLEANHTML', 'cleanhtml');

/**
 * PARAM_EMAIL - an email address following the RFC
 */
define('PARAM_EMAIL',   'email');

/**
 * PARAM_FILE - safe file name, all dangerous chars are stripped, protects against XSS, SQL injections and directory traversals
 */
define('PARAM_FILE',   'file');

/**
 * PARAM_FLOAT - a real/floating point number.
 */
define('PARAM_FLOAT',  'float');

/**
 * PARAM_HOST - expected fully qualified domain name (FQDN) or an IPv4 dotted quad (IP address)
 */
define('PARAM_HOST',     'host');

/**
 * PARAM_INT - integers only, use when expecting only numbers.
 */
define('PARAM_INT',      'int');

/**
 * PARAM_LOCALURL - expected properly formatted URL as well as one that refers to the local server itself. (NOT orthogonal to the others! Implies PARAM_URL!)
 */
define('PARAM_LOCALURL', 'localurl');

/**
 * PARAM_NOTAGS - all html tags are stripped from the text. Do not abuse this type.
 */
define('PARAM_NOTAGS',   'notags');

/**
 * PARAM_PATH - safe relative path name, all dangerous chars are stripped, protects against XSS, SQL injections and directory traversals
 * note: the leading slash is not removed, window drive letter is not allowed
 */
define('PARAM_PATH',     'path');

/**
 * PARAM_PEM - Privacy Enhanced Mail format
 */
define('PARAM_PEM',      'pem');

/**
 * PARAM_RAW specifies a parameter that is not cleaned/processed in any way
 */
define('PARAM_RAW', 'raw');

/**
 * PARAM_RAW_TRIMMED like PARAM_RAW but leading and trailing whitespace is stripped.
 */
define('PARAM_RAW_TRIMMED', 'raw_trimmed');

/**
 * PARAM_SAFEDIR - safe directory name, suitable for include() and require()
 */
define('PARAM_SAFEDIR',  'safedir');

/**
 * PARAM_SAFEPATH - several PARAM_SAFEDIR joined by "/", suitable for include() and require(), plugin paths, etc.
 */
define('PARAM_SAFEPATH',  'safepath');

/**
 * PARAM_SEQUENCE - expects a sequence of numbers like 8 to 1,5,6,4,6,8,9.  Numbers and comma only.
 */
define('PARAM_SEQUENCE',  'sequence');

/**
 * PARAM_TEXT - general plain text compatible with multilang filter, no other html tags. Please note '<', or '>' are allowed here.
 */
define('PARAM_TEXT',  'text');

/**
 * PARAM_URL - expected properly formatted URL. Please note that domain part is required, http://localhost/ is not accepted but http://localhost.localdomain/ is ok.
 */
define('PARAM_URL',      'url');

/**
 * PARAM_STRINGID - used to check if the given string is valid string identifier for get_string()
 */
define('PARAM_STRINGID',    'stringid');

///// DEPRECATED PARAM TYPES OR ALIASES - DO NOT USE FOR NEW CODE  /////
/**
 * PARAM_CLEAN - obsoleted, please use a more specific type of parameter.
 * It was one of the first types, that is why it is abused so much ;-)
 * @deprecated since 2.0
 */
define('PARAM_CLEAN',    'clean');

/**
 * PARAM_INTEGER - deprecated alias for PARAM_INT
 */
define('PARAM_INTEGER',  'int');

/**
 * PARAM_NUMBER - deprecated alias of PARAM_FLOAT
 */
define('PARAM_NUMBER',  'float');

/// Web Services ///

/**
 * VALUE_REQUIRED - if the parameter is not supplied, there is an error
 */
define('VALUE_REQUIRED', 1);

/**
 * VALUE_OPTIONAL - if the parameter is not supplied, then the param has no value
 */
define('VALUE_OPTIONAL', 2);

/**
 * VALUE_DEFAULT - if the parameter is not supplied, then the default value is used
 */
define('VALUE_DEFAULT', 0);

/**
 * NULL_NOT_ALLOWED - the parameter can not be set to null in the database
 */
define('NULL_NOT_ALLOWED', false);

/**
 * NULL_ALLOWED - the parameter can be set to null in the database
 */
define('NULL_ALLOWED', true);

/** Get remote addr constant */
define('GETREMOTEADDR_SKIP_HTTP_CLIENT_IP', '1');
/** Get remote addr constant */
define('GETREMOTEADDR_SKIP_HTTP_X_FORWARDED_FOR', '2');

///Tag constants///

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


/// PARAMETER HANDLING ////////////////////////////////////////////////////


/**
 * Returns a particular value for the named variable, taken from
 * POST or GET, otherwise returning a given default.
 *
 * This function should be used to initialise all optional values
 * in a script that are based on parameters.  Usually it will be
 * used like this:
 *    $name = optional_param('name', 'Fred', PARAM_TEXT);
 *
 * Please note $default and $type parameters are now required,
 * for now PARAM_CLEAN is used for backwards compatibility only.
 *
 * @param string $parname the name of the page parameter we want
 * @param mixed  $default the default value to return if nothing is found
 * @param string $type expected type of parameter
 * @return mixed
 */
function optional_param($parname, $default, $type) {
    if (!isset($type)) {
        debugging('optional_param() requires $default and $type to be specified.');
        $type = PARAM_CLEAN; // for now let's use this deprecated type
    }
    if (!isset($default)) {
        $default = null;
    }

    if (isset($_POST[$parname])) {       // POST has precedence
        $param = $_POST[$parname];
    } else if (isset($_GET[$parname])) {
        $param = $_GET[$parname];
    } else {
        return $default;
    }

    return clean_param($param, $type);
}

/**
 * Strict validation of parameter values, the values are only converted
 * to requested PHP type. Internally it is using clean_param, the values
 * before and after cleaning must be equal - otherwise
 * an invalid_parameter_exception is thrown.
 * Objects and classes are not accepted.
 *
 * @param mixed $param
 * @param int $type PARAM_ constant
 * @param bool $allownull are nulls valid value?
 * @param string $debuginfo optional debug information
 * @return mixed the $param value converted to PHP type or invalid_parameter_exception
 */
function validate_param($param, $type, $allownull=NULL_NOT_ALLOWED, $debuginfo='') {
    if (is_null($param)) {
        if ($allownull == NULL_ALLOWED) {
            return null;
        } else {
            throw new invalid_parameter_exception($debuginfo);
        }
    }
    if (is_array($param) or is_object($param)) {
        throw new invalid_parameter_exception($debuginfo);
    }

    $cleaned = clean_param($param, $type);
    if ((string)$param !== (string)$cleaned) {
        // conversion to string is usually lossless
        throw new invalid_parameter_exception($debuginfo.' value: '.$param);
    }

    return $cleaned;
}

/**
 * Used by {@link optional_param()} and {@link required_param()} to
 * clean the variables and/or cast to specific types, based on
 * an options field.
 * <code>
 * $course->format = clean_param($course->format, PARAM_ALPHA);
 * $selectedgrade_item = clean_param($selectedgrade_item, PARAM_INT);
 * </code>
 *
 * @param mixed $param the variable we are cleaning
 * @param int $type expected format of param after cleaning.
 * @return mixed
 */
function clean_param($param, $type) {

    global $CFG;

    if (is_array($param)) {              // Let's loop
        $newparam = array();
        foreach ($param as $key => $value) {
            $newparam[$key] = clean_param($value, $type);
        }
        return $newparam;
    }

    switch ($type) {
        case PARAM_RAW:          // no cleaning at all
            return $param;

        case PARAM_RAW_TRIMMED:         // no cleaning, but strip leading and trailing whitespace.
            return trim($param);

        case PARAM_CLEAN:        // General HTML cleaning, try to use more specific type if possible
            // this is deprecated!, please use more specific type instead
            if (is_numeric($param)) {
                return $param;
            }
            return clean_text($param);     // Sweep for scripts, etc

        case PARAM_CLEANHTML:    // clean html fragment
            $param = clean_text($param, FORMAT_HTML);     // Sweep for scripts, etc
            return trim($param);

        case PARAM_INT:
            return (int)$param;  // Convert to integer

        case PARAM_FLOAT:
        case PARAM_NUMBER:
            return (float)$param;  // Convert to float

        case PARAM_ALPHA:        // Remove everything not a-z
            return preg_replace('/[^a-zA-Z]/i', '', $param);

        case PARAM_ALPHAEXT:     // Remove everything not a-zA-Z_- (originally allowed "/" too)
            return preg_replace('/[^a-zA-Z_-]/i', '', $param);

        case PARAM_ALPHANUM:     // Remove everything not a-zA-Z0-9
            return preg_replace('/[^A-Za-z0-9]/i', '', $param);

        case PARAM_ALPHANUMEXT:     // Remove everything not a-zA-Z0-9_-
            return preg_replace('/[^A-Za-z0-9_-]/i', '', $param);

        case PARAM_SEQUENCE:     // Remove everything not 0-9,
            return preg_replace('/[^0-9,]/i', '', $param);

        case PARAM_BOOL:         // Convert to 1 or 0
            $tempstr = strtolower($param);
            if ($tempstr === 'on' or $tempstr === 'yes' or $tempstr === 'true') {
                $param = 1;
            } else if ($tempstr === 'off' or $tempstr === 'no'  or $tempstr === 'false') {
                $param = 0;
            } else {
                $param = empty($param) ? 0 : 1;
            }
            return $param;

        case PARAM_NOTAGS:       // Strip all tags
            return strip_tags($param);

        case PARAM_TEXT:    // leave only tags needed for multilang
            // if the multilang syntax is not correct we strip all tags
            // because it would break xhtml strict which is required for accessibility standards
            // please note this cleaning does not strip unbalanced '>' for BC compatibility reasons
            do {
                if (strpos($param, '</lang>') !== false) {
                    // old and future mutilang syntax
                    $param = strip_tags($param, '<lang>');
                    if (!preg_match_all('/<.*>/suU', $param, $matches)) {
                        break;
                    }
                    $open = false;
                    foreach ($matches[0] as $match) {
                        if ($match === '</lang>') {
                            if ($open) {
                                $open = false;
                                continue;
                            } else {
                                break 2;
                            }
                        }
                        if (!preg_match('/^<lang lang="[a-zA-Z0-9_-]+"\s*>$/u', $match)) {
                            break 2;
                        } else {
                            $open = true;
                        }
                    }
                    if ($open) {
                        break;
                    }
                    return $param;

                } else if (strpos($param, '</span>') !== false) {
                    // current problematic multilang syntax
                    $param = strip_tags($param, '<span>');
                    if (!preg_match_all('/<.*>/suU', $param, $matches)) {
                        break;
                    }
                    $open = false;
                    foreach ($matches[0] as $match) {
                        if ($match === '</span>') {
                            if ($open) {
                                $open = false;
                                continue;
                            } else {
                                break 2;
                            }
                        }
                        if (!preg_match('/^<span(\s+lang="[a-zA-Z0-9_-]+"|\s+class="multilang"){2}\s*>$/u', $match)) {
                            break 2;
                        } else {
                            $open = true;
                        }
                    }
                    if ($open) {
                        break;
                    }
                    return $param;
                }
            } while (false);
            // easy, just strip all tags, if we ever want to fix orphaned '&' we have to do that in format_string()
            return strip_tags($param);

        case PARAM_SAFEDIR:      // Remove everything not a-zA-Z0-9_-
            return preg_replace('/[^a-zA-Z0-9_-]/i', '', $param);

        case PARAM_SAFEPATH:     // Remove everything not a-zA-Z0-9/_-
            return preg_replace('/[^a-zA-Z0-9\/_-]/i', '', $param);

        case PARAM_FILE:         // Strip all suspicious characters from filename
            $param = preg_replace('~[[:cntrl:]]|[&<>"`\|\':\\\\/]~u', '', $param);
            $param = preg_replace('~\.\.+~', '', $param);
            if ($param === '.') {
                $param = '';
            }
            return $param;

        case PARAM_PATH:         // Strip all suspicious characters from file path
            $param = str_replace('\\', '/', $param);
            $param = preg_replace('~[[:cntrl:]]|[&<>"`\|\':]~u', '', $param);
            $param = preg_replace('~\.\.+~', '', $param);
            $param = preg_replace('~//+~', '/', $param);
            return preg_replace('~/(\./)+~', '/', $param);

        case PARAM_HOST:         // allow FQDN or IPv4 dotted quad
            $param = preg_replace('/[^\.\d\w-]/','', $param ); // only allowed chars
            // match ipv4 dotted quad
            if (preg_match('/(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})/',$param, $match)){
                // confirm values are ok
                if ( $match[0] > 255
                     || $match[1] > 255
                     || $match[3] > 255
                     || $match[4] > 255 ) {
                    // hmmm, what kind of dotted quad is this?
                    $param = '';
                }
            } elseif ( preg_match('/^[\w\d\.-]+$/', $param) // dots, hyphens, numbers
                       && !preg_match('/^[\.-]/',  $param) // no leading dots/hyphens
                       && !preg_match('/[\.-]$/',  $param) // no trailing dots/hyphens
                       ) {
                // all is ok - $param is respected
            } else {
                // all is not ok...
                $param='';
            }
            return $param;

        case PARAM_URL:          // allow safe ftp, http, mailto urls
            include_once($CFG->docroot . '/artefact/webservice/libs/validateurlsyntax.php');
            if (!empty($param) && validateUrlSyntax($param, 's?H?S?F?E?u-P-a?I?p?f?q?r?')) {
                // all is ok, param is respected
            } else {
                $param =''; // not really ok
            }
            return $param;

        case PARAM_LOCALURL:     // allow http absolute, root relative and relative URLs within wwwroot
            $param = clean_param($param, PARAM_URL);
            if (!empty($param)) {
                if (preg_match(':^/:', $param)) {
                    // root-relative, ok!
                } elseif (preg_match('/^'.preg_quote($CFG->wwwroot, '/').'/i',$param)) {
                    // absolute, and matches our wwwroot
                } else {
                    // relative - let's make sure there are no tricks
                    if (validateUrlSyntax('/' . $param, 's-u-P-a-p-f+q?r?')) {
                        // looks ok.
                    } else {
                        $param = '';
                    }
                }
            }
            return $param;

        case PARAM_PEM:
            $param = trim($param);
            // PEM formatted strings may contain letters/numbers and the symbols
            // forward slash: /
            // plus sign:     +
            // equal sign:    =
            // , surrounded by BEGIN and END CERTIFICATE prefix and suffixes
            if (preg_match('/^-----BEGIN CERTIFICATE-----([\s\w\/\+=]+)-----END CERTIFICATE-----$/', trim($param), $matches)) {
                list($wholething, $body) = $matches;
                unset($wholething, $matches);
                $b64 = clean_param($body, PARAM_BASE64);
                if (!empty($b64)) {
                    return "-----BEGIN CERTIFICATE-----\n$b64\n-----END CERTIFICATE-----\n";
                } else {
                    return '';
                }
            }
            return '';

        case PARAM_BASE64:
            if (!empty($param)) {
                // PEM formatted strings may contain letters/numbers and the symbols
                // forward slash: /
                // plus sign:     +
                // equal sign:    =
                if (0 >= preg_match('/^([\s\w\/\+=]+)$/', trim($param))) {
                    return '';
                }
                $lines = preg_split('/[\s]+/', $param, -1, PREG_SPLIT_NO_EMPTY);
                // Each line of base64 encoded data must be 64 characters in
                // length, except for the last line which may be less than (or
                // equal to) 64 characters long.
                for ($i=0, $j=count($lines); $i < $j; $i++) {
                    if ($i + 1 == $j) {
                        if (64 < strlen($lines[$i])) {
                            return '';
                        }
                        continue;
                    }

                    if (64 != strlen($lines[$i])) {
                        return '';
                    }
                }
                return implode("\n",$lines);
            } else {
                return '';
            }

        case PARAM_EMAIL:
            if (validate_email($param)) {
                return $param;
            } else {
                return '';
            }

        case PARAM_STRINGID:
            if (preg_match('|^[a-zA-Z][a-zA-Z0-9\.:/_-]*$|', $param)) {
                return $param;
            } else {
                return '';
            }

        default:                 // throw error, switched parameters in optional_param or another serious problem
            print_error("unknownparamtype", '', '', $type);
    }
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


/// ENVIRONMENT CHECKING  ////////////////////////////////////////////////////////////

/**
 * Returns the exact absolute path to plugin directory.
 *
 * @param string $plugintype type of plugin
 * @param string $name name of the plugin
 * @return string full path to plugin directory; NULL if not found
 */
function get_plugin_directory($plugintype, $name) {
    if ($plugintype === '') {
        $plugintype = 'mod';
    }

    $types = get_plugin_types(true);
    if (!array_key_exists($plugintype, $types)) {
        return NULL;
    }
    $name = clean_param($name, PARAM_SAFEDIR); // just in case ;-)

    return $types[$plugintype].'/'.$name;
}

/**
 * Return exact absolute path to a plugin directory,
 * this method support "simpletest_" prefix designed for unit testing.
 *
 * @param string $component name such as 'moodle', 'mod_forum' or special simpletest value
 * @return string full path to component directory; NULL if not found
 */
function get_component_directory($component) {
    global $CFG;
/*
    $simpletest = false;
    if (strpos($component, 'simpletest_') === 0) {
        $subdir = substr($component, strlen('simpletest_'));
        //TODO: this looks borked, where is it used actually?
        return $subdir;
    }
*/
    list($type, $plugin) = normalize_component($component);

    if ($type === 'core') {
        if ($plugin === NULL ) {
            $path = $CFG->libdir;
        } else {
            $subsystems = get_core_subsystems();
            if (isset($subsystems[$plugin])) {
                $path = $CFG->docroot.'/'.$subsystems[$plugin];
            } else {
                $path = NULL;
            }
        }

    } else {
        $path = get_plugin_directory($type, $plugin);
    }

    return $path;
}

/**
 * Normalize the component name using the "frankenstyle" names.
 * @param string $component
 * @return array $type+$plugin elements
 */
function normalize_component($component) {
    if ($component === 'moodle' or $component === 'core') {
        $type = 'core';
        $plugin = NULL;

    } else if (strpos($component, '_') === false) {
        $subsystems = get_core_subsystems();
        if (array_key_exists($component, $subsystems)) {
            $type   = 'core';
            $plugin = $component;
        } else {
            // everything else is a module
            $type   = 'mod';
            $plugin = $component;
        }

    } else {
        list($type, $plugin) = explode('_', $component, 2);
        $plugintypes = get_plugin_types(false);
        if ($type !== 'core' and !array_key_exists($type, $plugintypes)) {
            $type   = 'mod';
            $plugin = $component;
        }
    }

    return array($type, $plugin);
}

/**
 * List all core subsystems and their location
 *
 * This is a whitelist of components that are part of the core and their
 * language strings are defined in /lang/en/<<subsystem>>.php. If a given
 * plugin is not listed here and it does not have proper plugintype prefix,
 * then it is considered as course activity module.
 *
 * The location is docroot relative path. NULL means there is no special
 * directory for this subsystem. If the location is set, the subsystem's
 * renderer.php is expected to be there.
 *
 * @return array of (string)name => (string|null)location
 */
function get_core_subsystems() {
    global $CFG;

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
 * Lists all plugin types
 * @param bool $fullpaths false means relative paths from docroot
 * @return array Array of strings - name=>location
 */
function get_plugin_types($fullpaths=true) {
    global $CFG;

    static $info     = null;
    static $fullinfo = null;

    if (!$info) {
        $info = array('auth'          => 'auth',
                      'webservice'    => 'webservice',
                      'portfolio'     => 'portfolio',
                      'theme'         => 'theme'); // this is a bit hacky, themes may be in dataroot too

        $mods = get_plugin_list('mod');
        foreach ($mods as $mod => $moddir) {
            if (file_exists("$moddir/db/subplugins.php")) {
                $subplugins = array();
                include("$moddir/db/subplugins.php");
                foreach ($subplugins as $subtype=>$dir) {
                    $info[$subtype] = $dir;
                }
            }
        }

        // local is always last!
        $info['local'] = 'local';

        $fullinfo = array();
        foreach ($info as $type => $dir) {
            $fullinfo[$type] = $CFG->docroot.'/'.$dir;
        }
    }

    return ($fullpaths ? $fullinfo : $info);
}

/**
 * Simplified version of get_list_of_plugins()
 * @param string $plugintype type of plugin
 * @return array name=>fulllocation pairs of plugins of given type
 */
function get_plugin_list($plugintype) {
    global $CFG;

    $ignored = array('CVS', '_vti_cnf', 'simpletest', 'db', 'yui', 'phpunit');
    if ($plugintype == 'auth') {
        // Historically we have had an auth plugin called 'db', so allow a special case.
        $key = array_search('db', $ignored);
        if ($key !== false) {
            unset($ignored[$key]);
        }
    }

    if ($plugintype === '') {
        $plugintype = 'mod';
    }

    $fulldirs = array();

    if ($plugintype === 'mod') {
        // mod is an exception because we have to call this function from get_plugin_types()
        $fulldirs[] = $CFG->docroot.'/mod';

    } else if ($plugintype === 'theme') {
        $fulldirs[] = $CFG->docroot.'/theme';
        // themes are special because they may be stored also in separate directory
        if (!empty($CFG->themedir) and file_exists($CFG->themedir) and is_dir($CFG->themedir) ) {
            $fulldirs[] = $CFG->themedir;
        }

    } else {
        $types = get_plugin_types(true);
        if (!array_key_exists($plugintype, $types)) {
            return array();
        }
        $fulldir = $types[$plugintype];
        if (!file_exists($fulldir)) {
            return array();
        }
        $fulldirs[] = $fulldir;
    }

    $result = array();

    foreach ($fulldirs as $fulldir) {
        if (!is_dir($fulldir)) {
            continue;
        }
        $items = new DirectoryIterator($fulldir);
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
            $result[$pluginname] = $fulldir.'/'.$pluginname;
            unset($item);
        }
        unset($items);
    }

    //TODO: implement better sorting once we migrated all plugin names to 'pluginname', ksort does not work for unicode, that is why we have to sort by the dir name, not the strings!
    ksort($result);
    return $result;
}


/// MISCELLANEOUS ////////////////////////////////////////////////////////////////////

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
    $addr = cleanremoteaddr($addr, false); // normalise
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
                continue; // incorect mask number, eh?
            }
            $ip = cleanremoteaddr($ip, false); // normalise
            if ($ip === null) {
                continue;
            }
            if (strpos($ip, ':') !== false) {
                // IPv6
                if (!$ipv6) {
                    continue;
                }
                if ($mask > 128 or $mask < 0) {
                    continue; // nonsense
                }
                if ($mask == 0) {
                    return true; // any address
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
                    continue; // nonsense
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
                $ipstart = cleanremoteaddr(trim($parts[0]), false); // normalise
                if ($ipstart === null) {
                    continue;
                }
                $ipparts = explode(':', $ipstart);
                $start = hexdec(array_pop($ipparts));
                $ipparts[] = trim($parts[1]);
                $ipend = cleanremoteaddr(implode(':', $ipparts), false); // normalise
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
                $ipstart = cleanremoteaddr(trim($parts[0]), false); // normalise
                if ($ipstart === null) {
                    continue;
                }
                $ipparts = explode('.', $ipstart);
                $ipparts[3] = trim($parts[1]);
                $ipend = cleanremoteaddr(implode('.', $ipparts), false); // normalise
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
                    unset($parts[$count-1]); // trim trailing :
                    $count--;
                    $subnet = implode('.', $parts);
                }
                $isip = cleanremoteaddr($subnet, false); // normalise
                if ($isip !== null) {
                    if ($isip === $addr) {
                        return true;
                    }
                    continue;
                } else if ($count > 8) {
                    continue;
                }
                $zeros = array_fill(0, 8-$count, '0');
                $subnet = $subnet.':'.implode(':', $zeros).'/'.($count*16);
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
                    unset($parts[$count-1]); // trim trailing .
                    $count--;
                    $subnet = implode('.', $parts);
                }
                if ($count == 4) {
                    $subnet = cleanremoteaddr($subnet, false); // normalise
                    if ($subnet === $addr) {
                        return true;
                    }
                    continue;
                } else if ($count > 4) {
                    continue;
                }
                $zeros = array_fill(0, 4-$count, '0');
                $subnet = $subnet.'.'.implode('.', $zeros).'/'.($count*8);
                if (address_in_subnet($addr, $subnet)) {
                    return true;
                }
            }
        }
    }

    return false;
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
    global $CFG;

    if (empty($CFG->getremoteaddrconf)) {
        // This will happen, for example, before just after the upgrade, as the
        // user is redirected to the admin screen.
        $variablestoskip = 0;
    } else {
        $variablestoskip = $CFG->getremoteaddrconf;
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
            return null; // severly malformed
        }

        if ($count != 8) {
            if (strpos($addr, '::') === false) {
                return null; // malformed
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
            return null; // incorrect format - sorry
        }

        // normalise 0s and case
        $parts = array_map('hexdec', $parts);
        $parts = array_map('dechex', $parts);

        $result = implode(':', $parts);

        if (!$compress) {
            return $result;
        }

        if ($result === '0:0:0:0:0:0:0:0') {
            return '::'; // all addresses
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
        $parts[$key] = (int)$match; // normalise 0s
    }

    return implode('.', $parts);
}


/**
 * check if $url matches anything in proxybypass list
 *
 * any errors just result in the proxy being used (least bad)
 *
 * @global object
 * @param string $url url to check
 * @return boolean true if we should bypass the proxy
 */
function is_proxybypass( $url ) {
    global $CFG;

    // sanity check
    if (empty($CFG->proxyhost) or empty($CFG->proxybypass)) {
        return false;
    }

    // get the host part out of the url
    if (!$host = parse_url( $url, PHP_URL_HOST )) {
        return false;
    }

    // get the possible bypass hosts into an array
    $matches = explode( ',', $CFG->proxybypass );

    // check for a match
    // (IPs need to match the left hand side and hosts the right of the url,
    // but we can recklessly check both as there can't be a false +ve)
    $bypass = false;
    foreach ($matches as $match) {
        $match = trim($match);

        // try for IP match (Left side)
        $lhs = substr($host,0,strlen($match));
        if (strcasecmp($match,$lhs)==0) {
            return true;
        }

        // try for host match (Right side)
        $rhs = substr($host,-strlen($match));
        if (strcasecmp($match,$rhs)==0) {
            return true;
        }
    }

    // nothing matched.
    return false;
}


////////////////////////////////////////////////////////////////////////////////



/**
 * helper function to do partial function binding
 * so we can use it for preg_replace_callback, for example
 * this works with php functions, user functions, static methods and class methods
 * it returns you a callback that you can pass on like so:
 *
 * $callback = partial('somefunction', $arg1, $arg2);
 *     or
 * $callback = partial(array('someclass', 'somestaticmethod'), $arg1, $arg2);
 *     or even
 * $obj = new someclass();
 * $callback = partial(array($obj, 'somemethod'), $arg1, $arg2);
 *
 * and then the arguments that are passed through at calltime are appended to the argument list.
 *
 * @param mixed $function a php callback
 * $param mixed $arg1.. $argv arguments to partially bind with
 *
 * @return callback
 */
function partial() {
    if (!class_exists('partial')) {
        class partial{
            var $values = array();
            var $func;

            function __construct($func, $args) {
                $this->values = $args;
                $this->func = $func;
            }

            function method() {
                $args = func_get_args();
                return call_user_func_array($this->func, array_merge($this->values, $args));
            }
        }
    }
    $args = func_get_args();
    $func = array_shift($args);
    $p = new partial($func, $args);
    return array($p, 'method');
}

