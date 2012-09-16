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
	protected static $throwExcaptions = false;

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
		$header =	sCurl::init("https://selcdn.ru/auth/v1.0")
					->setHeaders(array("X-Auth-User: {$user}", "X-Auth-Key: {$key}"))
					->request("GET")
					->getHeaders();

		if ($header["HTTP-Code"] != 204)
			return $this->error($header["HTTP-Code"], __METHOD__);

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
	 * @return integer
	 */
	protected function error($code, $message)
	{
		if (self::$throwExcaptions) throw new SelectelStorageException($message, $code);
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
		$head =	sCurl::init($this->url)
				->setHeaders($this->token)
				->request("HEAD")
				->getHeaders();
		return $this->getX($head);
	}

	/**
	 * Getting containers list
	 *
	 * @param string $limit Limit (Default 10000)
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

		$cont =	sCurl::init($this->url)
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
		$info =	sCurl::init($this->url . $name)
				->setHeaders($headers)
				->request("PUT")
				->getInfo();
		
		if (!in_array($info["http_code"], array(201, 202)))
			return $this->error($info["http_code"], __METHOD__);
		
		return $this->getContainer($name);
	}

	/**
	 * Deleteing container or object by name
	 *
	 * @param string $name
	 *
	 * @return integera
	 */
	public function delete($name)
	{
		$info =	sCurl::init($this->url . $name)
				->setHeaders($this->token)
				->request("DELETE")
				->getInfo();
		
		if (!in_array($info["http_code"], array(204)))
			return $this->error($head["http_code"], __METHOD__);
		
		return $head["http_code"];
	}
	
	/**
	 * Copy
	 * 
	 * @param type $origin Origin object
	 * @param type $destin Destination
	 * 
	 * @return type
	 */
	public function copy($origin, $destin)
	{
		$destin = $this->url . $destin;
		$headers = array_merge($this->token, array("Destination: {$destin}"));
		$info =	sCurl::init($this->url . $origin)
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
			
		$info =	sCurl::init($this->url . $name)
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
		$headers =	sCurl::init($url)
					->setHeaders($this->token)
					->request("HEAD")
					->getHeaders();

		if (!in_array($headers["HTTP-Code"], array(204)))
			return $this->error($headers["HTTP-Code"], __METHOD__);
		
		return new SelectelContainer($url, $this->token, $this->format, $this->getX($headers));
	}
}

class SelectelContainer extends SelectelStorage
{
	/**
	 * 'x-' Headers of container
	 *
	 * @var array
	 */
	private $info;

	public function  __construct($url, $token = array(), $format = null, $info = array())
	{
		$this->url = $url . "/";
		$this->token = $token;
		$this->format = (!in_array($format, $this->formats, true) ? $this->format : $format);
		$this->info = (count($info) == 0 ? $this->getInfo(true) : $info);
	}
	
	/**
	 * Getting container info
	 * 
	 * @param boolean $refresh Refres? Default false
	 * 
	 * @return array
	 */
	public function getInfo($refresh = false)
	{
		if (!$refresh) return $this->info;
		
		$headers =	sCurl::init($this->url)
					->setHeaders($this->token)
					->request("HEAD")
					->getHeaders();

		if (!in_array($headers["HTTP-Code"], array(204)))
			return $this->error($headers["HTTP-Code"], __METHOD__);

		return $this->info = $this->getX($headers);
	}
	
	/**
	 * Getting file list
	 * 
	 * @param string $limit Limit
	 * @param string $marker Marker
	 * @param string $prefix Prefix
	 * @param string $path Path
	 * @param string $delimiter Delemiter
	 * @param string $format Format
	 * 
	 * @return array
	 */
	public function listFiles($limit = 10000, $marker = null, $prefix = null, $path = null, $delimiter = null, $format = null)
	{
		$params = array(
			'limit' => $limit,
			'marker' => $marker,
			'prefix' => $prefix,
			'path' => $path,
			'delimiter ' => $delimiter,
			'format' => (!in_array($format, $this->formats, true) ? $this->format : $format)
		);
		
		$res = sCurl::init($this->url)
				->setHeaders($this->token)
				->setParams($params)
				->request("GET")
				->getContent();

		if ($params['format'] == '')
			return explode("\n", trim($res));

		return trim($res);
	}

	/**
	 * Getting file with info and headers
	 *
	 * Supported headers:
	 * If-Match
	 * If-None-Match
	 * If-Modified-Since
	 * If-Unmodified-Since
	 *
	 * @param string $name
	 * @param array $headers
	 *
	 * @return array
	 */
	public function getFile($name, $headers = array())
	{
		$headers = array_merge($headers, $this->token);
		$res =	sCurl::init($this->url . $name)
				->setHeaders($headers)
				->request("GET")
				->getResult();
		return $res;
	}

	/**
	 * Getting file info
	 *
	 * @param string $name File name
	 *
	 * @return array
	 */
	public function getFileInfo($name)
	{
		$res =	$this->listFiles(1, '', $name, null, null,  'json');
		$info = current(json_decode($res, true));
		return $this->format == 'json' ? json_encode($info) : $info;
	}

	/**
	 * Upload local file
	 * 
	 * @param string $localFileName The name of a local file
	 * @param string $remouteFileName The name of storage file
	 * 
	 * @return array
	 */
	public function putFile($localFileName, $remouteFileName)
	{
		$info = sCurl::init($this->url . $remouteFileName)
			->setHeaders($this->token)
			->putFile($localFileName)
			->getInfo();
		
		if (!in_array($info["http_code"], array(201)))
			return $this->error($info["http_code"], __METHOD__);
		
		return $info;
	}
	
	/**
	 * Set meta info for file
	 * 
	 * @param string $name File name
	 * @param array $headers Headers
	 * 
	 * @return integer
	 */
	public function setFileHeaders($name, $headers)
	{
		$headers = $this->getX($headers, "X-Container-Meta-");
		if (get_class($this) != 'SelectelContainer') return 0;
		
		return $this->setMetaInfo($name, $headers);
	}
	
	/**
	 * Creating directory
	 * 
	 * @param string $name Directory name
	 * 
	 * @return array
	 */
	public function createDirectory($name)
	{
		$headers = array_merge(array("Content-Type: application/directory"), $this->token);
		$info =	sCurl::init($this->url . $name)
				->setHeaders($headers)
				->request("PUT")
				->getInfo();
		
		return $info;
	}
}

class SelectelStorageException extends Exception
{

}

class sCurl
{
	static private $instance = NULL;
	private $ch = NULL;
	private $url = NULL;
	private $params = array();

	/**
	 *
	 * @param string $url
	 *
	 * @return sCurl
	 */
	static function init($url)
	{
		if (self::$instance == NULL)
		{
			self::$instance = new sCurl($url);
		}
		return self::$instance->setUrl($url);
	}
	
	/**
	 * Curl wrapper
	 * 
	 * @param string $url
	 */
	private function __construct($url)
	{
		$this->ch = curl_init($url);
		curl_setopt($this->ch, CURLOPT_ENCODING, 'gzip,defalate');
		curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, false);
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($this->ch, CURLOPT_HEADER, true);
		curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, true);
		curl_setopt($this->ch, CURLOPT_BINARYTRANSFER, true);		
// TODO: big files
// curl_setopt($this->ch, CURLOPT_RANGE, "0-100");
		$this->setUrl($url);
	}

	/**
	 * Set url for request
	 *
	 * @param string $url URL
	 *
	 * @return sCurl
	 */
	public function setUrl($url)
	{
		$this->url = $url;
		return self::$instance;
	}

	private function __clone() {}

	/**
	 * Set method and request
	 *
	 * @param string $method
	 *
	 * @return sCurl
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

		return self::$instance;
	}

	/**
	 * Set request method
	 *
	 * @param string $method
	 *
	 * @return sCurl
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
	
	public function putFile($file)
	{
		$fp = fopen($file, "r");
		curl_setopt($this->ch, CURLOPT_INFILE, $fp);
		curl_setopt($this->ch, CURLOPT_INFILESIZE, filesize($file));
		return $this->request('PUT');
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
		$result['HTTP-Code'] = intval(str_replace("HTTP/1.1", "", $code[0]));
		
		preg_match_all("/([A-z\-]+)\: (.*)\r\n/", $head, $matches, PREG_SET_ORDER);
		
		foreach ($matches as $match)
			$result[strtolower($match[1])] = $match[2];
		
		return $result;
	}

	/**
	 * Set headers
	 *
	 * @param array $headers
	 *
	 * @return sCurl
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
	 * @return sCurl
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
		if (!is_null($header)) $this->result['header'][$header];
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
		if (!is_null($info)) $this->result['info'][$info];
		return $this->result['info'];
	}
}
