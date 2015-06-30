<?php

namespace OAuth\OAuth2\Service;

use BadMethodCallException;
use OAuth\Common\Consumer\CredentialsInterface;
use OAuth\Common\Http\Client\ClientInterface;
use OAuth\Common\Http\Exception\TokenResponseException;
use OAuth\Common\Http\Uri\UriInterface;
use OAuth\Common\Storage\TokenStorageInterface;
use OAuth\OAuth2\Token\StdOAuth2Token;

/**
 * Class Starbonus
 *
 * @author Adam Kuśmierz <adam@kusmierz.be>
 * @package OAuth\OAuth2\Service
 */
class Starbonus extends AbstractService
{

    /**
     * Scope to access transaction cashbacks
     */
    const SCOPE_API_TRANSACTION_CASHBACK = 'api:transaction-cashback';

    /**
     * @param \OAuth\Common\Consumer\CredentialsInterface $credentials
     * @param \OAuth\Common\Http\Client\ClientInterface $httpClient
     * @param \OAuth\Common\Storage\TokenStorageInterface $storage
     * @param array $scopes
     * @param \OAuth\Common\Http\Uri\UriInterface $baseApiUri
     * @throws \OAuth\OAuth2\Service\Exception\InvalidScopeException
     */
    public function __construct(
        CredentialsInterface $credentials,
        ClientInterface $httpClient,
        TokenStorageInterface $storage,
        $scopes = array(),
        UriInterface $baseApiUri = null
    ) {
        parent::__construct($credentials, $httpClient, $storage, $scopes, $baseApiUri);
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthorizationEndpoint()
    {
        throw new BadMethodCallException('Unimplemented');
    }

    /**
     * {@inheritdoc}
     */
    public function getAccessTokenEndpoint()
    {
        $uri = clone $this->baseApiUri;

        $uri->setPath('/oauth');

        return $uri;
    }

    /**
     * {@inheritdoc}
     */
    protected function getAuthorizationMethod()
    {
        return static::AUTHORIZATION_METHOD_HEADER_BEARER;
    }

    /**
     * {@inheritdoc}
     */
    public function request($path, $method = 'GET', $body = null, array $extraHeaders = array())
    {
        if (is_array($body)) {
            $body = json_encode($body, JSON_HEX_AMP + JSON_HEX_APOS + JSON_HEX_QUOT + JSON_HEX_TAG);
        }

        $response = parent::request($path, $method, $body, $extraHeaders);

        if (!empty($response)) {
            $response = json_decode($response, true);
        }

        return $response;
    }

    /**
     * {@inheritdoc}
     */
    public function requestAccessToken($code = null, $state = null)
    {
        if (null !== $state) {
            $this->validateAuthorizationState($state);
        }

        $bodyParams = array(
            'code' => $code,
            'client_id' => $this->credentials->getConsumerId(),
            'client_secret' => $this->credentials->getConsumerSecret(),
            'redirect_uri' => $this->credentials->getCallbackUrl(),
            'grant_type' => 'client_credentials',
        );

        $responseBody = $this->httpClient->retrieveResponse(
            $this->getAccessTokenEndpoint(),
            $bodyParams,
            $this->getExtraOAuthHeaders()
        );

        $token = $this->parseAccessTokenResponse($responseBody);
        $this->storage->storeAccessToken($this->service(), $token);

        return $token;
    }

    /**
     * {@inheritdoc}
     */
    protected function parseAccessTokenResponse($responseBody)
    {
        $data = json_decode($responseBody, true);

        if (null === $data || !is_array($data)) {
            throw new TokenResponseException('Unable to parse response.');
        } elseif (isset($data['error'])) {
            throw new TokenResponseException('Error in retrieving token: "' . $data['error'] . '"');
        }

        $token = new StdOAuth2Token();
        $token->setAccessToken($data['access_token']);
        $token->setLifetime($data['expires_in']);
        unset($data['access_token'], $data['expires_in']);

        $token->setExtraParams($data);

        return $token;
    }

    /**
     * Used to configure response type -- we want JSON from Starbonus
     *
     * @return array
     */
    protected function getExtraOAuthHeaders()
    {
        return array(
            'Accept' => 'application/json'
        );
    }

    /**
     * Required for Starbonus API calls.
     *
     * @return array
     */
    protected function getExtraApiHeaders()
    {
        return array(
            'Accept' => 'application/vnd.starbonus-api.v1+json',
            'Content-Type' => 'application/json',
        );
    }
}
