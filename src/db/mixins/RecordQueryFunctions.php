<?php

namespace dekey\domain\db\mixins;

/**
 * Combines functions required for {@link dekey\domain\db\RecordQuery}.
 * The only goal of this mixin is to allow building custom query classes without extending {@link dekey\domain\db\RecordQuery}
 *
 * @property string $alias public alias of the {@link _alias}
 * @property string $mainTableName public alias of the {@link _mainTableName}
 *
 * @mixin QueryConditionBuilderAccess
 *
 * @package dekey\domain\db\mixins
 * @author Dmitry Kolodko <prowwid@gmail.com>
 */
trait RecordQueryFunctions {
    public $primaryKeyName = 'id';
    private $_alias;
    private $_mainTableName;
    //region ------------------- SEARCH METHODS  --------------------

    /**
     * Method designed to make chain of query methods more accurate if query used as a stored object and not as a part
     * of active record.
     * Example:
     * <pre>
     * $finder = new ActiveQuery();
     *     $resultSet = $finder->find()
     *       ->active()
     *       ->withSomeRelation()
     *       ->all();
     *     $record = $finder->find()->one();
     * </pre>
     *
     * @return $this
     */
    public function find() {
        $clone = clone $this;
        foreach ($this->getBehaviors() as $name => $behavior) {
            $clone->attachBehavior($name, clone $behavior);
        }
        return $clone;
    }

    /**
     * @param $pk
     * @return \dekey\domain\db\Record|array|null
     */
    public function oneWithPk($pk) {
        $pkParam = $this->buildAliasedNameOfParam('pk');
        $primaryKey = $this->buildAliasedNameOfField($this->primaryKeyName);
        $this->andWhere("{$primaryKey}={$pkParam}", [$pkParam => $pk]);
        return $this->one();
    }

    /**
     * @override
     * @inheritdoc
     */
    public function alias($alias) {
        $this->_alias = $alias;
        return parent::alias($alias);
    }

    //endregion

    //region ------------------- GETTERS/SETTERS  -------------------

    public function getMainTableName() {
        if ($this->_mainTableName == null) {
            $method = new \ReflectionMethod($this->modelClass, 'tableName');
            $this->_mainTableName = $method->invoke(null);
        }
        return $this->_mainTableName;
    }

    public function setMainTableName($mainTableName) {
        $this->_mainTableName = $mainTableName;
    }

    public function getAlias() {
        if ($this->_alias === null) {
            $this->_alias = $this->getMainTableName();
        }
        return $this->_alias;
    }
    //endregion
}