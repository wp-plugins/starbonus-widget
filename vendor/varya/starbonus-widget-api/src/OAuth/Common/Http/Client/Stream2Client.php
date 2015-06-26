<?php

namespace OAuth\Common\Http\Client;

use OAuth\Common\Http\Exception\TokenResponseException;
use OAuth\Common\Http\Uri\UriInterface;

/**
 * Client implementation for streams/file_get_contents (with some api errors)
 */
class Stream2Client extends StreamClient
{
    /**
     * Any implementing HTTP providers should send a request to the provided endpoint with the parameters.
     * They should return, in string form, the response body and throw an exception on error.
     *
     * @param UriInterface $endpoint
     * @param mixed $requestBody
     * @param array $extraHeaders
     * @param string $method
     *
     * @return string
     *
     * @throws TokenResponseException
     * @throws \InvalidArgumentException
     */
    public function retrieveResponse(
        UriInterface $endpoint,
        $requestBody,
        array $extraHeaders = array(),
        $method = 'POST'
    ) {
        // Normalize method name
        $method = strtoupper($method);

        $this->normalizeHeaders($extraHeaders);

        if ($method === 'GET' && !empty($requestBody)) {
            throw new \InvalidArgumentException('No body expected for "GET" request.');
        }

        if (!isset($extraHeaders['Content-Type']) && $method === 'POST' && is_array($requestBody)) {
            $extraHeaders['Content-Type'] = 'Content-Type: application/x-www-form-urlencoded';
        }

        $host = 'Host: ' . $endpoint->getHost();
        // Append port to Host if it has been specified
        if ($endpoint->hasExplicitPortSpecified()) {
            $host .= ':' . $endpoint->getPort();
        }

        $extraHeaders['Host'] = $host;
        $extraHeaders['Connection'] = 'Connection: close';

        if (is_array($requestBody)) {
            $requestBody = http_build_query($requestBody, '', '&');
        }
        $extraHeaders['Content-length'] = 'Content-length: ' . strlen($requestBody);

        $context = $this->generateStreamContext($requestBody, $extraHeaders, $method);

        $firstError = error_get_last();
        $level = error_reporting(0);

        $stream = fopen($endpoint->getAbsoluteUri(), 'r', false, $context);

        if (!is_resource($stream)) {
            throw new TokenResponseException('Failed to open endpoint.');
        }

        // header information as well as meta data
        // about the stream
        $response_metadata = stream_get_meta_data($stream);

        // actual data at $url
        $response = stream_get_contents($stream);
        fclose($stream);

        error_reporting($level);

        $lastError = error_get_last();

        if (empty($response) || !is_array($response_metadata)) {
            throw new TokenResponseException('Failed to request resource.');
        }

        if (!empty($response_metadata['timed_out']) ||
            !empty($response_metadata['eof'])
        ) {
            throw new TokenResponseException('Failed to request resource (timeout or EOF).');
        }

        if (!empty($response_metadata['wrapper_data']) && is_array($response_metadata['wrapper_data'])) {
            $headers = $response_metadata['wrapper_data'];
            $statusCode = explode(' ', $headers[0], 3);
            if (empty($statusCode[1]) || intval($statusCode[1]) >= 400) {
                throw new TokenResponseException($response);
            }
        }

        if ($firstError !== $lastError && !empty($lastError) && is_array($lastError)) {
            throw new TokenResponseException($lastError['message']);
        }

        return $response;
    }

    /**
     * @param string $body
     * @param array $headers
     * @param string $method
     * @return resource
     */
    private function generateStreamContext($body, array $headers = array(), $method = 'GET')
    {
        return stream_context_create(
            array(
                'http' => array(
                    'method' => $method,
                    'header' => implode("\r\n", array_values($headers)),
                    'content' => $body,
                    'protocol_version' => '1.1',
                    'user_agent' => $this->userAgent,
                    'max_redirects' => $this->maxRedirects,
                    'timeout' => $this->timeout,
                    'ignore_errors' => true,
                    'follow_location' => false,
                ),
            )
        );
    }
}
