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

    return (preg_match('#^[-!\#$%&\'*+\\/0-9=?A-Z^_`a-z{|}~]+' .
                 '(\.[-!\#$%&\'*+\\/0-9=?A-Z^_`a-z{|}~]+)*' .
                  '@' .
                  '[-!\#$%&\'*+\\/0-9=?A-Z^_`a-z{|}~]+\.' .
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
 * and removes any nasty tags that could mess up pages.
 *
 * NOTE: the format parameter was deprecated because we can safely clean only HTML.
 *
 * @param string $text The text to be cleaned
 * @return string The cleaned up text
 */
function clean_text($text) {
    global $ALLOWED_TAGS, $CFG;

    if (empty($text) or is_numeric($text)) {
       return (string)$text;
    }

    $text = clean_html($text);

    // Remove potential script events - some extra protection for undiscovered bugs in our code
    $text = preg_replace("~([^a-z])language([[:space:]]*)=~i", "$1Xlanguage=", $text);
    $text = preg_replace("~([^a-z])on([a-z]+)([[:space:]]*)=~i", "$1Xon$2=", $text);

    return $text;
}

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
            throw new invalid_parameter_exception($debuginfo.' value: NULL');
        }
    }
    if (is_array($param) or is_object($param)) {
        throw new invalid_parameter_exception($debuginfo.' value: IS ARRAY or IS OBJECT');
    }

    $cleaned = clean_param($param, $type);
    if ((string)$param !== (string)$cleaned) {
        // conversion to string is usually lossless
        throw new invalid_parameter_exception($debuginfo.' value: '.$param);
    }

    return $cleaned;
}

/**
 * clean the variables and/or cast to specific types, based on
 * an options field.
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
            $param = clean_text($param);     // Sweep for scripts, etc
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
                } elseif (preg_match('/^' . preg_quote($CFG->wwwroot, '/') . '/i',$param)) {
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

        default:
            // throw error, switched parameters in clean or another serious problem
           throw new Exception("unknownparamtype: ".$type);
    }
}
