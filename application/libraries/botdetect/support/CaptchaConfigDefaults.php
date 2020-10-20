<?php

$CI =& get_instance();

$BotDetect = CaptchaConfiguration::GetSettings();
$BotDetect->HandlerUrl = $CI->config->base_url('index.php/botdetect/captcha-handler');
CaptchaConfiguration::SaveSettings($BotDetect);
