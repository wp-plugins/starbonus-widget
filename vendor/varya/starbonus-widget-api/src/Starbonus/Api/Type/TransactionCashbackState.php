<?php

namespace Starbonus\Api\Type;


/**
 * Class TransactionCashbackState
 *
 * @author Adam Kuśmierz <adam@kusmierz.be>
 * @package Starbonus\Api\Type
 */
class TransactionCashbackState extends AbstractType
{
    const STATE_PENDING = 'pending';
    const STATE_ACCEPTED = 'accepted';
    const STATE_DELETED = 'deleted';

    /**
     * @var array
     */
    protected static $values = array(
        self::STATE_PENDING,
        self::STATE_ACCEPTED,
        self::STATE_DELETED,
    );
}
