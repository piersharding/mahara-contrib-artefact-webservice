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
 */

/**
 * OAuth v1 Data Store
 *
 * @package   webservice
 * @copyright  Copyright (C) 2011 Catalyst IT Ltd (http://www.catalyst.net.nz)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Piers Harding
 */

require_once(dirname(__FILE__) . '/OAuthStoreAbstract.class.php');

class OAuthStoreMahara extends OAuthStoreAbstract {

    private $session;
    /**
     * Maximum delta a timestamp may be off from a previous timestamp.
     * Allows multiple consumers with some clock skew to work with the same token.
     * Unit is seconds, default max skew is 10 minutes.
     */
    protected $max_timestamp_skew = 600;

    /**
     * Default ttl for request tokens
     */
    protected $max_request_token_ttl = 3600;

	/*
	 * Takes two options: consumer_key and consumer_secret
	 */
	public function __construct( $options = array() ) {
//		if (!session_id()) {
//			session_start();
//		}
//		if(isset($options['consumer_key']) && isset($options['consumer_secret']))
//		{
//			$this->session = &$_SESSION['oauth_' . $options['consumer_key']];
//			$this->session['consumer_key'] = $options['consumer_key'];
//			$this->session['consumer_secret'] = $options['consumer_secret'];
//			$this->session['signature_methods'] = array('HMAC-SHA1');
//			$this->session['server_uri'] = $options['server_uri'];
//			$this->session['request_token_uri'] = $options['request_token_uri'];
//			$this->session['authorize_uri'] = $options['authorize_uri'];
//			$this->session['access_token_uri'] = $options['access_token_uri'];
//
//		}
//		else
//		{
//			throw new OAuthException2("OAuthStoreMahara needs consumer_token and consumer_secret");
//		}
	}

//	public function getSecretsForVerify ( $consumer_key, $token, $token_type = 'access' ) { throw new OAuthException2("OAuthStoreMahara doesn't support " . __METHOD__); }
//	public function getSecretsForSignature ( $uri, $userid )
//	{
//		return $this->session;
//	}

//	public function getServerTokenSecrets ( $consumer_key, $token, $token_type, $userid, $name = '')
//	{
//		if ($consumer_key != $this->session['consumer_key']) {
//			return array();
//		}
//		return array(
//			'consumer_key' => $consumer_key,
//			'consumer_secret' => $this->session['consumer_secret'],
//			'token' => $token,
//			'token_secret' => $this->session['token_secret'],
//			'token_name' => $name,
//			'signature_methods' => $this->session['signature_methods'],
//			'server_uri' => $this->session['server_uri'],
//			'request_token_uri' => $this->session['request_token_uri'],
//			'authorize_uri' => $this->session['authorize_uri'],
//			'access_token_uri' => $this->session['access_token_uri'],
//			'token_ttl' => 3600,
//		);
//	}

//	public function addServerToken ( $consumer_key, $token_type, $token, $token_secret, $userid, $options = array() )
//	{
//		$this->session['token_type'] = $token_type;
//		$this->session['token'] = $token;
//		$this->session['token_secret'] = $token_secret;
//	}

//	public function deleteServer ( $consumer_key, $userid, $user_is_admin = false ) { throw new OAuthException2("OAuthStoreMahara doesn't support " . __METHOD__); }
//	public function getServer( $consumer_key, $userid, $user_is_admin = false ) {
//		return array(
//			'id' => 0,
//			'userid' => $userid,
//			'consumer_key' => $this->session['consumer_key'],
//			'consumer_secret' => $this->session['consumer_secret'],
//			'signature_methods' => $this->session['signature_methods'],
//			'server_uri' => $this->session['server_uri'],
//			'request_token_uri' => $this->session['request_token_uri'],
//			'authorize_uri' => $this->session['authorize_uri'],
//			'access_token_uri' => $this->session['access_token_uri'],
//		);
//	}

//    public function getSecretsForVerify ( $consumer_key, $token, $token_type = 'access' )  { throw new OAuthException2("OAuthStoreMahara doesn't support " . __METHOD__); }
	public function getSecretsForSignature ( $uri, $user_id ) { throw new OAuthException2("OAuthStoreMahara doesn't support " . __METHOD__); }
	public function getServerTokenSecrets ( $consumer_key, $token, $token_type, $user_id, $name = '' ) { throw new OAuthException2("OAuthStoreMahara doesn't support " . __METHOD__); }
	public function addServerToken ( $consumer_key, $token_type, $token, $token_secret, $user_id, $options = array() ) { throw new OAuthException2("OAuthStoreMahara doesn't support " . __METHOD__); }
	public function getServer( $consumer_key, $user_id, $user_is_admin = false ) { throw new OAuthException2("OAuthStoreMahara doesn't support " . __METHOD__); }
	
	public function getServerForUri ( $uri, $userid ) { throw new OAuthException2("OAuthStoreMahara doesn't support " . __METHOD__); }
	public function listServerTokens ( $userid ) { throw new OAuthException2("OAuthStoreMahara doesn't support " . __METHOD__); }
	public function countServerTokens ( $consumer_key ) { throw new OAuthException2("OAuthStoreMahara doesn't support " . __METHOD__); }
	public function getServerToken ( $consumer_key, $token, $userid ) { throw new OAuthException2("OAuthStoreMahara doesn't support " . __METHOD__); }
	public function deleteServerToken ( $consumer_key, $token, $userid, $user_is_admin = false ) {
		// TODO
	}
//
//	public function setServerTokenTtl ( $consumer_key, $token, $token_ttl )
//	{
//		//This method just needs to exist. It doesn't have to do anything!
//	}

	public function listServers ( $q = '', $userid ) { throw new OAuthException2("OAuthStoreMahara doesn't support " . __METHOD__); }
	public function updateServer ( $server, $userid, $user_is_admin = false ) { throw new OAuthException2("OAuthStoreMahara doesn't support " . __METHOD__); }

//	public function updateConsumer ( $consumer, $userid, $user_is_admin = false ) { throw new OAuthException2("OAuthStoreMahara doesn't support " . __METHOD__); }
	public function deleteConsumer ( $consumer_key, $userid, $user_is_admin = false ) { throw new OAuthException2("OAuthStoreMahara doesn't support " . __METHOD__); }
//	public function getConsumer ( $consumer_key, $userid, $user_is_admin = false ) { throw new OAuthException2("OAuthStoreMahara doesn't support " . __METHOD__); }
	public function getConsumerStatic () { throw new OAuthException2("OAuthStoreMahara doesn't support " . __METHOD__); }

//	public function addConsumerRequestToken ( $consumer_key, $options = array() ) { throw new OAuthException2("OAuthStoreMahara doesn't support " . __METHOD__); }
//	public function getConsumerRequestToken ( $token ) { throw new OAuthException2("OAuthStoreMahara doesn't support " . __METHOD__); }
	public function deleteConsumerRequestToken ( $token ) { throw new OAuthException2("OAuthStoreMahara doesn't support " . __METHOD__); }
//	public function authorizeConsumerRequestToken ( $token, $userid, $referrer_host = '' ) { throw new OAuthException2("OAuthStoreMahara doesn't support " . __METHOD__); }
	public function countConsumerAccessTokens ( $consumer_key ) { throw new OAuthException2("OAuthStoreMahara doesn't support " . __METHOD__); }
//	public function exchangeConsumerRequestForAccessToken ( $token, $options = array() ) { throw new OAuthException2("OAuthStoreMahara doesn't support " . __METHOD__); }
	public function getConsumerAccessToken ( $token, $userid ) { throw new OAuthException2("OAuthStoreMahara doesn't support " . __METHOD__); }
	public function deleteConsumerAccessToken ( $token, $userid, $user_is_admin = false ) { throw new OAuthException2("OAuthStoreMahara doesn't support " . __METHOD__); }
	public function setConsumerAccessTokenTtl ( $token, $ttl ) { throw new OAuthException2("OAuthStoreMahara doesn't support " . __METHOD__); }

//	public function listConsumers ( $userid ) { throw new OAuthException2("OAuthStoreMahara doesn't support " . __METHOD__); }
	public function listConsumerApplications( $begin = 0, $total = 25 )  { throw new OAuthException2("OAuthStoreMahara doesn't support " . __METHOD__); }
	public function listConsumerTokens ( $userid ) { throw new OAuthException2("OAuthStoreMahara doesn't support " . __METHOD__); }

//	public function checkServerNonce ( $consumer_key, $token, $timestamp, $nonce ) { throw new OAuthException2("OAuthStoreMahara doesn't support " . __METHOD__); }
//
	public function addLog ( $keys, $received, $sent, $base_string, $notes, $userid = null ) { throw new OAuthException2("OAuthStoreMahara doesn't support " . __METHOD__); }
	public function listLog ( $options, $userid ) { throw new OAuthException2("OAuthStoreMahara doesn't support " . __METHOD__); }

	public function install () { throw new OAuthException2("OAuthStoreMahara doesn't support " . __METHOD__); }




    /**
     * Find stored credentials for the consumer key and token. Used by an OAuth server
     * when verifying an OAuth request.
     *
     * @param string consumer_key
     * @param string token
     * @param string token_type     false, 'request' or 'access'
     * @exception OAuthException2 when no secrets where found
     * @return array    assoc (consumer_secret, token_secret, osr_id, ost_id, userid)
     */

//	function get_records_sql_array($sql,$values, $limitfrom='', $limitnum='') {

    public function getSecretsForVerify ($consumer_key, $token, $token_type = 'access') {
        if ($token_type === false) {
            $rs = get_records_sql_assoc('
                        SELECT  id,
                                userid              as userid,
                                externalserviceid   as externalserviceid,
                                institution         as institution,
                                consumer_key        as consumer_key,
                                consumer_secret     as consumer_secret
                        FROM {oauth_server_registry}
                        WHERE consumer_key  = ?
                          AND enabled       = 1
                        ',
                        array($consumer_key));

            if (!empty($rs)) {
                $rs = (array) array_shift($rs);
                $rs['token']        = false;
                $rs['token_secret'] = false;
                $rs['user_id']      = false;
                $rs['osr_id']       = false;
            }
        }
        else {
            $rs = get_records_sql_assoc('
                        SELECT  osr.id                as osr_id,
                                osr_id_ref,
                                ost.userid            as user_id,
                                osr.userid            as service_user,
                                osr.externalserviceid as externalserviceid,
                                osr.institution       as institution,
                                consumer_key          as consumer_key,
                                consumer_secret       as consumer_secret,
                                token                 as token,
                                token_secret          as token_secret
                        FROM {oauth_server_registry} osr
                                JOIN {oauth_server_token} ost
                                ON osr_id_ref = osr.id
                        WHERE token_type    = ?
                          AND consumer_key  = ?
                          AND token         = ?
                          AND enabled       = 1
                          AND token_ttl     >= NOW()
                        ',
                        array($token_type, $consumer_key, $token));
            if (!empty($rs)) {
                $rs = (array) array_shift($rs);
            }
        }

        if (empty($rs)) {
            throw new OAuthException2('The consumer_key "'.$consumer_key.'" token "'.$token.'" combination does not exist or is not enabled.');
        }
        return $rs;
    }


    /**
     * Find the server details for signing a request, always looks for an access token.
     * The returned credentials depend on which local user is making the request.
     *
     * The consumer_key must belong to the user or be public (user id is null)
     *
     * For signing we need all of the following:
     *
     * consumer_key         consumer key associated with the server
     * consumer_secret      consumer secret associated with this server
     * token                access token associated with this server
     * token_secret         secret for the access token
     * signature_methods    signing methods supported by the server (array)
     *
     * @todo filter on token type (we should know how and with what to sign this request, and there might be old access tokens)
     * @param string uri    uri of the server
     * @param int userid   id of the logged on user
     * @param string name   (optional) name of the token (case sensitive)
     * @exception OAuthException2 when no credentials found
     * @return array
     */
//    public function getSecretsForSignature ($uri, $userid, $name = '') {
//        // Find a consumer key and token for the given uri
//        $ps     = parse_url($uri);
//        $host   = isset($ps['host']) ? $ps['host'] : 'localhost';
//        $path   = isset($ps['path']) ? $ps['path'] : '';
//
//        if (empty($path) || substr($path, -1) != '/') {
//            $path .= '/';
//        }
//
//        // The owner of the consumer_key is either the user or nobody (public consumer key)
//        $secrets = get_records_sql_assoc('
//                    SELECT  ocr_consumer_key        as consumer_key,
//                            ocr_consumer_secret     as consumer_secret,
//                            oct_token               as token,
//                            oct_token_secret        as token_secret,
//                            ocr_signature_methods   as signature_methods
//                    FROM {oauth_consumer_registry}
//                        JOIN {oauth_consumer_token} ON oct_ocr_id_ref = ocr_id
//                    WHERE ocr_server_uri_host = ?
//                      AND ocr_server_uri_path = LEFT(?, LENGTH(ocr_server_uri_path))
//                      AND (ocr_usa_id_ref = ? OR ocr_usa_id_ref IS NULL)
//                      AND oct_token_type      = \'access\'
//                      AND oct_name            = ?
//                      AND oct_token_ttl       >= NOW()
//                    ORDER BY ocr_usa_id_ref DESC, ocr_consumer_secret DESC, LENGTH(ocr_server_uri_path) DESC
//                    LIMIT 0,1
//                    ', array($host, $path, $userid, $name)
//                    );
//
//        if (empty($secrets)) {
//            throw new OAuthException2('No server tokens available for '.$uri);
//        }
//        $secrets = array_shift($secrets);
//        $secrets['signature_methods'] = explode(',', $secrets['signature_methods']);
//        return $secrets;
//    }


    /**
     * Get the token and token secret we obtained from a server.
     *
     * @param string    consumer_key
     * @param string    token
     * @param string    token_type
     * @param int       userid         the user owning the token
     * @param string    name            optional name for a named token
     * @exception OAuthException2 when no credentials found
     * @return array
     */
//    public function getServerTokenSecrets ( $consumer_key, $token, $token_type, $userid, $name = '' ) {
//        if ($token_type != 'request' && $token_type != 'access') {
//            throw new OAuthException2('Unkown token type "'.$token_type.'", must be either "request" or "access"');
//        }
//
//        // Take the most recent token of the given type
//        $r = get_records_sql_assoc('
//                    SELECT  ocr_consumer_key        as consumer_key,
//                            ocr_consumer_secret     as consumer_secret,
//                            oct_token               as token,
//                            oct_token_secret        as token_secret,
//                            oct_name                as token_name,
//                            ocr_signature_methods   as signature_methods,
//                            ocr_server_uri          as server_uri,
//                            ocr_request_token_uri   as request_token_uri,
//                            ocr_authorize_uri       as authorize_uri,
//                            ocr_access_token_uri    as access_token_uri,
//                            IF(oct_token_ttl >= \'9999-12-31\', NULL, UNIX_TIMESTAMP(oct_token_ttl) - UNIX_TIMESTAMP(NOW())) as token_ttl
//                    FROM {oauth_consumer_registry}
//                            JOIN {oauth_consumer_token}
//                            ON oct_ocr_id_ref = ocr_id
//                    WHERE ocr_consumer_key = ?
//                      AND oct_token_type   = ?
//                      AND oct_token        = ?
//                      AND oct_usa_id_ref   = ?
//                      AND oct_token_ttl    >= NOW()
//                    ', array($consumer_key, $token_type, $token, $userid)
//                    );
//
//        if (empty($r)) {
//            throw new OAuthException2('Could not find a "'.$token_type.'" token for consumer "'.$consumer_key.'" and user '.$userid);
//        }
//        $rs = array_shift($rs);
//        if (isset($r['signature_methods']) && !empty($r['signature_methods'])) {
//            $r['signature_methods'] = explode(',',$r['signature_methods']);
//        }
//        else {
//            $r['signature_methods'] = array();
//        }
//        return $r;
//    }


    /**
     * Add a request token we obtained from a server.
     *
     * @todo remove old tokens for this user and this ocr_id
     * @param string consumer_key   key of the server in the consumer registry
     * @param string token_type     one of 'request' or 'access'
     * @param string token
     * @param string token_secret
     * @param int    userid            the user owning the token
     * @param array  options            extra options, name and token_ttl
     * @exception OAuthException2 when server is not known
     * @exception OAuthException2 when we received a duplicate token
     */
//    public function addServerToken($consumer_key, $token_type, $token, $token_secret, $userid, $options = array()) {
//        if ($token_type != 'request' && $token_type != 'access') {
//            throw new OAuthException2('Unknown token type "'.$token_type.'", must be either "request" or "access"');
//        }
//
//        // Maximum time to live for this token
//        if (isset($options['token_ttl']) && is_numeric($options['token_ttl'])) {
//            $ttl = 'DATE_ADD(NOW(), INTERVAL '.intval($options['token_ttl']).' SECOND)';
//        }
//        else if ($token_type == 'request') {
//            $ttl = 'DATE_ADD(NOW(), INTERVAL '.$this->max_request_token_ttl.' SECOND)';
//        }
//        else {
//            $ttl = "'9999-12-31'";
//        }
//
//        if (isset($options['server_uri'])) {
//            $ocr_id = get_field_sql('
//                        SELECT ocr_id
//                        FROM {oauth_consumer_registry}
//                        WHERE ocr_consumer_key = ?
//                        AND ocr_usa_id_ref = ?
//                        AND ocr_server_uri = ?
//                        ', array($consumer_key, $userid, $options['server_uri']));
//        }
//        else {
//            $ocr_id = get_field_sql('
//                        SELECT ocr_id
//                        FROM {oauth_consumer_registry}
//                        WHERE ocr_consumer_key = ?
//                        AND ocr_usa_id_ref = ?
//                        ', array($consumer_key, $userid));
//        }
//
//        if (empty($ocr_id)) {
//            throw new OAuthException2('No server associated with consumer_key "'.$consumer_key.'"');
//        }
//
//        // Named tokens, unique per user/consumer key
//        if (isset($options['name']) && $options['name'] != '') {
//            $name = $options['name'];
//        }
//        else {
//            $name = '';
//        }
//
//        // Delete any old tokens with the same type and name for this user/server combination
//        delete_records_sql('
//                    DELETE FROM {oauth_consumer_token}
//                    WHERE oct_ocr_id_ref = ?
//                      AND oct_usa_id_ref = ?
//                      AND oct_token_type = ?
//                      AND oct_name       = ?
//                    ',
//                    array($ocr_id, $userid, strtolower($token_type), $name));
//
////function insert_record($table, $dataobject, $primarykey=false, $returnpk=false) {
//        // Insert the new token
//        $ocr = (object) array(
//                        'oct_ocr_id_ref'   => $ocr_id,
//                        'oct_usa_id_ref'   => $userid,
//                        'oct_name'         => $name,
//                        'oct_token'        => $token,
//                        'oct_token_secret' => $token_secret,
//                        'oct_token_type'   => strtolower($token_type),
//                        'oct_timestamp'    => time(),
//                        'oct_token_ttl'    => $ttl,
//        );
//        $res = insert_record('oauth_consumer_token', $ocr);
////        get_records_sql_array('
////                    INSERT IGNORE INTO oauth_consumer_token
////                    SET oct_ocr_id_ref  = %d,
////                        oct_usa_id_ref  = %d,
////                        oct_name        = \'%s\',
////                        oct_token       = \'%s\',
////                        oct_token_secret= \'%s\',
////                        oct_token_type  = LOWER(\'%s\'),
////                        oct_timestamp   = NOW(),
////                        oct_token_ttl   = '.$ttl.'
////                    ',
////                    $ocr_id,
////                    $userid,
////                    $name,
////                    $token,
////                    $token_secret,
////                    $token_type);
//
////                if (!$this->query_affected_rows()) {
//        if (!$res) {
//            throw new OAuthException2('Received duplicate token "'.$token.'" for the same consumer_key "'.$consumer_key.'"');
//        }
//    }


    /**
     * Delete a server key.  This removes access to that site.
     *
     * @param string consumer_key
     * @param int userid   user registering this server
     */
    public function deleteServer($consumer_key, $userid, $user_is_admin = false) {
        if ($user_is_admin) {
            delete_records_sql('
                    DELETE FROM {oauth_server_registry}
                    WHERE consumer_key = ?
                      AND (userid = ? OR userid IS NULL)
                    ', array($consumer_key, $userid));
        }
        else {
            delete_records_sql('
                    DELETE FROM {oauth_server_registry}
                    WHERE consumer_key = ?
                      AND userid   = ?
                    ', array($consumer_key, $userid));
        }
    }


    /**
     * Get a server from the consumer registry using the consumer key
     *
     * @param string consumer_key
     * @param int userid
     * @param boolean user_is_admin (optional)
     * @exception OAuthException2 when server is not found
     * @return array
     */
//    public function getServer($consumer_key, $userid, $user_is_admin = false) {
//        $r = get_records_sql_assoc('
//                SELECT  ocr_id                  as id,
//                        ocr_usa_id_ref          as userid,
//                        ocr_consumer_key        as consumer_key,
//                        ocr_consumer_secret     as consumer_secret,
//                        ocr_signature_methods   as signature_methods,
//                        ocr_server_uri          as server_uri,
//                        ocr_request_token_uri   as request_token_uri,
//                        ocr_authorize_uri       as authorize_uri,
//                        ocr_access_token_uri    as access_token_uri
//                FROM {oauth_consumer_registry}
//                WHERE ocr_consumer_key = ?
//                  AND (ocr_usa_id_ref = ? OR ocr_usa_id_ref IS NULL)
//                ',  array($consumer_key, $userid));
//
//        if (empty($r)) {
//            throw new OAuthException2('No server with consumer_key "'.$consumer_key.'" has been registered (for this user)');
//        }
//        $r = array_shift($r);
//
//        if (isset($r['signature_methods']) && !empty($r['signature_methods'])) {
//            $r['signature_methods'] = explode(',',$r['signature_methods']);
//        }
//        else {
//            $r['signature_methods'] = array();
//        }
//        return $r;
//    }



    /**
     * Find the server details that might be used for a request
     *
     * The consumer_key must belong to the user or be public (user id is null)
     *
     * @param string uri    uri of the server
     * @param int userid   id of the logged on user
     * @exception OAuthException2 when no credentials found
     * @return array
     */
//    public function getServerForUri($uri, $userid) {
//        // Find a consumer key and token for the given uri
//        $ps     = parse_url($uri);
//        $host   = isset($ps['host']) ? $ps['host'] : 'localhost';
//        $path   = isset($ps['path']) ? $ps['path'] : '';
//
//        if (empty($path) || substr($path, -1) != '/') {
//            $path .= '/';
//        }
//
//        // The owner of the consumer_key is either the user or nobody (public consumer key)
//        $server = get_records_sql_assoc('
//                    SELECT  ocr_id                  as id,
//                            ocr_usa_id_ref          as userid,
//                            ocr_consumer_key        as consumer_key,
//                            ocr_consumer_secret     as consumer_secret,
//                            ocr_signature_methods   as signature_methods,
//                            ocr_server_uri          as server_uri,
//                            ocr_request_token_uri   as request_token_uri,
//                            ocr_authorize_uri       as authorize_uri,
//                            ocr_access_token_uri    as access_token_uri
//                    FROM {oauth_consumer_registry}
//                    WHERE ocr_server_uri_host = ?
//                      AND ocr_server_uri_path = LEFT(?, LENGTH(ocr_server_uri_path))
//                      AND (ocr_usa_id_ref = ? OR ocr_usa_id_ref IS NULL)
//                    ORDER BY ocr_usa_id_ref DESC, consumer_secret DESC, LENGTH(ocr_server_uri_path) DESC
//                    LIMIT 0,1
//                    ', array($host, $path, $userid)
//                    );
//
//        if (empty($server)) {
//            throw new OAuthException2('No server available for '.$uri);
//        }
//        $server['signature_methods'] = explode(',', $server['signature_methods']);
//        return $server;
//    }


    /**
     * Get a list of all server token this user has access to.
     *
     * @param int usr_id
     * @return array
     */
//    public function listServerTokens($userid) {
//        $ts = get_records_sql_assoc('
//                    SELECT  ocr_consumer_key        as consumer_key,
//                            ocr_consumer_secret     as consumer_secret,
//                            oct_id                  as token_id,
//                            oct_token               as token,
//                            oct_token_secret        as token_secret,
//                            oct.userid              as userid,
//                            ocr_signature_methods   as signature_methods,
//                            ocr_server_uri          as server_uri,
//                            ocr_server_uri_host     as server_uri_host,
//                            ocr_server_uri_path     as server_uri_path,
//                            ocr_request_token_uri   as request_token_uri,
//                            ocr_authorize_uri       as authorize_uri,
//                            ocr_access_token_uri    as access_token_uri,
//                            oct_timestamp           as timestamp
//                    FROM {oauth_consumer_registry} ocr
//                            JOIN {oauth_consumer_token} oct
//                            ON oct_ocr_id_ref = ocr_id
//                    WHERE oct.userid = ?
//                      AND oct_token_type = \'access\'
//                      AND oct_token_ttl  >= NOW()
//                    ORDER BY ocr_server_uri_host, ocr_server_uri_path
//                    ', array($userid));
//        return $ts;
//    }


    /**
     * Count how many tokens we have for the given server
     *
     * @param string consumer_key
     * @return int
     */
//    public function countServerTokens($consumer_key) {
//        $count = count_records_sql('
//                    SELECT COUNT(oct_id)
//                    FROM {oauth_consumer_token}
//                            JOIN {oauth_consumer_registry}
//                            ON oct_ocr_id_ref = ocr_id
//                    WHERE oct_token_type   = \'access\'
//                      AND ocr_consumer_key = ?
//                      AND oct_token_ttl    >= NOW()
//                    ', array($consumer_key));
//
//        return $count;
//    }


    /**
     * Get a specific server token for the given user
     *
     * @param string consumer_key
     * @param string token
     * @param int userid
     * @exception OAuthException2 when no such token found
     * @return array
     */
//    public function getServerToken($consumer_key, $token, $userid) {
//        $ts = get_records_sql_assoc('
//                    SELECT  ocr_consumer_key        as consumer_key,
//                            ocr_consumer_secret     as consumer_secret,
//                            oct_token               as token,
//                            oct_token_secret        as token_secret,
//                            oct_usa_id_ref          as userid,
//                            ocr_signature_methods   as signature_methods,
//                            ocr_server_uri          as server_uri,
//                            ocr_server_uri_host     as server_uri_host,
//                            ocr_server_uri_path     as server_uri_path,
//                            ocr_request_token_uri   as request_token_uri,
//                            ocr_authorize_uri       as authorize_uri,
//                            ocr_access_token_uri    as access_token_uri,
//                            oct_timestamp           as timestamp
//                    FROM {oauth_consumer_registry}
//                            JOIN {oauth_consumer_token}
//                            ON oct_ocr_id_ref = ocr_id
//                    WHERE ocr_consumer_key = ?
//                      AND oct_usa_id_ref   = ?
//                      AND oct_token_type   = \'access\'
//                      AND oct_token        = ?
//                      AND oct_token_ttl    >= NOW()
//                    ', array($consumer_key, $userid, $token));
//
//        if (empty($ts)) {
//            throw new OAuthException2('No such consumer key ('.$consumer_key.') and token ('.$token.') combination for user "'.$userid.'"');
//        }
//        $ts = array_shift($ts);
//        return $ts;
//    }


    /**
     * Delete a token we obtained from a server.
     *
     * @param string consumer_key
     * @param string token
     * @param int userid
     * @param boolean user_is_admin
     */
//    public function deleteServerToken($consumer_key, $token, $userid, $user_is_admin = false) {
//        if ($user_is_admin) {
//            delete_records_sql('
//                DELETE
//                FROM {oauth_consumer_token}
//                        JOIN {oauth_consumer_registry}
//                        ON oct_ocr_id_ref = ocr_id
//                WHERE ocr_consumer_key  = ?
//                  AND oct_token         = ?
//                ', array($consumer_key, $token));
//        }
//        else {
//            delete_records_sql('
//                DELETE
//                FROM {oauth_consumer_token}
//                        JOIN oauth_consumer_registry
//                        ON oct_ocr_id_ref = ocr_id
//                WHERE ocr_consumer_key  = ?
//                  AND oct_token         = ?
//                  AND oct_usa_id_ref    = ?
//                ', array($consumer_key, $token, $userid));
//        }
//    }


    /**
     * Set the ttl of a server access token.  This is done when the
     * server receives a valid request with a xoauth_token_ttl parameter in it.
     *
     * @param string consumer_key
     * @param string token
     * @param int token_ttl
     */
//    public function setServerTokenTtl($consumer_key, $token, $token_ttl) {
//        if ($token_ttl <= 0) {
//            // Immediate delete when the token is past its ttl
//            $this->deleteServerToken($consumer_key, $token, 0, true);
//        }
//        else {
//            // Set maximum time to live for this token
//            execute_sql('
//                        UPDATE {oauth_consumer_token}, {oauth_consumer_registry}
//                        SET ost_token_ttl = DATE_ADD(NOW(), INTERVAL ? SECOND)
//                        WHERE ocr_consumer_key  = ?
//                          AND oct_ocr_id_ref    = ocr_id
//                          AND oct_token         = ?
//                        ', array($token_ttl, $consumer_key, $token));
//        }
//    }


    /**
     * Get a list of all consumers from the consumer registry.
     * The consumer keys belong to the user or are public (user id is null)
     *
     * @param string q  query term
     * @param int userid
     * @return array
     */
//    public function listServers( $q = '', $userid) {
//        $q    = trim(str_replace('%', '', $q));
//        $args = array();
//
//        if (!empty($q)) {
//            $where = ' WHERE (  ocr_consumer_key like \'%?%\'
//                             OR ocr_server_uri like \'%?%\'
//                             OR ocr_server_uri_host like \'%?%\'
//                             OR ocr_server_uri_path like \'%?%\')
//                         AND (ocr_usa_id_ref = ? OR ocr_usa_id_ref IS NULL)
//                    ';
//
//            $args[] = $q;
//            $args[] = $q;
//            $args[] = $q;
//            $args[] = $q;
//            $args[] = $userid;
//        }
//        else {
//            $where  = ' WHERE ocr_usa_id_ref = ? OR ocr_usa_id_ref IS NULL';
//            $args[] = $userid;
//        }
//
//        $servers = get_records_sql_assoc('
//                    SELECT  ocr_id                  as id,
//                            ocr_usa_id_ref          as userid,
//                            ocr_consumer_key        as consumer_key,
//                            ocr_consumer_secret     as consumer_secret,
//                            ocr_signature_methods   as signature_methods,
//                            ocr_server_uri          as server_uri,
//                            ocr_server_uri_host     as server_uri_host,
//                            ocr_server_uri_path     as server_uri_path,
//                            ocr_request_token_uri   as request_token_uri,
//                            ocr_authorize_uri       as authorize_uri,
//                            ocr_access_token_uri    as access_token_uri
//                    FROM {oauth_consumer_registry}
//                    '.$where.'
//                    ORDER BY ocr_server_uri_host, ocr_server_uri_path
//                    ', $args);
//        return $servers;
//    }


    /**
     * Register or update a server for our site (we will be the consumer)
     *
     * (This is the registry at the consumers, registering servers ;-) )
     *
     * @param array server
     * @param int userid   user registering this server
     * @param boolean user_is_admin
     * @exception OAuthException2 when fields are missing or on duplicate consumer_key
     * @return consumer_key
     */
//    public function updateServer($server, $userid, $user_is_admin = false) {
//        foreach (array('consumer_key', 'server_uri') as $f) {
//            if (empty($server[$f])) {
//                throw new OAuthException2('The field "'.$f.'" must be set and non empty');
//            }
//        }
//
//        if (!empty($server['id'])) {
//            $exists = get_field_sql('
//                        SELECT ocr_id
//                        FROM {oauth_consumer_registry}
//                        WHERE ocr_consumer_key = ?
//                          AND ocr_id <> ?
//                          AND (ocr_usa_id_ref = ? OR ocr_usa_id_ref IS NULL)
//                        ', array($server['consumer_key'], $server['id'], $userid));
//        }
//        else {
//            $exists = get_field_sql('
//                        SELECT ocr_id
//                        FROM {oauth_consumer_registry}
//                        WHERE ocr_consumer_key = ?
//                          AND (ocr_usa_id_ref = ? OR ocr_usa_id_ref IS NULL)
//                        ', array($server['consumer_key'], $userid));
//        }
//
//        if (!empty($exists)) {
//            throw new OAuthException2('The server with key "'.$server['consumer_key'].'" has already been registered');
//        }
//
//        $parts = parse_url($server['server_uri']);
//        $host  = (isset($parts['host']) ? $parts['host'] : 'localhost');
//        $path  = (isset($parts['path']) ? $parts['path'] : '/');
//
//        if (isset($server['signature_methods'])) {
//            if (is_array($server['signature_methods'])) {
//                $server['signature_methods'] = strtoupper(implode(',', $server['signature_methods']));
//            }
//        }
//        else {
//            $server['signature_methods'] = '';
//        }
//
//        // When the user is an admin, then the user can update the userid of this record
//        if ($user_is_admin && array_key_exists('userid', $server)) {
//            if (is_null($server['userid'])) {
//                $update_user =  ', ocr_usa_id_ref = NULL';
//            }
//            else {
//                $update_user =  ', ocr_usa_id_ref = '.intval($server['userid']);
//            }
//        }
//        else {
//            $update_user = '';
//        }
//
//        if (!empty($server['id'])) {
//            // Check if the current user can update this server definition
//            if (!$user_is_admin) {
//                $ocr_usa_id_ref = get_field_sql('
//                                    SELECT ocr_usa_id_ref
//                                    FROM {oauth_consumer_registry}
//                                    WHERE ocr_id = ?
//                                    ', array($server['id']));
//
//                if ($ocr_usa_id_ref != $userid) {
//                    throw new OAuthException2('The user "'.$userid.'" is not allowed to update this server');
//                }
//            }
//
//            // Update the consumer registration
//            execute_sql('
//                    UPDATE {oauth_consumer_registry}
//                    SET ocr_consumer_key        = ?,
//                        ocr_consumer_secret     = ?,
//                        ocr_server_uri          = ?,
//                        ocr_server_uri_host     = ?,
//                        ocr_server_uri_path     = ?,
//                        ocr_timestamp           = NOW(),
//                        ocr_request_token_uri   = ?,
//                        ocr_authorize_uri       = ?,
//                        ocr_access_token_uri    = ?,
//                        ocr_signature_methods   = ?
//                        '.$update_user.'
//                    WHERE ocr_id = ?
//                    ',
//                    array($server['consumer_key'],
//                    $server['consumer_secret'],
//                    $server['server_uri'],
//                    strtolower($host),
//                    $path,
//                    (isset($server['request_token_uri']) ? $server['request_token_uri'] : ''),
//                    (isset($server['authorize_uri'])     ? $server['authorize_uri']     : ''),
//                    (isset($server['access_token_uri'])  ? $server['access_token_uri']  : ''),
//                    $server['signature_methods'],
//                    $server['id'])
//                    );
//        }
//        else {
//            if (empty($update_user)) {
//                // Per default the user owning the key is the user registering the key
//                $update_user =  ', ocr_usa_id_ref = '.intval($userid);
//            }
//
//            execute_sql('
//                    INSERT INTO {oauth_consumer_registry}
//                    SET ocr_consumer_key        = ?,
//                        ocr_consumer_secret     = ?,
//                        ocr_server_uri          = ?,
//                        ocr_server_uri_host     = ?,
//                        ocr_server_uri_path     = ?,
//                        ocr_timestamp           = NOW(),
//                        ocr_request_token_uri   = ?,
//                        ocr_authorize_uri       = ?,
//                        ocr_access_token_uri    = ?,
//                        ocr_signature_methods   = ?
//                        '.$update_user,
//                    array($server['consumer_key'],
//                    $server['consumer_secret'],
//                    $server['server_uri'],
//                    strtolower($host),
//                    $path,
//                    (isset($server['request_token_uri']) ? $server['request_token_uri'] : ''),
//                    (isset($server['authorize_uri'])     ? $server['authorize_uri']     : ''),
//                    (isset($server['access_token_uri'])  ? $server['access_token_uri']  : ''),
//                    $server['signature_methods'])
//                    );
//
////            $ocr_id = $this->query_insert_id();
//        }
//        return $server['consumer_key'];
//    }


    /**
     * Insert/update a new consumer with this server (we will be the server)
     * When this is a new consumer, then also generate the consumer key and secret.
     * Never updates the consumer key and secret.
     * When the id is set, then the key and secret must correspond to the entry
     * being updated.
     *
     * (This is the registry at the server, registering consumers ;-) )
     *
     * @param array consumer
     * @param int userid   user registering this consumer
     * @param boolean user_is_admin
     * @return string consumer key
     */
    public function updateConsumer($consumer, $userid, $user_is_admin = false) {
        if (!$user_is_admin) {
            foreach (array('requester_name', 'requester_email') as $f) {
                if (empty($consumer[$f])) {
                    throw new OAuthException2('The field "'.$f.'" must be set and non empty');
                }
            }
        }

        if (!empty($consumer['id'])) {
            if (empty($consumer['consumer_key'])) {
                throw new OAuthException2('The field "consumer_key" must be set and non empty');
            }
            if (!$user_is_admin && empty($consumer['consumer_secret'])) {
                throw new OAuthException2('The field "consumer_secret" must be set and non empty');
            }

            // Check if the current user can update this server definition
            if (!$user_is_admin) {
                $osr_usa_id_ref = get_field_sql('
                                    SELECT userid
                                    FROM {oauth_server_registry}
                                    WHERE id = ?
                                    ', array($consumer['id']));

                if ($osr_usa_id_ref != $userid) {
                    throw new OAuthException2('The user "'.$userid.'" is not allowed to update this consumer');
                }
            }
            else {
                // User is an admin, allow a key owner to be changed or key to be shared
                if (array_key_exists('userid',$consumer)) {
                    if (is_null($consumer['userid'])) {
                        execute_sql('
                            UPDATE {oauth_server_registry}
                            SET userid = NULL
                            WHERE id = ?
                            ', array($consumer['id']));
                    }
                    else {
                        execute_sql('
                            UPDATE {oauth_server_registry}
                            SET userid = ?
                            WHERE id = ?
                            ', array($consumer['userid'], $consumer['id']));
                    }
                }
            }

            execute_sql('
                UPDATE {oauth_server_registry}
                SET requester_name      = ?,
                    requester_email     = ?,
                    callback_uri        = ?,
                    application_uri     = ?,
                    application_title   = ?,
                    application_descr   = ?,
                    application_notes   = ?,
                    application_type    = ?,
                    timestamp           = NOW(),
                    institution         = ?,
                    externalserviceid   = ?
                WHERE id              = ?
                  AND consumer_key    = ?
                  AND consumer_secret = ?
                ',
                array($consumer['requester_name'],
                $consumer['requester_email'],
                (isset($consumer['callback_uri'])        ? $consumer['callback_uri']              : ''),
                (isset($consumer['application_uri'])     ? $consumer['application_uri']           : ''),
                (isset($consumer['application_title'])   ? $consumer['application_title']         : ''),
                (isset($consumer['application_descr'])   ? $consumer['application_descr']         : ''),
                (isset($consumer['application_notes'])   ? $consumer['application_notes']         : ''),
                (isset($consumer['application_type'])    ? $consumer['application_type']          : ''),
                $consumer['institution'],
                $consumer['externalserviceid'],
                $consumer['id'],
                $consumer['consumer_key'],
                $consumer['consumer_secret'])
                );


            $consumer_key = $consumer['consumer_key'];
        }
        else {
            $consumer_key   = $this->generateKey(true);
            $consumer_secret= $this->generateKey();

            // When the user is an admin, then the user can be forced to something else that the user
            if ($user_is_admin && array_key_exists('userid',$consumer)) {
                if (is_null($consumer['userid'])) {
                    $owner_id = 'NULL';
                }
                else {
                    $owner_id = intval($consumer['userid']);
                }
            }
            else {
                // No admin, take the user id as the owner id.
                $owner_id = intval($userid);
            }

            execute_sql('
                INSERT INTO {oauth_server_registry}
                   (enabled,
                    status,
                    userid,
                    institution,
                    externalserviceid,
                    consumer_key,
                    consumer_secret,
                    requester_name,
                    requester_email,
                    callback_uri,
                    application_uri,
                    application_title,
                    application_descr,
                    application_notes,
                    application_type,
                    timestamp,
                    issue_date)
                VALUES(1,
                       \'active\',
                       ?,
                       ?,
                       ?,
                       ?,
                       ?,
                       ?,
                       ?,
                       ?,
                       ?,
                       ?,
                       ?,
                       ?,
                       ?,
                       NOW(),
                       NOW())
                ',
                array($owner_id,
                $consumer['institution'],
                $consumer['externalserviceid'],
                $consumer_key,
                $consumer_secret,
                $consumer['requester_name'],
                $consumer['requester_email'],
                (isset($consumer['callback_uri'])        ? $consumer['callback_uri']              : ''),
                (isset($consumer['application_uri'])     ? $consumer['application_uri']           : ''),
                (isset($consumer['application_title'])   ? $consumer['application_title']         : ''),
                (isset($consumer['application_descr'])   ? $consumer['application_descr']         : ''),
                (isset($consumer['application_notes'])   ? $consumer['application_notes']         : ''),
                (isset($consumer['application_type'])    ? $consumer['application_type']          : ''),
//                (isset($consumer['application_commercial']) ? $consumer['application_commercial'] : 0)
                    )
                );
        }
        return $consumer_key;

    }



    /**
     * Delete a consumer key.  This removes access to our site for all applications using this key.
     *
     * @param string consumer_key
     * @param int userid   user registering this server
     * @param boolean user_is_admin
     */
//    public function deleteConsumer($consumer_key, $userid, $user_is_admin = false) {
//        if ($user_is_admin) {
//            delete_records_sql('
//                    DELETE FROM {oauth_server_registry}
//                    WHERE osr_consumer_key = ?
//                      AND (osr_usa_id_ref = ? OR osr_usa_id_ref IS NULL)
//                    ', array($consumer_key, $userid));
//        }
//        else {
//            delete_records_sql('
//                    DELETE FROM {oauth_server_registry}
//                    WHERE osr_consumer_key = ?
//                      AND osr_usa_id_ref   = ?
//                    ', array($consumer_key, $userid));
//        }
//    }



    /**
     * Fetch a consumer of this server, by consumer_key.
     *
     * @param string consumer_key
     * @param int userid
     * @param boolean user_is_admin (optional)
     * @exception OAuthException2 when consumer not found
     * @return array
     */
    public function getConsumer($consumer_key, $userid, $user_is_admin = false) {
        $consumer = get_records_sql_assoc('
                        SELECT  *
                        FROM {oauth_server_registry}
                        WHERE consumer_key = ?
                        ', array($consumer_key));

        if (empty($consumer)) {
            throw new OAuthException2('No consumer with consumer_key "'.$consumer_key.'"');
        }

        $consumer = (array) array_shift($consumer);

        if (!$user_is_admin && !empty($consumer['userid']) && $consumer['userid'] != $userid) {
            throw new OAuthException2('No access to the consumer information for consumer_key "'.$consumer_key.'"');
        }
        return $consumer;
    }


    /**
     * Fetch the static consumer key for this provider.  The user for the static consumer
     * key is NULL (no user, shared key).  If the key did not exist then the key is created.
     *
     * @return string
     */
//    public function getConsumerStatic () {
//        $consumer = get_field_sql('
//                        SELECT osr_consumer_key
//                        FROM {oauth_server_registry}
//                        WHERE osr_consumer_key LIKE \'sc-%%\'
//                          AND osr_usa_id_ref IS NULL
//                        ');
//
//        if (empty($consumer)) {
//            $consumer_key = 'sc-'.$this->generateKey(true);
//            execute_sql('
//                INSERT INTO {oauth_server_registry}
//                SET osr_enabled             = 1,
//                    osr_status              = \'active\',
//                    osr_usa_id_ref          = NULL,
//                    osr_consumer_key        = ?,
//                    osr_consumer_secret     = \'\',
//                    osr_requester_name      = \'\',
//                    osr_requester_email     = \'\',
//                    osr_callback_uri        = \'\',
//                    osr_application_uri     = \'\',
//                    osr_application_title   = \'Static shared consumer key\',
//                    osr_application_descr   = \'\',
//                    osr_application_notes   = \'Static shared consumer key\',
//                    osr_application_type    = \'\',
//                    osr_application_commercial = 0,
//                    osr_timestamp           = NOW(),
//                    osr_issue_date          = NOW()
//                ',
//                array($consumer_key)
//                );
//
//            // Just make sure that if the consumer key is truncated that we get the truncated string
//            $consumer = $this->getConsumerStatic();
//        }
//        return $consumer;
//    }


    /**
     * Add an unautorized request token to our server.
     *
     * @param string consumer_key
     * @param array options     (eg. token_ttl)
     * @return array (token, token_secret)
     */
    public function addConsumerRequestToken($consumer_key, $options = array()) {
        $token  = $this->generateKey(true);
        $secret = $this->generateKey();
        $osr_id = get_field_sql('
                        SELECT id
                        FROM {oauth_server_registry}
                        WHERE consumer_key = ?
                          AND enabled      = 1
                        ', array($consumer_key));

        if (!$osr_id) {
            throw new OAuthException2('No server with consumer_key "'.$consumer_key.'" or consumer_key is disabled');
        }

        if (isset($options['token_ttl']) && is_numeric($options['token_ttl'])) {
            $ttl = intval($options['token_ttl']);
        }
        else {
            $ttl = $this->max_request_token_ttl;
        }

        if (!isset($options['oauth_callback'])) {
            // 1.0a Compatibility : store callback url associated with request token
            $options['oauth_callback']='oob';
        }

        $ttl = date("F j, Y, g:i a", (time() + $ttl));
        execute_sql('
                INSERT INTO {oauth_server_token}
                   (osr_id_ref,
                     userid,
                     token,
                     token_secret,
                     token_type,
                     token_ttl,
                     referrer_host,
                     verifier,
                     callback_uri)
                VALUES
                   (?,
                    1,
                    ?,
                    ?,
                    \'request\',
                    ?,
                    ?,
                    ?,
                    ?)
                ', array($osr_id, $token, $secret, $ttl, getremoteaddr(), 1, $options['oauth_callback']));

        return array('token'=>$token, 'token_secret'=>$secret, 'token_ttl'=>$ttl);
    }


    /**
     * Fetch the consumer request token, by request token.
     *
     * @param string token
     * @return array  token and consumer details
     */
    public function getConsumerRequestToken($token) {
        $rs = get_records_sql_assoc('
                SELECT  token               as token,
                        token_secret        as token_secret,
                        consumer_key        as consumer_key,
                        consumer_secret     as consumer_secret,
                        token_type          as token_type,
                        ost.callback_uri    as callback_url,
                        application_title   as application_title,
                        application_descr   as application_descr,
                        application_uri     as application_uri
                FROM {oauth_server_token} ost
                        JOIN {oauth_server_registry} osr
                        ON osr_id_ref = osr.id
                WHERE token_type = \'request\'
                  AND token      = ?
                  AND token_ttl  >= NOW()
                ', array($token));

        !empty($rs) && $rs = (array) array_shift($rs);
        return $rs;
    }


    /**
     * Delete a consumer token.  The token must be a request or authorized token.
     *
     * @param string token
     */
//    public function deleteConsumerRequestToken($token) {
//        delete_records_sql('
//                    DELETE FROM {oauth_server_token}
//                    WHERE ost_token      = ?
//                      AND ost_token_type = \'request\'
//                    ', array($token));
//    }


    /**
     * Upgrade a request token to be an authorized request token.
     *
     * @param string token
     * @param int    userid  user authorizing the token
     * @param string referrer_host used to set the referrer host for this token, for user feedback
     */
    public function authorizeConsumerRequestToken($token, $userid, $referrer_host = '') {
        // 1.0a Compatibility : create a token verifier
        global $USER;
        
        $verifier = substr(md5(rand()),0,10);
        execute_sql(' 
                    UPDATE {oauth_server_token}
                    SET authorized    = 1,
                        userid        = ?,
                        timestamp     = NOW(),
                        referrer_host = ?,
                        verifier      = ?
                    WHERE token      = ?
                      AND token_type = \'request\'
                    ', array($userid, $referrer_host, $verifier, $token));
        return $verifier;
    }


    /**
     * Count the consumer access tokens for the given consumer.
     *
     * @param string consumer_key
     * @return int
     */
//    public function countConsumerAccessTokens($consumer_key) {
//        $count = get_field_sql('
//                    SELECT COUNT(ost_id)
//                    FROM {oauth_server_token}
//                            JOIN {oauth_server_registry}
//                            ON ost_osr_id_ref = osr_id
//                    WHERE ost_token_type   = \'access\'
//                      AND osr_consumer_key = ?
//                      AND ost_token_ttl    >= NOW()
//                    ', array($consumer_key));
//
//        return $count;
//    }


    /**
     * Exchange an authorized request token for new access token.
     *
     * @param string token
     * @param array options     options for the token, token_ttl
     * @exception OAuthException2 when token could not be exchanged
     * @return array (token, token_secret)
     */
    public function exchangeConsumerRequestForAccessToken($token, $options = array()) {
        $new_token  = $this->generateKey(true);
        $new_secret = $this->generateKey();

        // Maximum time to live for this token
        if (isset($options['token_ttl']) && is_numeric($options['token_ttl'])) {
            $ttl_sql = date("F j, Y, g:i a", (time() + intval($options['token_ttl'])));
        }
        else {
            $ttl_sql = '9999-12-31';
        }

        if (isset($options['verifier'])) {
            $verifier = $options['verifier'];

            // 1.0a Compatibility : check token against oauth_verifier
            $rs = get_records_sql_assoc('SELECT * FROM {oauth_server_token} 
                                            WHERE token      = ?
                          AND token_type = \'request\'
                          AND authorized = 1
                          AND token_ttl  >= NOW()
                          AND verifier = ?', array($token, $verifier));
        }
        else {

            // 1.0
            $rs = get_records_sql_assoc('SELECT * FROM {oauth_server_token} 
                                            WHERE token      = ?
                                          AND token_type = \'request\'
                                          AND authorized = 1
                                          AND token_ttl  >= NOW()', array($token));
        }
        if (empty($rs)) {
            throw new OAuthException2('Can\'t exchange request token "'.$token.'" for access token. No such token or not authorized');
        }
        $db_token = array_shift($rs);
        $db_token->token = $new_token;
        $db_token->token_secret = $new_secret;
        $db_token->token_type = 'access';
        $db_token->token_ttl = $ttl_sql;
        $db_token->timestamp = date("F j, Y, g:i a", time());
        $result = update_record('oauth_server_token', $db_token);

        if (!$result) {
            throw new OAuthException2('Can\'t exchange request token "'.$token.'" for access token. No such token or not authorized');
        }

        $ret = array('token' => $new_token, 'token_secret' => $new_secret);
        $ttl = get_field_sql('
                    SELECT token_ttl as token_ttl
                    FROM {oauth_server_token}
                    WHERE token_ttl < \'9999-12-31\' AND
                          token = ?', array($new_token));
        if ($ttl) {
            $ret['token_ttl'] = strtotime($ttl) - time();
        }
        return $ret;
    }


    /**
     * Fetch the consumer access token, by access token.
     *
     * @param string token
     * @param int userid
     * @exception OAuthException2 when token is not found
     * @return array  token and consumer details
     */
//    public function getConsumerAccessToken($token, $userid) {
//        $rs = get_record_sql_assoc('
//                SELECT  ost_token               as token,
//                        ost_token_secret        as token_secret,
//                        ost_referrer_host       as token_referrer_host,
//                        osr_consumer_key        as consumer_key,
//                        osr_consumer_secret     as consumer_secret,
//                        osr_application_uri     as application_uri,
//                        osr_application_title   as application_title,
//                        osr_application_descr   as application_descr,
//                        osr_callback_uri        as callback_uri
//                FROM {oauth_server_token}
//                        JOIN {oauth_server_registry}
//                        ON ost_osr_id_ref = osr_id
//                WHERE ost_token_type = \'access\'
//                  AND ost_token      = ?
//                  AND ost_usa_id_ref = ?
//                  AND ost_token_ttl  >= NOW()
//                ', array($token, $userid));
//
//        if (empty($rs)) {
//            throw new OAuthException2('No server_token "'.$token.'" for user "'.$userid.'"');
//        }
//        return $rs;
//    }


    /**
     * Delete a consumer access token.
     *
     * @param string token
     * @param int userid
     * @param boolean user_is_admin
     */
//    public function deleteConsumerAccessToken($token, $userid, $user_is_admin = false) {
//        if ($user_is_admin) {
//            delete_records_sql('
//                        DELETE FROM {oauth_server_token}
//                        WHERE ost_token      = ?
//                          AND ost_token_type = \'access\'
//                        ', array($token));
//        }
//        else {
//            delete_records_sql('
//                        DELETE FROM {oauth_server_token}
//                        WHERE ost_token      = ?
//                          AND ost_token_type = \'access\'
//                          AND ost_usa_id_ref = ?
//                        ', array($token, $userid));
//        }
//    }


    /**
     * Set the ttl of a consumer access token.  This is done when the
     * server receives a valid request with a xoauth_token_ttl parameter in it.
     *
     * @param string token
     * @param int ttl
     */
//    public function setConsumerAccessTokenTtl($token, $token_ttl) {
//        if ($token_ttl <= 0) {
//            // Immediate delete when the token is past its ttl
//            $this->deleteConsumerAccessToken($token, 0, true);
//        }
//        else {
//            // Set maximum time to live for this token
//            execute_sql('
//                        UPDATE {oauth_server_token}
//                        SET ost_token_ttl = DATE_ADD(NOW(), INTERVAL ? SECOND)
//                        WHERE ost_token      = ?
//                          AND ost_token_type = \'access\'
//                        ', array($token_ttl, $token));
//        }
//    }


    /**
     * Fetch a list of all consumer keys, secrets etc.
     * Returns the public (userid is null) and the keys owned by the user
     *
     * @param int userid
     * @return array
     */
    public function listConsumers($userid) {
        $rs = get_records_sql_assoc('
                SELECT  osr.id              as id,
                        userid              as userid,
                        institution         as institution,
                        externalserviceid   as externalserviceid,
                        u.username          as username,
                        u.email             as email,
                        consumer_key        as consumer_key,
                        consumer_secret     as consumer_secret,
                        enabled             as enabled,
                        status              as status,
                        issue_date          as issue_date,
                        application_uri     as application_uri,
                        application_title   as application_title,
                        application_descr   as application_descr,
                        requester_name      as requester_name,
                        requester_email     as requester_email,
                        callback_uri        as callback_uri
                FROM {oauth_server_registry} osr
                JOIN {usr} u
                ON osr.userid = u.id
                WHERE (userid = ? OR userid IS NULL)
                ORDER BY application_title
                ', array($userid));
        return $rs;
    }

    /**
     * List of all registered applications. Data returned has not sensitive
     * information and therefore is suitable for public displaying.
     *
     * @param int $begin
     * @param int $total
     * @return array
     */
//    public function listConsumerApplications($begin = 0, $total = 25) {
//        $rs = get_records_sql_assoc('
//                SELECT  osr_id                  as id,
//                        osr_enabled             as enabled,
//                        osr_status              as status,
//                        osr_issue_date          as issue_date,
//                        osr_application_uri     as application_uri,
//                        osr_application_title   as application_title,
//                        osr_application_descr   as application_descr
//                FROM {oauth_server_registry}
//                ORDER BY osr_application_title
//                ');
//        // TODO: pagination
//        return $rs;
//    }

    /**
     * Fetch a list of all consumer tokens accessing the account of the given user.
     *
     * @param int userid
     * @return array
     */
//    public function listConsumerTokens($userid) {
//        $rs = get_records_sql_assoc('
//                SELECT  osr_consumer_key        as consumer_key,
//                        osr_consumer_secret     as consumer_secret,
//                        osr_enabled             as enabled,
//                        osr_status              as status,
//                        osr_application_uri     as application_uri,
//                        osr_application_title   as application_title,
//                        osr_application_descr   as application_descr,
//                        ost_timestamp           as timestamp,
//                        ost_token               as token,
//                        ost_token_secret        as token_secret,
//                        ost_referrer_host       as token_referrer_host,
//                        osr_callback_uri        as callback_uri
//                FROM {oauth_server_registry}
//                    JOIN {oauth_server_token}
//                    ON ost_osr_id_ref = osr_id
//                WHERE ost_usa_id_ref = ?
//                  AND ost_token_type = \'access\'
//                  AND ost_token_ttl  >= NOW()
//                ORDER BY osr_application_title
//                ', array($userid));
//        return $rs;
//    }


    /**
     * Check an nonce/timestamp combination.  Clears any nonce combinations
     * that are older than the one received.
     *
     * @param string    consumer_key
     * @param string    token
     * @param int       timestamp
     * @param string    nonce
     * @exception OAuthException2   thrown when the timestamp is not in sequence or nonce is not unique
     */
    public function checkServerNonce($consumer_key, $token, $timestamp, $nonce) {
        $high_water = date("F j, Y, g:i a", ($timestamp + $this->max_timestamp_skew));
        $r = get_records_sql_assoc('
                            SELECT MAX(timestamp) AS max_stamp, CAST(MAX(timestamp) > ? AS INTEGER) AS max_highwater
                            FROM {oauth_server_nonce}
                            WHERE consumer_key = ?
                              AND token        = ?
                            ', array($high_water, $consumer_key, $token));

        $r = (array) array_shift($r);
        if (!empty($r) && $r['max_highwater']) { // XXX need to make this portable
            throw new OAuthException2('Timestamp is out of sequence. Request rejected. Got '.$timestamp.' last max is '.$r['max_stamp'].' allowed skew is '.$this->max_timestamp_skew);
        }

        // Insert the new combination
        $timestamp_fmt = date("F j, Y, g:i a", $timestamp);
        $result = execute_sql('
                INSERT INTO {oauth_server_nonce} 
                  ( consumer_key,
                    token,
                    timestamp,
                    nonce )
                    VALUES (?, ?, ?, ?)
                ', array($consumer_key, $token, $timestamp_fmt, $nonce));

        if (!$result) {
            throw new OAuthException2('Duplicate timestamp/nonce combination, possible replay attack.  Request rejected.');
        }

        // Clean up all timestamps older than the one we just received
        $low_water = date("F j, Y, g:i a", ($timestamp - $this->max_timestamp_skew));
        delete_records_sql('
                DELETE FROM oauth_server_nonce
                WHERE consumer_key  = ?
                  AND token         = ?
                  AND timestamp     < ?
                ', array($consumer_key, $token, $low_water));
    }


//    /**
//     * Add an entry to the log table
//     *
//     * @param array keys (osr_consumer_key, ost_token, ocr_consumer_key, oct_token)
//     * @param string received
//     * @param string sent
//     * @param string base_string
//     * @param string notes
//     * @param int (optional) userid
//     */
//    public function addLog ( $keys, $received, $sent, $base_string, $notes, $userid = null )
//    {
//        $args = array();
//        $ps   = array();
//        foreach ($keys as $key => $value)
//        {
//            $args[] = $value;
//            $ps[]   = "olg_$key = '%s'";
//        }
//
//        if (!empty($_SERVER['REMOTE_ADDR']))
//        {
//            $remote_ip = $_SERVER['REMOTE_ADDR'];
//        }
//        else if (!empty($_SERVER['REMOTE_IP']))
//        {
//            $remote_ip = $_SERVER['REMOTE_IP'];
//        }
//        else
//        {
//            $remote_ip = '0.0.0.0';
//        }
//
//        // Build the SQL
//        $ps[] = "olg_received   = '%s'";                        $args[] = $this->makeUTF8($received);
//        $ps[] = "olg_sent       = '%s'";                        $args[] = $this->makeUTF8($sent);
//        $ps[] = "olg_base_string= '%s'";                        $args[] = $base_string;
//        $ps[] = "olg_notes      = '%s'";                        $args[] = $this->makeUTF8($notes);
//        $ps[] = "olg_usa_id_ref = NULLIF(%d,0)";                $args[] = $userid;
//        $ps[] = "olg_remote_ip  = IFNULL(INET_ATON('%s'),0)";   $args[] = $remote_ip;
//
//        $this->query('INSERT INTO oauth_log SET '.implode(',', $ps), $args);
//    }
//
//
//    /**
//     * Get a page of entries from the log.  Returns the last 100 records
//     * matching the options given.
//     *
//     * @param array options
//     * @param int userid   current user
//     * @return array log records
//     */
//    public function listLog ( $options, $userid )
//    {
//        $where = array();
//        $args  = array();
//        if (empty($options))
//        {
//            $where[] = 'olg_usa_id_ref = %d';
//            $args[]  = $userid;
//        }
//        else
//        {
//            foreach ($options as $option => $value)
//            {
//                if (strlen($value) > 0)
//                {
//                    switch ($option)
//                    {
//                    case 'osr_consumer_key':
//                    case 'ocr_consumer_key':
//                    case 'ost_token':
//                    case 'oct_token':
//                        $where[] = 'olg_'.$option.' = \'%s\'';
//                        $args[]  = $value;
//                        break;
//                    }
//                }
//            }
//
//            $where[] = '(olg_usa_id_ref IS NULL OR olg_usa_id_ref = %d)';
//            $args[]  = $userid;
//        }
//
//        $rs = $this->query_all_assoc('
//                    SELECT olg_id,
//                            olg_osr_consumer_key    AS osr_consumer_key,
//                            olg_ost_token           AS ost_token,
//                            olg_ocr_consumer_key    AS ocr_consumer_key,
//                            olg_oct_token           AS oct_token,
//                            olg_usa_id_ref          AS userid,
//                            olg_received            AS received,
//                            olg_sent                AS sent,
//                            olg_base_string         AS base_string,
//                            olg_notes               AS notes,
//                            olg_timestamp           AS timestamp,
//                            INET_NTOA(olg_remote_ip) AS remote_ip
//                    FROM oauth_log
//                    WHERE '.implode(' AND ', $where).'
//                    ORDER BY olg_id DESC
//                    LIMIT 0,100', $args);
//
//        return $rs;
//    }

}


require_once(get_config('docroot').'/artefact/webservice/libs/moodlelib.php');
/**
 * Returns most reliable client address
 *
 * @global object
 * @param string $default If an address can't be determined, then return this
 * @return string The remote IP address
 */
//function getremoteaddr($default='0.0.0.0') {
//
//    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
//        $address = $_SERVER['HTTP_CLIENT_IP'];
//        return $address ? $address : $default;
//    }
//    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
//        $address = $_SERVER['HTTP_X_FORWARDED_FOR'];
//        return $address ? $address : $default;
//    }
//    if (!empty($_SERVER['REMOTE_ADDR'])) {
//        $address = $_SERVER['REMOTE_ADDR'];
//        return $address ? $address : $default;
//    } else {
//        return $default;
//    }
//}

?>
