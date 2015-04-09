<?php

/**
 * Simple redirection query
 *
 * http://www.example.com/starbonus/{clickId}/{siteUrl}
 */

/**
 * Required - first parameter from query
 *
 * @var string $starbonusClickId
 *
 */
$starbonusClickId = "{clickId}";

/**
 * Optional - second parameter from query
 * Url to redirection before setting cookie
 * If it is empty site will be redirect to http://www.example.com
 *
 * @var string $starbonusUrlDomain
 */
$starbonusUrlDomain = "{siteUrl}";

/**
 * Cookie expiration days
 *
 * @var int $cookieExpire
 */
$cookieExpire = 14;

/**
 * Server name
 *
 * @var string $url
 */
$url = $_SERVER['SERVER_NAME'];

/**
 * During redirection $starbonusClickId could not be empty
 */
if (!empty($starbonusClickId)) {
    setcookie('starbonus', $starbonusClickId, time() + 60 * 60 * 24 * $cookieExpire);

    $parsed = parse_url($url);
    $relativeparts = array_intersect_key($parsed, array_flip(['path', 'query', 'fragment']));

    $url = http_build_url($relativeparts);

    // Redirect to $url
    header('Location', $url);
}
