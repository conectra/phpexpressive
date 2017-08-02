<?php

namespace Solis\Expressive\Classes\Illuminate\Replicate;

use Solis\Expressive\Classes\Illuminate\Insert\InsertBuilder;
use Solis\Expressive\Classes\Illuminate\Util\Actions;
use Solis\Expressive\Contracts\ExpressiveContract;
use Illuminate\Database\Capsule\Manager as Capsule;
use Solis\Expressive\Classes\Illuminate\Database;
use Solis\Expressive\Abstractions\ExpressiveAbstract;
use Solis\PhpSchema\Abstractions\Database\FieldEntryAbstract;
use Solis\Breaker\TException;

/**
 * Class PatchBuilder
 *
 * @package Solis\Expressive\Classes\Illuminate\Insert
 */
final class ReplicateBuilder
{

    /**
     * @var InsertBuilder
     */
    private $insertBuilder;

    /**
     * PatchBuilder constructor.
     */
    public function __construct()
    {
        $this->setInsertBuilder(new InsertBuilder());
    }

    /**
     * @return InsertBuilder
     */
    public function getInsertBuilder()
    {
        return $this->insertBuilder;
    }

    /**
     * @param InsertBuilder $insertBuilder
     */
    public function setInsertBuilder($insertBuilder)
    {
        $this->insertBuilder = $insertBuilder;
    }

    /**
     * @param ExpressiveContract $model
     *
     * @return ExpressiveContract|boolean
     *
     * @throws TException;
     */
    public function replicate(ExpressiveContract $model)
    {
        if (empty($model->getSchema()->getDatabase())) {
            throw new TException(
                __CLASS__,
                __METHOD__,
                'database schema entry has not been defined for ' . get_class($model),
                400
            );
        }

        $original = $model->search();
        if (empty($original)) {
            throw new TException(
                __CLASS__,
                __METHOD__,
                'object for ' . get_class($model) . ' has not been found in the database',
                400
            );
        }

        return $this->create($original);
    }

    /**
     * @param ExpressiveContract $model
     *
     * @return ExpressiveContract|boolean
     *
     * @throws TException;
     */
    private function create(
        ExpressiveContract $model
    ) {
        $databaseIncrementableKeys = [];
        $applicationIncrementableKeys = [];

        foreach ($model->getSchema()->getProperties() as $property) {
            if ($property->getBehavior()->getIncrementalBehavior() == 'database') {
                if (!in_array($property->getProperty(), $databaseIncrementableKeys)) {
                    $databaseIncrementableKeys[] = $property->getProperty();
                }
            }

            if ($property->getBehavior()->getIncrementalBehavior() == 'application') {
                if (!in_array($property->getProperty(), $databaseIncrementableKeys)) {
                    $applicationIncrementableKeys[] = $property->getProperty();
                }
            }
        }

        if (empty($databaseIncrementableKeys) && empty($applicationIncrementableKeys)) {
            throw new TException(
                __CLASS__,
                __METHOD__,
                'you must have a least one incremental key to use replicate method',
                500
            );
        }

        $table = $model->getSchema()->getDatabase()->getTable();

        Database::beginTransaction($model);
        try {

            $model = Actions::doThingWhenDatabaseAction(
                $model,
                'whenInsert',
                'Before'
            );

            // verify direct dependencies to $model
            $model = $this->hasOneDependency($model);

            Capsule::table($table)->insert(
                $this->getInsertFields(
                    $model,
                    $applicationIncrementableKeys,
                    $databaseIncrementableKeys
                )
            );
        } catch (\PDOException $exception) {

            Database::rollbackActiveTransaction($model);
            throw new TException(
                __CLASS__,
                __METHOD__,
                $exception->getMessage(),
                400
            );
        }

        $model = $this->getInsertBuilder()->setPrimaryKeysFromLast($model);

        // verify dependencies related to model
        $this->hasManyDependencies($model);

        Actions::doThingWhenDatabaseAction(
            $model,
            'whenInsert',
            'after'
        );

        Database::commitActiveTransaction($model);

        // return the last inserted entry
        return $model;
    }

    /**
     * @param ExpressiveContract $model
     *
     * @return ExpressiveContract
     *
     * @throws TException
     */
    public function hasOneDependency($model)
    {
        $dependencies = $model->getSchema()->getDatabase()->getByRelationshipType('hasOne');
        if (empty($dependencies)) {
            return $model;
        }

        foreach (array_values($dependencies) as $dependency) {
            $value = $model->{$dependency->getProperty()};

            if(!empty($value)){
                if (! $value instanceof ExpressiveAbstract) {
                    throw new TException(
                        __CLASS__,
                        __METHOD__,
                        "dependency must be instance of ExpressiveAbstract in class " . get_class($model),
                        500
                    );
                }

                $model = $this->hasOne(
                    $model,
                    $dependency
                );
            }
        }

        return $model;
    }

    /**
     * @param ExpressiveContract $model
     *
     * @throws TException
     */
    public function hasManyDependencies($model)
    {
        $dependencies = $model->getSchema()->getDatabase()->getByRelationshipType('hasMany');
        if (!empty($dependencies)) {
            foreach (array_values($dependencies) as $dependency) {
                $value = $model->{$dependency->getProperty()};
                if (!empty($value)) {
                    $this->hasMany(
                        $model,
                        $dependency
                    );
                }
            }
        }
    }

    /**
     * @param ExpressiveContract $model
     * @param array              $applicationIncrementableKeys
     * @param array              $databaseIncrementableKeys
     *
     * @return array
     *
     * @throws TException
     */
    private function getInsertFields(
        ExpressiveContract $model,
        $applicationIncrementableKeys = [],
        $databaseIncrementableKeys = []
    ) {
        $persistentFields = array_filter($model->getSchema()->getDatabase()->getFields(), function (FieldEntryAbstract $item) use ($model){
            if (
                !empty($item->getBehavior()->isAutoIncrement()) &&
                $item->getBehavior()->getIncrementalBehavior() === 'database' &&
                is_null($item->getProperty())
            ) {
                return false;
            }
            if(!empty($item->getObject())){
                if($item->getObject()->getRelationship()->getType() === 'hasMany') {
                    return false;
                }
            }
            if (is_null($model->{$item->getProperty()}) && empty($item->getBehavior()->isRequired())) {
                return false;
            }
            if(is_null($model->{$item->getProperty()}) && $item->getBehavior()->isRequired()){
                throw new TException(
                    __CLASS__,
                    __METHOD__,
                    "a persistent field [ {$item->getProperty()} ] cannot be empty when inserting object " . get_class($model),
                    400
                );
            }
            return true;
        });

        if (empty($persistentFields)) {
            throw new TException(
                __CLASS__,
                __METHOD__,
                "class " . get_class($model) . " has not persistent fields",
                500
            );
        }

        if (!empty($applicationIncrementableKeys)) {
            $last = $model->last();
            foreach ($applicationIncrementableKeys as $key) {
                $model->{$key} = $last->{$key} + 1;
            }
        }

        $fields = [];
        foreach ($persistentFields as $persistentField) {
            if (!in_array($persistentField->getProperty(), $databaseIncrementableKeys)) {
                $fields[$persistentField->getColumn()] = $model->{$persistentField->getProperty()};
            }
        }

        return $fields;
    }

    /**
     * @param ExpressiveContract $model
     * @param FieldEntryAbstract $dependency
     *
     * @return ExpressiveContract
     *
     * @throws TException
     */
    public function hasOne(
        $model,
        $dependency
    ) {
        $value = $model->{$dependency->getProperty()};
        $instance = is_array($dependency) ? call_user_func_array(
            [$dependency->getObject()->getClass(), 'make'],
            [$model->{$dependency->getProperty()}]
        ) : $value;

        $sharedFields = $dependency->getObject()->getRelationship()->getSharedFields();
        if (!empty($sharedFields)) {
            foreach ($sharedFields as $sharedField) {
                $instance->{$sharedField} = $model->{$sharedField};
            }
        }
        if (empty($instance->search())) {
            throw new TException(
                __CLASS__,
                __METHOD__,
                "dependency " . get_class($instance) . " has not been found in database for class " . get_class($model),
                500
            );
        }

        $refers = $dependency->getObject()->getRelationship()->getSource()->getRefers();

        $field = $dependency->getObject()->getRelationship()->getSource()->getField();

        $model->{$field} = $instance->{$refers};

        return $model;
    }

    /**
     * @param ExpressiveContract|ExpressiveContract[] $model
     * @param FieldEntryAbstract                      $dependency
     *
     * @return ExpressiveContract
     *
     * @throws TException
     */
    public function hasMany(
        $model,
        $dependency
    ) {

        $dependencyValue = $model->{$dependency->getProperty()};

        $dependencyValue = !is_array($dependencyValue) ? [$dependencyValue] : $dependencyValue;

        $field = $dependency->getObject()->getRelationship()->getSource()->getField();

        $refers = $dependency->getObject()->getRelationship()->getSource()->getRefers();

        $sharedFields = $dependency->getObject()->getRelationship()->getSharedFields();
        foreach ($dependencyValue as $item) {
            $item->$refers = $model->$field;

            if (!empty($sharedFields)) {
                foreach ($sharedFields as $sharedField) {
                    $item->{$sharedField} = $model->{$sharedField};
                }
            }
            if (!$this->create($item)) {
                throw new TException(
                    __CLASS__,
                    __METHOD__,
                    "error creating dependency " . get_class($item) . " for " . get_class($model),
                    500
                );
            }
        }
    }
}
