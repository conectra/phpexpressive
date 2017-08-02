<?php

namespace Solis\Expressive\Classes\Illuminate\Patch;

use Solis\Expressive\Contracts\ExpressiveContract;
use Solis\PhpSchema\Abstractions\Database\FieldEntryAbstract;
use Solis\Breaker\TException;

/**
 * Class RelationshipBuilder
 *
 * @package Solis\Expressive\Classes\Illuminate\Patch
 */
final class RelationshipBuilder
{

    /**
     * @param ExpressiveContract $model
     * @param ExpressiveContract $original
     * @param FieldEntryAbstract $dependency
     *
     * @return ExpressiveContract
     *
     * @throws TException
     */
    public function hasMany(
        $model,
        $original,
        $dependency
    ) {
        $field = $dependency->getObject()->getRelationship()->getSource()->getField();
        $refers = $dependency->getObject()->getRelationship()->getSource()->getRefers();

        $originalArray = $original->{$dependency->getProperty()};
        $originalArray = !is_array($originalArray) ? [$originalArray] : $originalArray;
        if (!empty($originalArray)) {
            foreach ($originalArray as $originalDependency) {
                if (empty($originalDependency->delete())) {
                    throw new TException(
                        __CLASS__,
                        __METHOD__,
                        'Error removing dependency has many in patch method',
                        500
                    );
                };
            }
        }

        $dependencyArray = $model->{$dependency->getProperty()};
        $dependencyArray = !is_array($dependencyArray) ? [$dependencyArray] : $dependencyArray;

        foreach ($dependencyArray as $dependencyValue) {

            $dependencyValue->$refers = $model->$field;
            $sharedFields = $dependency->getObject()->getRelationship()->getSharedFields();
            if (!empty($sharedFields)) {
                foreach ($sharedFields as $sharedField) {
                    $dependencyValue->$sharedField = $model->$sharedField;
                }
            }
            $dependencyValue->create();
        }
    }
}
