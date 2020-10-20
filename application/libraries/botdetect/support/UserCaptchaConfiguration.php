<?php

final class BDCI_UserCaptchaConfiguration {

    /**
     * Disable instance creation.
     */
    private function __construct() {}

    /**
     * Get user's captcha configuration by captcha id.
     *
     * @param string  $captcha_id
     * @return array
     */
    public static function get($captcha_id)
    {
        $captcha_id = trim($captcha_id);

        $captcha_id_temp = strtolower($captcha_id);
        $configs = array_change_key_case(self::all(), CASE_LOWER);

        $config = (is_array($configs) && array_key_exists($captcha_id_temp, $configs))
            ? $configs[$captcha_id_temp]
            : null;

        if (is_array($config)) {
            $config['CaptchaId'] = $captcha_id;
        }

        return $config;
    }

    /**
     * Get all user's captcha configuration.
     *
     * @return array
     */
    public static function all()
    {
        $CI =& get_instance();
        $CI->load->config('captcha', TRUE);
        $all_configs = $CI->config->config;
        $captcha_configs = $all_configs['captcha'];
        return $captcha_configs;
    }

    /**
     * Save user's captcha configuration options.
     *
     * @param array     $config
     * @return void
     */
    public static function save(array $config)
    {
        unset($config['CaptchaId']);
        unset($config['UserInputID']);

        if (empty($config)) {
            return;
        }

        $settings = CaptchaConfiguration::LoadSettings();

        foreach ($config as $option => $value) {
            $settings->$option = $value;
        }

        CaptchaConfiguration::SaveSettings($settings);
    }
}
