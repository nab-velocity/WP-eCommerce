<?php

/**
 *  WpeVelocity_Helper class
 *  This class use to control theversion and css js restriction for our template only
 *  
 *  @category payment
 *  @package wp-e-velocity
 *  @author velocity
 *  @since 1.0.0
 */
class WpeVelocity_Helper {

    private static $version = '1.0.0';

    /*
     * This static function return version number.
     * 
     * @param null
     * return string version number
     */

    public static function getVersion() {
        return self::$version;
    }

    /*
     * This function create the log for payment and refund both process.
     * 
     * @param mixed $log
     * return null
     */

    public static function islog($log = NULL, $type = '') {

        if ($type == 'P') {
            $file = realpath(dirname(dirname(__FILE__))) . '/log/payment.log';
        } elseif ($type == 'R') {
            $file = realpath(dirname(dirname(__FILE__))) . '/log/refund.log';
        } else {
            $file = '';
        }
        
        if($file != '') {
            $log = current_time('mysql') . "\n" . $log . "\n-----------------------------------\n\r\n\r";
            $myfile = fopen($file, "a") or die("Unable to open file!");
            fwrite($myfile, $log);
            fclose($myfile);
        }
    }
    
    /*
     * This function control the js & css file availability on our template only.
     * 
     * @param null
     * return boolean
     */

    public static function isWpeVelocityPage() {
        return ($_GET['page'] == 'wpevelocity-configuration' || $_GET['page'] == 'wpevelocity-refund');
    }

}
