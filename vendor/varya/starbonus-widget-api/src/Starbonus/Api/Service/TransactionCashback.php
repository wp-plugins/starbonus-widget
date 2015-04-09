<?php

namespace Starbonus\Api\Service;

use Starbonus\Api\Api;
use Starbonus\Api\Hydrator;
use Starbonus\Api\Entity;

/**
 * Class TransactionCashback
 *
 * @author Adam Kuśmierz <adam@kusmierz.be>
 * @package Starbonus\Api\Service
 */
class TransactionCashback extends AbstractService
{

    /**
     * @var string
     */
    protected $path = '/transaction-cashback';

    /**
     * @inheritdoc
     */
    public function __construct(Api $api)
    {
        parent::__construct($api);

        $this->hydrator = new Hydrator\TransactionCashbackMapper();
    }

    /**
     * Create a resource
     *
     * @param  Entity\TransactionCashback $entity
     * @return mixed
     */
    public function create(Entity\TransactionCashback $entity)
    {
        $data = $this->getHydrator()->extract($entity);

        $response = $this->getApiInstance()->request($this->path, 'POST', $data);

        $entity = new Entity\TransactionCashback();

        return $this->getHydrator()->hydrate($response, $entity);
    }

    /**
     * Patch (partial in-place update) a resource
     *
     * @param  mixed $id
     * @param  Entity\TransactionCashback $entity
     * @return mixed
     */
    public function patch($id, Entity\TransactionCashback $entity)
    {
        $data = $this->getHydrator()->extract($entity);

        $data = array_filter($data);

        $response = $this->getApiInstance()->request($this->path . '/' . $id, 'PATCH', $data);

        $entity = new Entity\TransactionCashback();

        return $this->getHydrator()->hydrate($response, $entity);
    }
}
