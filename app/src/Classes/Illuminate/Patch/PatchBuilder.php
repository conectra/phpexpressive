<?php

namespace Solis\Expressive\Classes\Illuminate\Patch;

use Solis\Expressive\Classes\Illuminate\Update\UpdateBuilder;
use Solis\Expressive\Contracts\ExpressiveContract;
use Solis\Expressive\Classes\Illuminate\Database;
use Solis\Breaker\TException;

/**
 * Class PatchBuilder
 *
 * @package Solis\Expressive\Classes\Illuminate\Insert
 */
final class PatchBuilder
{

    /**
     * @var UpdateBuilder
     */
    private $updateBuilder;

    /**
     * @var RelationshipBuilder
     */
    private $relationshipBuilder;

    /**
     * PatchBuilder constructor.
     */
    public function __construct()
    {
        $this->setUpdateBuilder(new UpdateBuilder());
        $this->setRelationshipBuilder(new RelationshipBuilder());
    }

    /**
     * @return UpdateBuilder
     */
    public function getUpdateBuilder()
    {
        return $this->updateBuilder;
    }

    /**
     * @param UpdateBuilder $updateBuilder
     */
    public function setUpdateBuilder($updateBuilder)
    {
        $this->updateBuilder = $updateBuilder;
    }

    /**
     * @return RelationshipBuilder
     */
    public function getRelationshipBuilder()
    {
        return $this->relationshipBuilder;
    }

    /**
     * @param RelationshipBuilder $relationshipBuilder
     */
    public function setRelationshipBuilder($relationshipBuilder)
    {
        $this->relationshipBuilder = $relationshipBuilder;
    }

    /**
     * @param ExpressiveContract $model
     *
     * @return ExpressiveContract|boolean
     *
     * @throws TException;
     */
    public function patch(ExpressiveContract $model)
    {
        if (empty($model->getSchema()->getDatabase())) {
            throw new TException(
                __CLASS__,
                __METHOD__,
                'database schema entry has not been defined for ' . get_class($model),
                400
            );
        }

        // id utilizado para controle de transações
        $iTid = $model->getUniqid();

        try {

            Database::beginTransaction($model);

            $model->setUniqid(uniqid(rand()));

            $original = $model->search();
            if (empty($original)) {
                throw new TException(
                    __CLASS__,
                    __METHOD__,
                    'object for ' . get_class($model) . ' has not been found in the database',
                    400
                );
            }

            $record = $this->getUpdateBuilder()->update($model, false, false);
            if (empty($record)) {
                return $record;
            }

            $this->hasManyDependencies(
                $model,
                $original
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

        $model->setUniqid($iTid);

        Database::commitActiveTransaction($model);

        return $model->search();
    }

    /**
     * @param ExpressiveContract $model
     * @param ExpressiveContract $original
     *
     * @throws TException
     */
    public function hasManyDependencies(
        $model,
        $original
    ) {
        $dependencies = $model->getSchema()->getDatabase()->getByRelationshipType('hasMany');

        if (!empty($dependencies)) {
            foreach (array_values($dependencies) as $dependency) {
                $originalValue = $original->{$dependency->getProperty()};

                $updatedValue = $model->{$dependency->getProperty()};

                if (!empty($originalValue) || !empty($updatedValue)) {
                    $this->getRelationshipBuilder()->hasMany(
                        $model,
                        $original,
                        $dependency
                    );
                }
            }
        }
    }
}
