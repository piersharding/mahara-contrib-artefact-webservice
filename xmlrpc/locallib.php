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
 * XML-RPC web service implementation classes and methods.
 *
 * @package   webservice
 * @copyright 2009 Moodle Pty Ltd (http://moodle.com)
 * @copyright  Copyright (C) 2011 Catalyst IT Ltd (http://www.catalyst.net.nz)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Piers Harding
 */

require_once(get_config('docroot')."/artefact/webservice/locallib.php");

/**
 * XML-RPC service server implementation.
 * @author Petr Skoda (skodak)
 */
class webservice_xmlrpc_server extends webservice_zend_server {
    /**
     * Contructor
     * @param integer $authmethod authentication method one of WEBSERVICE_AUTHMETHOD_*
     */
    public function __construct($authmethod) {
        require_once 'Zend/XmlRpc/Server.php';
        parent::__construct($authmethod, 'Zend_XmlRpc_Server');
        $this->wsname = 'xmlrpc';
    }

    /**
     * Set up zend service class
     * @return void
     */
    protected function init_zend_server() {
        parent::init_zend_server();
        // this exception indicates request failed
        Zend_XmlRpc_Server_Fault::attachFaultException('mahara_ws_exception');
        Zend_XmlRpc_Server_Fault::attachFaultException('MaharaException');
        Zend_XmlRpc_Server_Fault::attachFaultException('UserException');
        Zend_XmlRpc_Server_Fault::attachFaultException('SystemException');
        Zend_XmlRpc_Server_Fault::attachFaultException('ParameterException');
        Zend_XmlRpc_Server_Fault::attachFaultException('webservice_parameter_exception');
        Zend_XmlRpc_Server_Fault::attachFaultException('invalid_parameter_exception');
        Zend_XmlRpc_Server_Fault::attachFaultException('invalid_response_exception');
    }

}

/**
 * XML-RPC test client class
 */
class webservice_xmlrpc_test_client implements webservice_test_client_interface {
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

        require_once 'Zend/XmlRpc/Client.php';
        $client = new Zend_XmlRpc_Client($serverurl);
        return $client->call($function, $params);
    }
}