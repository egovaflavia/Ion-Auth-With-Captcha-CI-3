<?php

final class BDCI_LibraryLoader {

    /**
     * Disable instance creation.
     */
    private function __construct() {}

    /**
     * The the BotDetect CAPTCHA Library and override captcha library settings.
     *
     * @param object $CI  CodeIgniter object
     * @return void
     */
    public static function load()
    {
        // load bd php library
        self::load_botdetect_library();

        // load the captcha configuration defaults
        self::load_captcha_config_defaults();
    }

    /**
     * Load BotDetect CAPTCHA Library.
     *
     * @return void
     */
    private static function load_botdetect_library()
    {
        // determine the bd library location and define a constant for the path to the library
        self::determine_library_location();
        self::include_file(BDCI_LIB_PATH . 'provider' . DS . 'botdetect.php', true);
    }

    /**
     * Load the captcha configuration defaults.
     *
     * @return void
     */
    private static function load_captcha_config_defaults()
    {
        self::include_file(BDCI_LIB_PATH . 'support' . DS . 'CaptchaConfigDefaults.php', true);
    }

    /**
     * Define a constant for the path to the bd library.
     *
     * @param string $path
     * @return void
     */
    private static function define_library_path($path)
    {
        if (!defined('BDCLIB_PATH')) {
            define('BDCLIB_PATH', dirname($path) . DS . 'botdetect/');
        }
    }

    /**
     * Determine the bd library location and define a constant for the path to the bd library.
     *
     * @return void
     */
    private static function determine_library_location()
    {
        $outer_lib_1 = realpath(FCPATH . '../../botdetect-captcha-lib/botdetect.php');
        $outer_lib_2 = realpath(FCPATH . '../../lib/botdetect.php');

        $inner_root_dir_lib_1 = FCPATH . 'botdetect-captcha-lib' . DS . 'botdetect.php';
        $inner_root_dir_lib_2 = FCPATH . 'lib' . DS . 'botdetect.php';

        $inner_app_dir_lib_1 = BDCI_LIB_PATH . 'botdetect-captcha-lib' . DS . 'botdetect.php';
        $inner_app_dir_lib_2 = BDCI_LIB_PATH . 'lib' . DS . 'botdetect.php';

        if (is_readable($outer_lib_1)) {
            self::define_library_path($outer_lib_1);
        } else if (is_readable($outer_lib_2)) {
            self::define_library_path($outer_lib_2);
        } else if (is_readable($inner_app_dir_lib_1)) {
            self::define_library_path($inner_app_dir_lib_1);
        } else if (is_readable($inner_app_dir_lib_2)) {
            self::define_library_path($inner_app_dir_lib_2);
        } else if (is_readable($inner_root_dir_lib_1)) {
            self::define_library_path($inner_root_dir_lib_1);
        } else if (is_readable($inner_root_dir_lib_2)) {
            self::define_library_path($inner_root_dir_lib_2);
        } else {
            // show an error message if user does not include lib yet
            self::show_error_library_include_message();
        }
    }

    /**
     * Show an error message if user does not yet include the BD libarry into the lib/ folder.
     */
    private static function show_error_library_include_message()
    {
        $destination_path = BDCI_LIB_PATH . 'botdetect-captcha-lib';
        echo 'You have downloaded our CodeIgniter example, but you are missing BotDetect Captcha library which comes as a separate download. To resolve the issue:

            <br><br>1) Download BotDetect PHP CAPTCHA Library from here: <a href="https://captcha.com/captcha-download.html?version=php&amp;utm_source=installation&amp;utm_medium=php&amp;utm_campaign=CodeIgniter">https://captcha.com/captcha-download.html?version=php</a>

            <br><br>2) Copy (all contents of the directory)
            <br>from: &lt;BDLIB&gt;/botdetect-captcha-lib
            <br>to: ' . $destination_path . '
            <br><i>* where &lt;BDLIB&gt; stands for the downloaded and extracted contents of the BotDetect PHP Captcha library</i>

            <br><br>Here is where you can find more details: <a href="https://captcha.com/doc/php/howto/codeigniter-captcha.html?utm_source=installation&amp;utm_medium=php&amp;utm_campaign=CodeIgniter">https://captcha.com/doc/php/howto/codeigniter-captcha.html</a>
            <br>';
        die;
    }

    /**
     * Include a file.
     *
     * @param string  $file_path
     * @param bool    $once
     * @return void
     */
    private static function include_file($file_path, $once = false)
    {
        if (is_file($file_path)) {
            $once ? include_once($file_path) : include($file_path);
        }
    }

}
