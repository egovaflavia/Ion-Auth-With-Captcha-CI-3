<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Captcha_handler extends CI_Controller {

    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        if ($this->is_get_resource_contents_request()) {
            // getting contents of css, js, and gif files.
            $this->get_resource_contents();
        } else {
            // getting captcha image, sound, validation result
            $this->load_botdetect_captcha_library();
            $command_string = $this->input->get('get');

            if (!BDC_StringHelper::HasValue($command_string)) {
                BDC_HttpHelper::BadRequest('command');
            }

            $command_string = BDC_StringHelper::Normalize($command_string);
            $command = BDC_CaptchaHttpCommand::FromQuerystring($command_string);
            $response_body = '';
            switch ($command) {
                case BDC_CaptchaHttpCommand::GetImage:
                    $response_body = $this->get_image();
                    break;
                case BDC_CaptchaHttpCommand::GetSound:
                    $response_body = $this->get_sound();
                    break;
                case BDC_CaptchaHttpCommand::GetValidationResult:
                    $response_body = $this->get_validation_result();
                    break;
                case \BDC_CaptchaHttpCommand::GetScriptInclude:
                    $response_body = $this->get_script_include();
                    break;
                case \BDC_CaptchaHttpCommand::GetP:
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
    }

    public function get_resource_contents()
    {
        $filename = $this->input->get('get');

        if (!preg_match('/^[a-z-]+\.(css|gif|js)$/', $filename)) {
            $this->bad_request('Invalid file name.');
        }

        $resource_path = $this->get_resource_path();

        if (is_null($resource_path)) {
            $this->bad_request('Resource folder could not be found.');
        }

        $file_path = $resource_path . $filename;

        if (!is_file($file_path)) {
            $this->bad_request(sprintf('File "%s" could not be found.', $filename));
        }

        $file_info  = pathinfo($file_path);
        $this->output
            ->set_content_type($file_info['extension'])
            ->set_output(file_get_contents($file_path));
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
        $captcha_id = $this->input->get('c');
        if (!isset($captcha_id) || !preg_match('/^(\w+)$/ui', $captcha_id)) {
            $this->bad_request('Invalid captcha id.');
        }

        $captchaInstanceId = $this->input->get('t');
        if (is_null($captchaInstanceId) || !(32 == strlen($captchaInstanceId) &&
                (1 === preg_match("/^([a-f0-9]+)$/u", $captchaInstanceId)))) {
            $this->bad_request('Invalid instance id.');
        }

        $this->load->library('botdetect/BotDetectCaptcha', array(
                'captchaConfig' => $captcha_id,
                'captchaInstanceId' => $captchaInstanceId
            )
        );
    }

    public function get_image() {

        if (is_null($this->botdetectcaptcha)) {
            BDC_HttpHelper::BadRequest('captcha');
        }

        // identifier of the particular Captcha object instance
        $instance_id = $this->get_instance_id();
        if (is_null($instance_id)) {
            BDC_HttpHelper::BadRequest('instance');
        }

        // image generation invalidates sound cache, if any
        $this->clearSoundData($instance_id);

        // response headers
        BDC_HttpHelper::DisallowCache();

        // response MIME type & headers
        $mime_type = $this->botdetectcaptcha->CaptchaBase->ImageMimeType;
        $this->output->set_content_type($mime_type);
        // we don't support content chunking, since image files
        // are regenerated randomly on each request
        header('Accept-Ranges: none');

        // image generation
        $raw_image = $this->botdetectcaptcha->CaptchaBase->GetImage($instance_id);
        $this->botdetectcaptcha->CaptchaBase->SaveCodeCollection();
        return $raw_image;
    }

    public function get_sound()
    {
        if (is_null($this->botdetectcaptcha)) {
            BDC_HttpHelper::BadRequest('captcha');
        }

        // identifier of the particular Captcha object instance
        $instance_id = $this->get_instance_id();
        if (is_null($instance_id)) {
            BDC_HttpHelper::BadRequest('instance');
        }

        $soundBytes = $this->getSoundData($this->botdetectcaptcha, $instance_id);

        if (is_null($soundBytes)) {
            \BDC_HttpHelper::BadRequest('Please reload the form page before requesting another Captcha sound');
            exit;
        }

        $totalSize = strlen($soundBytes);

        // response headers
        \BDC_HttpHelper::SmartDisallowCache();

        // response MIME type & headers
        $mime_type = $this->botdetectcaptcha->CaptchaBase->SoundMimeType;
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
                \BDC_HttpHelper::BadRequest('invalid byte range');
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

    public function getSoundData($p_Captcha, $p_InstanceId) {
        $shouldCache = (
            ($p_Captcha->SoundRegenerationMode == \SoundRegenerationMode::None) || // no sound regeneration allowed, so we must cache the first and only generated sound
            $this->detectIosRangeRequest() // keep the same Captcha sound across all chunked iOS requests
        );

        if ($shouldCache) {
            $loaded = $this->loadSoundData($p_InstanceId);
            if (!is_null($loaded)) {
                return $loaded;
            }
        } else {
            $this->clearSoundData($p_InstanceId);
        }

        $soundBytes = $this->generateSoundData($p_Captcha, $p_InstanceId);
        if ($shouldCache) {
            $this->saveSoundData($p_InstanceId, $soundBytes);
        }
        return $soundBytes;
    }

    private function generateSoundData($p_Captcha, $p_InstanceId) {
        $rawSound = $p_Captcha->CaptchaBase->GetSound($p_InstanceId);
        $p_Captcha->CaptchaBase->SaveCodeCollection(); // always record sound generation count
        return $rawSound;
    }

    private function saveSoundData($p_InstanceId, $p_SoundBytes) {
        BDC_Persistence_Save("BDC_Cached_SoundData_" . $p_InstanceId, $p_SoundBytes);
    }

    private function loadSoundData($p_InstanceId) {
        return BDC_Persistence_Load("BDC_Cached_SoundData_" . $p_InstanceId);
    }

    private function clearSoundData($p_InstanceId) {
        BDC_Persistence_Clear("BDC_Cached_SoundData_" . $p_InstanceId);
    }


    // Instead of relying on unreliable user agent checks, we detect the iOS sound
    // requests by the Http headers they will always contain
    private function detectIosRangeRequest() {
        if (array_key_exists('HTTP_RANGE', $_SERVER) &&
            \BDC_StringHelper::HasValue($_SERVER['HTTP_RANGE'])) {

            // Safari on MacOS and all browsers on <= iOS 10.x
            if (array_key_exists('HTTP_X_PLAYBACK_SESSION_ID', $_SERVER) &&
                \BDC_StringHelper::HasValue($_SERVER['HTTP_X_PLAYBACK_SESSION_ID'])) {
                return true;
            }

            $userAgent = array_key_exists('HTTP_USER_AGENT', $_SERVER) ? $_SERVER['HTTP_USER_AGENT'] : null;

            // all browsers on iOS 11.x and later
            if (\BDC_StringHelper::HasValue($userAgent)) {
                $userAgentLC = \BDC_StringHelper::Lowercase($userAgent);
                if (\BDC_StringHelper::Contains($userAgentLC, "like mac os") || \BDC_StringHelper::Contains($userAgentLC, "like macos")) {
                    return true;
                }
            }
        }
        return false;
    }

    private function getSoundByteRange() {
        // chunked requests must include the desired byte range
        $rangeStr = $_SERVER['HTTP_RANGE'];
        if (!\BDC_StringHelper::HasValue($rangeStr)) {
            return;
        }

        $matches = array();
        preg_match_all('/bytes=([0-9]+)-([0-9]+)/', $rangeStr, $matches);
        return array(
            'start' => (int) $matches[1][0],
            'end'   => (int) $matches[2][0]
        );
    }

    private function detectFakeRangeRequest() {
        $detected = false;
        if (array_key_exists('HTTP_RANGE', $_SERVER)) {
            $rangeStr = $_SERVER['HTTP_RANGE'];
            if (\BDC_StringHelper::HasValue($rangeStr) &&
                preg_match('/bytes=0-$/', $rangeStr)) {
                $detected = true;
            }
        }
        return $detected;
    }

    public function get_validation_result()
    {
        if (is_null($this->botdetectcaptcha)) {
            BDC_HttpHelper::BadRequest('captcha');
        }

        // identifier of the particular Captcha object instance
        $instance_id = $this->get_instance_id();
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
            $result = $this->botdetectcaptcha->AjaxValidate($user_input, $instance_id);
            $this->botdetectcaptcha->CaptchaBase->SaveCodeCollection();
        }

        $result_json = $this->get_json_validation_result($result);

        return $result_json;
    }

    public function get_script_include() {
        // saved data for the specified Captcha object in the application
        if (is_null($this->botdetectcaptcha)) {
            \BDC_HttpHelper::BadRequest('captcha');
        }

        // identifier of the particular Captcha object instance
        $instance_id = $this->get_instance_id();
        if (is_null($instance_id)) {
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

        $file_path = $resource_path . 'bdc-traditional-api-script-include.js';

        if (!is_file($file_path)) {
            $this->bad_request(sprintf('File "%s" could not be found.', $file_path));
        }

        $script = file_get_contents($file_path);


        // 2. load BotDetect Init script
        $script .= \BDC_CaptchaScriptsHelper::GetInitScriptMarkup($this->botdetectcaptcha, $instance_id);

        // 3. load remote scripts if enabled
        if ($this->botdetectcaptcha->RemoteScriptEnabled) {
            $script .= "\r\n";
            $script .= \BDC_CaptchaScriptsHelper::GetRemoteScript($this->botdetectcaptcha);
        }

        return $script;
    }

    private function get_instance_id()
    {
        $instance_id = $this->input->get('t');
        if (!BDC_StringHelper::HasValue($instance_id) ||
            !BDC_CaptchaBase::IsValidInstanceId($instance_id)) {
            return;
        }
        return $instance_id;
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

    private function is_get_resource_contents_request()
    {
        $http_get_data = $this->input->get(NULL, TRUE);
        return array_key_exists('get', $http_get_data) && !array_key_exists('c', $http_get_data);
    }

    private function bad_request($message)
    {
        $this->output->set_content_type('text/plain');
        $this->output->set_status_header('400');
        echo $message;
        exit;
    }

    public function get_p() {
        if (is_null($this->botdetectcaptcha)) {
            \BDC_HttpHelper::BadRequest('Captcha doesn\'t exist');
        }

        // identifier of the particular Captcha object instance
        $instance_id = $this->get_instance_id();
        if (is_null($instance_id)) {
            \BDC_HttpHelper::BadRequest('Instance doesn\'t exist');
        }

        // create new one
        $p = $this->botdetectcaptcha->GenPw($instance_id);
        $this->botdetectcaptcha->SavePw($this->botdetectcaptcha);

        // response data
        $response = "{\"sp\":\"{$p->GetSP()}\",\"hs\":\"{$p->GetHs()}\"}";


        // response MIME type & headers
        header('Content-Type: application/json');
        header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet');
        \BDC_HttpHelper::SmartDisallowCache();

        return $response;
    }
}
