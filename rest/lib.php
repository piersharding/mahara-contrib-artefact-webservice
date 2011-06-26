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
 * @author     Piers Harding
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 */

require_once(get_config('docroot').'/artefact/webservice/libs/moodlelib.php');
require_once(get_config('docroot').'/artefact/webservice/libs/weblib.php');
require_once(get_config('docroot')."/artefact/webservice/libs/filelib.php");
/**
 * Mahara REST client class
 * TODO: XML to PHP
 */
class webservice_rest_client {

    private $serverurl;
    private $auth;

    /**
     * Constructor
     * @param string $serverurl a Mahara URL
     * @param array $auth
     */
    public function __construct($serverurl, $auth) {
        $this->serverurl = $serverurl;
        $this->set_auth($auth);
    }

    /**
     * Set the auth values used to do the REST call
     * @param array $auth
     */
    public function set_auth($auth) {
        $values = array();
        foreach ($auth as $k => $v) {
            $values[]= "$k=".urlencode($v);
        }
        $this->auth = implode('&', $values);
    }

    /**
     * Execute client WS request with token authentication
     * @param string $functionname
     * @param array $params
     * @param bool $json
     * @return mixed
     */
    public function call($functionname, $params, $json=false) {
        global $CFG;

        if ($json) {
            $data = json_encode($params);
            $url = $this->serverurl . '?'.$this->auth.'&wsfunction=' . $functionname . '&alt=json';
            $result = file_get_contents ($url, false, stream_context_create (array ('http'=>array ('method'=>'POST'
                    , 'header'=>"Content-Type: application/json\r\nConnection: close\r\nContent-Length: ".strlen($data)."\r\n"
                    , 'content'=>$data
                    ))));
            $values = (array)json_decode($result);
            $result = array();
            foreach ($values as $k => $v) {
                $result[$k] = (is_object($v) ? (array)$v : $v);
            }
            return $result;
        }

        $result = download_file_content($this->serverurl
                        . '?'.$this->auth.'&wsfunction='
                        . $functionname, null, $params);

        //TODO : transform the XML result into PHP values - MDL-22965
        $xml2array = new xml2array($result);
        $raw = $xml2array->getResult();
//        var_dump($raw);

        if (isset($raw['EXCEPTION'])) {
            $debug = isset($raw['EXCEPTION']['DEBUGINFO']) ? $raw['EXCEPTION']['DEBUGINFO']['#text'] : '';
            throw new Exception('REST error: '.$raw['EXCEPTION']['MESSAGE']['#text'].
                                ' ('.$raw['EXCEPTION']['@class'].') '.$debug);
        }

        $result = array();
        if (isset($raw['RESPONSE'])) {
            $node = $raw['RESPONSE'];
            if (isset($node['MULTIPLE'])) {
                $result = self::recurse_structure($node['MULTIPLE']);
            }
            else if (isset($raw['RESPONSE']['SINGLE'])) {
                $result = $raw['RESPONSE']['SINGLE'];
            }
            else {
                // empty result ?
                $result = $raw['RESPONSE'];
            }
        }
        return $result;
    }

    private static function recurse_structure($node) {
        $result = array();
        if (isset($node['SINGLE']['KEY'])) {
            foreach ($node['SINGLE']['KEY'] as $element) {
                if (isset($element['MULTIPLE'])) {
                    $item[$element['@name']] = self::recurse_structure($element['MULTIPLE']);
                }
                else {
                    $item[$element['@name']] = (isset($element['VALUE']['#text']) ? $element['VALUE']['#text'] : '');
                }
            }
            $result[]= $item;
        }
        else {
            if (isset($node['SINGLE'])) {
                foreach($node['SINGLE'] as $single) {
                    $item = array();
                    $single = array_shift($single);
                    foreach ($single as $element) {
                        if (isset($element['MULTIPLE'])) {
                            $item[$element['@name']] = self::recurse_structure($element['MULTIPLE']);
                        }
                        else {
                            $item[$element['@name']] = (isset($element['VALUE']['#text']) ? $element['VALUE']['#text'] : '');
                        }
                    }
                    $result[]= $item;
                }
            }
        }
        return $result;
    }

}