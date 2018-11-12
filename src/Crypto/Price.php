<?php

namespace Crypto;

use Crypto\Exception\NotFoundCryptoException;
use GuzzleHttp\Client as GuzzleClient;
use Predis\Client as PredisClient;

class Price
{
    const DATA_ENDPOINT = 'https://coincap.io/front';

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
            $resource = $this->guzzleClient->request('GET', self::DATA_ENDPOINT);
            $file     = $resource->getBody();

            $rawData = json_decode($file, JSON_OBJECT_AS_ARRAY);

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
                $price = (float)($prices[$this->currency->getCode()]['price'] / $prices['BTC']['price']) * 10 ^ 8;
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
                'name'   => $currency['long'],
                'price'  => $currency['price'],
                'change' => $currency['cap24hrChange']
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
}
