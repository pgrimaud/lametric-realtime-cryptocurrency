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
        'code'    => '',
        'change'  => false,
        'satoshi' => false
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

        if (!isset($this->parameters['code'])) {
            throw new MissingCryptoException('Empty crypto code, please configure it');
        } else {
            $this->data['code'] = addslashes($this->parameters['code']);
        }

        $this->data['change']  = isset($this->parameters['change']) && strtolower($this->parameters['change']) === 'yes';
        $this->data['satoshi'] = isset($this->parameters['satoshi']) && $this->parameters['satoshi'] == 1;
    }

    /**
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }
}
