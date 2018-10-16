<?php

class RestHttp
{
    public static function httpRequest($method, $url, $auth = '', $request_headers = array(), $obj = '')
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $request_headers);

        if (in_array($method, array('POST', 'PUT', 'DELETE'))) {
            curl_setopt($ch, CURLOPT_USERPWD, $auth);
        }

        switch ($method) {
            case 'GET':
                curl_setopt($ch, CURLOPT_HTTPGET, 1);
                break;
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $obj);
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST,'PUT');
                curl_setopt($ch, CURLOPT_POSTFIELDS, $obj);
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }

        $response_headers_io = new RestIO();

        curl_setopt($ch, CURLOPT_HEADERFUNCTION, array(
            &$response_headers_io,
            'write'
        ));

        $response_body_io = new RestIO();
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, array(
            &$response_body_io,
            'write'
        ));

        try {
            curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $parsed_headers = self::parseHttpHeaders($response_headers_io->contents());
            $response_headers = array(
                'http_code' => $http_code
            );

            foreach ($parsed_headers as $key => $val) {
                $response_headers[strtolower($key)] = $val;
            }

            $response_body = $response_body_io->contents();

            return array($response_headers, $response_body);
        } catch (Exception $e) {
            curl_close($ch);
            error_log('Error: ' . $e->getMessage());
            return NULL;
        }

    }

    public static function parseHttpHeaders($headers)
    {
        $return = array();
        $fields = explode("\r\n", preg_replace('/\x0D\x0A[\x09\x20]+/', ' ', $headers));
        foreach ($fields as $field) {
            if (preg_match('/([^:]+): (.+)/m', $field, $match)) {
                $match[1] = preg_replace('/(?<=^|[\x09\x20\x2D])./e', 'strtoupper("\0")', strtolower(trim($match[1])));
                if (isset($return[$match[1]])) {
                    $return[$match[1]] = array(
                        $return[$match[1]],
                        $match[2]
                    );
                } else {
                    $return[$match[1]] = trim($match[2]);
                }
            }
        }
        return $return;
    }
}


class RestIO
{
    function RestIO()
    {
        $this->contents = '';
    }

    function write($ch, $data)
    {
        $this->contents .= $data;
        return strlen($data);
    }

    function contents()
    {
        return $this->contents;
    }
}

class ImageClient
{
    public $endpoint;
    public $headers = array();

    function __construct($endpoint = 'default')
    {

        $this->_setEndpoint($endpoint);

    }

    public function _setEndpoint($endpoint)
    {

        $this->endpoint = is_array($endpoint) ? $endpoint : array();
        //$this->headers['Authorization'] = 'Basic ' . base64_encode($CONFIG['Rest']['auth']);

    }

    public function getFile($bucket, $file)
    {
        $url = $this->buildRestPath($bucket, $file);
        $response = RestHttp::httpRequest('GET', $url);
        return $this->parseBody($response);

    }

    public function setFile($bucket, $file, $content, $contentType)
    {

        $url = $this->buildRestPath($bucket, $file);
        $headers = $this->headers;
        $headers['Content-Type'] = $contentType;
        $response = RestHttp::httpRequest('PUT', $url, $this->endpoint['auth'],$this->buildRequestHeads($headers), $content);
        return $this->parseBody($response);

    }

    public function copyFile($bucket, $sourceBucket, $file)
    {

        $url = $this->buildRestPath($bucket, $file);
        $headers = $this->headers;
        $headers['x-sfs-copy-source'] = '/' . $sourceBucket . '/' . $file;
        $response = RestHttp::httpRequest('PUT', $url, $this->endpoint['auth'], $this->buildRequestHeads($headers));
        return $this->parseBody($response);

    }

    public function deleteFile($bucket, $file)
    {

        $url = $this->buildRestPath($bucket, $file);
        $response = RestHttp::httpRequest('DELETE', $url, $this->endpoint['auth']);
        return $this->parseBody($response);

    }

    public function buildRestPath($bucket = NULL, $key = NULL)
    {
        $config = $this->endpoint;
        $path = 'http://';
        $path .= $config['host'];

        if (isset($config['port']) && !empty($config['port'])) {
            $path .= ':' . $config['port'];
        }

        // Add '.../bucket'
        if (!is_null($bucket)) {
            $path .= '/' . urlencode($bucket);
        }

        // Add '.../key'
        if (!is_null($key)) {
            $path .= '/' . urlencode($key);
        }

        return $path;

    }

    public function buildRequestHeads($headers)
    {
        $arrHeader = array();
        foreach($headers as $k=>$v) {
            $arrHeader[] = "{$k}: {$v}";
        }
        return $arrHeader;
    }

    public function parseBody($response)
    {

        return $response[1];

    }
}