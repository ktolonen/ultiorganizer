<?php
/*
***************************************************************************
*   Copyright (C) 2007-2008 by Sixdegrees                                 *
*   cesar@sixdegrees.com.br                                               *
*   "Working with freedom"                                                *
*   http://www.sixdegrees.com.br                                          *
*                                                                         *
*   Modified by Ethan Smith (ethan@3thirty.net), April 2008               *
*      - Added support for non-standard port numbers (rewrote cleanURL)   *
*      - getFileLogs will now include an array of files, if multiple      *
*        have been modified files are                                     *
*      - added setRepository method, to fix mis-spelling of old           *
*        setRespository method                                            *
*      - various bugfixes (out by one error on getFileLogs)               *
*                                                                         *
*   Permission is hereby granted, free of charge, to any person obtaining *
*   a copy of this software and associated documentation files (the       *
*   "Software"), to deal in the Software without restriction, including   *
*   without limitation the rights to use, copy, modify, merge, publish,   *
*   distribute, sublicense, and/or sell copies of the Software, and to    *
*   permit persons to whom the Software is furnished to do so, subject to *
*   the following conditions:                                             *
*                                                                         *
*   The above copyright notice and this permission notice shall be        *
*   included in all copies or substantial portions of the Software.       *
*                                                                         *
*   THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,       *
*   EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF    *
*   MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.*
*   IN NO EVENT SHALL THE AUTHORS BE LIABLE FOR ANY CLAIM, DAMAGES OR     *
*   OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, *
*   ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR *
*   OTHER DEALINGS IN THE SOFTWARE.                                       *
***************************************************************************
*/
define("PHPSVN_DIR",dirname(__FILE__) );

require(PHPSVN_DIR."/http.php");
require(PHPSVN_DIR."/xml_parser.php");
require(PHPSVN_DIR."/definitions.php");


/**
 *  PHP SVN CLIENT
 *
 *  This class is a SVN client. It can perform read operations
 *  to a SVN server (over Web-DAV). 
 *  It can get directory files, file contents, logs. All the operaration
 *  could be done for a specific version or for the last version.
 *
 *  @author Cesar D. Rodas <cesar@sixdegrees.com.br>
 *  @license BSD License
 */
class phpSVNclient 
{
    /**
     *  SVN Repository URL
     *
     *  @var string
     *  @access private
     */
    var $_url;
    /**
     *  Cache, for don't request the same thing in a
     *  short period of time.
     *
     *  @var string
     *  @access private
     */
    var $_cache;
    /**
     *  HTTP Client object
     *
     *  @var object
     *  @access private
     */
    var $_http;
    /**
     *  Respository Version.
     *
     *  @access private
     *  @var interger
     */
    var $_repVersion;
    /**
     *  Password
     *
     *  @access private
     *  @var string
     */
    var $pass;
    /**
     *  Password
     *
     *  @access private
     *  @var string
     */
    var $user;
    /**
     *  Last error number
     *
     *  Possible values are NOT_ERROR, NOT_FOUND, AUTH_REQUIRED, UNKOWN_ERROR
     *
     *  @access public
     *  @var integer
     */
    var $errNro;

    function phpSVNclient()
    {
        $http = & $this->_http;
        $http = new http_class;
        $http->user_agent = "phpSVNclient (http://cesars.users.phpclasses.org/svnclient)";
    }
    
    /**
     *  Set URL
     *
     *  Set the project repository URL.
     *
     *  @param string $url URL of the project.
     *  @access public
     */
    function setRepository($url)
    {
        $this->_url = $url;
    }

    /**
     *  Set URL
     *
     *  Old method; there's a typo in the name. This is now a wrapper for setRepository
     *  Deprecated (?)
     *
     *  @param string $url URL of the project.
     *  @access public
     */
    function setRespository($url)
    {
        return $this->setRepository($url);
    }

    /**
     *  Add Authentication  settings
     *
     *  @param string $user Username
     *  @param string $pass Password
     */
    function setAuth($user,$pass) {
        $this->user = $user;
        $this->pass = $pass;
    }

    /**
     *  Get Files
     *
     *  This method returns all the files in $folder
     *  in the version $version of the repository.
     *
     *  @param string  $folder Folder to get files
     *  @param integer $version Repository version, -1 means actual
     *  @return array List of files.
     */
    function getDirectoryFiles($folder='/',$version=-1) {
        $actVersion = $this->getVersion();
        if ( $version == -1 ||  $version > $actVersion) {
            $version = $actVersion;
        }
        $url = $this->cleanURL($this->_url."/!svn/bc/".$version."/".$folder."/");
        $this->initQuery($args,"PROPFIND",$url);
        $args['Body'] = PHPSVN_NORMAL_REQUEST;
        $args['Headers']['Content-Length'] = strlen(PHPSVN_NORMAL_REQUEST);

        if ( ! $this->Request($args, $headers, $body) ) {
            return false;
        }
        $parser=new xml_parser_class;
        $parser->Parse( $body,true);


        $fileinfo =  array(

            SVN_LAST_MODIFIED => "last-mod",
            SVN_RELATIVE_URL => "path",
            SVN_STATUS => "status"
        );

        $start = false;
        $last = "";
        $tmp = array();
        $files = array();
        $tmp1 = 0;


        foreach($parser->structure as $key=>$value) {
            if ( is_array($value) and $value["Tag"] == SVN_FILE) {
                if ( count($tmp) > 0 && $tmp1++ > 0) {
                    $files[] = $tmp;
                }
                $start=true;
                $last = "";
                $tmp = array();
                continue;
            }
            if (!$start) continue;
            if ( $last != "") {
                $tmp[ $fileinfo[$last] ] = $value;
                $last = "";
                continue;
            }
            if ( is_array($value) && isset($value["Tag"]) && isset( $fileinfo[$value["Tag"]] ) ) {
                $last = $value["Tag"];
            }
        }

        return $files;
    }

    /**
     *  Returns file contents
     *
     *  @param string  $file File pathname
     *  @param integer $version File Version
     *  @return Array File content and information
     */
    function getFile($file,$version=-1) {
        $actVersion = $this->getVersion();
        if ( $version == -1 ||  $version > $actVersion) {
            $version = $actVersion;
        }
        $url = $this->cleanURL($this->_url."/!svn/bc/".$version."/".$file."/");
        $this->initQuery($args,"GET",$url);
        if ( ! $this->Request($args, $headers, $body) )
            return false;
        return $body;
    }
    
    /**
     *  Get changes logs of a file.
     *
     *  Get repository change logs between version
     *  $vini and $vend.
     *
     *  @param integer $vini Initial Version
     *  @param integer $vend End Version
     *  @return Array Respository Logs
     */
    function getRepositoryLogs($vini=0,$vend=-1) {
        return $this->getFileLogs("/",$vini,$vend);
    }

    /**
     *  Get changes logs of a file.
     *
     *  Get repository change of a file between version
     *  $vini and $vend.
     *
     *  @param
     *  @param integer $vini Initial Version
     *  @param integer $vend End Version
     *  @return Array Respository Logs
     */
    function getFileLogs($file, $vini=0,$vend=-1) {
        $actVersion = $this->getVersion();
        if ( $vend == -1 || $vend > $actVersion)
            $vend = $actVersion;

        if ( $vini < 0) $vini=0;
        if ( $vini > $vend) $vini = $vend;

        $url = $this->cleanURL($this->_url."/!svn/bc/".$actVersion."/".$file."/");
        $this->initQuery($args,"REPORT",$url);
        $args['Body'] = sprintf(PHPSVN_LOGS_REQUEST,$vini,$vend);
        $args['Headers']['Content-Length'] = strlen($args['Body']);
        $args['Headers']['Depth']=1;

        if ( ! $this->Request($args, $headers, $body) )
            return false;


        $parser=new xml_parser_class;
        $parser->Parse( $body,true);


        $fileinfo =  array(
            SVN_LOGS_VERSION=>"version",
            SVN_LOGS_AUTHOR => "author",
            SVN_LOGS_DATE => "date",
            SVN_LOGS_MODIFIED_FILES => "files",
            SVN_LOGS_ADDED_FILES => "files",
            SVN_LOGS_RENAMED_FILES => "files",
            SVN_LOGS_DELETED_FILES => "files",
            SVN_LOGS_COMMENT => "comment"
        );

        $start = false;
        $last = "";
        $tmp = array();


        foreach($parser->structure as $key=>$value) {
            if ( is_array($value) and $value["Tag"] == SVN_LOGS_BEGINGS) {
                if (count($tmp) > 0) {
                    $logs[] = $tmp;
                }
                $start=true;
                $last = "";
                $tmp = array();
                continue;
            }
            if (!$start) continue;
            if ( $last != "") {
                // store details of all files into an array. If there is only one file, store as a string
                if ($tmp[$fileinfo[$last]] && !is_array($tmp[$fileinfo[$last]])){
                        $tmp[$fileinfo[$last]] = array($tmp[$fileinfo[$last]]);
		}

                if (is_array($tmp[$fileinfo[$last]]))
                        $tmp[$fileinfo[$last]][] = $value;
                else
                        $tmp[$fileinfo[$last]] = $value;
                $last = "";
                continue;
            }
            if ( is_array($value) && isset($value["Tag"]) && isset( $fileinfo[$value["Tag"]] ) ) {
                $last = $value["Tag"];
            }
        }

        // add the last element to the array
        $logs[] = $tmp;

        return $logs;
    }

    /**
     *  Get the repository version
     *
     *  @return integer Repository version
     *  @access public
     */
    function getVersion() {
        if ( $this->_repVersion > 0) return $this->_repVersion;

        $this->_repVersion = -1;
        
        $this->initQuery($args,"PROPFIND",$this->cleanURL($this->_url."/!svn/vcc/default") );
        $args['Body'] = PHPSVN_VERSION_REQUEST;
        $args['Headers']['Content-Length'] = strlen(PHPSVN_NORMAL_REQUEST);
        $args['Headers']['Depth']=0;

        if ( !$this->Request($args, $tmp, $body) )  {
            return $this->_repVersion;
        }

        $parser=new xml_parser_class;
        $parser->Parse( $body,true);
        $enable=false;
        foreach($parser->structure as $value) {
            if ( $enable ) {
                $t = explode("/",$value);

                // start from the end and move backwards until we find a non-blank entry
                $index = count($t) - 1;
                while ($t[$index] == ""){
                        $index--;
                }

                // check the last non-empty element to see if it's numeric. If so, it's the revision number
                if (is_numeric($t[$index])) {
                    $this->_repVersion = $t[$index];
                    break;
                }
            }
            if ( is_array($value) && $value['Tag'] == 'D:href') $enable = true;
        }
        
        return $this->_repVersion;
    }

    /**
     *  Prepare HTTP CLIENT object
     *
     *  @param array &$arguments Byreferences variable.
     *  @param string $method Method for the request (GET,POST,PROPFIND, REPORT,ETC).
     *  @param string $url URL for the action.
     *  @access private
     */
    function initQuery(&$arguments,$method, $url) {
        $http = & $this->_http;
        $http->GetRequestArguments($url,$arguments);
        if ( isset($this->user) && isset($this->pass)) {
            $arguments["Headers"]["Authorization"] = " Basic ".base64_encode($this->user.":".$this->pass);
        }
        $arguments["RequestMethod"]=$method;
        $arguments["Headers"]["Content-Type"] = "text/xml";
        $arguments["Headers"]["Depth"] = 1;
    }

    /**
     *  Open a connection, send request, read header
     *  and body.
     *
     *  @param Array $args Connetion's argument
     *  @param Array &$headers Array with the header response.
     *  @param string &$body Body response.
     *  @return boolean True is query success
     *  @access private
     */
    function Request($args, &$headers, &$body) {
        $http = & $this->_http;
        $http->Open($args);
        $http->SendRequest($args);
        $http->ReadReplyHeaders($headers);
        if ($http->response_status[0] != 2) {
            switch( $http->response_status ) {
                case 404:
                    $this->errNro=NOT_FOUND;
                    break;
                case 401:
                    $this->errNro=AUTH_REQUIRED;
                    break;
                default:
                    $this->errNro=UNKNOWN_ERROR;

            }
            $http->close();
            return false;
        }
        $this->errNro = NO_ERROR;
        $body='';
        $tbody='';
        for(;;)
        {
            $error=$http->ReadReplyBody($tbody,1000);
            if($error!="" || strlen($tbody)==0) break;
            $body.=($tbody);
        }
        $http->close();
        return true;
    }
    
    /**
     *  Clean URL
     *
     *  Delete "//" on URL requests.
     *
     *  @param string $url URL
     *  @return string New cleaned URL.
     *  @access private
     */
    function cleanURL($url) {
        return preg_replace("/((^:)\/\/)/", "//", $url);
    }
}
?>
