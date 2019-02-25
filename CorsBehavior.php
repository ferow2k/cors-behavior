<?php

/*
 * CorsBehavior class file
 *
 * @author Igor Manturov, Jr. <im@youtu.me>
 * @link https://github.com/iAchilles/cors-behavior
 */

/**
 * CorsBehavior Automatically adds the Access-Control-Allow-Origin response 
 * header for specific routes.
 *
 * @version 1.0
 *
 */
class CorsBehavior extends CBehavior
{

    private $_allowOrigin;
    public $allowMethods = false;
    public $allowHeaders = false;

    private $_route = array();

    private $cors = [
        'Origin' => ['*'],
        'Access-Control-Request-Method' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'],
        'Access-Control-Request-Headers' => ['*'],
        'Access-Control-Allow-Credentials' => null,
        'Access-Control-Max-Age' => 86400,
        'Access-Control-Expose-Headers' => [],
    ];

    public function events()
    {
        return array_merge(parent::events(),
                array('onBeginRequest' => 'onBeginRequestHandler'));
    }

    public function onBeginRequestHandler($event)
    {
        if (is_null($this->_allowOrigin))
        {
            return;
        }

        if ($this->checkAllowedRoute())
        {

            $origin = $this->parseHeaders();

            if ($origin !== false)
            {
                $this->setAllowOriginHeader($origin);
            }
        }
    }

    /**
     * Sets list of routes for CORS-requests.
     * @param mixed $route An array of routes (controllerID/actionID). If you
     * want to allow CORS-requests for any routes, the value of the parameter
     * must be a string that contains the "*".
     * @throws CException
     */
    public function setRoute($route)
    {
        if (!is_array($route) && $route !== '*')
        {
            throw new CException('The value of the "route" property must be an '
                    . 'array or a string that contains the "*".');
        }

        $this->_route = $route;
    }

    /**
     * Sets the allowed origin.
     * @param string $origin The origin that is allowed to access the resource.
     * A "*" can be specified to enable access to resource from any origin.
     * A wildcard can be used to specify list of allowed origins,
     * e.g. "*.yourdomain.com" (sub.yourdomain.com, yourdomain.com,
     * sub.sub.yourdomain.com will be allowed origins in that case)
     * @throws CExecption
     */
    public function setAllowOrigin($origin)
    {
        if (!is_string($origin))
        {
            throw new CExecption('The value of the "allowOrigin" property must be '
                    . 'a string.');
        }

        $this->_allowOrigin = $origin;
    }

    /**
     * Parses headers and returns the value of the Origin request header.
     * @return mixed The origin that is allowed to access the resource.
     * (the value of the Origin request header), otherwise false.
     */
    protected function parseHeaders()
    {
        $headers = $this->extractHeaders();

        if ($headers === false)
        {
            return false;
        }

        $headers = array_change_key_case($headers, CASE_LOWER);

        if (!array_key_exists('origin', $headers))
        {
            return false;
        }

        $origin = $headers['origin'];
        $origin = parse_url($origin, PHP_URL_HOST);

        if (is_null($origin))
        {
            return false;
        }

        if(strlen($this->_allowOrigin) === 1)
        {
            return $headers['origin'];
        }

        $allowedOrigins = explode(',', $this->_allowOrigin);
        foreach ($allowedOrigins as $allowedOrigin) {
            $allowedOrigin = str_replace(' ', '', $allowedOrigin);
            if (stripos($allowedOrigin, '*') === false)
            {
                return $origin === $allowedOrigin ? $headers['origin'] : false;
            }

            $pattern = '/' . substr($allowedOrigin, 1) . '$/';

            if (substr($allowedOrigin, 2) === $origin
                    || preg_match($pattern, $origin) === 1)
            {
                return $headers['origin'];
            }
        }

    }

    /**
     * Checks if CORS-request is allowed for the current route.
     * @return boolean Whether CORS-request is allowed for the current route.
     */
    protected function checkAllowedRoute()
    {
        if ($this->_route === '*')
        {
            return true;
        }

        $route = Yii::app()->getUrlManager()
                ->parseUrl(Yii::app()->getRequest());

        //Check without wildcard entry
        if(in_array($route, $this->_route)){
            return true;
        }

        //Check wildcard entry
        if(!is_array($this->_route)){
            return false;
        }

        foreach ($this->_route as $routeVal) {
            //Skip element without wildcard(*)
            if(strpos($routeVal, '*') === false){
                continue;
            }
            $str1 = substr($route, 0, strlen($routeVal)-1);
            $str2 = substr($routeVal, 0, strlen($routeVal)-1);
            if( $str1== $str2){
                return true;
            }
        }
        return false;
    }

    /**
     * Sets Access-Control-Allow-Origin response header.
     * @param string $origin the value of the Access-Control-Allow-Origin response
     * header.
     */
    protected function setAllowOriginHeader($origin)
    {
        header('Access-Control-Allow-Origin: ' . $origin);
//        header('Access-Control-Allow-Methods: *');
//        header('Access-Control-Allow-Headers: *');
        if($this->allowMethods !== false){
            header('Access-Control-Allow-Methods: '.$this->allowMethods);
        }
        if($this->allowHeaders !== false){
            header('Access-Control-Allow-Headers: '.$this->allowHeaders);
        }
        if (\Yii::app()->request->requestType == 'OPTIONS') {
            \Yii::app()->end();
        }
    }

    /**
     * Extract CORS headers from the request.
     * @return array CORS headers to handle
     */
    public function extractHeaders()
    {
        $headers = [];
        foreach (array_keys($this->cors) as $headerField) {
            $serverField = $this->headerizeToPhp($headerField);
            $headerData = isset($_SERVER[$serverField]) ? $_SERVER[$serverField] : null;
            if ($headerData !== null) {
                $headers[$headerField] = $headerData;
            }
        }

        return $headers;
    }

    /**
     * Convert any string (including php headers with HTTP prefix) to header format.
     *
     * Example:
     *  - X-Pingother -> HTTP_X_PINGOTHER
     *  - X PINGOTHER -> HTTP_X_PINGOTHER
     * @param string $string string to convert
     * @return string the result in "php $_SERVER header" format
     */
    protected function headerizeToPhp($string)
    {
        return 'HTTP_' . strtoupper(str_replace([' ', '-'], ['_', '_'], $string));
    }
}
