<?php

namespace easmith\selectel\storage;

/**
 * Created 06.09.14 23:47 by PhpStorm.
 *
 * PHP version 5
 *
 * @category selectel-storage-php-class
 * @package class_package
 * @author Eugene Kuznetcov <easmith@mail.ru>
 */
class SCurl
{

    private static $instance = null;

    /**
     * Curl resource
     *
     * @var null|resource
     */
    private $ch;

    /**
     * Current URL
     *
     * @var string
     */
    private $url;

    /**
     * Last request result
     *
     * @var array
     */
    private $result = array();

    /**
     * Request params
     *
     * @var array
     */
    private $params = array();

    /**
     * Curl wrapper
     *
     * @param string $url
     */
    private function __construct($url)
    {
        $this->setUrl($url);
        $this->curlInit();
    }

    private function curlInit()
    {
        $this->ch = curl_init($this->url);
        curl_setopt($this->ch, CURLOPT_ENCODING, 'gzip,deflate');
        curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_HEADER, true);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, 2);
        // Проверяем существование константы перед использованием для совместимости c php 8.4
        if (defined('CURLOPT_BINARYTRANSFER')) {
            curl_setopt($this->ch, CURLOPT_BINARYTRANSFER, true);
        }
// TODO: big files
// curl_setopt($this->ch, CURLOPT_RANGE, "0-100");
    }

    /**
     *
     * @param string $url
     *
     * @return SCurl
     */
    public static function init($url)
    {
        if (self::$instance == null) {
            self::$instance = new SCurl($url);
        }
        return self::$instance->setUrl($url);
    }

    /**
     * Set url for request
     *
     * @param string $url URL
     *
     * @return SCurl|null
     */
    public function setUrl($url)
    {
        $this->url = $url;
        return self::$instance;
    }

    /**
     * @param $file
     * @return mixed
     * @throws SelectelStorageException
     */
    public function putFile($file)
    {
        if (!file_exists($file)) {
            throw new SelectelStorageException("File '{$file}' does not exist");
        }
        $fp = fopen($file, "r");
        curl_setopt($this->ch, CURLOPT_INFILE, $fp);
        curl_setopt($this->ch, CURLOPT_INFILESIZE, filesize($file));
        $this->request('PUT');
        fclose($fp);
        return self::$instance;
    }

    /**
     * Set method and request
     *
     * @param string $method
     *
     * @return SCurl
     */
    public function request($method)
    {
        $this->method($method);
        $this->params = array();
        curl_setopt($this->ch, CURLOPT_URL, $this->url);

        $response = explode("\r\n\r\n", curl_exec($this->ch));

        $this->result['info'] = curl_getinfo($this->ch);
        $this->result['header'] = $this->parseHead($response[0]);
        unset($response[0]);
        $this->result['content'] = join("\r\n\r\n", $response);

        // reinit
        $this->curlInit();

        return self::$instance;
    }

    /**
     * Set request method
     *
     * @param string $method
     *
     * @return SCurl
     */
    private function method($method)
    {
        switch ($method) {
            case "GET" : {
                $this->url .= "?" . http_build_query($this->params);
                curl_setopt($this->ch, CURLOPT_HTTPGET, true);
                break;
            }
            case "HEAD" : {
                $this->url .= "?" . http_build_query($this->params);
                curl_setopt($this->ch, CURLOPT_NOBODY, true);
                break;
            }
            case "POST" : {
                curl_setopt($this->ch, CURLOPT_POST, true);
                curl_setopt($this->ch, CURLOPT_POSTFIELDS, http_build_query($this->params));
                break;
            }
            case "PUT" : {
                curl_setopt($this->ch, CURLOPT_PUT, true);
                break;
            }
            default : {
                curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, $method);
                break;
            }
        }
        return self::$instance;
    }

    /**
     * Header Parser
     *
     * @param array $head
     *
     * @return array
     */
    private function parseHead($head)
    {
        $result = array();
        $code = explode("\r\n", $head);
        preg_match('/HTTP\/(.+) (\d+)/', $code[0], $codeMatches);

        $result['HTTP-Version'] = $codeMatches[1];
        $result['HTTP-Code'] = (int)$codeMatches[2];
        preg_match_all("/([A-z\-]+)\: (.*)\r\n/", $head, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $result[strtolower($match[1])] = $match[2];
        }

        return $result;
    }

    public function putFileContents($contents)
    {
        $fp = fopen("php://temp", "rb+");
        fputs($fp, $contents);
        rewind($fp);
        curl_setopt($this->ch, CURLOPT_INFILE, $fp);
        curl_setopt($this->ch, CURLOPT_INFILESIZE, strlen($contents));
        $this->request('PUT');
        fclose($fp);
        return self::$instance;
    }

    /**
     * Set headers
     *
     * @param array $headers
     *
     * @return SCurl
     */
    public function setHeaders($headers)
    {
        $headers = array_merge(array("Expect:"), $headers);
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);
        return self::$instance;
    }

    /**
     * Set request parameters
     *
     * @param array $params
     *
     * @return SCurl
     */
    public function setParams($params)
    {
        $this->params = $params;
        return self::$instance;
    }

    /**
     * Getting info, headers and content of last response
     *
     * @return array
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * Getting headers of last response
     *
     * @param string $header Header
     *
     * @return array
     */
    public function getHeaders($header = null)
    {
        if (!is_null($header))
            $this->result['header'][$header];
        return $this->result['header'];
    }

    /**
     * Getting content of last response
     *
     * @return array
     */
    public function getContent()
    {
        return $this->result['content'];
    }

    /**
     * Getting info of last response
     *
     * @param string $info Info's field
     *
     * @return array
     */
    public function getInfo($info = null)
    {
        if (!is_null($info)) {
            $this->result['info'][$info];
        }
        return $this->result['info'];
    }

    private function __clone()
    {

    }

}
