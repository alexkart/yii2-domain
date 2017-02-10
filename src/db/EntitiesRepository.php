<?php

namespace dekey\domain\db;

use dekey\domain;
use dekey\domain\contracts;
use dekey\domain\data\EntitiesProvider;
use dekey\domain\exceptions\UnableToSaveEntityException;
use yii\base\InvalidConfigException;

/**
 * Represents entities DB repository.
 *
 * @package dekey\domain\base
 * @author Dmitry Kolodko <prowwid@gmail.com>
 */
class EntitiesRepository extends base\Repository {
    /**
     * @var string data mapper class name. Required to map data from record to entity. Change it in {@link init()} method
     * if you need custom mapper. But be aware - data mapper is internal class and it is strongly advised to not
     * touch this property.
     */
    public $dataMapperClassName = domain\base\DataMapper::class;
    /**
     * @var string entities finder class name. This class being used if no finder specified in morel directory. Change it
     * in {@link init()} method if you need custom default finder.
     */
    private $_defaultFinderClass = Finder::class;

    public function __construct($config = []) {
        $this->entitiesProviderClassName = EntitiesProvider::class;
        parent::__construct($config);
    }

    //----------------------- ENTITY MANIPULATION METHODS -----------------------//

    /**
     * @override
     * @param domain\base\Entity $entity
     */
    protected function saveEntityInternal(contracts\DomainEntity $entity, $runValidation, $attributes) {
        $isEntityNew = $entity->isNew();
        if ($this->triggerModelEvent($isEntityNew ? self::EVENT_BEFORE_ADD : self::EVENT_BEFORE_UPDATE, $entity) && $this->triggerModelEvent(self::EVENT_BEFORE_SAVE, $entity)) {
            $dataSource = $entity->getDataMapper()->getDataSource();
            $result = $runValidation ? $dataSource->validateAndSave($attributes) : $dataSource->saveWithoutValidation($attributes);
        } else {
            $result = false;
        }
        if ($result) {
            $this->triggerModelEvent($isEntityNew ? self::EVENT_BEFORE_ADD : self::EVENT_AFTER_UPDATE, $entity);
            $this->triggerModelEvent(self::EVENT_AFTER_SAVE, $entity);
        } else {
            $exception = new UnableToSaveEntityException('Failed to save entity ' . get_class($entity));
            $exception->errorsList = $dataSource->getErrors();
            throw $exception;
        }

        return $result;
    }

    /**
     * @param domain\base\Entity $entity
     * @return bool result.
     */
    public function delete(contracts\DomainEntity $entity) {
        if ($this->triggerModelEvent(self::EVENT_BEFORE_DELETE, $entity)) {
            $result = $entity->getDataMapper()->getDataSource()->deleteRecord();
        } else {
            $result = false;
        }
        if ($result) {
            $this->triggerModelEvent(self::EVENT_AFTER_DELETE, $entity);
        }
        return $result;
    }

    /**
     * @param domain\base\Entity $entity
     * @return bool result.
     */
    public function validate(contracts\DomainEntity $entity) {
        $dataSource = $entity->getDataMapper()->getDataSource();
        return $dataSource->validate();
    }

    //----------------------- INSTANTIATION METHODS -----------------------//

    public function createNewEntity() {
        $container = $this->container;
        return $container->create([
            'class' => $this->getEntityClass(),
            'dataMapper' => $container->create($this->dataMapperClassName, [$this->createRecord()]),
        ]);
    }

    private function createRecord() {
        return $this->container->create($this->getRecordClass());
    }

    public function createEntityFromSource(contracts\EntityDataSource $record) {
        $container = $this->container;
        return $container->create([
            'class' => $this->getEntityClass(),
            'dataMapper' => $container->create($this->dataMapperClassName, [$record]),
        ]);
    }

    //----------------------- SEARCH METHODS -----------------------//

    /**
     * @return Finder|RecordQuery
     */
    public function find() {
        return $this->createFinder();
    }

    protected function createFinder() {
        return $this->container->create($this->getFinderClass(), [$query = $this->createQuery(), $repository = $this]);
    }

    //----------------------- GETTERS FOR DYNAMIC PROPERTIES -----------------------//

    protected function getRecordClass() {
        return $this->buildModelElementClassName('Record');
    }

    protected function getFinderClass() {
        return $this->buildModelElementClassName('Finder', $this->getDefaultFinderClass());
    }

    //----------------------- GETTERS/SETTERS -----------------------//

    protected function getDefaultFinderClass() {
        return $this->_defaultFinderClass;
    }

    protected function setDefaultFinderClass($defaultFinderClass) {
        if (!class_exists($defaultFinderClass) && !interface_exists($defaultFinderClass)) {
            throw new InvalidConfigException('Default finder class should be an existing class or interface!');
        }
        $this->_defaultFinderClass = $defaultFinderClass;
    }
}