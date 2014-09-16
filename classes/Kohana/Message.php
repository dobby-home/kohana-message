<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @package    Kohana/Message
 * @author     Oleg Mikhaylenko <olegm@infokinetika.ru>
 * @copyright  CSI Infokinetika, Ltd.
 *
 */
class Kohana_Message {

    protected $message = Array();

    protected $values;
    /**
     * @var Kohana_Message
     */
    protected static $_instance;

    public $httpstatus = 200;

    public static $only200 = true;

    protected $_handle = null;


    /**
     * @param string $code
     * @param string $message
     * @param string $type
     * @param int    $status
     * @return Kohana_Message
     */
    public static function instance($code = '', $message = '', $type = '', $status = null) {

        if (!isset(self::$_instance)) {
            // Create a new session instance
            self::$_instance = new Message($code, $message, $type, $status);
        } else {
            self::$_instance->add($code, $message, $type, $status);
        }
        return self::$_instance;
    }

    /**
     * @param string $code
     * @param string $message
     * @param string $type
     * @param null   $status
     */
    public function __construct($code = '', $message = '', $type = '', $status = null) {
        $this->add($code, $message, $type, $status);
    }

    /**
     * Load Message from session
     *
     * @static
     * @return Message
     */
    public static function load() {

        $i = Message::instance();
        $session = Session::instance();
        $i->message = $session->get_once('message', array());
        return $i;
    }

    /**
     * Add message to stack
     *
     * @param        $code
     * @param        $message
     * @param string $type
     * @param null   $status
     */
    public function add($code, $message, $type = '', $status = null) {

        $values = array();
        if (is_array($message)) {
            $values = $message[1];
            $message = $message[0];
        }
        $message = trim($message);
        if ($message != '') {
            if (function_exists('___')) {
                $message = ___($message, $values);
            }
        }
        // Format errors (0,'Text','Type')
        if (is_numeric($code)) {
            $mess['code'] = $code;
            $mess['mess'] = trim(preg_replace('/[\s]+/', ' ', $message));
            $type = trim($type) == '' ? 'general' : $type;

            $mess['type'] = $type;
            $this->message[] = $mess;

            if (!self::$only200 && !is_null($status)) {
                $this->httpstatus = $status;
            }

        } elseif (is_array($code)) {

            // Kohana Error
            $f = false;
            if (isset($code['_external'])) {
                foreach ($code['_external'] as $key => $value) {
                    $this->add(100, __($value),  $key);
                    $f = true;
                }
            } else {
                foreach ($code as $key => $value) {
                    if (is_array($value)) {
                        $this->add(100, __($value[0]), $key);
                    } else {
                        $this->add(100, __($value),  $key);
                    }
                    $f = true;
                }
            }
            if (!self::$only200 && $f) {
                $this->httpstatus = 400;
            }

        } elseif (is_string($code) && $code != '') {


            $mess['code'] = $code;
            $mess['mess'] = trim(preg_replace('/[\s]+/', ' ', $message));
            $type = trim($type) == '' ? 'general' : $type;

            $mess['type'] = $type;
            $this->message[] = $mess;

            if (!self::$only200 && !is_null($status)) {
                $this->httpstatus = $status;
            }
        }
    }

    /**
     * Rewrite value
     *
     * @param $values
     * @return Message
     */
    public function setValue($values) {
        $this->values = $values;
        return $this;
    }

    /**
     * Save messages into session
     *
     * @return Message
     */
    public function save() {
        $session = Session::instance();
        $session->set('message', $this->message);
        return $this;
    }

    /**
     * Out message to browser
     *
     * @param bool   $die
     * @param string $type
     * @return string
     */
    public function out($die = false, $type = 'json') {
        $resp = Response::factory();

        $resp->status($this->httpstatus);
        $resp->send_headers();

        switch ($type) {
            case 'empty':
                $res = '';
                break;
            case 'text':
                $res = $this->toText();
                break;
            //json
            default:
                $res = json_encode(Array('messages' => $this->message,
                    'values' => $this->values));;
        }

        if ($die) {
            die($res);
        } else {
            return $res;
        }
    }

    /**
     * Check messages stack
     *
     * @param bool   $dieout
     * @param string $type
     * @return bool
     */
    public function isEmpty($dieout = false, $type = 'json') {

        if ($dieout && count($this->message) != 0) {
            $this->out(true, $type);
        } else {
            return count($this->message) == 0;
        }
        return true;
    }

    /**
     * @return array
     */
    public function toArray() {
        return $this->message;
    }

    /**
     * @param string $type (all, error, message)
     * @param bool   $clear
     * @return string
     */
    public function toText($type = 'all', $clear = false) {
        $str = '';
        for ($i = 0; $i < count($this->message); $i++) {
            if ($type == 'all' || ($type == 'error' && $this->message[$i]['code'] != 0) || ($type == 'message' && $this->message[$i]['code'] == 0)) {
                $str .= ' ' . $this->message[$i]['mess'];
            }
        }
        if ($clear) {
            $this->message = array();
        }
        return $str;
    }

    public function fatal() {
        echo $this->toText();
        die();
    }

}