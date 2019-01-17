<?php

require_once __DIR__ . '/vendor/autoload.php'; // change path as needed

if (!function_exists('hash_equals')) {
    function hash_equals($str1, $str2)
    {
        if (strlen($str1) != strlen($str2)) {
            return false;
        } else {
            $res = $str1 ^ $str2;
            $ret = 0;
            for ($i = strlen($res) - 1; $i >= 0; $i--) {
                $ret |= ord($res[$i]);
            }

            return !$ret;
        }
    }
}

class AuthResponse
{
    public $isError;
    public $errorMessage;

    /**
     * AuthResponse constructor.
     *
     * @param bool   $isError
     * @param string $errorMessage
     */
    public function __construct($isError, $errorMessage)
    {
        $this->isError      = $isError;
        $this->errorMessage = $errorMessage;
    }
}

class AuthProvider
{

    private function debugRequest()
    {
        ob_start();
        echo json_encode(apache_request_headers()) . PHP_EOL;
        echo json_encode($_SERVER) . PHP_EOL;
        #echo json_encode($_POST) . PHP_EOL;
        echo file_get_contents('php://input');
        //  Return the contents of the output buffer
        $htmlStr = ob_get_contents();
        // Clean (erase) the output buffer and turn off output buffering
        ob_end_clean();
        // Write final string to file
        file_put_contents('log.txt', $htmlStr, FILE_APPEND);
    }

    public function validate()
    {
        //get headers
        $a             = getallheaders();
        $provided_hmac = substr($a['Authorization'], 5);

        //Get data from request
        $data = file_get_contents('php://input');

        //json decode into array
        //$json = json_decode($data, true);

        $dotenv = Dotenv\Dotenv::create(__DIR__);
        $dotenv->load();

        //hashing
        $hash            = hash_hmac("sha256", $data, base64_decode(getenv('TEAMS_SECRET_KEY')), true);
        $calculated_hmac = base64_encode($hash);

        try {
            if (!hash_equals($provided_hmac, $calculated_hmac)) {
                throw new Exception("No hash matching");
            }
        } catch (Exception $e) {
            $e->getMessage();
            $this->debugRequest();
        }
    }
}

class AmberyMenu
{
    public function getAmberyTodayMenu()
    {

        $dotenv = Dotenv\Dotenv::create(__DIR__);
        $dotenv->load();

        $client = new GuzzleHttp\Client();
        $result = $client->request(
            'GET',
            'https://graph.facebook.com/v2.11/393693797503834/feed?access_token=' . getenv('FB_APP_ID') . '|' . getenv('FB_APP_SECRET')
        );

        if (200 == $result->getStatusCode()) {
            $posts = json_decode($result->getBody());
            if ($posts) {
                foreach ($posts->data as $post) {
                    #var_dump($post);
                    #var_dump($this->strpos_arr($post->message, ['Meniu', 'Desert', 'cartofi', 'Ciorba']));
                    if (!empty($post->message) && $this->strpos_arr($post->message, ['Meniu', 'Desert', 'cartofi', 'Ciorba'])) {
                        // check date
                        $meniuDate = DateTime::createFromFormat(DateTime::ISO8601, $post->created_time);
                        #var_dump($meniuDate->format('Y-m-d'), date('Y-m-d'), $post->message);
                        if (date('Y-m-d') == $meniuDate->format('Y-m-d')) {
                            try {
                                $return['type'] = 'message';
                                $return['text'] = '(puke) ' . $post->message;
                                echo json_encode($return);
                            } catch (Exception $e) {
                                $return['type'] = 'message';
                                $return['text'] = 'Mai sunt 100 de coaste la cuptor. Acusica le scoatem. Mai asteapta!';
                                echo json_encode($return);
                            }
                        }
                    }
                }
            }
        }

    }

    private function strpos_arr($haystack, $needle)
    {
        if (!is_array($needle)) {
            $needle = array($needle);
        }
        foreach ($needle as $what) {
            return ($pos = strpos($haystack, $what)) !== false;
        }

        return false;
    }
}

$authProvider = new AuthProvider();
$authProvider->validate();

(new AmberyMenu())->getAmberyTodayMenu();