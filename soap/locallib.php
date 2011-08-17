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
 * SOAP web service implementation classes and methods.
 *
 * @package   webservice
 * @copyright 2009 Moodle Pty Ltd (http://moodle.com)
 * @copyright  Copyright (C) 2011 Catalyst IT Ltd (http://www.catalyst.net.nz)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Piers Harding
 */

require_once(get_config('docroot')."/artefact/webservice/locallib.php");

 // must not cache wsdl - the list of functions is created on the fly
ini_set('soap.wsdl_cache_enabled', '0');
require_once 'Zend/Soap/Server.php';
require_once 'Zend/Soap/AutoDiscover.php';

class Zend_Soap_Server_Local extends Zend_Soap_Server {

    /**
     * Handle a request
     *
     * Instantiates SoapServer object with options set in object, and
     * dispatches its handle() method.
     *
     * $request may be any of:
     * - DOMDocument; if so, then cast to XML
     * - DOMNode; if so, then grab owner document and cast to XML
     * - SimpleXMLElement; if so, then cast to XML
     * - stdClass; if so, calls __toString() and verifies XML
     * - string; if so, verifies XML
     *
     * If no request is passed, pulls request using php:://input (for
     * cross-platform compatability purposes).
     *
     * @param DOMDocument|DOMNode|SimpleXMLElement|stdClass|string $request Optional request
     * @return void|string
     */
    public function handle($request = null)
    {
        if (null === $request) {
            $request = file_get_contents('php://input');
        }

        // Set Zend_Soap_Server error handler
        $displayErrorsOriginalState = $this->_initializeSoapErrorContext();

        $setRequestException = null;
        /**
         * @see Zend_Soap_Server_Exception
         */
        require_once 'Zend/Soap/Server/Exception.php';
        try {
            $this->_setRequest($request);
        } catch (Zend_Soap_Server_Exception $e) {
            $setRequestException = $e;
        }

        $soap = $this->_getSoap();

        ob_start();
        if($setRequestException instanceof Exception) {
            // Send SOAP fault message if we've catched exception
            $soap->fault("Sender", $setRequestException->getMessage());
        } else {
            try {
                $soap->handle($request);
            } catch (Exception $e) {
                $fault = $this->fault($e);
                if (isset($e->debuginfo)) {
                    $fault->faultstring .= ' '.$e->debuginfo;
                }
                $soap->fault($fault->faultcode, $fault->faultstring);
            }
        }
        $this->_response = ob_get_clean();

        // Restore original error handler
        restore_error_handler();
        ini_set('display_errors', $displayErrorsOriginalState);

        if (!$this->_returnResponse) {
            echo $this->_response;
            return;
        }

        return $this->_response;
    }
}

/**
 * SOAP service server implementation.
 * @author Petr Skoda (skodak)
 */
class webservice_soap_server extends webservice_zend_server {

    private $payload_signed = false;
    private $payload_encrypted = false;
    public $publickey = null;

    /**
     * Contructor
     * @param bool $simple use simple authentication
     */
    public function __construct($authmethod) {
         // must not cache wsdl - the list of functions is created on the fly
        ini_set('soap.wsdl_cache_enabled', '0');
        require_once 'Zend/Soap/Server.php';
        require_once 'Zend/Soap/AutoDiscover.php';

        if (optional_param('wsdl', 0, PARAM_BOOL)) {
            parent::__construct($authmethod, 'Zend_Soap_AutoDiscover');
        } else {
            parent::__construct($authmethod, 'Zend_Soap_Server_Local');
        }
        $this->wsname = 'soap';
    }

    /**
     * Set up zend service class
     * @return void
     */
    protected function init_zend_server() {
        global $CFG;

        parent::init_zend_server();

        if ($this->authmethod == WEBSERVICE_AUTHMETHOD_USERNAME) {
            $username = optional_param('wsusername', '', PARAM_RAW);
            $password = optional_param('wspassword', '', PARAM_RAW);
            // aparently some clients and zend soap server does not work well with "&" in urls :-(
            //TODO: the zend error has been fixed in the last Zend SOAP version, check that is fixed and remove obsolete code
            $url = $CFG->wwwroot.'artefact/webservice/soap/simpleserver.php/'.urlencode($username).'/'.urlencode($password);
            // the Zend server is using this uri directly in xml - weird :-(
            $this->zend_server->setUri(htmlentities($url));
        } else {
            $wstoken = optional_param('wstoken', '', PARAM_RAW);
            $url = $CFG->wwwroot.'artefact/webservice/soap/server.php?wstoken='.urlencode($wstoken);
            // the Zend server is using this uri directly in xml - weird :-(
            $this->zend_server->setUri(htmlentities($url));
        }

        if (!optional_param('wsdl', 0, PARAM_BOOL)) {
            $this->zend_server->setReturnResponse(true);
            //TODO: the error handling in Zend Soap server is useless, XML-RPC is much, much better :-(
            $this->zend_server->registerFaultException('MaharaException');
            $this->zend_server->registerFaultException('UserException');
            $this->zend_server->registerFaultException('NotFoundException');
            $this->zend_server->registerFaultException('SystemException');
            $this->zend_server->registerFaultException('InvalidArgumentException');
            $this->zend_server->registerFaultException('AccessDeniedException');
            $this->zend_server->registerFaultException('ParameterException');
            $this->zend_server->registerFaultException('mahara_ws_exception');
            $this->zend_server->registerFaultException('webservice_parameter_exception');
            $this->zend_server->registerFaultException('invalid_parameter_exception');
            $this->zend_server->registerFaultException('invalid_response_exception');
            $this->zend_server->registerFaultException('webservice_access_exception');
        }
    }

    /**
     * For SOAP - we want to inspect for auth headers
     * and do decrypt / sigs
     *
     * @return $xml
     */
    protected function modify_payload() {

        $xml = null;

        // don't do any of this if we are in the WSDL phase
        if (optional_param('wsdl', 0, PARAM_BOOL)) {
            return $xml;
        }

        // check for encryption and signatures
        if ($this->authmethod == WEBSERVICE_AUTHMETHOD_PERMANENT_TOKEN) {
            // we need the token so that we can find the key
            if (!$dbtoken = get_record('external_tokens', 'token', $this->token, 'tokentype', EXTERNAL_TOKEN_PERMANENT)) {
                // log failed login attempts
                ws_add_to_log(0, 'webservice', get_string('tokenauthlog', 'artefact.webservice'), '' , get_string('failedtolog', 'artefact.webservice').": ".$this->token. " - ".getremoteaddr() , 0);
                throw new webservice_access_exception(get_string('invalidtoken', 'artefact.webservice'));
            }
            // is WS-Security active ?
            if ($dbtoken->wssigenc) {
                $this->publickey = $dbtoken->publickey;
            }
        }
        else if ($this->authmethod == WEBSERVICE_AUTHMETHOD_USERNAME && !empty($this->username)) {
            // get the user
            $user = get_record('usr', 'username', $this->username);
            if (empty($user)) {
                throw new webservice_access_exception(get_string('wrongusernamepassword', 'artefact.webservice'));
            }
            // get the institution from the external user
            $ext_user = get_record('external_services_users', 'userid', $user->id);
            if (empty($ext_user)) {
                throw new webservice_access_exception(get_string('wrongusernamepassword', 'artefact.webservice'));
            }
            // is WS-Security active ?
            if ($ext_user->wssigenc) {
                $this->publickey = $ext_user->publickey;
            }
        }

        // only both if we can find a public key
        if (!empty($this->publickey)) {
            // A singleton provides our site's SSL info
            require_once(get_config('docroot').'/api/xmlrpc/lib.php');
            $HTTP_RAW_POST_DATA = file_get_contents('php://input');
            $openssl = OpenSslRepo::singleton();
            $payload                 = $HTTP_RAW_POST_DATA;
            $this->payload_encrypted = false;
            $this->payload_signed    = false;

            try {
                $xml = new SimpleXMLElement($payload);
            } catch (Exception $e) {
                throw new XmlrpcServerException('Payload is not a valid XML document', 6001);
            }

            // Cascading switch. Kinda.
            try {
                if ($xml->getName() == 'encryptedMessage') {
                    $this->payload_encrypted = true;
                    $payload                 = xmlenc_envelope_strip($xml);
                }

                if ($xml->getName() == 'signedMessage') {
                    $this->payload_signed = true;

                    $signature      = base64_decode($xml->Signature->SignatureValue);
                    $payload        = base64_decode($xml->object);
                    $timestamp      = $xml->timestamp;

                    // Does the signature match the data and the public cert?
                    $signature_verified = openssl_verify($payload, $signature, $this->publickey);
                    if ($signature_verified == 1) {
                        // Parse the XML
                        try {
                            $xml = new SimpleXMLElement($payload);
                        } catch (Exception $e) {
                            throw new MaharaException('Signed payload is not a valid XML document', 6007);
                        }
                    }
                    else {
                        throw new MaharaException('An error occurred while trying to verify your message signature', 6004);
                    }
                }
                $xml = $payload;
            }
            catch (CryptException $e) {
                if ($e->getCode() == 7025) {
                    // The key they used to contact us is old, respond with the new key correctly
                    // This sucks. Error handling of our mnet code needs to improve
                    ob_start();
                    xmlrpc_error($e->getMessage(), $e->getCode());
                    $response = ob_get_contents();
                    ob_end_clean();

                    // Sign and encrypt our response, even though we don't know if the
                    // request was signed and encrypted
                    $response = xmldsig_envelope($response);
                    $response = xmlenc_envelope($response, $this->publickey);
                    $xml = $response;
                }
            }
        }

        // standard auth
        if ((!isset($_REQUEST['wsusername']) && $this->authmethod == WEBSERVICE_AUTHMETHOD_USERNAME) || !empty($this->publickey)) {
            // wsse auth
            // we may already have the xml if sig/enc
            if (empty($xml)) {
                $xml = file_get_contents('php://input');
            }
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
//                        error_log('username/password is wsse: '.$this->username.'/'.$this->password);
                        $this->authmethod = WEBSERVICE_AUTHMETHOD_USERNAME;
                    }
                }
            }
        }

        return $xml;
    }

    /**
     * This method parses the $_REQUEST superglobal and looks for
     * the following information:
     *  1/ user authentication - username+password or token (wsusername, wspassword and wstoken parameters)
     *
     * @return void
     */
    protected function parse_request() {
        parent::parse_request();

        if (!$this->username or !$this->password) {
            //note: this is the workaround for the trouble with & in soap urls
            $authdata = get_file_argument();
            $authdata = explode('/', trim($authdata, '/'));
            if (count($authdata) == 2) {
                list($this->username, $this->password) = $authdata;
            }
        }
    }

    /**
     * Send the error information to the WS client
     * formatted as XML document.
     * @param exception $ex
     * @return void
     */
    protected function send_error($ex=null) {
        // Zend Soap server fault handling is incomplete compared to XML-RPC :-(
        // we can not use: echo $this->zend_server->fault($ex);
        //TODO: send some better response in XML
        if ($ex) {
            $info = $ex->getMessage();
            if (isset($ex->debuginfo)) {
                $info .= ' - '.$ex->debuginfo;
            }
        } else {
            $info = 'Unknown error';
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">
<SOAP-ENV:Body><SOAP-ENV:Fault>
<faultcode>MAHARA:error</faultcode>
<faultstring>'.$info.'</faultstring>
</SOAP-ENV:Fault></SOAP-ENV:Body></SOAP-ENV:Envelope>';

        $this->send_headers();
        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: inline; filename="response.xml"');

        echo $xml;
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
        if (!empty($this->publickey)) {
            // do sigs + encrypt
            require_once(get_config('docroot').'/api/xmlrpc/lib.php');
            $openssl = OpenSslRepo::singleton();
            if ($this->payload_signed) {
                // Sign and encrypt our response, even though we don't know if the
                // request was signed and encrypted
                $response = xmldsig_envelope($response);
            }
            if ($this->payload_encrypted) {
                $response = xmlenc_envelope($response, $this->publickey);
            }
        }
        return $response;
    }
}

/**
 * SOAP test client class
 */
class webservice_soap_test_client implements webservice_test_client_interface {
    /**
     * Execute test client WS request
     * @param string $serverurl
     * @param string $function
     * @param array $params
     * @return mixed
     */
    public function simpletest($serverurl, $function, $params) {
        //zend expects 0 based array with numeric indexes
        $params = array_values($params);
        require_once 'Zend/Soap/Client.php';
        $client = new Zend_Soap_Client($serverurl.'&wsdl=1');
        return $client->__call($function, $params);
    }
}
