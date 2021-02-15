<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$config = require_once __DIR__ . '/../config/parameters.php';

use Crypto\Exception\{NotFoundCryptoException, NotUpdatedException};
use Crypto\{Currency, Price, Response, Validator};

Sentry\init(['dsn' => $config['sentry_key']]);

header("Content-Type: application/json");

$response = new Response();

try {

    $validator = new Validator($_GET);
    $validator->check();

    $currency = new Currency();
    $currency->setCode($validator->getData()['code']);
    $currency->setShowChange($validator->getData()['change']);
    $currency->setSatoshi($validator->getData()['satoshi']);

    $price = new Price(new \GuzzleHttp\Client(), new \Predis\Client(), $currency);
    $price->getValue();

    echo $response->data($price->getCurrency());

} catch (NotUpdatedException $exception) {
    echo $response->error('Please update application!');
} catch (\Crypto\Exception\MissingCryptoException $exception) {
    echo $response->error($exception->getMessage());
} catch (NotFoundCryptoException $exception) {
    $currencyCode = $exception->getMessage();
    echo $response->error('Invalid currency code ' . $currencyCode . '! Please check your configuration!');
} catch (Exception $exception) {
    echo $response->error();
}