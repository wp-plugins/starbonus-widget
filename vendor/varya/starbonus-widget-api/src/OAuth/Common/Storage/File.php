<?php

namespace OAuth\Common\Storage;

use OAuth\Common\Token\TokenInterface;
use OAuth\Common\Storage\Exception\TokenNotFoundException;
use OAuth\Common\Storage\Exception\AuthorizationStateNotFoundException;

/**
 * Stores a token in a files
 *
 * @author Adam Kuśmierz <adam@kusmierz.be>
 * @package OAuth\Common\Storage
 */
class File implements TokenStorageInterface
{
    /**
     * @var object|TokenInterface
     */
    protected $cachedTokens;

    /**
     * @var object
     */
    protected $cachedStates;

    /**
     * @var string
     */
    protected $directory;

    /**
     * @var string
     */
    protected $filenamePrefix = 'OAuthStorage_';

    /**
     * @var string
     */
    protected $filenameStatePrefix = 'OAuthStorageState_';

    /**
     * Constructor
     *
     * @param string|null $dir By default it's temporary directory
     * @param string|null $prefix User prefix
     */
    public function __construct($dir = null, $prefix = null)
    {
        if (empty($dir)) {
            $dir = sys_get_temp_dir();
        }

        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }

        $prefix = preg_replace('/[^a-z0-9_-]/i', '', strval($prefix));

        if (empty($prefix)) {
            $prefix = sha1(__DIR__);
        }

        $this->filenamePrefix .= $prefix . '_';
        $this->filenameStatePrefix .= $prefix . '_';

        $this->directory = rtrim($dir, '\\/') . DIRECTORY_SEPARATOR;

        $this->cachedTokens = array();
        $this->cachedStates = array();
    }

    /**
     * @param string $service
     * @return string
     */
    protected function getPath($service)
    {
        $dir = rtrim($this->directory, '\\/') . DIRECTORY_SEPARATOR;

        $path = $dir . $this->getFilename($service);

        return $path;
    }

    /**
     * @param string $service
     * @return string
     */
    protected function getFilename($service)
    {
        $filename = $this->filenamePrefix . md5(implode('::', array(__CLASS__, $service)));

        return $filename;
    }

    /**
     * @param string $service
     * @return string
     */
    protected function getStatePath($service)
    {
        $dir = rtrim($this->directory, '\\/') . DIRECTORY_SEPARATOR;

        $path = $dir . $this->getStateFilename($service);

        return $path;
    }

    /**
     * @param string $service
     * @return string
     */
    protected function getStateFilename($service)
    {
        $filename = $this->filenameStatePrefix . md5(implode('::', array(__CLASS__, $service)));

        return $filename;
    }

    /**
     * {@inheritDoc}
     */
    public function retrieveAccessToken($service)
    {
        if (!$this->hasAccessToken($service)) {
            throw new TokenNotFoundException('Token not found in redis');
        }

        if (isset($this->cachedTokens[$service])) {
            return $this->cachedTokens[$service];
        }

        $val = file_get_contents($this->getPath($service));

        return $this->cachedTokens[$service] = unserialize($val);
    }

    /**
     * {@inheritDoc}
     */
    public function storeAccessToken($service, TokenInterface $token)
    {
        // (over)write the token
        file_put_contents($this->getPath($service), serialize($token));

        $this->cachedTokens[$service] = $token;

        // allow chaining
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function hasAccessToken($service)
    {
        if (isset($this->cachedTokens[$service])
            && $this->cachedTokens[$service] instanceof TokenInterface
        ) {
            return true;
        }

        return file_exists($this->getPath($service));
    }

    /**
     * {@inheritDoc}
     */
    public function clearToken($service)
    {
        unlink($this->getPath($service));
        unset($this->cachedTokens[$service]);

        // allow chaining
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function clearAllTokens()
    {
        // memory
        $this->cachedTokens = array();

        foreach (glob($this->directory . $this->filenamePrefix . '*') as $filename) {
            unlink($filename);
        }

        // allow chaining
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function retrieveAuthorizationState($service)
    {
        if (!$this->hasAuthorizationState($service)) {
            throw new AuthorizationStateNotFoundException('State not found in redis');
        }

        if (isset($this->cachedStates[$service])) {
            return $this->cachedStates[$service];
        }

        $val = file_get_contents($this->getStatePath($service));

        return $this->cachedStates[$service] = unserialize($val);
    }

    /**
     * {@inheritDoc}
     */
    public function storeAuthorizationState($service, $state)
    {
        // (over)write the token
        file_put_contents($this->getStatePath($service), serialize($state));
        $this->cachedStates[$service] = $state;

        // allow chaining
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function hasAuthorizationState($service)
    {
        if (isset($this->cachedStates[$service])
            && null !== $this->cachedStates[$service]
        ) {
            return true;
        }

        return file_exists($this->getStatePath($service));
    }

    /**
     * {@inheritDoc}
     */
    public function clearAuthorizationState($service)
    {
        unlink($this->getStatePath($service));
        unset($this->cachedStates[$service]);

        // allow chaining
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function clearAllAuthorizationStates()
    {
        // memory
        $this->cachedStates = array();

        foreach (glob($this->directory . $this->filenameStatePrefix . '*') as $filename) {
            unlink($filename);
        }

        // allow chaining
        return $this;
    }
}
