<?php
/**
 * Selectel Storage PHP class
 *
 * PHP version 5
 *
 * @author   Eugene Smith <easmith@mail.ru>
 */


class SelectelStorage
{
    /**
     * Header string in array for authtorization.
     *
     * @var array()
     */
    protected $token = array();

    /**
     * Storage url
     *
     * @var string
     */
    protected $url = '';

    /**
     * The response format
     *
     * @var string
     */
    protected $format = '';

    /**
     * Allowed response formats
     *
     * @var array
     */
    protected $formats = array ('', 'json', 'xml');

    /**
     * Throw exception on Error
     *
     * @var boolean
     */
    protected static $throwExceptions = true;

    /**
     * Creating Selectel Storage PHP class
     *
     * @param string $user Account id
     * @param string $key Storage key
     * @param string $format Allowed response formats
     *
     * @return SelectelStorage
     */
    public function __construct($user, $key, $format = null)
    {
        $header = SCurl::init("https://auth.selcdn.ru/")
            ->setHeaders(array("X-Auth-User: {$user}", "X-Auth-Key: {$key}"))
            ->request("GET")
            ->getHeaders();

        if ($header["HTTP-Code"] != 204)
        {
            if ($header["HTTP-Code"] == 403)
                return $this->error($header["HTTP-Code"], "Forbidden for user '{$user}'");

            return $this->error($header["HTTP-Code"], __METHOD__);
        }

        $this->format = (!in_array($format, $this->formats, true) ? $this->format : $format);
        $this->url = $header['x-storage-url'];
        $this->token = array("X-Auth-Token: {$header['x-storage-token']}");
    }

    /**
     * Handle errors
     *
     * @param integer $code
     * @param string $message
     *
     * @return mixed
     * @throws SelectelStorageException
     */
    protected function error($code, $message)
    {
        if (self::$throwExceptions)
            throw new SelectelStorageException($message, $code);
        return $code;
    }

    /**
     * Select only 'x-' from headers
     *
     * @param array $headers Array of headers
     * @param string $prefix Frefix for filtering
     *
     * @return array
     */
    protected static function getX($headers, $prefix = 'x-')
    {
        $result = array();
        foreach($headers as $key => $value)
            if (stripos($key, "x-") === 0)
                $result[$key] = $value;
        return $result;
    }

    /**
     * Getting storage info
     *
     * @return array
     */
    public function getInfo()
    {
        $head =	SCurl::init($this->url)
            ->setHeaders($this->token)
            ->request("HEAD")
            ->getHeaders();
        return $this->getX($head);
    }

    /**
     * Getting containers list
     *
     * @param int $limit Limit (Default 10000)
     * @param string $marker Marker (Default '')
     * @param string $format Format ('', 'json', 'xml') (Default self::$format)
     *
     * @return string
     */
    public function listContainers($limit = 10000, $marker = '', $format = null)
    {
        $params = array(
            'limit' => $limit,
            'marker' => $marker,
            'format' => (!in_array($format, $this->formats, true) ? $this->format : $format)
        );

        $cont =	SCurl::init($this->url)
            ->setHeaders($this->token)
            ->setParams($params)
            ->request("GET")
            ->getContent();

        if ($params['format'] == '')
            return explode("\n", trim($cont));

        return trim($cont);
    }

    /**
     * Create container by name.
     * Headers for
     *
     * @param string $name
     * @param array $headers
     *
     * @return SelectelContainer
     */
    public function createContainer($name, $headers = array())
    {
        $headers = array_merge($this->token, $headers);
        $info =	SCurl::init($this->url . $name)
            ->setHeaders($headers)
            ->request("PUT")
            ->getInfo();

        if (!in_array($info["http_code"], array(201, 202)))
            return $this->error($info["http_code"], __METHOD__);

        return $this->getContainer($name);
    }

    /**
     * Delete container or object by name
     *
     * @param string $name
     *
     * @return integera
     */
    public function delete($name)
    {
        $info =	SCurl::init($this->url . $name)
            ->setHeaders($this->token)
            ->request("DELETE")
            ->getInfo();

        if (!in_array($info["http_code"], array(204)))
            return $this->error($info["http_code"], __METHOD__);

        return $info;
    }

    /**
     * Copy
     *
     * @param string $origin Origin object
     * @param string $destin Destination
     *
     * @return array
     */
    public function copy($origin, $destin)
    {
        $url = parse_url($this->url);
        $destin = $url['path'] . $destin;
        $headers = array_merge($this->token, array("Destination: {$destin}"));
        print_r($headers);
        $info =	SCurl::init($this->url . $origin)
            ->setHeaders($headers)
            ->request("COPY")
            ->getResult();

        return $info;
    }

    /**
     * Setting meta info
     *
     * @param string $name Name of object
     * @param array $headers Headers
     *
     * @return integer
     */
    protected function setMetaInfo($name, $headers)
    {
        if (get_class($this) == 'SelectelStorage')
            $headers = $this->getX($headers, "X-Container-Meta-");
        elseif (get_class($this) == 'SelectelContainer')
            $headers = $this->getX($headers, "X-Container-Meta-");
        else
            return 0;

        $info =	SCurl::init($this->url . $name)
            ->setHeaders($headers)
            ->request("POST")
            ->getInfo();

        if (!in_array($info["http_code"], array(204)))
            return $this->error($info["http_code"], __METHOD__);

        return $info["http_code"];
    }

    public function setContainerHeaders($name, $headers)
    {
        $headers = $this->getX($headers, "X-Container-Meta-");
        if (get_class($this) != 'SelectelStorage') return 0;

        return $this->setMetaInfo($name, $headers);
    }

    /**
     * Select container by name
     *
     * @param string $name
     *
     * @return SelectelContainer
     */
    public function getContainer($name)
    {
        $url = $this->url . $name;
        $headers =	SCurl::init($url)
            ->setHeaders($this->token)
            ->request("HEAD")
            ->getHeaders();

        if (!in_array($headers["HTTP-Code"], array(204)))
            return $this->error($headers["HTTP-Code"], __METHOD__);

        return new SelectelContainer($url, $this->token, $this->format, $this->getX($headers));
    }
}
