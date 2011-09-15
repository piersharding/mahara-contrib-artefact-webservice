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

require_once(get_config('docroot')."/artefact/webservice/locallib.php");

require_once(dirname(dirname(__FILE__)).'/libs/oauth-php/OAuthServer.php');
require_once(dirname(dirname(__FILE__)).'/libs/oauth-php/OAuthStore.php');

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
//                error_log('could not get oauth: '.var_export($e, true));
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
//        error_log('VALUES AFTER: '.var_export($this->parameters, true));

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
            echo json_encode($this->returns)."\n";
        }
        else {
            $xml = '<?xml version="1.0" encoding="UTF-8" ?>'."\n";
            $xml .= '<RESPONSE>'."\n";
            $xml .= self::xmlize_result($this->returns, $this->function->returns_desc);
            $xml .= '</RESPONSE>'."\n";
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
//        error_log('exception: '.var_export($ex, true));
        if ($this->format == 'json') {
            echo json_encode(array('exception' => get_class($ex), 'message' => $ex->getMessage(), 'debuginfo' => (isset($ex->debuginfo) ? $ex->debuginfo : '')))."\n";
        }
        else {
            $xml = '<?xml version="1.0" encoding="UTF-8" ?>'."\n";
            $xml .= '<EXCEPTION class="'.get_class($ex).'">'."\n";
            $xml .= '<MESSAGE>'.htmlentities($ex->getMessage(), ENT_COMPAT, 'UTF-8').'</MESSAGE>'."\n";
            if (isset($ex->debuginfo)) {
                $xml .= '<DEBUGINFO>'.htmlentities($ex->debuginfo, ENT_COMPAT, 'UTF-8').'</DEBUGINFO>'."\n";
            }
            $xml .= '</EXCEPTION>'."\n";
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
        header('Expires: '. gmdate('D, d M Y H:i:s', 0) .' GMT');
        header('Pragma: no-cache');
        header('Accept-Ranges: none');
    }

    /**
     * Internal implementation - recursive function producing XML markup.
     * @param mixed $returns
     * @param $desc
     * @return unknown_type
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
                return '<VALUE null="null"/>'."\n";
            } else {
                return '<VALUE>'.htmlentities($returns, ENT_COMPAT, 'UTF-8').'</VALUE>'."\n";
            }

        } else if ($desc instanceof external_multiple_structure) {
            $mult = '<MULTIPLE>'."\n";
            if (!empty($returns)) {
                foreach ($returns as $val) {
                    $mult .= self::xmlize_result($val, $desc->content);
                }
            }
            $mult .= '</MULTIPLE>'."\n";
            return $mult;

        } else if ($desc instanceof external_single_structure) {
            $single = '<SINGLE>'."\n";
            foreach ($desc->keys as $key=>$subdesc) {
                if (!array_key_exists($key, $returns)) {
                    if ($subdesc->required == VALUE_REQUIRED) {
                        $single .= '<ERROR>Missing required key "'.$key.'"</ERROR>';
                        continue;
                    } else {
                        //optional field
                        continue;
                    }
                }
                $single .= '<KEY name="'.$key.'">'.self::xmlize_result($returns[$key], $subdesc).'</KEY>'."\n";
            }
            $single .= '</SINGLE>'."\n";
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
     * @return mixed
     */
    public function simpletest($serverurl, $function, $params) {
        return download_file_content($serverurl.'&wsfunction='.$function, null, $params);
    }
}
