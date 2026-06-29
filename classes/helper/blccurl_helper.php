<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * cURL helper class
 *
 * @package    block_blc_modules
 * @copyright  2026 Terus Technology
 * @author     Ali <ali@teruselearning.co.uk>, Rama <rama@teruselearning.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * This is a wrapper class for curl, it is quite easy to use:
 *
 * $c = new blccurl_helper;
 *
 * enable cache
 * $c = new blccurl_helper(['cache'=>true]);
 *
 * enable cookie
 * $c = new blccurl_helper(['cookie'=>true]);
 *
 * enable proxy
 * $c = new blccurl_helper(['proxy'=>true]);
 *
 *
 * HTTP GET Method
 * $html = $c->get('http://example.com');
 *
 * HTTP POST Method
 * $html = $c->post('http://example.com/', ['q'=>'words', 'name'=>'moodle']);
 *
 * HTTP PUT Method
 * $html = $c->put('http://example.com/', ['file'=>'/var/www/test.txt']);
 */

namespace block_blc_modules\helper;

/**
 * This class is used to send HTTP requests, it is a wrapper of cURL module.
 */
class blccurl_helper {
    /** @var blccurlcache_helper|bool */
    public $cache;
    /** @var bool */
    public $proxy = false;
    /** @var array */
    public $response = [];
    /** @var array */
    public $header = [];
    /** @var string */
    public $info;
    /** @var string */
    public $error;

    /** @var array */
    private $options;
    /** @var bool */
    private $debug = false;
    /** @var bool */
    private $cookie = false;

    /**
     * Constructs a new blccurl_helper instance.
     *
     * @param array $options
     */
    public function __construct($options = []) {
        if (!function_exists('curl_init')) {
            $this->error = 'cURL module must be enabled!';
            trigger_error($this->error, E_USER_ERROR);
        }

        // The options of curl should be init here.
        $this->resetopt();

        if (!empty($options['debug'])) {
            $this->debug = true;
        }

        if (!empty($options['cookie'])) {
            if ($options['cookie'] === true) {
                $this->cookie = 'curl_cookie.txt';
            } else {
                $this->cookie = $options['cookie'];
            }
        }

        if (!empty($options['cache'])) {
            $this->cache = new blccurlcache_helper();
        }
    }

    /**
     * Resets the CURL options that have already been set
     */
    public function resetopt() {
        $this->options = [];
        $this->options['CURLOPT_USERAGENT'] = 'MoodleBot/1.0';
        // True to include the header in the output.
        $this->options['CURLOPT_HEADER'] = 0;
        // True to Exclude the body from the output.
        $this->options['CURLOPT_NOBODY'] = 0;
        // TRUE to follow any "Location: " header that the server
        // sends as part of the HTTP header
        // (note this is recursive, PHP will follow as many "Location: " headers that it is sent).
        $this->options['CURLOPT_MAXREDIRS'] = 10;
        $this->options['CURLOPT_ENCODING'] = '';
        // TRUE to return the transfer as a string of the return
        // value of curl_exec() instead of outputting it out directly.
        $this->options['CURLOPT_RETURNTRANSFER'] = 1;
        $this->options['CURLOPT_BINARYTRANSFER'] = 0;
        $this->options['CURLOPT_SSL_VERIFYPEER'] = 0;
        $this->options['CURLOPT_SSL_VERIFYHOST'] = 2;
        $this->options['CURLOPT_CONNECTTIMEOUT'] = 30;
    }

    /**
     * Reset Cookie
     */
    public function resetcookie() {
        if (!empty($this->cookie)) {
            if (is_file($this->cookie)) {
                $fp = fopen($this->cookie, 'w');
                if (!empty($fp)) {
                    fwrite($fp, '');
                    fclose($fp);
                }
            }
        }
    }

    /**
     * Set curl options
     *
     * @param ?array $options If array is null, this function will reset the options to default value.
     */
    public function setopt($options = []) {
        if (is_array($options)) {
            foreach ($options as $name => $val) {
                if (stripos($name, 'CURLOPT_') === false) {
                    $name = strtoupper('CURLOPT_'.$name);
                }
                $this->options[$name] = $val;
            }
        }
    }

    /**
     * Reset http method
     */
    public function cleanopt() {
        unset($this->options['CURLOPT_HTTPGET']);
        unset($this->options['CURLOPT_POST']);
        unset($this->options['CURLOPT_POSTFIELDS']);
        unset($this->options['CURLOPT_PUT']);
        unset($this->options['CURLOPT_INFILE']);
        unset($this->options['CURLOPT_INFILESIZE']);
        unset($this->options['CURLOPT_CUSTOMREQUEST']);
    }

    /**
     * Set HTTP Request Header
     *
     * @param array $header
     */
    public function set_header($header) {
        if (is_array($header)) {
            foreach ($header as $v) {
                $this->set_header($v);
            }
        } else {
            $this->header[] = $header;
        }
    }

    /**
     * Set HTTP Response Header
     *
     * @return array
     */
    public function get_response() {
        return $this->response;
    }

    /**
     * Format HTTP headers for cURL request
     *
     * @param resource $ch cURL handle
     * @param string $header Header string
     * @return int Header length
     */
    private function format_header($ch, $header): int {
        if (strlen($header) > 2) {
            [$key, $value] = explode(" ", rtrim($header, "\r\n"), 2);
            $key = rtrim($key, ':');
            if (!empty($this->response[$key])) {
                if (is_array($this->response[$key])) {
                    $this->response[$key][] = $value;
                } else {
                    $tmp = $this->response[$key];
                    $this->response[$key] = [];
                    $this->response[$key][] = $tmp;
                    $this->response[$key][] = $value;
                }
            } else {
                $this->response[$key] = $value;
            }
        }
        return strlen($header);
    }

    /**
     * Set options for individual curl instance
     *
     * @param object $curl A curl handle
     * @param array $options
     * @return object The curl handle
     */
    private function apply_opt($curl, $options) {
        $logger = new debug_helper();

        // Clean up.
        $this->cleanopt();

        // Set cookie.
        if (!empty($this->cookie) || !empty($options['cookie'])) {
            $this->setopt([
                'cookiejar' => $this->cookie,
                'cookiefile' => $this->cookie,
            ]);
        }

        // Set proxy.
        if (!empty($this->proxy) || !empty($options['proxy'])) {
            $this->setopt($this->proxy);
        }

        $this->setopt($options);

        // Reset before set options.
        curl_setopt($curl, CURLOPT_HEADERFUNCTION, [&$this, 'format_header']);

        // Set headers.
        if (empty($this->header)) {
            $this->set_header([
                'User-Agent: MoodleBot/1.0',
                'Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7',
                'Connection: keep-alive',
            ]);
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, $this->header);

        if ($this->debug) {
            echo '<h1>Options</h1>';
            $logger->info('cURL Options: ' . json_encode($this->options));
            echo '<h1>Header</h1>';
            $logger->info('cURL Header: ' . json_encode($this->header));
        }

        // Set options.
        foreach ($this->options as $name => $val) {
            if (is_string($name)) {
                $name = constant(strtoupper($name));
            }
            curl_setopt($curl, $name, $val);
        }

        return $curl;
    }
    /**
     * Download multiple files in parallel
     *
     * Calls {@link multi()} with specific download headers
     *
     * $c = new blccurl_helper;
     * $c->download([
     *              ['url'=>'http://localhost/', 'file'=>fopen('a', 'wb')],
     *              ['url'=>'http://localhost/20/', 'file'=>fopen('b', 'wb')]
     * ]);
     *
     * @param array $requests An array of files to request
     * @param array $options An array of options to set
     * @return array An array of results
     */
    public function download($requests, $options = []) {
        $options['CURLOPT_BINARYTRANSFER'] = 1;
        $options['RETURNTRANSFER'] = false;
        return $this->multi($requests, $options);
    }

    /**
     * Multi HTTP Requests
     * This function could run multi-requests in parallel.
     *
     * @param array $requests An array of files to request
     * @param array $options An array of options to set
     * @return array An array of results
     */
    protected function multi($requests, $options = []) {
        $count   = count($requests);
        $handles = [];
        $result = [];
        $main    = curl_multi_init();

        for ($i = 0; $i < $count; $i++) {
            $url = $requests[$i];
            foreach ($url as $n => $v) {
                $options[$n] = $url[$n];
            }
            $handles[$i] = curl_init($url['url']);
            $this->apply_opt($handles[$i], $options);
            curl_multi_add_handle($main, $handles[$i]);
        }

        $running = 0;
        do {
            curl_multi_exec($main, $running);
        } while ($running > 0);

        for ($i = 0; $i < $count; $i++) {
            if (!empty($options['CURLOPT_RETURNTRANSFER'])) {
                $result[] = true;
            } else {
                $result[] = curl_multi_getcontent($handles[$i]);
            }
            curl_multi_remove_handle($main, $handles[$i]);
        }
        curl_multi_close($main);

        return $result;
    }

    /**
     * Single HTTP Request
     *
     * @param string $url The URL to request
     * @param array $options
     * @return bool
     */
    protected function request($url, $options = []) {
        $logger = new debug_helper();

        // Create curl instance.
        $curl = curl_init($url);
        $options['url'] = $url;

        $this->apply_opt($curl, $options);

        if ($this->cache && $ret = $this->cache->get($this->options)) {
            return $ret;
        } else {
            $ret = curl_exec($curl);
            if ($this->cache) {
                $this->cache->set($this->options, $ret);
            }
        }

        $this->info = curl_getinfo($curl);
        $this->error = curl_error($curl);

        if ($this->debug) {
            echo '<h1>Return Data</h1>';
            $logger->info('cURL Response: ' . substr($ret, 0, 500));
            echo '<h1>Info</h1>';
            $logger->info('cURL Info: ' . json_encode($this->info));
            echo '<h1>Error</h1>';
            if (!empty($this->error)) {
                $logger->error('cURL Error: ' . $this->error);
            }
        }

        curl_close($curl);

        if (empty($this->error)) {
            return $ret;
        } else {
            return $this->error;
        }
    }

    /**
     * HTTP HEAD method
     *
     * @see request()
     *
     * @param string $url
     * @param array $options
     * @return bool
     */
    public function head($url, $options = []) {
        $options['CURLOPT_HTTPGET'] = 0;
        $options['CURLOPT_HEADER']  = 1;
        $options['CURLOPT_NOBODY']  = 1;
        return $this->request($url, $options);
    }

    /**
     * Recursive function formating an array in POST parameter
     * @param array $arraydata - the array that we are going to format and add into &$data array
     * @param string $currentdata - a row of the final postdata array at instant T
     *                when finish, it's assign to $data under this format: name[keyname][][]...[]='value'
     * @param array $data - the final data array containing all POST parameters : 1 row = 1 parameter
     */
    protected function format_array_postdata_for_curlcall($arraydata, $currentdata, &$data) {
        foreach ($arraydata as $k => $v) {
            $newcurrentdata = $currentdata;
            if (is_object($v)) {
                $v = (array) $v;
            }
            if (is_array($v)) { // The value is an array, call the function recursively.
                $newcurrentdata = $newcurrentdata.'['.urlencode($k).']';
                $this->format_array_postdata_for_curlcall($v, $newcurrentdata, $data);
            } else { // Add the POST parameter to the $data array.
                $data[] = $newcurrentdata.'['.urlencode($k).']='.urlencode($v);
            }
        }
    }

    /**
     * Transform a PHP array into POST parameter
     * (see the recursive function format_array_postdata_for_curlcall)
     *
     * @param array $postdata
     * @return array containing all POST parameters  (1 row = 1 POST parameter)
     */
    protected function format_postdata_for_curlcall($postdata) {
        if (is_object($postdata)) {
            $postdata = (array) $postdata;
        }
        $data = [];
        foreach ($postdata as $k => $v) {
            if (is_object($v)) {
                $v = (array) $v;
            }
            if (is_array($v)) {
                $currentdata = urlencode($k);
                $this->format_array_postdata_for_curlcall($v, $currentdata, $data);
            } else {
                $data[] = urlencode($k).'='.urlencode($v);
            }
        }
        $convertedpostdata = implode('&', $data);
        return $convertedpostdata;
    }

    /**
     * HTTP POST method
     *
     * @param string $url
     * @param array|string $params
     * @param array $options
     * @return bool
     */
    public function post($url, $params = '', $options = []) {
        $options['CURLOPT_POST'] = 1;
        if (is_array($params)) {
            $params = $this->format_postdata_for_curlcall($params);
        }
        $options['CURLOPT_POSTFIELDS'] = $params;
        return $this->request($url, $options);
    }

    /**
     * HTTP GET method
     *
     * @param string $url
     * @param array $params
     * @param array $options
     * @return bool
     */
    public function get($url, $params = [], $options = []) {
        $options['CURLOPT_HTTPGET'] = 1;

        if (!empty($params)) {
            $url .= (stripos($url, '?') !== false) ? '&' : '?';
            $url .= http_build_query($params, '', '&');
        }
        return $this->request($url, $options);
    }

    /**
     * HTTP PUT method
     *
     * @param string $url
     * @param array $params
     * @param array $options
     * @return bool
     */
    public function put($url, $params = [], $options = []) {
        $file = $params['file'];
        if (!is_file($file)) {
            return null;
        }
        $fp = fopen($file, 'r');
        $size = filesize($file);
        $options['CURLOPT_PUT']        = 1;
        $options['CURLOPT_INFILESIZE'] = $size;
        $options['CURLOPT_INFILE']     = $fp;
        if (!isset($this->options['CURLOPT_USERPWD'])) {
            $this->setopt(['CURLOPT_USERPWD' => 'anonymous: noreply@moodle.org']);
        }
        $ret = $this->request($url, $options);
        fclose($fp);
        return $ret;
    }

    /**
     * HTTP DELETE method
     *
     * @param string $url
     * @param array $params
     * @param array $options
     * @return bool
     */
    public function delete($url, $params = [], $options = []) {
        $options['CURLOPT_CUSTOMREQUEST'] = 'DELETE';
        if (!isset($options['CURLOPT_USERPWD'])) {
            $options['CURLOPT_USERPWD'] = 'anonymous: noreply@moodle.org';
        }
        $ret = $this->request($url, $options);
        return $ret;
    }

    /**
     * HTTP TRACE method
     *
     * @param string $url
     * @param array $options
     * @return bool
     */
    public function trace($url, $options = []) {
        $options['CURLOPT_CUSTOMREQUEST'] = 'TRACE';
        $ret = $this->request($url, $options);
        return $ret;
    }

    /**
     * HTTP OPTIONS method
     *
     * @param string $url
     * @param array $options
     * @return bool
     */
    public function options($url, $options = []) {
        $options['CURLOPT_CUSTOMREQUEST'] = 'OPTIONS';
        $ret = $this->request($url, $options);
        return $ret;
    }

    /**
     * Get curl response info
     *
     * @return array
     */
    public function get_info() {
        return $this->info;
    }
}
