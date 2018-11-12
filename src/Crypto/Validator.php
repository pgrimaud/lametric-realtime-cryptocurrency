<?php

namespace Crypto;

use Crypto\Exception\MissingCryptoException;
use Crypto\Exception\NotUpdatedException;

class Validator
{
    /**
     * @var array
     */
    private $parameters = [];

    /**
     * @var array
     */
    private $data = [
        'codes'  => '',
        'change' => false
    ];

    /**
     * Validation constructor.
     * @param $parameters
     */
    public function __construct($parameters)
    {
        $this->parameters = $parameters;
    }

    /**
     * @throws MissingCryptoException
     * @throws NotUpdatedException
     */
    public function check()
    {
        // compatibility
        if (isset($this->parameters['currency'])) {
            throw new NotUpdatedException();
        }

        if (!count($this->parameters['code'])) {
            throw new MissingCryptoException();
        }

        $this->data['change'] = isset($this->parameters['change']) && strtolower($this->parameters['change']) === 'yes';
    }

    /**
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }
}
