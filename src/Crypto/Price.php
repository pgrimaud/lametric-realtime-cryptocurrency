<?php

declare(strict_types=1);

namespace Crypto;

use Crypto\Exception\NotFoundCryptoException;
use GuzzleHttp\Client as GuzzleClient;
use Predis\Client as PredisClient;

class Price
{
    const DATA_ENDPOINT = 'https://web-api.coinmarketcap.com/v1/cryptocurrency/listings/latest?convert=USD&cryptocurrency_type=all&limit=4999';

    /**
     * @param GuzzleClient $guzzleClient
     * @param PredisClient $predisClient
     * @param Currency     $currency
     */
    public function __construct(private GuzzleClient $guzzleClient, private PredisClient $predisClient, private Currency $currency)
    {
    }

    /**
     * @return void
     *
     * @throws NotFoundCryptoException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getValue(): void
    {
        $redisKey = 'lametric:cryptocurrency';

        $pricesFile = $this->predisClient->get($redisKey);
        $ttl        = $this->predisClient->ttl($redisKey);

        if (!$pricesFile || $ttl < 0) {
            $rawData = $this->fetchData();

            $prices = $this->formatData($rawData);

            // save to redis
            $this->predisClient->set($redisKey, json_encode($prices));
            $this->predisClient->expireat($redisKey, strtotime("+1 minute"));
        } else {
            $prices = json_decode($pricesFile, true);
        }

        if (isset($prices[$this->currency->getCode()])) {
            $this->currency->setName($this->currency->getCode());

            if ($this->currency->isSatoshi()) {
                $price = (float) ($prices[$this->currency->getCode()]['price'] / $prices['BTC']['price']) * pow(10, 8);
            } else {
                $price = (float) $prices[$this->currency->getCode()]['price'];
            }

            $this->currency->setPrice($price);
            $this->currency->setChange((float) $prices[$this->currency->getCode()]['change']);
        } else {
            throw new NotFoundCryptoException($this->currency->getCode());
        }
    }

    /**
     * @param array $data
     *
     * @return array
     */
    public function formatData(array $data): array
    {
        $formattedData = [];

        foreach ($data as $currency) {
            $formattedData[$currency['short']] = [
                'price'  => $currency['price'],
                'change' => $currency['change'],
            ];
        }

        return $formattedData;
    }

    /**
     * @return Currency
     */
    public function getCurrency(): Currency
    {
        return $this->currency;
    }

    /**
     * @return array
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function fetchData(): array
    {
        $resource = $this->guzzleClient->request('GET', self::DATA_ENDPOINT);

        $sources = json_decode((string) $resource->getBody(), true);

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
