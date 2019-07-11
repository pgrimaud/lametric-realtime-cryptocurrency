<?php

namespace Crypto;

use Crypto\Exception\NotFoundCryptoException;
use GuzzleHttp\Client as GuzzleClient;
use Predis\Client as PredisClient;

class Price
{
    const DATA_ENDPOINT = 'https://coinmarketcap.com/all/views/all/';

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
        $file     = str_replace("\n", '', (string)$resource->getBody());

        preg_match_all('/<tr id=(.*?)col-symbol">(.*?)<\/td>(.*?)class="price" data-usd="(.*?)"(.*?)data-timespan="24h" data-percentusd="(.*?)"/', $file, $out);

        $data = [];

        foreach ($out[2] as $key => $crypto) {
            $data[] = [
                'short'  => $crypto,
                'price'  => str_replace(',', '', number_format($out[4][$key], 10)),
                'change' => $out[6][$key],
            ];
        }

        return $data;
    }
}
