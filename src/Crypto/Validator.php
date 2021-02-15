<?php

declare(strict_types=1);

namespace Crypto;

use Crypto\Exception\MissingCryptoException;
use Crypto\Exception\NotUpdatedException;

class Validator
{
    /**
     * @var array
     */
    private array $parameters = [];

    /**
     * @var array
     */
    private array $data = [
        'code'    => '',
        'change'  => false,
        'satoshi' => false,
    ];

    /**
     * @param array $parameters
     */
    public function __construct(array $parameters = [])
    {
        $this->parameters = $parameters;
    }

    /**
     * @throws MissingCryptoException
     * @throws NotUpdatedException
     */
    public function check(): void
    {
        // compatibility
        if (isset($this->parameters['currency'])) {
            throw new NotUpdatedException();
        }

        if (!isset($this->parameters['code'])) {
            throw new MissingCryptoException('Empty crypto code, please configure it');
        } else {
            $this->data['code'] = addslashes($this->parameters['code']);
        }

        $this->data['change']  = isset($this->parameters['change']) && strtolower($this->parameters['change']) === 'yes';
        $this->data['satoshi'] = isset($this->parameters['satoshi']) && $this->parameters['satoshi'] == 'true';
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }
}
