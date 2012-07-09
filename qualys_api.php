<?php
/*
    **************************************************************************************
    *
    *
    *                   ----- QUALYS API CLASS -----
    *
    *
    *                   By Nick MacCarthy
    *                   nickmaccarthy@gmail.com
    *
    **************************************************************************************

    This class is a hodgepodge of methods I have written over the past years for easy Qualys API maniuplation for a PHP application.

    There are two classes here.  One for the Qualys API Version 1 and one for API V2.  
    API version 1 you log in everytime you want to make a call, then log out.  Its very simple in that you pass along your login credentials
    everytime you call a method.

    In version 2 however, its a little more complicated.  You must log in to the API and establish a session and get a session id, 
    after which you need to pass that session ID during each method call.  To do this, the standard CURL lib's are being called to track this.
    When calling the API2 class, you must pass along the $url, $username, $password and $client_name during __construct() or when you call the class.
    __construct() will take care of logging us in and getting the session ID.  __constrcut() will be called upon the initialization of this class.  Upon 
    the __destruct() of this class, we will log back in and destroy the session and log the user out.  This is all taken care of in the class.
    See examples below for proper usage.  

    

    Basic usage examples:
    API V1:
        // Initialize the class
        $api1 = new QualysAPI_v1();
        
        // make an API call
        $download_raw_scan = $api1->GetScanReport($url, $username, $password, $scan_ref_id, $download_to_filename);

    API V2:
        // Initialize the class
        $api2 = new QualysAPI_v2($url, $username, $password, $client_name);  // This will get us logged in and the session set upon __construct();

        // make an API call
        $poll_scans = $api2->PollScans();

        // here you can either unset the class to log us out
        unset($api2)

        // or upon completion, or exit of the script, the class will be destroyed and the __destruct method will run to log us out

*/

// QualysAPI_v1/*{{{*/
class QualysAPI_v1{

    /* GetQualysKB *//*{{{*/
    /**
    * This method will download the Qualys Knowlege Base and return the raw XML back
    *
    * @param string $base_url - The base URL for the api call - i.e. https://qualysapi.qualys.<tld>/msp
    * @param string $username - The username for the account
    * @param string $password - The password for the account
    * @returns string $output - This will be the raw XML from the API call, in this case the Qualys KB
    */
    public function GetQualysKB($base_url, $username, $password, $parse = NULL){
        $url = $base_url . "knowledgebase_download.php?show_cvss_submetrics=1&show_pci_flag=1";

        $output = $this->GetData($url, $username, $password);
        return $output;
    }/*}}}*/

    /* GetAssetGroups *//*{{{*/
    /**
    * This method will download the Asset Group List for the client
    *
    * @param string $base_url - The base URL for the api call - i.e. https://qualysapi.qualys.<tld>/msp
    * @param string $username - The username for the account
    * @param string $password - The password for the account
    * @returns string $output - This will be the raw XML from the API call, in this case the Asset Group list for the client
    */
    public function GetAssetGroups($base_url, $username, $password){

        $url = $base_url . "asset_group_list.php";
        $output = $this->GetData($url, $username, $password);
        return $output;
    }/*}}}*/ 

    public function asset_group($base_url, $username, $password, $arr)/*{{{*/
    {

        $url = $base_url . "asset_group.php";
        foreach ($arr as $key => $val)
        {
            if ($val) $post_vars[$key] = $val;
        }

        $output = $this->post_url($url, $username, $password, $post_vars);
        

        return $output;

    }/*}}}*/

    public function asset_data_report($base_url, $username, $password, $opts){/*{{{*/

        $url = $base_url . "asset_data_report.php";

        foreach ($opts as $key => $val)
        {
            if ($val) $post_vars[$key] = $val;
        }

        $output = $this->post_url($url, $username, $password, $post_vars);

        return $output;
    }/*}}}*/ 

    /**
    *
    *   Used to add, list, and remove scheduled scans and maps to the qualys account.
    *   For more infor, see "Scheduled Scans and Maps" in the Qualys API v1 documentation 
    *   Note: This method will eventually be deperecated and replaced with the 'scheduled_scans' method, so if you are building someting new, start using that one instead...
    *
    *   @param      string      $base_url   Base URL - ex. https://qualysapi.qualys.com/msp
    *   @param      string      $username   Username for the qualys account
    *   @param      string      $password   Password for the qualys account
    *   @param      array       $opts       Associative array of options to pass along.  Example "array( 'active' => 'yes', 'type' => 'scan' )"
    *   @return     string      $output     XML output from our API call
    **/
    public function ScheduleScan($base_url, $username, $password, $opts)
    {
        $url = $base_url . "scheduled_scans.php";

        $output = $this->post_url($url, $username, $password, $opts);

        return $output;
    }

    /**
    *
    * Same as "ScheduledScans()" with new naming convention
    *
    **/
    public function scheduled_scans($base_url, $username, $password, $opts)
    {
        $url = $base_url . "scheduled_scans.php";

        $output = $this->post_url($url, $username, $password, $opts);

        return $output;
    }

    public function scan_running_list($base_url, $username, $password)
    {
        $url = $base_url . "scan_running_list.php";

        $output = $this->post_url($url, $username, $password);

        return $output;
    }

    public function report_template_list($base_url, $username, $password)
    {
        $url = $base_url . "report_template_list.php";

        $output = $this->post_url($url, $username, $password);

        return $output;
    }

    public function scan_target_history($base_url, $username, $password, $opts)
    {
        $url = $base_url . "scan_target_history.php";

        $output = $this->post_url($url,$username, $password, $opts);

        return $output;

    }

    /* GetData() *//*{{{*/
    /**
    * This method will do the necessary CURL functions to make the API call to qualys
    *
    * @param string $base_url - The base URL for the api call - i.e. https://qualysapi.qualys.<tld>/msp
    * @param string $username - The username for the account
    * @param string $password - The password for the account
    * @param string $filename - If set, this will output data to a file
    * @returns string $result - This is the HTTP request for the API call 
    */
     public function GetData($base_url, $username, $password, $filename = NULL){

            $ch = curl_init();

            if($filename != NULL ){
                    $fp = fopen($filename, "w");
            }

        /* curl options */
                        curl_setopt($ch, CURLOPT_URL, $base_url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                        curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");

                        if($filename != NULL){
                                curl_setopt($ch, CURLOPT_FILE, $fp);
                                $data = curl_exec($ch);
                        }
                        if(!$filename){
                                $result =& curl_exec($ch);
            return $result;
                        }

        if(!curl_errno($ch)){
            $info = curl_getinfo($ch);
            echo "$filename (" . $this->byte_convert($info['size_download']) . ") downloaded at an average speed of " . $this->byte_convert($info['speed_download']) . " per second.\n";
        }

                        curl_close($ch);

                        if($filename != NULL ){
                                fclose($fp);
            return 1;
                        }

    }/*}}}*/

    public function get_url($url, $username, $password){

            $ch = curl_init();

            /* curl options */
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");

            $result =& curl_exec($ch);

            curl_close($ch);

            return $result;
    }


    public function post_url($url, $username, $password, $post_array = NULL){

            if(!is_null($post_array))
            {
                $post_string = http_build_query($post_array);
            }

            $ch = curl_init($url);

            curl_setopt($ch, CURLOPT_HEADER, FALSE);

            if($post_array){
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);
            }

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");

            $curl_result = curl_exec($ch);

            curl_close($ch);

            $raw_headers = substr($curl_result, 0, strpos($curl_result, "\r\n\r\n"));
            $body =  substr($curl_result, strpos($curl_result, "\r\n\r\n")) ;

            $result = $body; 

            $raw_header_array = explode("\r\n", $raw_headers);
            $http_code = array_shift($raw_header_array);

            foreach($raw_header_array as $header_line){
                $key = strtoupper(trim(substr($header_line, 0, strpos($header_line, ":"))));
                $val = trim(substr($header_line, strpos($header_line, ":")+1));

                $headers[$key] = $val;
            }

            return $result;

      }/*}}}*/

public function byte_convert($bytes){/*{{{*/
        $symbol = array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');

        $exp = 0;
        $converted_value = 0;
        if( $bytes > 0 )
        {
          $exp = floor( log($bytes)/log(1024) );
          $converted_value = ( $bytes/pow(1024,floor($exp)) );
        }

        return sprintf( '%.2f '.$symbol[$exp], $converted_value );
      }/*}}}*/

}// end class QualysAPI_v1/*}}}*/ 

// QualysAPI_ v2/*{{{*/
/**
* @package QualysAPI_v2
* @version 1.0
* @author Nick MacCarthy
*
*/
class QualysAPI_v2{


/* __contstruct *//*{{{*/
/**
* This method will take care of logging us into the qualys api v2.0, getting a session ID,  and keeping the session ID handy for other API calls used during the instance of the class.
* 
* @param string $base_url - The base url needd for the api - ex "https://qualysapi.qualys.<tld>/api/2.0/fo/"
* @param string $username - The username for the account
* @param string $password - The password for the account
* @param string $customer_name - The name of the customer for which this is running for - not required
* @return NULL
*/ 

public function __construct($base_url, $username, $password){

        $this->base_url = $base_url;
        $this->username = $username;

        $this->cookie_file = "/tmp/cookie_file" . rand();
        
        $this->headers = array("Content-type: application/x-www-form-urlencoded", "X-Requested-With: vulnDB");
                        
        // On construct(), login automagiclly
        $url = $base_url . "session/";
        $postdata = array( 'echo_request' => 1, 'action' => 'login', 'username' => $username, 'password' => $password);

        $output = $this->post_url($url, $postdata, $this->headers);
        
}/*}}}*/ 

    /* DownloadScanResults(); */ /*{{{*/
    /**  
    * This method takes care of downloading the scan results in whatever format specified.
    *
    * @param string $scan_ref Scan Reference ID From Qualys
    * @param string $mode reporting mode - can either be 'extended' or 'brief'
    * @param string $output_format output format for scan api call -- valid is 'json' or 'csv'
    * @return string 
    */
    public function DownloadScanResults($scan_ref, $mode, $output_format){

            $url = $this->base_url . "scan/";

            $postdata = array ( 
                                    'action' => 'fetch',
                                    'scan_ref' => $scan_ref,
                                    'mode' => $mode,
                                    'output_format' => $output_format,
                            );

            $output = $this->post_url($url, $postdata, $this->headers);

            return $output;

    }/* }}}*/

    public function Reports($opts)
    {
                            $url = $this->base_url . "report/";

                            $output = $this->post_url($url, $opts, $this->headers);

                            return $output;
    }
    
    /* PollScans() * / /*{{{*/
    /**
    * This method takes care of 'polling' the scans for an account within in a given time period.  
    * The $postdata array contains the relevent arugments for the api call to 'list' the scans, 'show the asset groups' and 'show the options profile' 
    * @param string $sinceDate -- The date from which to show the scans from 
    * @return string
    *
    */
    public function PollScans($sinceDate)
    {

                                $url = $this->base_url . "scan/";

                                $postdata = array( 'action' => 'list', 'show_ags' => '1', 'show_op' => 1, 'launched_after_datetime' => $sinceDate );

                                $output = $this->post_url($url, $postdata, $this->headers);

                                return $output;

    }/*}}}*/

/* post_url *//*{{{*/ 
/**
* This method will make the appropriate CURL call to make the API call to Qualys
* @param string url - The URL for the API call
* @param array $post_array - An array for the post data we need to send the API call
* @param array $headers - An array of the headers we need to make for the API call
* @param string $filename - If set, this will output data to a file
* @returns string $result - This will be the result from the HTTP request
*/
public function post_url($url, $post_array, $header_array){

        $post_string = http_build_query($post_array);

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookie_file);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookie_file);
        //curl_setopt($ch, CURLOPT_COOKIEJAR, "/tmp/cookie_file");
        //curl_setopt($ch, CURLOPT_COOKIEFILE, "/tmp/cookie_file");


        curl_setopt($ch, CURLOPT_HEADER, FALSE);

        if($post_array){
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header_array);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        $curl_result = curl_exec($ch);

        curl_close($ch);

        $raw_headers = substr($curl_result, 0, strpos($curl_result, "\r\n\r\n"));
        $body =  substr($curl_result, strpos($curl_result, "\r\n\r\n")) ;

        $result = $body; 

        $raw_header_array = explode("\r\n", $raw_headers);
        $http_code = array_shift($raw_header_array);

        foreach($raw_header_array as $header_line){
            $key = strtoupper(trim(substr($header_line, 0, strpos($header_line, ":"))));
            $val = trim(substr($header_line, strpos($header_line, ":")+1));

            $headers[$key] = $val;
        }

        return $result;


   }/*}}}*/

    /* __destruct() */ /*{{{*/
    /**
    * This method will log us out of the api 2.0 cleanly upson class destruction
    */
    public function __destruct(){

            // On destruct(), logout automagically
            $url = $this->base_url . "session/";
            $postdata = array( 'action' => 'logout' );
            
            $output = $this->post_url($url, $postdata, $this->headers);

            unset($this->cookie_file);
            
    } /*}}}*/

}/*}}}*/   

?>
