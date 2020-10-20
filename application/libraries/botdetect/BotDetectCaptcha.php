<?php
if (!defined('DS')) { define('DS', DIRECTORY_SEPARATOR); }
if (!defined('BDCI_LIB_PATH')) { define('BDCI_LIB_PATH', dirname(__FILE__) . DS); }

require_once(BDCI_LIB_PATH . 'support/LibraryLoader.php');
require_once(BDCI_LIB_PATH . 'support/UserCaptchaConfiguration.php');

class BotDetectCaptcha {

    /**
     * The CodeIgniter super-object.
     *
     * @var object
     */
    protected $CI;

    /**
     * @var object
     */
    private $captcha;

    /**
     * @var object
     */
    private static $instance;

    /**
     * BotDetect CodeIgniter CAPTCHA library information.
     *
     * @var array
     */
    public static $product_info;

    /**
     * Create a new BotDetect CodeIgniter CAPTCHA Library object.
     *
     * @param  array  $params
     * @return void
     */
    public function __construct($params = array())
    {
        self::$instance =& $this;

        // get the CodeIgniter super-object
        $this->CI =& get_instance();

        // init session
        $this->init_session();

        // load botdetect captcha library
        BDCI_LibraryLoader::load();

        // change the keys in $param array to lowercase,
        // this will avoid user being able to pass in a lowercase option (e.g. captchaconfig)
        $params = array_change_key_case($params, CASE_LOWER);

        if (empty($params) ||
            !array_key_exists('captchaconfig', $params) ||
            empty($params['captchaconfig'])
        ) {
            $error_message  = 'The BotDetect Captcha library requires you to declare "captchaConfig" option and assigns a captcha configuration key defined in config/captcha.php file.<br>';
            $error_message .= 'For example: <b>$this->load->library(\'botdetect/BotDetectCaptcha\', array(\'captchaConfig\' => \'LoginCaptcha\'));</b>';
            throw new InvalidArgumentException($error_message);
        }

        $captcha_id = $params['captchaconfig'];

        $captchaInstanceId = null;
        if(isset($params['captchainstanceid'])) {
            $captchaInstanceId = $params['captchainstanceid'];
        }

        // get captcha config
        $config = BDCI_UserCaptchaConfiguration::get($captcha_id);

        if (null === $config) {
            throw new InvalidArgumentException(sprintf('The "%s" option could not be found in config/captcha.php file.', $captcha_id));
        }

        if (!is_array($config)) {
            throw new InvalidArgumentException(sprintf('Expected argument of type "array", "%s" given', gettype($config)));
        }

        // save user's captcha configuration options
        BDCI_UserCaptchaConfiguration::save($config);
        
        // init botdetect captcha instance
        $this->init_captcha($config, $captchaInstanceId);
    }

    /**
     * Initialize session.
     *
     * @return void
     */
    public function init_session()
    {
        // CI >= 3.0.0
        if (version_compare(CI_VERSION, '3.0.0', '>=')) {
            $this->CI->load->library('session');
            return;
        }

        // CI < 3.0.0 & PHP >= 5.4.0
        if (function_exists('session_status')) {
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }
            return;
        }

        // CI < 3.0.0 & PHP < 5.4.0
        if ('' == session_id()) {
            session_start();
        }
    }

    /**
     * Initialize CAPTCHA instance.
     *
     * @param  array  $config
     * @return void
     */
    public function init_captcha($config = array(), $captchaInstanceId = null)
    {
        // set captchaId and create an instance of the Captcha
        $captchaId = (array_key_exists('CaptchaId', $config)) ? $config['CaptchaId'] : 'defaultCaptchaId';
        $this->captcha = new Captcha($captchaId, $captchaInstanceId);

        // set user's input id
        if (array_key_exists('UserInputID', $config)) {
            $this->captcha->UserInputID = $config['UserInputID'];
        }
    }

    /**
     * Get BotDetect Captcha instance.
     *
     * @return object
     */
    public static function &get_instance()
    {
        return self::$instance;
    }

    public function __call($method, $args = array())
    {
        if (method_exists($this, $method)) {
            return call_user_func_array(array($this, $method), $args);
        }

        if (method_exists($this->captcha, $method)) {
            return call_user_func_array(array($this->captcha, $method), $args);
        }

        if (method_exists($this->captcha->get_CaptchaBase(), $method)) {
            return call_user_func_array(array($this->captcha->get_CaptchaBase(), $method), $args);
        }
    }

    /**
     * Auto-magic helpers for civilized property access.
     */
    public function __get($name)
    {
        if (method_exists($this->captcha->get_CaptchaBase(), ($method = 'get_'.$name))) {
            return $this->captcha->get_CaptchaBase()->$method();
        }

        if (method_exists($this->captcha, ($method = 'get_'.$name))) {
            return $this->captcha->$method();
        }

        if (method_exists($this, ($method = 'get_'.$name))) {
            return $this->$method();
        }
    }

    public function __isset($name)
    {
        if (method_exists($this->captcha->get_CaptchaBase(), ($method = 'isset_'.$name))) {
            return $this->captcha->get_CaptchaBase()->$method();
        }

        if (method_exists($this->captcha, ($method = 'isset_'.$name))) {
            return $this->captcha->$method();
        }

        if (method_exists($this, ($method = 'isset_'.$name))) {
            return $this->$method();
        }
    }

    public function __set($name, $value)
    {
        if (method_exists($this->captcha->get_CaptchaBase(), ($method = 'set_'.$name))) {
            return $this->captcha->get_CaptchaBase()->$method($value);
        }

        if (method_exists($this->captcha, ($method = 'set_'.$name))) {
            $this->captcha->$method($value);
        } else if (method_exists($this, ($method = 'set_'.$name))) {
            $this->$method($value);
        }
    }

    public function __unset($name)
    {
        if (method_exists($this->captcha->get_CaptchaBase(), ($method = 'unset_'.$name))) {
            return $this->captcha->get_CaptchaBase()->$method();
        }

        if (method_exists($this->captcha, ($method = 'unset_'.$name))) {
            $this->captcha->$method();
        } else if (method_exists($this, ($method = 'unset_'.$name))) {
            $this->$method();
        }
    }

    /**
     * Get the BotDetect CodeIgniter CAPTCHA library information.
     *
     * @return array
     */
    public static function get_product_info()
    {
        return self::$product_info;
    }
}

// static field initialization
BotDetectCaptcha::$product_info = array(
    'name' => 'BotDetect 4 PHP Captcha generator integration for the CodeIgniter framework',
    'version' => '4.2.5'
);
