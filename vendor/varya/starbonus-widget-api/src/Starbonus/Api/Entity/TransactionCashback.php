<?php

namespace Starbonus\Api\Entity;

use InvalidArgumentException;
use OutOfBoundsException;
use Starbonus\Api\Type;

/**
 * Class TransactionCashback
 *
 * @author Adam Kuśmierz <adam@kusmierz.be>
 * @package Starbonus\Api\Entity
 */
class TransactionCashback
{

    /**
     * Starbonus transaction ID
     *
     * @var int
     */
    protected $id;

    /**
     * Click ID (from cookie)
     *
     * @var int
     */
    protected $click;

    /**
     * Amount of order (in "pennies"!)
     *
     * @var int
     */
    protected $amountPurchase;

    /**
     * @var string
     */
    protected $currency = 'pln';

    /**
     * Shop's order id, could be id or string
     *
     * @var string|int
     */
    protected $transaction;

    /**
     * [pending, accepted, deleted]
     *
     * @var string
     */
    protected $state = Type\TransactionCashbackState::STATE_PENDING;

    /**
     * For commission calculation, optional
     *
     * @var string|int
     */
    protected $category;

    /**
     * @return int
     */
    public function getClick()
    {
        return $this->click;
    }

    /**
     * @param int $click
     *
     * @return $this
     */
    public function setClick($click)
    {
        $this->click = $click;
        return $this;
    }

    /**
     * @return int
     */
    public function getAmountPurchase()
    {
        return $this->amountPurchase;
    }

    /**
     * @param int $amountPurchase
     *
     * @return $this
     */
    public function setAmountPurchase($amountPurchase)
    {
        if (strval(intval($amountPurchase)) !== strval($amountPurchase)) {
            throw new InvalidArgumentException('Amount have to be integer!');
        }

        $this->amountPurchase = intval($amountPurchase);

        return $this;
    }

    /**
     * @return string
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * @param string $currency
     *
     * @return $this
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;
        return $this;
    }

    /**
     * @return int|string
     */
    public function getTransaction()
    {
        return $this->transaction;
    }

    /**
     * @param int|string $transaction
     *
     * @return $this
     */
    public function setTransaction($transaction)
    {
        $this->transaction = $transaction;
        return $this;
    }

    /**
     * @return string
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * @param string $state
     *
     * @return $this
     */
    public function setState($state)
    {
        if (!in_array($state, Type\TransactionCashbackState::getValues())) {
            throw new OutOfBoundsException('Wrong transaction state');
        }

        $this->state = $state;

        return $this;
    }

    /**
     * @return int|string
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * @param int|string $category
     *
     * @return $this
     */
    public function setCategory($category)
    {
        $this->category = $category;
        return $this;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     *
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

}
