<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */

/**
 * phpman_Download
 * 
 * @author Daichi Kamemoto(a.k.a: yudoufu) <daikame@gmail.com> 
 */
class phpman_Download
{
    private $fp = null;
    private $filename = null;

    public function __construct($url)
    {
        if (!ini_get('allow_url_fopen')) {
            throw new Exception("Error: Not Allowed url connection on PHP Setting. check 'allow_url_fopen' param");
        }
        $this->fp = fopen($url, 'r');
    }

    public static function connect($url)
    {
        return new self($url);
    }

    private function parseHeaders()
    {
        $i = -1; // 配列keyの調整のため-1スタート
        $headers = array();
        $meta = stream_get_meta_data($this->fp);

        foreach($meta["wrapper_data"] as $header) {
            if (substr($header, 0, 7) == 'HTTP/1.') {
                $headers[++$i] = array('HTTP-Status' => substr($header, 9, 3));
            } else {
                list($key, $value) = explode(': ', $header);
                $headers[$i][$key] = $value;
            }
        }

        return $headers;
    }

    private function parseFilename()
    {
        $headers = $this->parseHeaders();
        $url = '';

        foreach ($headers as $h) foreach ($h as $key => $header) {
            if (ucfirst($key) == 'Location') {
                // ファイル名のために最後のLocation のみが欲しいので、上書き。
                $url = $header;
            }
        }

        return basename($url);
    }

    public function getFilename()
    {
        if (is_null($this->filename)) {
            $this->filename = $this->parseFilename();
        }
        return $this->filename;
    }

    public function getData()
    {
        return stream_get_contents($this->fp);
    }

    public function save($dir = '/tmp')
    {
        if (!is_writable($dir)) {
            throw new Exception("Error: You cannot write the directory (maybe permission error? try with root or sudo): $dir");
        }
        $path = $dir.DIRECTORY_SEPARATOR.$this->getFilename();

        if (false === file_put_contents($path, $this->getData())) {
            throw new Exception("Error: failed to save file: $path");
        }

        $this->close();

        return $path;
    }

    public function close()
    {
        fclose($this->fp);
    }
}
