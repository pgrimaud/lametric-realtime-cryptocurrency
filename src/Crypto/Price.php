<?php

namespace Crypto;

use Crypto\Exception\NotFoundCryptoException;
use GuzzleHttp\Client as GuzzleClient;
use Predis\Client as PredisClient;

class Price
{
    const DATA_ENDPOINT = 'https://web-api.coinmarketcap.com/v1/cryptocurrency/listings/latest?convert=USD&cryptocurrency_type=all&limit=3000';

    /**
     * @var GuzzleClient
     */
    private $guzzleClient;

    /**
     * @var PredisClient
     */
    private $predisClient;

    /**
     * @var Currency
     */
    private $currency;

    /**
     * Price constructor.
     * @param GuzzleClient $guzzleClient
     * @param PredisClient $predisClient
     * @param Currency     $currency
     */
    public function __construct(GuzzleClient $guzzleClient, PredisClient $predisClient, Currency $currency)
    {
        $this->guzzleClient = $guzzleClient;
        $this->predisClient = $predisClient;
        $this->currency     = $currency;
    }

    /**
     * @return void
     * @throws NotFoundCryptoException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getValue()
    {
        $redisKey = 'lametric:cryptocurrency';

        $pricesFile = $this->predisClient->get($redisKey);
        $ttl        = $this->predisClient->ttl($redisKey);

        if (!$pricesFile || $ttl < 0) {
            $rawData = $this->fetchData();

            $prices = $this->formatData($rawData);

            // save to redis
            $this->predisClient->set($redisKey, json_encode($prices));
            $this->predisClient->expireat($redisKey, strtotime("+3 minutes"));
        } else {
            $prices = json_decode($pricesFile, JSON_OBJECT_AS_ARRAY);
        }

        if ($prices[$this->currency->getCode()]) {
            $this->currency->setName($prices[$this->currency->getCode()]['name']);

            if ($this->currency->isSatoshi()) {
                $price = (float)($prices[$this->currency->getCode()]['price'] / $prices['BTC']['price']) * pow(10, 8);
            } else {
                $price = (float)$prices[$this->currency->getCode()]['price'];
            }

            $this->currency->setPrice($price);
            $this->currency->setChange((float)$prices[$this->currency->getCode()]['change']);
        } else {
            throw new NotFoundCryptoException($this->currency->getCode());
        }
    }

    /**
     * @param $data
     * @return array
     */
    public function formatData($data)
    {
        $formattedData = [];

        foreach ($data as $currency) {
            $formattedData[$currency['short']] = [
                'price'  => $currency['price'],
                'change' => $currency['change']
            ];
        }

        return $formattedData;
    }

    /**
     * @return Currency
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function fetchData()
    {
        $resource = $this->guzzleClient->request('GET', self::DATA_ENDPOINT);

        $sources = json_decode((string)$resource->getBody(), true);

        $data = [];

        foreach ($sources['data'] as $crypto) {
            $data[] = [
                'short'  => $crypto['symbol'],
                'price'  => $crypto['quote']['USD']['price'],
                'change' => $crypto['quote']['USD']['percent_change_24h'],
            ];
        }

        return $data;
    }
}
