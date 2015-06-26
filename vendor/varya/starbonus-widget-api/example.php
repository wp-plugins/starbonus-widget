<?php

// Bootstrap the library
require_once __DIR__ . '/vendor/autoload.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('date.timezone', 'Europe/Warsaw');

// Setup the credentials for the requests
$credentials = new \OAuth\Common\Consumer\Credentials(
    'testclient',
    'testpass',
    null
);

// dev server
$uri = new \OAuth\Common\Http\Uri\Uri('http://api.starbonus.kusmierz.be');
$starbonusApi = new \Starbonus\Api\Api($credentials, $uri);
// production
// $starbonusApi = new \Starbonus\Api\Api($credentials);

// WARNING! static value here is ONLY for demo purpose. DO NOT include it in production code.
// Please DO NOT send anything to SB if there is no "starbonus" cookie.
$click = (!empty($_COOKIE['starbonus']) ? $_COOKIE['starbonus'] : 'GgE3lZ7SncW4QjMr');

// get order details from shop's database
// just for this DEMO generate some random data...
$order = array(
    'id' => uniqid('order_', true),
    'amount' => rand(100, 99999),
);

// create new one
$entity = new \Starbonus\Api\Entity\TransactionCashback();

$entity
    // get from cookie
    ->setClick($click)
    // shop's order ID
    ->setTransaction($order['id'])// should be uniq! if you will try to change transaction "accepted" you will get 500 error
    // multiply by 100 (ie. 12,30 zł will be 1230)
    ->setAmountPurchase($order['amount'])
    ->setState('pending');
// optional
//->setCategory('foo');

var_dump($entity);

try {
    $result = $starbonusApi->serviceTransactionCashback()->create($entity);

    $entityID = $result->getId();

    var_dump($result);
} catch (\Exception $e) {
    // do something
    die($e->getMessage());
}

if (!empty($entityID)) {
    // update
    $entity = new \Starbonus\Api\Entity\TransactionCashback();

    $entity
        // update state to accepted
        ->setState('accepted');
    // user modified his order, we could _optionally_ update the price too (20pln here)
    //->setAmountPurchase(2000);

    try {
        // save it
        $result = $starbonusApi->serviceTransactionCashback()->patch($entityID, $entity);

        var_dump($result);
    } catch (\Exception $e) {
        // do something
        die($e->getMessage());
    }
}
