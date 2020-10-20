<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Simple_captcha_handler extends CI_Controller {

    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        // getting captcha image, sound, validation result
        $this->load_botdetect_captcha_library();

        $command_string = $this->input->get('get');

        if (!BDC_StringHelper::HasValue($command_string)) {
            BDC_HttpHelper::BadRequest('command');
        }

        $command_string = BDC_StringHelper::Normalize($command_string);
        $command = BDC_SimpleCaptchaHttpCommand::FromQuerystring($command_string);
        $response_body = '';
        switch ($command) {
            case BDC_SimpleCaptchaHttpCommand::GetImage:
                $response_body = $this->get_image();
                break;
            case BDC_SimpleCaptchaHttpCommand::GetBase64ImageString:
                $response_body = $this->get_base64_image_string();
                break;
            case BDC_SimpleCaptchaHttpCommand::GetSound:
                $response_body = $this->get_sound();
                break;
            case BDC_SimpleCaptchaHttpCommand::GetValidationResult:
                $response_body = $this->get_validation_result();
                break;
            case BDC_SimpleCaptchaHttpCommand::GetHtml:
                $response_body = $this->get_html();
                break;
            
            // Sound icon
            case BDC_SimpleCaptchaHttpCommand::GetSoundIcon:
                $response_body = $this->get_sound_icon();
                break;
            case BDC_SimpleCaptchaHttpCommand::GetSoundSmallIcon:
                $response_body = $this->get_small_sound_icon();
                break;
            case BDC_SimpleCaptchaHttpCommand::GetSoundDisabledIcon:
                $response_body = $this->get_disabled_sound_icon();
                break;
            case BDC_SimpleCaptchaHttpCommand::GetSoundSmallDisabledIcon:
                $response_body = $this->get_small_disabled_sound_icon();
                break;
            
            // Reload icon
            case BDC_SimpleCaptchaHttpCommand::GetReloadIcon:
                $response_body = $this->get_reload_icon();
                break;
            case BDC_SimpleCaptchaHttpCommand::GetReloadSmallIcon:
                $response_body = $this->get_small_reload_icon();
                break;
            case BDC_SimpleCaptchaHttpCommand::GetReloadDisabledIcon:
                $response_body = $this->get_disabled_reload_icon();
                break;
            case BDC_SimpleCaptchaHttpCommand::GetReloadSmallDisabledIcon:
                $response_body = $this->get_small_disabled_reload_icon();
                break;
            // css, js
            case BDC_SimpleCaptchaHttpCommand::GetScriptInclude:
                $response_body = $this->get_script_include();
                break;
            case BDC_SimpleCaptchaHttpCommand::GetLayoutStyleSheet:
                $response_body = $this->get_layout_stylesheet();
                break;
            case BDC_SimpleCaptchaHttpCommand::GetP:
                $response_body = $this->get_p();
                break;

            default:
                BDC_HttpHelper::BadRequest('command');
        }

        // disallow audio file search engine indexing
        $this->output
            ->set_header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet')
            ->cache(0)
            ->set_output($response_body);
    }

    private function get_resource_path()
    {
        if (!defined('DS')) { define('DS', DIRECTORY_SEPARATOR); }

        $outer_1 = FCPATH . '../../botdetect-captcha-lib/botdetect/public/';
        $outer_2 = FCPATH . '../../lib/botdetect/public/';
        
        $inner_root_dir_1 = FCPATH . 'botdetect-captcha-lib' . DS . 'botdetect' . DS . 'public' . DS;
        $inner_root_dir_2 = FCPATH . 'lib' . DS . 'botdetect' . DS . 'public' . DS;
        
        $inner_app_dir_1 = APPPATH . 'libraries' . DS . 'botdetect' . DS . 'botdetect-captcha-lib' . DS . 'botdetect' . DS .'public' . DS;
        $inner_app_dir_2 = APPPATH . 'libraries' . DS . 'botdetect' . DS . 'lib' . DS . 'botdetect' . DS .'public' . DS;
        
        if (is_dir($inner_app_dir_1)) { return $inner_app_dir_1; }
        if (is_dir($inner_app_dir_2)) { return $inner_app_dir_2; }
        if (is_dir($outer_1)) { return $outer_1; }
        if (is_dir($outer_2)) { return $outer_2; }
        if (is_dir($inner_root_dir_1)) { return $inner_root_dir_1; }
        if (is_dir($inner_root_dir_2)) { return $inner_root_dir_2; }

        return null;
    }

    private function load_botdetect_captcha_library()
    {
        require_once(APPPATH . 'libraries/botdetect/support/SimpleLibraryLoader.php');
        BDCI_SimpleLibraryLoader::load();

        $captcha_style_name = $this->input->get('c');
        if (is_null($captcha_style_name) || !preg_match('/^(\w+)$/ui', $captcha_style_name)) {
            return null;
        }
        $captcha_id = $this->input->get('t');
        if ($captcha_id !== null) {
            $captcha_id = BDC_StringHelper::Normalize($captcha_id);
            if (1 !== preg_match(BDC_SimpleCaptchaBase::VALID_CAPTCHA_ID, $captcha_id)) {
                return null;
            }
        }

        $this->load->library('botdetect/BotDetectSimpleCaptcha', array(
                'captchaStyleName' => $captcha_style_name,
                'captchaId' => $captcha_id
            )
        );
    }


    /**
     * Generate a Captcha image.
     *
     * @return image
     */
    public function get_image()
    {
        header("Access-Control-Allow-Origin: *");
        // authenticate client-side request
        $cors_auth = new CorsAuth();
        if (!$cors_auth->IsClientAllowed()) {
            BDC_HttpHelper::BadRequest($cors_auth->GetFrontEnd() . " is not an allowed front-end");
            return null;
        }

        if (is_null($this->botdetectsimplecaptcha)) {
            BDC_HttpHelper::BadRequest('captcha');
        }

        // identifier of the particular Captcha object instance
        $captcha_id = $this->get_captcha_id();
        if (is_null($captcha_id)) {
            BDC_HttpHelper::BadRequest('instance');
        }

        // image generation invalidates sound cache, if any
        $this->clearSoundData($this->botdetectsimplecaptcha, $captcha_id);

        // response headers
        BDC_HttpHelper::DisallowCache();

        // response MIME type & headers
        $image_type = ImageFormat::GetName($this->botdetectsimplecaptcha->ImageFormat);
        $image_type = strtolower($image_type[0]);
        $mime_type = "image/" . $image_type;
        header("Content-Type: {$mime_type}");

        // we don't support content chunking, since image files
        // are regenerated randomly on each request
        header('Accept-Ranges: none');

        // image generation
        $raw_image = $this->get_image_data($this->botdetectsimplecaptcha);

        $length = strlen($raw_image);
        header("Content-Length: {$length}");
        return $raw_image;
    }

    public function get_base64_image_string()
    {
        header("Access-Control-Allow-Origin: *");
  
        // authenticate client-side request
        $cors_auth = new CorsAuth();
        if (!$cors_auth->IsClientAllowed()) {
            BDC_HttpHelper::BadRequest($cors_auth->GetFrontEnd() . " is not an allowed front-end");
            return null;
        }
        
        // MIME type
        $image_type = ImageFormat::GetName($this->botdetectsimplecaptcha->ImageFormat);
        $image_type = strtolower($image_type[0]);
        $mime_type = "image/" . $image_type;
        $raw_image = $this->get_image_data($this->botdetectsimplecaptcha);
        $base64_image_string = sprintf('data:%s;base64,%s', $mime_type, base64_encode($raw_image));
        return $base64_image_string;
    }

    private function get_image_data($p_Captcha)
    {
        // identifier of the particular Captcha object instance
        $captcha_id = $this->get_captcha_id();
        if (is_null($captcha_id)) {
          BDC_HttpHelper::BadRequest('Captcha Id doesn\'t exist');
        }
      
        if ($this->is_obvious_bot_request($p_Captcha)) {
          return;
        }
      
        // image generation invalidates sound cache, if any
        $this->clearSoundData($p_Captcha, $captcha_id);
      
        // response headers
        BDC_HttpHelper::DisallowCache();
      
        // we don't support content chunking, since image files
        // are regenerated randomly on each request
        header('Accept-Ranges: none');
      
        // disallow audio file search engine indexing
        header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet');
      
        // image generation
        $raw_image = $p_Captcha->CaptchaBase->GetImage($captcha_id);
        $p_Captcha->SaveCode($captcha_id, $p_Captcha->CaptchaBase->Code); // record generated Captcha code for validation
      
        return $raw_image;
    }

    public function get_sound()
    {
        header("Access-Control-Allow-Origin: *");
        // authenticate client-side request
        $cors_auth = new \CorsAuth();
        if (!$cors_auth->IsClientAllowed()) {
            BDC_HttpHelper::BadRequest($cors_auth->GetFrontEnd() . " is not an allowed front-end");
            return null;
        }

        if (is_null($this->botdetectsimplecaptcha)) {
            BDC_HttpHelper::BadRequest('captcha');
        }

        // identifier of the particular Captcha object instance
        $instance_id = $this->get_captcha_id();
        if (is_null($instance_id)) {
            BDC_HttpHelper::BadRequest('instance');
        }

        $soundBytes = $this->getSoundData($this->botdetectsimplecaptcha, $instance_id);

        if (is_null($soundBytes)) {
            BDC_HttpHelper::BadRequest('Please reload the form page before requesting another Captcha sound');
            exit;
        }

        $totalSize = strlen($soundBytes);

        // response headers
        BDC_HttpHelper::SmartDisallowCache();

        // response MIME type & headers
        $mime_type = $this->botdetectsimplecaptcha->CaptchaBase->SoundMimeType;
        $this->output->set_content_type($mime_type);

        if (!array_key_exists('d', $_GET)) { // javascript player not used, we send the file directly as a download
            $downloadId = \BDC_CryptoHelper::GenerateGuid();
            header("Content-Disposition: attachment; filename=captcha_{$downloadId}.wav");
        }

        if ($this->detectIosRangeRequest()) { // iPhone/iPad sound issues workaround: chunked response for iOS clients
            // sound byte subset
            $range = $this->getSoundByteRange();
            $rangeStart = $range['start'];
            $rangeEnd = $range['end'];
            $rangeSize = $rangeEnd - $rangeStart + 1;

            // initial iOS 6.0.1 testing; leaving as fallback since we can't be sure it won't happen again:
            // we depend on observed behavior of invalid range requests to detect
            // end of sound playback, cleanup and tell AppleCoreMedia to stop requesting
            // invalid "bytes=rangeEnd-rangeEnd" ranges in an infinite(?) loop
            if ($rangeStart == $rangeEnd || $rangeEnd > $totalSize) {
                BDC_HttpHelper::BadRequest('invalid byte range');
            }

            $rangeBytes = substr($soundBytes, $rangeStart, $rangeSize);

            // partial content response with the requested byte range
            header('HTTP/1.1 206 Partial Content');
            header('Accept-Ranges: bytes');
            header("Content-Length: {$rangeSize}");
            header("Content-Range: bytes {$rangeStart}-{$rangeEnd}/{$totalSize}");
            return $rangeBytes; // chrome needs this kind of response to be able to replay Html5 audio
        } else if ($this->detectFakeRangeRequest()) {
            header('Accept-Ranges: bytes');
            header("Content-Length: {$totalSize}");
            $end = $totalSize - 1;
            header("Content-Range: bytes 0-{$end}/{$totalSize}");
            return $soundBytes;
        } else { // regular sound request
            header('Accept-Ranges: none');
            header("Content-Length: {$totalSize}");
            return $soundBytes;
        }
    }

    public function getSoundData($p_Captcha, $p_CaptchaId)
    {
        $shouldCache = (
            ($p_Captcha->SoundRegenerationMode == \SoundRegenerationMode::None) || // no sound regeneration allowed, so we must cache the first and only generated sound
            $this->detectIosRangeRequest() // keep the same Captcha sound across all chunked iOS requests
        );

        if ($shouldCache) {
            $loaded = $this->loadSoundData($p_Captcha, $p_CaptchaId);
            if (!is_null($loaded)) {
                return $loaded;
            }
        } else {
            $this->clearSoundData($p_Captcha, $p_CaptchaId);
        }

        $soundBytes = $this->generateSoundData($p_Captcha, $p_CaptchaId);
        if ($shouldCache) {
            $this->saveSoundData($p_Captcha, $p_CaptchaId, $soundBytes);
        }
        return $soundBytes;
    }

    private function generateSoundData($p_Captcha, $p_CaptchaId)
    {
        $rawSound = $p_Captcha->CaptchaBase->GetSound($p_CaptchaId);
        $p_Captcha->SaveCode($p_CaptchaId, $p_Captcha->CaptchaBase->Code); // always record sound generation count
        return $rawSound;
    }
    private function saveSoundData($p_Captcha, $p_CaptchaId, $p_SoundBytes)
    {
        $p_Captcha->get_CaptchaPersistence()->GetPersistenceProvider()->Save("BDC_Cached_SoundData_" . $p_CaptchaId, $p_SoundBytes);
    }
    private function loadSoundData($p_Captcha, $p_CaptchaId)
    {
        $soundBytes = $p_Captcha->get_CaptchaPersistence()->GetPersistenceProvider()->Load("BDC_Cached_SoundData_" . $p_CaptchaId);
        return $soundBytes;
    }
    private function clearSoundData($p_Captcha, $p_CaptchaId)
    {
        $p_Captcha->get_CaptchaPersistence()->GetPersistenceProvider()->Remove("BDC_Cached_SoundData_" . $p_CaptchaId);
    }


    // Instead of relying on unreliable user agent checks, we detect the iOS sound
    // requests by the Http headers they will always contain
    private function detectIosRangeRequest()
    {
        if (array_key_exists('HTTP_RANGE', $_SERVER) &&
            BDC_StringHelper::HasValue($_SERVER['HTTP_RANGE'])) {

            // Safari on MacOS and all browsers on <= iOS 10.x
            if (array_key_exists('HTTP_X_PLAYBACK_SESSION_ID', $_SERVER) &&
                BDC_StringHelper::HasValue($_SERVER['HTTP_X_PLAYBACK_SESSION_ID'])) {
                return true;
            }

            $userAgent = array_key_exists('HTTP_USER_AGENT', $_SERVER) ? $_SERVER['HTTP_USER_AGENT'] : null;

            // all browsers on iOS 11.x and later
            if (BDC_StringHelper::HasValue($userAgent)) {
                $userAgentLC = BDC_StringHelper::Lowercase($userAgent);
                if (BDC_StringHelper::Contains($userAgentLC, "like mac os") || BDC_StringHelper::Contains($userAgentLC, "like macos")) {
                    return true;
                }
            }
        }
        return false;
    }

    private function getSoundByteRange()
    {
        // chunked requests must include the desired byte range
        $rangeStr = $_SERVER['HTTP_RANGE'];
        if (!BDC_StringHelper::HasValue($rangeStr)) {
            return;
        }

        $matches = array();
        preg_match_all('/bytes=([0-9]+)-([0-9]+)/', $rangeStr, $matches);
        return array(
            'start' => (int) $matches[1][0],
            'end'   => (int) $matches[2][0]
        );
    }

    private function detectFakeRangeRequest()
    {
        $detected = false;
        if (array_key_exists('HTTP_RANGE', $_SERVER)) {
            $rangeStr = $_SERVER['HTTP_RANGE'];
            if (BDC_StringHelper::HasValue($rangeStr) &&
                preg_match('/bytes=0-$/', $rangeStr)) {
                $detected = true;
            }
        }
        return $detected;
    }

    public function get_html()
    {
        header("Access-Control-Allow-Origin: *");
        $cors_auth = new CorsAuth();
        if (!$cors_auth->IsClientAllowed()) {
            BDC_HttpHelper::BadRequest($cors_auth->GetFrontEnd() . " is not an allowed front-end");
        }

        if (is_null($this->botdetectsimplecaptcha)) {
            BDC_HttpHelper::BadRequest('captcha');
        }

        $html = "<div>" . $this->botdetectsimplecaptcha->Html() . "</div>";
        return $html;
    }

    public function get_validation_result()
    {
        header("Access-Control-Allow-Origin: *");
        // authenticate client-side request
        $cors_auth = new CorsAuth();
        if (!$cors_auth->IsClientAllowed()) {
            BDC_HttpHelper::BadRequest($cors_auth->GetFrontEnd() . " is not an allowed front-end");
            return null;
        }

        if (is_null($this->botdetectsimplecaptcha)) {
            BDC_HttpHelper::BadRequest('captcha');
        }

        // identifier of the particular Captcha object instance
        $instance_id = $this->get_captcha_id();
        if (is_null($instance_id)) {
            BDC_HttpHelper::BadRequest('instance');
        }

        $mime_type = 'application/json';
        $this->output->set_content_type($mime_type);

        // code to validate
        $user_input = $this->get_user_input();

        // JSON-encoded validation result
        $result = false;
        if (isset($user_input) && (isset($instance_id))) {
            $result = $this->botdetectsimplecaptcha->AjaxValidate($user_input, $instance_id);
        }

        $result_json = $this->get_json_validation_result($result);

        return $result_json;
    }

    // Get Reload Icon group
    public function get_sound_icon()
    {
        $file_path = realpath($this->get_resource_path() . 'bdc-sound-icon.gif');
        return $this->get_web_resource($file_path, 'image/gif');
    }
    public function get_small_sound_icon()
    {
        $file_path = realpath($this->get_resource_path() . 'bdc-sound-small-icon.gif');
        return $this->get_web_resource($file_path, 'image/gif');
    }
    public function get_disabled_sound_icon()
    {
        $file_path = realpath($this->get_resource_path() . 'bdc-sound-disabled-icon.gif');
        return $this->get_web_resource($file_path, 'image/gif');
    }
    public function get_small_disabled_sound_icon()
    {
        $file_path = realpath($this->get_resource_path() . 'bdc-sound-small-disabled-icon.gif');
        return $this->get_web_resource($file_path, 'image/gif');
    }
    // Get Reload Icon group
    public function get_reload_icon()
    {
        $file_path = realpath($this->get_resource_path() . 'bdc-reload-icon.gif');
        return $this->get_web_resource($file_path, 'image/gif');
    }
    public function get_small_reload_icon()
    {
        $file_path = realpath($this->get_resource_path() . 'bdc-reload-small-icon.gif');
        return $this->get_web_resource($file_path, 'image/gif');
    }
    public function get_disabled_reload_icon()
    {
        $file_path = realpath($this->get_resource_path() . 'bdc-reload-disabled-icon.gif');
        return $this->get_web_resource($file_path, 'image/gif');
    }
    public function get_small_disabled_reload_icon()
    {
        $file_path = realpath($this->get_resource_path() . 'bdc-reload-small-disabled-icon.gif');
        return $this->get_web_resource($file_path, 'image/gif');
    }
    public function get_layout_stylesheet()
    {
        
        $file_path = realpath($this->get_resource_path() . 'bdc-layout-stylesheet.css');
        return $this->get_web_resource($file_path, 'text/css');
    }

    public function get_script_include()
    {

        header("Access-Control-Allow-Origin: *");
        
        // saved data for the specified Captcha object in the application
        if (is_null($this->botdetectsimplecaptcha)) {
            \BDC_HttpHelper::BadRequest('captcha');
        }

        // identifier of the particular Captcha object instance
        $captcha_id = $this->get_captcha_id();
        if (is_null($captcha_id)) {
            \BDC_HttpHelper::BadRequest('instance');
        }

        // response MIME type & headers
        header('Content-Type: text/javascript');
        header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet');

        // 1. load BotDetect script
        $resource_path = $this->get_resource_path();

        if (is_null($resource_path)) {
            $this->bad_request('Resource folder could not be found.');
        }

        $file_path = $resource_path . 'bdc-simple-api-script-include.js';

        if (!is_file($file_path)) {
            $this->bad_request(sprintf('File "%s" could not be found.', $file_path));
        }

        $script = file_get_contents($file_path);


        // 2. load BotDetect Init script
        $script .= \BDC_SimpleCaptchaScriptsHelper::GetInitScriptMarkup($this->botdetectsimplecaptcha, $captcha_id);

        // 3. load remote scripts if enabled
        if ($this->botdetectsimplecaptcha->RemoteScriptEnabled) {
            $script .= "\r\n";
            $script .= \BDC_SimpleCaptchaScriptsHelper::GetRemoteScript($this->botdetectsimplecaptcha);
        }

        return $script;
    }

    private function get_web_resource($p_Resource, $p_MimeType, $hasEtag = true)
    {
        header("Content-Type: $p_MimeType");
        if ($hasEtag) {
            \BDC_HttpHelper::AllowEtagCache($p_Resource);
        }

        return file_get_contents($p_Resource);
    }

    private function is_obvious_bot_request($p_Captcha)
    {
        $captchaRequestValidator = new \SimpleCaptchaRequestValidator($p_Captcha->Configuration);
      
      
        // some basic request checks
        $captchaRequestValidator->RecordRequest();
      
        if ($captchaRequestValidator->IsObviousBotAttempt()) {
          \BDC_HttpHelper::TooManyRequests('IsObviousBotAttempt');
        }
      
        return false;
    }

    private function get_captcha_id()
    {
        $captcha_id = $this->input->get('t');
        if (!BDC_StringHelper::HasValue($captcha_id) ||
            !BDC_CaptchaBase::IsValidInstanceId($captcha_id)) {
            return;
        }
        return $captcha_id;
    }

    // extract the user input Captcha code string from the Ajax validation request
    private function get_user_input()
    {
        // BotDetect built-in Ajax Captcha validation
        $input = $this->input->get('i');

        if (empty($input)) {
            // jQuery validation support, the input key may be just about anything,
            // so we have to loop through fields and take the first unrecognized one
            $recognized = array('get', 'c', 't', 'd');
            foreach ($this->input->get(NULL, TRUE) as $key => $value) {
                if (!in_array($key, $recognized)) {
                    $input = $value;
                    break;
                }
            }
        }

        return $input;
    }

    // encodes the Captcha validation result in a simple JSON wrapper
    private function get_json_validation_result($result)
    {
        $result_str = ($result ? 'true': 'false');
        return $result_str;
    }

    private function bad_request($message)
    {
        $this->output->set_content_type('text/plain');
        $this->output->set_status_header('400');
        echo $message;
        exit;
    }

    public function get_p()
    {
        header("Access-Control-Allow-Origin: *");
        // authenticate client-side request
        $cors_auth = new \CorsAuth();
        if (!$cors_auth->IsClientAllowed()) {
            \BDC_HttpHelper::BadRequest($cors_auth->GetFrontEnd() . " is not an allowed front-end");
            return null;
        }

        if (is_null($this->botdetectsimplecaptcha)) {
            \BDC_HttpHelper::BadRequest('Captcha doesn\'t exist');
        }

        // identifier of the particular Captcha object instance
        $captcha_id = $this->get_captcha_id();
        if (is_null($captcha_id)) {
            \BDC_HttpHelper::BadRequest('Instance doesn\'t exist');
        }

        // create new one
        $p = $this->botdetectsimplecaptcha->GenPw($captcha_id);
        $this->botdetectsimplecaptcha->SavePw($this->botdetectsimplecaptcha, $captcha_id);

        // response data
        $response = "{\"sp\":\"{$p->GetSP()}\",\"hs\":\"{$p->GetHs()}\"}";


        // response MIME type & headers
        header('Content-Type: application/json');
        header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet');
        \BDC_HttpHelper::SmartDisallowCache();

        return $response;
    }
}
