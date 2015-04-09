<?php

namespace Starbonus\Api\Hydrator;

use Starbonus\Api\Entity;
use Zend\Stdlib\Exception;
use Zend\Stdlib\Hydrator\AbstractHydrator;

/**
 * Class TransactionCashbackMapper
 *
 * @author Adam Kuśmierz <adam@kusmierz.be>
 * @package Starbonus\Api\Hydrator
 */
class TransactionCashbackMapper extends AbstractHydrator
{
    /**
     * Extract values from an object
     *
     * @param \Starbonus\Api\Entity\TransactionCashback $object
     * @return array
     */
    public function extract($object)
    {
        $data = array(
            'click' => $object->getClick(),
            'amountPurchase' => $object->getAmountPurchase(),
            'currency' => $object->getCurrency(),
            'transaction' => $object->getTransaction(),
            'state' => $object->getState(),
            'category' => $object->getCategory(),
        );

        return $data;
    }

    /**
     * Hydrate $object with the provided $data.
     *
     * @param array $data
     * @param \Starbonus\Api\Entity\TransactionCashback $object
     * @return \Starbonus\Api\Entity\TransactionCashback
     */
    public function hydrate(array $data, $object)
    {

        if (isset($data['id'])) {
            $object->setId($data['id']);
        }
        if (isset($data['click'])) {
            $object->setClick($data['click']);
        }
        if (isset($data['amountPurchase'])) {
            $object->setAmountPurchase($data['amountPurchase']);
        }
        if (isset($data['currency'])) {
            $object->setCurrency($data['currency']);
        }
        if (isset($data['transaction'])) {
            $object->setTransaction($data['transaction']);
        }
        if (isset($data['state'])) {
            $object->setState($data['state']);
        }
        if (isset($data['category'])) {
            $object->setCategory($data['category']);
        }

        if (isset($data['_embedded']) && is_array($data['_embedded'])) {
            $embedded = $data['_embedded'];

            if (isset($embedded['click']) && is_array($embedded['click']) && isset($embedded['click']['id'])) {
                $object->setClick($embedded['click']['id']);
            }
        }

        return $object;
    }
}
