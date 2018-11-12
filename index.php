<?php

require __DIR__ . '/vendor/autoload.php';

use Crypto\Currency;
use Crypto\Exception\NotFoundCryptoException;
use Crypto\Exception\NotUpdatedException;
use Crypto\Price;
use Crypto\Response;
use Crypto\Validator;

header("Content-Type: application/json");

$response = new Response();

try {

    $validator = new Validator($_GET);
    $validator->check();

    $currency = new Currency();
    $currency->setCode($validator->getData()['code']);
    $currency->setShowChange($validator->getData()['change']);

    $price = new Price(new \GuzzleHttp\Client(), new \Predis\Client(), $currency);
    $price->getValue();

    echo $response->data($price->getCurrency());

} catch (NotUpdatedException $exception) {

    echo $response->error('Please update application!');

} catch (NotFoundCryptoException $exception) {

    $currencyCode = $exception->getMessage();
    echo $response->error('Invalid currency code ' . $currencyCode . '! Please check your configuration!');

} catch (Exception $exception) {

    echo $response->error();

} catch (\GuzzleHttp\Exception\GuzzleException $e) {

    echo $response->error();
}
