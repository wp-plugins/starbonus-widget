<?php

namespace Starbonus\Api;

use OAuth\Common\Consumer\Credentials;
use OAuth\Common\Http\Client\Stream2Client;
use OAuth\Common\Http\Uri\Uri;
use OAuth\Common\Http\Uri\UriInterface;
use OAuth\Common\Service\ServiceInterface;
use OAuth\Common\Storage\File;
use OAuth\Common\Storage\TokenStorageInterface;
use OAuth\OAuth2\Service\Starbonus as OAuth2StarbonusService;
use OAuth\ServiceFactory;

/**
 * Class Starbonus Api
 *
 * @author Adam Kuśmierz <adam@kusmierz.be>
 * @package Starbonus\Api
 */
class Api
{

    /**
     * @var string
     */
    protected $serviceName = 'Starbonus';

    /**
     * @var string
     */
    protected $apiDefaultHost = 'https://api.starbonus.pl';

    /**
     * @var \OAuth\Common\Service\ServiceInterface
     */
    protected $apiInstance;

    /**
     * @var \OAuth\Common\Storage\TokenStorageInterface
     */
    protected $storage;

    /**
     * @var array|\Starbonus\Api\Service\ServiceInterface[]
     */
    protected $services = array();

    /**
     * @var \OAuth\Common\Consumer\Credentials
     */
    private $credentials;

    /**
     * @var \OAuth\Common\Http\Uri\UriInterface
     */
    private $baseApiUri;

    /**
     * @param \OAuth\Common\Consumer\Credentials $credentials
     * @param \OAuth\Common\Http\Uri\UriInterface $baseApiUri
     */
    public function __construct(Credentials $credentials, UriInterface $baseApiUri = null)
    {
        $this->credentials = $credentials;
        $this->baseApiUri = $baseApiUri;

        if (is_null($this->baseApiUri)) {
            $this->baseApiUri = new Uri($this->apiDefaultHost);
        }
    }

    /**
     * @return null|\OAuth\Common\Token\TokenInterface
     */
    public function getToken()
    {
        $storage = $this->getStorage();
        $token = null;
        if ($storage->hasAccessToken($this->serviceName)) {
            $token = $storage->retrieveAccessToken($this->serviceName);
            if ($token->getEndOfLife() <= time()) {
                $rtoken = $token->getRefreshToken();
                if (!empty($rtoken)) {
                    $token = $this->getApiInstance()->refreshAccessToken($token);
                } else {
                    $token = null;
                }
            }
        }

        if (empty($token)) {
            $token = $this->getApiInstance()->requestAccessToken();
        }

        return $token;
    }

    /**
     * @return \OAuth\Common\Service\ServiceInterface
     */
    public function getApiInstance()
    {
        if (empty($this->apiInstance)) {
            // reset service instance
            $this->services = array();

            /** @var $serviceFactory ServiceFactory An OAuth service factory. */
            $serviceFactory = new ServiceFactory();

            $serviceFactory->setHttpClient(new Stream2Client());

            $this->setApiInstance($serviceFactory->createService(
                $this->serviceName, $this->credentials, $this->getStorage(),
                array(OAuth2StarbonusService::SCOPE_API_TRANSACTION_CASHBACK),
                $this->baseApiUri
            ));
        }

        return $this->apiInstance;
    }

    /**
     * @param \OAuth\Common\Service\ServiceInterface $apiInstance
     *
     * @return $this
     */
    public function setApiInstance(ServiceInterface $apiInstance)
    {
        $this->apiInstance = $apiInstance;

        $this->getToken();

        return $this;
    }

    /**
     * @return \OAuth\Common\Storage\TokenStorageInterface
     */
    public function getStorage()
    {
        if (empty($this->storage)) {
            $prefix = sha1($this->serviceName . '_' . $this->credentials->getConsumerId() . '_' . $this->baseApiUri->getAbsoluteUri());
            $this->storage = new File(null, $prefix);
        }

        return $this->storage;
    }

    /**
     * @param \OAuth\Common\Storage\TokenStorageInterface $storage
     *
     * @return $this
     */
    public function setStorage(TokenStorageInterface $storage)
    {
        // reset the api instance
        $this->apiInstance = null;

        $this->storage = $storage;

        return $this;
    }

    /**
     * @return \Starbonus\Api\Service\TransactionCashback
     */
    public function serviceTransactionCashback()
    {
        if (!isset($this->services[__FUNCTION__])) {
            $this->services[__FUNCTION__] = new Service\TransactionCashback($this);
        }

        return $this->services[__FUNCTION__];
    }
}
