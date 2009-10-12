<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */

/**
 * phpman_Command 
 * 
 * @author Daichi Kamemoto(a.k.a: yudoufu) <daikame@gmail.com> 
 */
class phpman_Command
{
    private $_command = null;

    /**
     * parse user input parameters
     * 
     * @param array $args   user input parameters
     * @return string       request search string
     * @throws Exception
     */
    public function parseArgs($args)
    {
        $this->_command = array_shift($args);

        if (empty($args)) {
            $this->usage();
            exit(0);
        }

        while ($arg = array_shift($args)) {
            if (strpos($arg, '-') === 0) {
                switch ($arg) {
                    case '-s':
                    case '--sync':
                        try {
                            $lang = $this->prompt('Your language? (choose from: http://php.net/download-docs.php)', 'ja');
                            $mirror = $this->prompt('Mirror site?', 'www.php.net');

                            $this->sync(compact('lang', 'mirror'));
                        } catch (Exception $e) {
                            self::outputln($e->getMessage());
                            exit(1);
                        }
                        exit(0);
                    case '-h':
                    case '--help':
                        $this->usage();
                        exit(0);
                    default:
                        break;
                }
            } else {
                if (isset($param)) {
                    throw new Exception("Parameter too much!");
                }
                $param = $arg;
            }
        }

        return $param;
    }

    public function sync($options = array('lang' => 'ja', 'mirror' => 'www.php.net',))
    {
        /****
         * download configuration
         ****/
        require_once 'PEAR/Config.php';

        $config = PEAR_Config::singleton();

        $download_dir = $config->get('download_dir');
        $data_dir = $config->get('data_dir') . '/phpman/';
        if (!is_writable($download_dir)) {
            throw new Exception("Error: You cannot write the directory (maybe permission error? try with root or sodo): $download_dir");
        }

        $file_url = 'http://' . $options['mirror'] . '/get/php_manual_' . $options['lang'] . '.tar.gz/from/this/mirror';

        self::outputln("Request $file_url ...");

        /****
         * get Manual Tarball
         ****/
        require_once 'Download.php';
        $file = phpman_Download::connect($file_url)->save($download_dir);

        self::outputln('Complete to download manual file.');

        /****
         * extract Tarball
         ****/
        require_once 'Archive/Tar.php';

        $tar = new Archive_Tar($file);
        if (!$tar->extract($data_dir)) {
            throw new Exception("Error: failed to extract file: $file");
        }

        self::outputln('Complete to extract manual files.');
    }

    /**
     * download 
     * 
     * @param mixed $file_url   Request url.
     * @return array            retrun 2 parameter, filename string and response object.
     */
    private function download($file_url)
    {
        $request = new HTTP_Request2($file_url);
        $response = $request->send();

        switch($response->getStatus()) {
            case 302:
            case 301:
                $headers = $response->getHeader();
                $return  = $this->download($headers['location']);
                break;
            case 200:
                $return = array('filename'=> basename($request->getUrl()->getPath()), 'response' => $response);
                break;
            default:
                #TODO: error?
                throw new Exception('retruned response invalid status: '.$response->getStatus());
                break;
        }
        return $return;
    }

    /**
     * Create And Output User Prompt.
     * 
     * @param string $message        Display Prompt Messages.
     * @param string $default        Default Parameter If user isn't input.
     * @param mixed $other_params    Another Parameter(s).
     * @return string                Choiced Value
     */
    public function prompt($message, $default = '', $other_params = array())
    {
        if ($default) {
            $value = $default;
            if (!empty($other_params)) {
                if (!is_array($other_params)) {
                    $other_params = array($other_params);
                }
                $value = strtoupper($value).'/'.implode('/', $other_params);
            }
            $message .= "\t[".$value."]";
        }
        $message .= ' :';

        self::output($message);

        $result = trim(fgets(STDIN));

        if (!empty($other_params) && !in_array($result ,$other_params)) {
            #TODO: FIX Policy. Now, return default Value at Miss Selected.
            $result = $default;
        }

        return empty($result) ? $default : $result;
    }

    public static function output($message) {
        echo $message;
    }

    public static function outputln($message) {
        self::output($message.PHP_EOL);
    }

    /**
     * Usage This command.
     * 
     * @return void
     */
    public function usage()
    {
        self::outputln("Usage:
    {$this->_command} [-s,--sync] [-h,--help] <search_word>

    -s,--sync   update php manual newest
    -h,--help   show this help

    Example:
    * At the command line:
      % {$this->_command} -h
      % {$this->_command} mysql
      % {$this->_command} mysql_query
    ");
    }
}

