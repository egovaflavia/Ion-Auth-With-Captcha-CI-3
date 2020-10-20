<?php
if (!defined('DS')) { define('DS', DIRECTORY_SEPARATOR); }
if (!defined('BDCI_LIB_PATH')) { define('BDCI_LIB_PATH', dirname(__FILE__) . DS); }

require_once(BDCI_LIB_PATH . 'support/SimpleLibraryLoader.php');

class BotDetectSimpleCaptcha {

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

        
        // load botdetect captcha library
        BDCI_SimpleLibraryLoader::load();

        $captcha_id = null;
        if(isset($params['captchaId'])) {
            $captcha_id = $params['captchaId'];
        }
        
        // init botdetect captcha instance
        $this->init_captcha($params, $captcha_id);
    }

    /**
     * Initialize CAPTCHA instance.
     *
     * @param  array  $config
     * @return void
     */
    public function init_captcha($params = array(), $captchaId = null)
    {
        if (array_key_exists('captchaStyleName', $params)) {
            $captchaStyleName = $params['captchaStyleName'];
        } else {
            $captchaStyleName = 'defaultCaptcha';
        }
        $this->captcha = new \SimpleCaptcha($captchaStyleName, $captchaId);
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

}
