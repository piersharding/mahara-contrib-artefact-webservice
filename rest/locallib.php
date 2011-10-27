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
 * REST web service implementation classes and methods.
 *
 * @package   webservice
 * @copyright 2009 Moodle Pty Ltd (http://moodle.com)
 * @copyright  Copyright (C) 2011 Catalyst IT Ltd (http://www.catalyst.net.nz)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Piers Harding
 */

require_once(get_config('docroot') . "/artefact/webservice/locallib.php");

require_once(dirname(dirname(__FILE__)) . '/libs/oauth-php/OAuthServer.php');
require_once(dirname(dirname(__FILE__)) . '/libs/oauth-php/OAuthStore.php');

/**
 * REST service server implementation.
 * @author Petr Skoda (skodak)
 */
class webservice_rest_server extends webservice_base_server {

    /** @property mixed $format results format - xml or json */
    protected $format = 'xml';

    /**
     * Contructor
     */
    public function __construct($authmethod) {
        parent::__construct($authmethod);
        $this->wsname = 'rest';
    }

    /**
     * This method parses the $_REQUEST superglobal and looks for
     * the following information:
     *  1/ user authentication - username+password or token (wsusername, wspassword and wstoken parameters)
     *  2/ function name (wsfunction parameter)
     *  3/ function parameters (all other parameters except those above)
     *
     * @return void
     */
    protected function parse_request() {
        global $OAUTH_SERVER;
        // determine the request/response format
        if ((isset($_REQUEST['alt']) && trim($_REQUEST['alt']) == 'json') ||
            (isset($_GET['alt']) && trim($_GET['alt']) == 'json') ||
            (isset($_REQUEST['alt']) && trim($_REQUEST['alt']) == 'json') ||
            (isset($_SERVER['HTTP_ACCEPT']) && $_SERVER['HTTP_ACCEPT'] == 'application/json') ||
            (isset($_SERVER['HTTP_ACCEPT']) && $_SERVER['HTTP_ACCEPT'] == 'application/jsonrequest') ||
            $_SERVER['CONTENT_TYPE'] == 'application/json' ||
            $_SERVER['CONTENT_TYPE'] == 'application/jsonrequest' ){
            $this->format = 'json';
        }
        else {
            $this->format = 'xml';
        }
        unset($_REQUEST['alt']);

        $this->parameters = $_REQUEST;
        if ($OAUTH_SERVER) {
            $oauth_token = null;
            $headers = OAuthRequestLogger::getAllHeaders();
            try {
                $oauth_token = $OAUTH_SERVER->verifyExtended();
            }
            catch (OAuthException2 $e) {
                // let all others fail
                if (isset($_REQUEST['oauth_token']) || preg_grep('/oauth/', array_values($headers))) {
                    $this->auth = 'OAUTH';
                    throw $e;
                }
            }
            if ($oauth_token) {
                $this->authmethod = WEBSERVICE_AUTHMETHOD_OAUTH_TOKEN;
                $token = $OAUTH_SERVER->getParam('oauth_token');
                $store = OAuthStore::instance();
                $secrets = $store->getSecretsForVerify($oauth_token['consumer_key'],
                                                       $OAUTH_SERVER->urldecode($token),
                                                       'access');
               $this->oauth_token_details = $secrets;

               // the content type might be different for the OAuth client
                if (isset($headers['Content-Type']) && $headers['Content-Type'] == 'application/octet-stream' && $this->format != 'json') {
                    $body = file_get_contents('php://input');
                    parse_str($body, $parameters);
                    $this->parameters = array_merge($this->parameters, $parameters);
                }
            }
        }
        // make sure oauth parameters are gone
        foreach (array('oauth_nonce', 'oauth_timestamp', 'oauth_consumer_key', 'oauth_signature_method', 'oauth_version', 'oauth_token', 'oauth_signature',) as $param) {
            if (isset($this->parameters[$param])) {
                unset($this->parameters[$param]);
            }
        }

        // merge parameters from JSON request body if there is one
        if ($this->format == 'json') {
            // get request body
            $values = (array)json_decode(@file_get_contents('php://input'), true);
            if (!empty($values)) {
                $this->parameters = array_merge($this->parameters, $values);
            }
        }

        if ($this->authmethod == WEBSERVICE_AUTHMETHOD_USERNAME) {
            $this->username = isset($this->parameters['wsusername']) ? trim($this->parameters['wsusername']) : null;
            unset($this->parameters['wsusername']);

            $this->password = isset($this->parameters['wspassword']) ? trim($this->parameters['wspassword']) : null;
            unset($this->parameters['wspassword']);

        }
        else if ($this->authmethod == WEBSERVICE_AUTHMETHOD_PERMANENT_TOKEN){
            // is some other form of token - what kind is it?
            $this->token = isset($this->parameters['wstoken']) ? trim($this->parameters['wstoken']) : null;
            unset($this->parameters['wstoken']);
        }
        $this->functionname = isset($this->parameters['wsfunction']) ? trim($this->parameters['wsfunction']) : null;
        unset($this->parameters['wsfunction']);
    }

    /**
     * Send the result of function call to the WS client
     * formatted as XML document.
     * @return void
     */
    protected function send_response() {
        $this->send_headers($this->format);
        if ($this->format == 'json') {
            echo json_encode($this->returns) . "\n";
        }
        else {
            $xml = '<?xml version="1.0" encoding="UTF-8" ?>' . "\n";
            $xml .= '<RESPONSE>' . "\n";
            $xml .= self::xmlize_result($this->returns, $this->function->returns_desc);
            $xml .= '</RESPONSE>' . "\n";
            echo $xml;
        }
    }

    /**
     * Send the error information to the WS client
     * formatted as XML document.
     * @param exception $ex
     * @return void
     */
    protected function send_error($ex=null) {
        $this->send_headers($this->format);
        if ($this->format == 'json') {
            echo json_encode(array('exception' => get_class($ex), 'message' => $ex->getMessage(), 'debuginfo' => (isset($ex->debuginfo) ? $ex->debuginfo : ''))) . "\n";
        }
        else {
            $xml = '<?xml version="1.0" encoding="UTF-8" ?>' . "\n";
            $xml .= '<EXCEPTION class="' . get_class($ex) . '">' . "\n";
            $xml .= '<MESSAGE>' . htmlentities($ex->getMessage(), ENT_COMPAT, 'UTF-8') . '</MESSAGE>' . "\n";
            if (isset($ex->debuginfo)) {
                $xml .= '<DEBUGINFO>' . htmlentities($ex->debuginfo, ENT_COMPAT, 'UTF-8') . '</DEBUGINFO>' . "\n";
            }
            $xml .= '</EXCEPTION>' . "\n";
            echo $xml;
        }
    }

    /**
     * Internal implementation - sending of page headers.
     * @return void
     */
    protected function send_headers($type='xml') {
        if ($type == 'xml') {
            header('Content-Type: application/xml; charset=utf-8');
            header('Content-Disposition: inline; filename="response.xml"');
        }
        else {
            header('Content-Type: application/jsonrequest; charset=utf-8');
        }
        header('Cache-Control: private, must-revalidate, pre-check=0, post-check=0, max-age=0');
        header('Expires: '. gmdate('D, d M Y H:i:s', 0) . ' GMT');
        header('Pragma: no-cache');
        header('Accept-Ranges: none');
    }

    /**
     * Internal implementation - recursive function producing XML markup.
     * @param mixed $returns
     * @param $desc
     * @return string XML result
     */
    protected static function xmlize_result($returns, $desc) {
        if ($desc === null) {
            return '';

        } else if ($desc instanceof external_value) {
            if (is_bool($returns)) {
                // we want 1/0 instead of true/false here
                $returns = (int)$returns;
            }
            if (is_null($returns)) {
                return '<VALUE null="null"/>' . "\n";
            }
            else {
                return '<VALUE>' . htmlentities($returns, ENT_COMPAT, 'UTF-8') . '</VALUE>' . "\n";
            }

        }
        else if ($desc instanceof external_multiple_structure) {
            $mult = '<MULTIPLE>' . "\n";
            if (!empty($returns)) {
                foreach ($returns as $val) {
                    $mult .= self::xmlize_result($val, $desc->content);
                }
            }
            $mult .= '</MULTIPLE>'."\n";
            return $mult;

        } else if ($desc instanceof external_single_structure) {
            $single = '<SINGLE>' . "\n";
            foreach ($desc->keys as $key=>$subdesc) {
                if (!array_key_exists($key, $returns)) {
                    if ($subdesc->required == VALUE_REQUIRED) {
                        $single .= '<ERROR>Missing required key "' . $key . '"</ERROR>';
                        continue;
                    }
                    else {
                        //optional field
                        continue;
                    }
                }
                $single .= '<KEY name="' . $key . '">' . self::xmlize_result($returns[$key], $subdesc) . '</KEY>' . "\n";
            }
            $single .= '</SINGLE>' . "\n";
            return $single;
        }
    }
}


/**
 * REST test client class
 */
class webservice_rest_test_client implements webservice_test_client_interface {
    /**
     * Execute test client WS request
     * @param string $serverurl
     * @param string $function
     * @param array $params
     * @return mixed webservice call return values
     */
    public function simpletest($serverurl, $function, $params) {
        return webservice_download_file_content($serverurl . '&wsfunction=' . $function, null, $params);
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
        if (is_array($v)) {
            //the value is an array, call the function recursively
            $newcurrentdata = $newcurrentdata . '[' . urlencode($k) . ']';
            format_array_postdata_for_curlcall($v, $newcurrentdata, $data);
        }
        else {
            //add the POST parameter to the $data array
            $data[] = $newcurrentdata . '[' . urlencode($k) . ']=' . urlencode($v);
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
        }
        else {
            $data[] = urlencode($k) . '=' . urlencode($v);
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
 *   filesize and appropriately larger timeout based on get_config('curltimeoutkbitrate')
 * @return mixed false if request failed or content of the file as string if ok. True if file downloaded into $tofile successfully.
 */
function webservice_download_file_content($url, $headers=null, $postdata=null, $fullresponse=false, $timeout=300, $connecttimeout=20, $skipcertverify=false, $tofile=NULL, $calctimeout=false) {
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
        }
        else {
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

    $proxyhost = get_config('proxyhost');
    if (!empty($proxyhost) and !$proxybypass) {
        // SOCKS supported in PHP5 only
        $proxytype = get_config('proxytype');
        if (!empty($proxytype) and (get_config('proxytype') == 'SOCKS5')) {
            if (defined('CURLPROXY_SOCKS5')) {
                curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
            }
            else {
                curl_close($ch);
                if ($fullresponse) {
                    $response = new stdClass();
                    $response->status        = '0';
                    $response->headers       = array();
                    $response->response_code = 'SOCKS5 proxy is not supported in PHP4';
                    $response->results       = '';
                    $response->error         = 'SOCKS5 proxy is not supported in PHP4';
                    return $response;
                }
                else {
                    debugging("SOCKS5 proxy is not supported in PHP4.", DEBUG_ALL);
                    return false;
                }
            }
        }

        curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, false);

        $proxyport = get_config('proxyport');
        if (empty($proxyport)) {
            curl_setopt($ch, CURLOPT_PROXY, get_config('proxyhost'));
        }
        else {
            curl_setopt($ch, CURLOPT_PROXY, get_config('proxyhost') . ':' . get_config('proxyport'));
        }

        $proxyuser = get_config('proxyuser');
        $proxypassword = get_config('proxypassword');
        if (!empty($proxyuser) and !empty($proxypassword)) {
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, get_config('proxyuser') . ':' . get_config('proxypassword'));
            if (defined('CURLOPT_PROXYAUTH')) {
                // any proxy authentication if PHP 5.1
                curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_BASIC | CURLAUTH_NTLM);
            }
        }
    }

    // set up header and content handlers
    $received = new stdClass();
    $received->headers = array();
    // received headers array
    $received->tofile  = $tofile;
    $received->fh      = null;
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, ws_partial('download_file_content_header_handler', $received));
    if ($tofile) {
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, ws_partial('download_file_content_write_handler', $received));
    }

    $curltimeoutkbitrate = get_config('curltimeoutkbitrate');
    if (!isset($curltimeoutkbitrate)) {
        //use very slow rate of 56kbps as a timeout speed when not set
        $bitrate = 56;
    }
    else {
        $bitrate = get_config('curltimeoutkbitrate');
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

        if ($err === '' && $info['download_content_length'] > 0) {
            //no curl errors
            //adjust for large files only - take max timeout.
            $timeout = max($timeout, ceil($info['download_content_length'] * 8 / ($bitrate * 1024)));
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
            }
            else {
                $response->status    = '0';
            }
            $response->headers       = array();
            $response->response_code = $error;
            $response->results       = false;
            $response->error         = $error;
            return $response;
        }
        else {
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

        }
        else {
            $response = new stdClass();;
            $response->status        = (string)$info['http_code'];
            $response->headers       = $received->headers;
            $response->response_code = $received->headers[0];
            $response->results       = $result;
            $response->error         = '';
        }

        if ($fullresponse) {
            return $response;
        }
        else if ($info['http_code'] != 200) {
            debugging("cURL request for \"$url\" failed, HTTP response code: " . $response->response_code, DEBUG_ALL);
            return false;
        }
        else {
            return $response->results;
        }
    }
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
    // sanity check
    $proxyhost = get_config('proxyhost');
    $proxybypass = get_config('proxybypass');
    if (empty($proxyhost) or empty($proxybypass)) {
        return false;
    }

    // get the host part out of the url
    if (!$host = parse_url( $url, PHP_URL_HOST )) {
        return false;
    }

    // get the possible bypass hosts into an array
    $matches = explode( ',', get_config('proxybypass') );

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

/**
 * CURL callback handler for HTTP headers
 */
function download_file_content_header_handler($received, $ch, $header) {
    $received->headers[] = $header;
    return strlen($header);
}

/**
 * CURL callback handler for writing to HTTP connection
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

/**
 * helper function to do partial function binding
 * so we can use it for preg_replace_callback, for example
 * this works with php functions, user functions, static methods and class methods
 * it returns you a callback that you can pass on like so:
 *
 * $callback = ws_partial('somefunction', $arg1, $arg2);
 *     or
 * $callback = ws_partial(array('someclass', 'somestaticmethod'), $arg1, $arg2);
 *     or even
 * $obj = new someclass();
 * $callback = ws_partial(array($obj, 'somemethod'), $arg1, $arg2);
 *
 * and then the arguments that are passed through at calltime are appended to the argument list.
 *
 * @param mixed $function a php callback
 * $param mixed $arg1.. $argv arguments to partially bind with
 *
 * @return callback
 */
function ws_partial() {
    if (!class_exists('ws_partial')) {
        class ws_partial{
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
    $p = new ws_partial($func, $args);
    return array($p, 'method');
}
