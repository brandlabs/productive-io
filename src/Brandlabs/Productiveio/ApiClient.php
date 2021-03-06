<?php

namespace Brandlabs\Productiveio;

use Brandlabs\Productiveio\Exceptions\ProductiveioRequestException;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;

class ApiClient
{
    const API_BASE_URL = 'https://api.productive.io/api/v2';
    const CONTENT_TYPE = 'application/vnd.api+json';
    const BULK_CONTENT_TYPE = 'application/vnd.api+json; ext=bulk';
    const AUTH_TOKEN_HEADER_KEY = 'X-Auth-Token';
    const CONTENT_TYPE_HEADER_KEY = 'Content-Type';
    const ORGANIZATION_ID_HEADER_KEY = 'X-Organization-Id';

    /**
     * @var GuzzleHttp\Client $guzzleClient
     */
    private $guzzleClient;

    /**
     * Request timeout in seconds
     *
     * @var float
     */
    private $timeout;

    /**
     * Productiveio API auth token
     *
     * @var string
     */
    private $authToken;

    /**
     * Productiveio organisation id to be used as X-Organization-Id header when accessing
     * organisation's data
     *
     * @var int
     */
    private $organisationId;

    /**
     * ApiClient constructor
     *
     * @param string $authToken
     * @param int $organisationId
     * @param float $timeout
     * @param GuzzleClient $guzzleClient
     */
    public function __construct(
        string $authToken,
        int $organisationId,
        float $timeout = 60.0,
        GuzzleClient $guzzleClient = null
    ) {

        $this->authToken = $authToken;
        $this->organisationId = $organisationId;
        $this->timeout = $timeout;

        if (is_null($guzzleClient)) {
            $guzzleClient = new GuzzleClient([
                /**
                 * How long in seconds before a request times out if the server does not respond
                 */
                'timeout' => $timeout,
            ]);
        }

        $this->guzzleClient = $guzzleClient;
    }

    /**
     * concatenates $this->baseApiUrl and resource endpoint $path.
     * e.g if $this->baseApiUrl = 'https://208.86.251.7:10880' and
     * $path = "/api/v1/companies"
     * result will be "https://208.86.251.7:10880/api/v1/companies"
     *
     * @param  string $path
     * @return string
     */
    public function getRequestUrl(string $path)
    {
        // if API path does not start with '/', then prefix '/'
        $path = (strpos($path, '/') === 0) ? $path : "/$path";
        return self::API_BASE_URL . $path;
    }

    /**
     * Makes HTTP $method request to a given $uri.
     *
     * @param  string $method  Request method, any of 'PUT', 'GET', 'POST', 'DELETE'
     * @param  string $uri     full request URL path e.g https://208.86.251.7:10880/api/v1/companies
     * @param  array  $options Guzzle client request option
     * see http://docs.guzzlephp.org/en/stable/request-options.html#request-options
     *
     * @return mixed
     * @throws ProductiveioRequestException
     */
    public function request(string $method, string $uri, array $options = [])
    {
        try {
            $headersOptionKey = 'headers';
            // set API Auth token headers field
            $options[$headersOptionKey][self::AUTH_TOKEN_HEADER_KEY] = $this->authToken;

            // set Organisation Id headers field
            $options[$headersOptionKey][self::ORGANIZATION_ID_HEADER_KEY] = $this->organisationId;

            $response = $this->guzzleClient->request($method, $uri, $options);
            $asAssocArray = true;
            return json_decode($response->getBody()->getContents(), $asAssocArray);

        } catch (RequestException $ex) {
            throw new ProductiveioRequestException($ex->getMessage(), $ex->getCode(), $ex);
        }
    }

    public function setContentTypeHeader(array $options, $contentType)
    {
        $contentTypeHeaderKey = self::CONTENT_TYPE_HEADER_KEY;
        $options = $options ?? [];
        $options['headers'][$contentTypeHeaderKey] = $contentType;

        return $options;
    }

    /**
     * Get a list resources from the given resource endpoint.
     *
     * @param string $path
     * @param array $requestParams
     * $requestParams = [
     *      "sort" => string, prefix "-" to sort in descending order. e.g "-name" or "name",
     *      "page[number]" => int the index of the page you want to view,
     *      "page[size]" => int the number of resources you want to return per page,
     *      "filter[property_name]" => string where 'property_name' is the name of the field to
     *          filter by value given e.g "filter[person_id]"
     * ]
     * @param bool $aggregates
     * @return mixed
     */
    public function getList(string $path, array $requestParams = [], bool $aggregates = false)
    {
        $requestUrl = $this->getRequestUrl($path);
        if ($aggregates) {
            // set "aggregates" query param flag
            $requestParams['aggregates'] = '';
        }

        $options = [];
        $options = $this->setContentTypeHeader($options, self::CONTENT_TYPE);

        if (!empty($requestParams)) {
            $options['query'] = $requestParams;
        }

        return $this->request('GET', $requestUrl, $options);
    }

    /**
     * Get a resource from the specified endpoint.
     *
     * @param  string $path resource api endpoint e.g /api/v2/activities
     * @param  string $id   resource identifier
     * @return mixed array|null
     */
    public function getResource(string $path, string $id)
    {
        $requestUrl = $this->getRequestUrl($path . "/$id");

        $options = [];
        $options = $this->setContentTypeHeader($options, self::CONTENT_TYPE);

        return $this->request('GET', $requestUrl, $options);
    }

    /**
     * Send a post request to create a resource
     *
     * @param  string $path    resource api endpoint e.g /api/v2/activities
     * @param  array  $payload
     * @return mixed
     */
    public function createResource(string $path, array $payload = [])
    {
        $requestUrl = $this->getRequestUrl($path);

        $options = [
            'json' => $payload,
        ];

        $options = $this->setContentTypeHeader($options, self::CONTENT_TYPE);

        return $this->request('POST', $requestUrl, $options);
    }

    /**
     * Send a put request to update the specified resource.
     *
     * @param  string $path api endpoint e.g /api/v2/activities
     * @param  string $id   resource identifier
     * @return mixed
     */
    public function updateResource(string $path, string $id, array $payload = [])
    {
        $requestUrl = $this->getRequestUrl($path . "/$id");
        $options = [
            'json' => $payload,
        ];

        $options = $this->setContentTypeHeader($options, self::CONTENT_TYPE);
        return $this->request('PATCH', $requestUrl, $options);
    }

    /**
     * Send a delete request to remove the specified resource.
     *
     * @param  string $path api endpoint e.g /api/v2/activities
     * @param  string $id   resource identifier
     * @return mixed
     */
    public function deleteResource(string $path, string $id)
    {
        $requestUrl = $this->getRequestUrl($path . "/$id");

        $options = [];
        $options = $this->setContentTypeHeader($options, self::CONTENT_TYPE);
        return $this->request('DELETE', $requestUrl, $options);
    }
}
