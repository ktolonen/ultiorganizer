<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * The HSV Class and the accompanying functions allow you to convert between HSV and RGB. 
 * 
 * RGB is what the computer understands. HSV is what people can use to easily work with colors. This code
 * gives you the tools to convert between the two models.
 *
 * PHP Version 4
 *
 * LICENSE: This class is subject to the freebsd license found at http://freebsd.org/copyright/license.html.
 *
 * @version     1.0
 * @package     HSVClass
 * @author      Michael Heuss <mrheuss@mac.com>
 * @copyright   2005 Michael Heuss
 * @license     http://freebsd.org/copyright/license.html
 * @since       Sep 4, 2005
 * @link        http://mikeheuss.com/scripts/ColorToy/
 * 
 * 
 * THANKS TO SNEAKY FOR SPOTTING A BUG that would create a divide by zero error.
 * 
 */

/**
 * The HSV Class and the accompanying functions allow you to convert between HSV and RGB. 
 * 
 * RGB is what the computer understands. HSV is what people can use to easily work with colors. This code
 * gives you the tools to convert between the two models.
 *
 * PHP Version 4
 *
 * LICENSE: This class is subject to the freebsd license found at http://freebsd.org/copyright/license.html.
 *
 * @version     1.0
 * @package     HSVClass
 * @author      Michael Heuss <mrheuss@mac.com>
 * @copyright   2005 Michael Heuss
 * @license     http://freebsd.org/copyright/license.html
 * @since       Sep 4, 2005
 * @link        http://mikeheuss.com/scripts/ColorToy/
 */
class HSVClass
{

    /**
     * $m_hue
     * 
     * The current hue. Valid values range from 0 to 360
     * 
     * @var float 
     * @access public
     */
    var $m_hue;

    /**
     * $m_saturation
     * 
     * The current saturation. Valid values range from 0 to 1.
     * 
     * @var float
     * @access public
     */
    var $m_saturation;

    /**
     * $m_brightness
     * 
     * The current brightness. Valid values range from 0 to 1.
     * 
     * @var float
     * @access public
     */
    var $m_brightness;


    /**
     * Constructor - initializes the variables
     * 
     */

    function __construct($arg_hue = 0, $arg_saturation = 0, $arg_brightness = 0)
    {
        $this->m_hue = $arg_hue;
        $this->m_saturation = $arg_saturation;
        $this->m_brightness = $arg_brightness;
    }

    /**
     * sets the current color
     * 
     * Sets the HSV values, overwriting whatever was there previously.
     * 
     * @return bool True if successful, false otherwise
     * @access public
     * @param float $arg_h The hue must be between 0 and 360
     * @param float $arg_s The saturation must be between 0 and 1
     * @param float $arg_v the brightness must be between 0 and 1
     */
    function setHSV($arg_h, $arg_s, $arg_v)
    {

        if (($arg_s > 1) || ($arg_s < 0)) return false;
        if (($arg_v > 1) || ($arg_v < 0)) return false;
        if (($arg_h > 360) || ($arg_h < 0)) return false;

        $this->m_hue = $arg_h;
        $this->m_saturation = $arg_s;
        $this->m_brightness = $arg_v;

        return true;
    }

    /**
     * sets the current color
     * 
     * The RGB is immediately converted in HSV and stored
     * 
     * @return bool true if successful, false otherwie
     * @access public
     * @param integer $arg_r The red color must between 0 and 255
     * @param integer $arg_g The green color must between 0 and 255
     * @param integer $arg_b The blue color must between 0 and 255
     */

    function setRGB($arg_r, $arg_g, $arg_b)
    {
        $t_array = convertRGBtoHSV(array("r" => $arg_r, "g" => $arg_g, "b" => $arg_b));
        if ($t_array == null) return false;
        $this->m_hue = $t_array['h'];
        $this->m_saturation = $t_array['s'];
        $this->m_brightness = $t_array['v'];

        return true;
    }

    /**
     * sets the current color
     * 
     * Sets the HSV values, overwriting whatever was there previously. The string is in #FFFFFF format.
     * 
     * @return bool True if successful, false is not
     * @access public
     * @param string color in RGB string format (i.e. FFFFFF)
     */
    function setRGBString($arg_html_string)
    {
        if (strlen($arg_html_string) != 6) return false;
        $r = $arg_html_string[0] . $arg_html_string[1];
        $g = $arg_html_string[2] . $arg_html_string[3];
        $b = $arg_html_string[4] . $arg_html_string[5];

        $r = hexdec($r);
        $g = hexdec($g);
        $b = hexdec($b);

        return $this->setRGB($r, $g, $b);
    }

    /**
     * Alters the brightness of the current color
     * 
     * The value passed in should be greater than negative one and less than one. If by changing brightness
     * it becomes greater than one or less than zero, the brightness wraps around. For instance, adding +.2 to a 
     * value of .9 leaves you with .1
     * 
     * @param float $arg_amount is the total amount of brightness to be added to the current color, between -1 and 1
     * @return void
     * @access public
     */

    function changeBrightness($arg_amount)
    {

        $this->m_brightness += $arg_amount;

        while ($this->m_brightness > 1) $this->m_brightness -= 1;
        while ($this->m_brightness < 0) $this->m_brightness += 1;
    }

    /**
     * Alters the saturation of the current color
     * 
     * The value passed in should be greater than -1 and less than 1. If by changing the saturation the value
     * becomes greater than 1 or less than 0,  the brightness wraps around. For instance, adding +.2 to a 
     * value of .9 leaves you with .1. 
     * 
     * @param float $arg_amount is the total amount of saturation to be added to the current color, between -1 and 1
     * @return void
     * @access public
     * 
     */

    function changeSaturation($arg_amount)
    {

        $this->m_saturation += $arg_amount;

        while ($this->m_saturation > 1) $this->m_saturation -= 1;
        while ($this->m_saturation < 0) $this->m_saturation += 1;
    }

    /**
     * Alters the hue of the current color
     * 
     * @param float $arg_degrees is the amount of change from the current hue
     * @return void
     * @access public  
     */

    function changeHue($arg_degrees)
    {

        $this->m_hue += $arg_degrees;

        while ($this->m_hue > 360) $this->m_hue -= 360;
        while ($this->m_hue < 0) $this->m_hue += 360;

        return true;
    }

    /**
     * Returns array of the current color
     * 
     * @return array using template array ("r"=>?, "g"=>?, b=>?);
     * @access public
     */
    function getRGB()
    {
        $temp = convertHSVtoRGB(array("h" => $this->m_hue, "s" => $this->m_saturation, "v" => $this->m_brightness));

        $temp['r'] = intval($temp['r']);
        $temp['g'] = intval($temp['g']);
        $temp['b'] = intval($temp['b']);

        return $temp;
    }

    /**
     * Return HTML string of current color
     * 
     * @return string current color in FFFFFF format
     * @access public
     */

    function getRGBString()
    {

        $temp = $this->getRGB();

        $r = dechex($temp['r']);
        $g = dechex($temp['g']);
        $b = dechex($temp['b']);

        if (strlen($r) == 1) $r = "0" . $r;
        if (strlen($g) == 1) $g = "0" . $g;
        if (strlen($b) == 1) $b = "0" . $b;

        return strtoupper($r . $g . $b);
    }

    /**
     * Returns an array containing the hue, saturation and brightness
     * 
     * @return array
     * @access public
     */

    function getHSV()
    {
        return array("h" => $this->m_hue, "s" => $this->m_saturation, "v" => $this->m_brightness);
    }
} //End of class



/**
 * Converts RGB to HSV
 * 
 * RGB is made up of three components, each having a possible value of 0 to 255. HSV is made up of three
 * components, with H, the Hue, having the possible values of 0 to 360, while the other two having possible
 * values 0 to 1.
 * 
 * By passing in RGB in array format, you will recieve back HSV in array format.
 * 
 * The RGB array should be in the following format:
 *      array("r"=>?, "g"=>?, "b"=>?)             
 *
 * The returned hsv is in the following format
 *      array("h"=>?, "s"=>?, "v"=>?);
 * 
 * @param array $arg RGB One element for each component. i.e.  array("r"=>?, "g"=>?, "b"=>?)   
 * @return array containing the HSV, or null if there is a problem 
 */

function convertRGBtoHSV($arg_rgb)
{
    //wee only accept arrays
    if (!is_array($arg_rgb)) return null;

    //Convert for simplicity
    $r = $arg_rgb['r'];
    $g = $arg_rgb['g'];
    $b = $arg_rgb['b'];

    //Get the min and max
    $min = min($arg_rgb);
    $max = max($arg_rgb);

    //ensure it is within bounds
    if ($max > 255) return null;
    if ($min < 0) return null;

    //Convert the brightness to a percentage
    $v = $max / 255;

    //Get the delta of max and min
    $delta = $max - $min;

    //Is max zero?
    if (($max != 0) && ($delta != 0)) {
        //COmpute the saturation
        $s = $delta / $max;
    } else {
        //Get out of here, we have no color
        $s = 0;
        $h = -1;
        return array("h" => $h, "s" => $s, "v" => $v);
    }

    //Compute the hue
    if ($r == $max) {
        $h = ($g - $b) / $delta;
    } elseif ($g == $max) {
        $h = 2 + ($b - $r) / $delta;
    } else {
        $h = 4 + ($r - $g) / $delta;
    }

    //To degrees
    $h *= 60;
    if ($h < 0) $h += 360;

    //Return the results
    return array("h" => $h, "s" => $s, "v" => $v);
}




/**
 * Convert HSV to RGB String
 * 
 * Converts an HSV value into an RGB string for use in HTML
 * 
 * @param float $m_hue is the hue of the color
 * @param float $m_brightness is the 
 * @param float $m_saturation contains the saturation of the color
 * @return string HTML RGB string
 */
function convertHSVtoRGBString($arg_h, $arg_s, $arg_v)
{

    //Ensure the input is between the ranges
    while ($arg_h > 360) $arg_h -= 360;
    while ($arg_h < 0) $arg_h += 360;

    while ($arg_s > 1) $arg_s -= 1;
    while ($arg_s < 0) $arg_s += 1;

    while ($arg_v > 1) $arg_v -= 1;
    while ($arg_v < 0) $arg_v += 1;

    $t_array = convertHSVtoRGB(array("h" => $arg_h, "s" => $arg_s, "v" => $arg_v));

    if ($t_array == null) return "";

    $r = dechex($t_array['r']);
    $g = dechex($t_array['g']);
    $b = dechex($t_array['b']);

    if (strlen($r) == 1) $r = "0" . $r;
    if (strlen($g) == 1) $g = "0" . $g;
    if (strlen($b) == 1) $b = "0" . $b;

    return strtoupper($r . $g . $b);
}



/**
 * Converts HSV to RGB
 * 
 * RGB is made up of three components, each having a possible value of 0 to 255. HSV is made up of three
 * components, with H, the Hue, having the possible values of 0 to 360, while the other two having possible
 * values 0 to 1.
 * 
 * By passing in HSV in array format, you will recieve back RGB in array format.
 * 
 * The RGB array is in the following format:
 *      array("r"=>?, "g"=>?, "b"=>?)             
 *
 * The HSV should be in the following format
 *      array("h"=>?, "s"=>?, "v"=>?);
 * 
 * @param array One element for H,S, and V. i.e array("h"=>?, "s"=>?, "v"=>?) 
 * @return array containing the HSV, or null if there is a problem 
 */

function convertHSVtoRGB($arg_hsv)
{
    if (!is_array($arg_hsv)) return null;

    $h = $arg_hsv['h'];
    $s = $arg_hsv['s'];
    $v = $arg_hsv['v'];


    $r = $g = $b = 0;

    //Is this a gray?
    if ($s == 0) {
        $r = $g = $b = $v;
        return array("r" => $r, "g" => $g, "b" => $b);
    }

    $h /= 60;
    $i = floor($h);
    $f = $h - $i;
    $p = 255 * $v * (1 - $s);
    $q = 255 * $v * (1 - ($s * $f));
    $t = 255 * $v * (1 - $s * (1 - $f));
    $v *= 255;

    switch ($i) {
        case 0:
            $r = $v;
            $g = $t;
            $b = $p;
            break;
        case 1:
            $r = $q;
            $g = $v;
            $b = $p;
            break;
        case 2:
            $r = $p;
            $g = $v;
            $b = $t;
            break;
        case 3:
            $r = $p;
            $g = $q;
            $b = $v;
            break;
        case 4:
            $r = $t;
            $g = $p;
            $b = $v;
            break;
        default:
            $r = $v;
            $g = $p;
            $b = $q;
            break;
    } //end of switch
    return array("r" => $r, "g" => $g, "b" => $b);
}
