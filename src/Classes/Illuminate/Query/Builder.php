<?php

namespace Solis\Expressive\Classes\Illuminate\Query;

use Illuminate\Database\Capsule\Manager as Capsule;
use Solis\Expressive\Contracts\ExpressiveContract;
use Solis\Expressive\Exception;

/**
 * Class StmtBuilder
 *
 * @package Solis\Expressive\Classes\Illuminate\Select
 */
class Builder
{
    /**
     * @var string
     */
    private $table;

    /**
     * @var array
     */
    private $arguments;

    /**
     * @var array
     */
    private $options;

    /**
     * @var \Illuminate\Database\Query\Builder $stmt
     */
    private $stmt;

    /**
     * StmtBuilder constructor.
     *
     * @param string $table
     * @param array $arguments
     * @param array $options
     */
    public function __construct(string $table, array $arguments = [], array $options = [])
    {
        $this->table = $table;

        $this->arguments = $this->toMultiArray($arguments);

        $this->options = $options;

        $this->stmt = Capsule::table($table);
    }

    /**
     * @return $this
     */
    public function where()
    {
        $this->stmt = $this->whereArguments($this->stmt, $this->arguments);

        return $this;
    }

    /**
     * @param \Illuminate\Database\Query\Builder $stmt
     * @param array                              $arguments
     *
     * @return \Illuminate\Database\Query\Builder
     */
    private function whereArguments($stmt, array $arguments)
    {
        foreach ($arguments as $argument) {
            $stmt = $this->addWhere($stmt, $argument);
        }

        return $stmt;
    }

    /**
     * @param \Illuminate\Database\Query\Builder $stmt
     * @param array                              $argument
     *
     * @return \Illuminate\Database\Query\Builder
     */
    private function addWhere($stmt, $argument)
    {
        $type = $argument['type'] ?? 'basic';

        return $type === 'nested' ? $this->addNestedWhere($stmt, $argument) : $this->addBasicWhere($stmt, $argument);
    }

    /**
     * @param \Illuminate\Database\Query\Builder $stmt
     * @param array                              $argument
     *
     * @return \Illuminate\Database\Query\Builder
     */
    private function addBasicWhere($stmt, $argument)
    {
        $stmt->where(
            $argument['column'],
            $argument['operator'] ?? '=',
            $argument['value'],
            $argument['chainType'] ?? 'and'
        );

        return $stmt;
    }

    /**
     * @param \Illuminate\Database\Query\Builder $stmt
     * @param array                              $argument
     *
     * @return \Illuminate\Database\Query\Builder
     */
    private function addNestedWhere($stmt, $argument)
    {
        $chainType = $argument['chainType'] ?? 'or';

        return $chainType === 'and' ? $this->addAndWhere($stmt, $argument) : $this->addOrWhere($stmt, $argument);
    }

    /**
     * @param \Illuminate\Database\Query\Builder $stmt
     * @param array                              $argument
     *
     * @return \Illuminate\Database\Query\Builder
     */
    private function addOrWhere($stmt, $argument)
    {
        $stmt->orWhere(function ($query) use ($argument) {
            foreach ($argument['column'] as $column) {
                $query = $this->addWhere($query, $column);
            }
        });

        return $stmt;
    }

    /**
     * @param \Illuminate\Database\Query\Builder $stmt
     * @param array                              $argument
     *
     * @return \Illuminate\Database\Query\Builder
     */
    private function addAndWhere($stmt, $argument)
    {
        $stmt->where(function ($query) use ($argument) {
            foreach ($argument['column'] as $column) {
                $query = $this->addWhere($query, $column);
            }
        });

        return $stmt;
    }

    /**
     * @param ExpressiveContract $model
     *
     * @return $this
     * @throws Exception
     */
    public function whereKeys(ExpressiveContract $model)
    {
        $primaryKeys = $model::$schema->getKeys();

        foreach ($primaryKeys as $key) {
            $value = $model->{$key->getProperty()};

            if (is_null($value) && empty($key->getBehavior()->isRequired())) {
                continue;
            }

            if (is_null($value)) {
                throw new Exception("property '{$key->getProperty()}' used as primary key cannot be null", 400);
            }

            $this->stmt->where($key->getProperty(), '=', $value);
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function orderBy()
    {
        $orderBy = $this->options['orderBy'] ?? null;

        if (empty($orderBy)) {
            return $this;
        }

        $orderBy = $this->toMultiArray($orderBy);

        foreach ($orderBy as $option) {
            $this->stmt->orderBy(
                $option['column'],
                $option['direction'] ?? 'asc'
            );
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function limit()
    {
        $limit = $this->options['limit'] ?? null;
        if (empty($limit)) {
            return $this;
        }

        $number = $limit['number'] ?? null;
        if ($number) {
            $this->stmt->limit(intval($number));
        }

        $offset = $limit['offset'] ?? null;
        if ($offset) {
            $this->stmt->offset(intval($offset));
        }

        return $this;
    }

    /**
     * @return array|bool
     */
    public function dependencies()
    {
        $dependencies = $this->options['withDependencies'] ?? false;

        if ($dependencies == 'true') {
            return true;
        }

        if ($dependencies == 'false') {
            return false;
        }

        return is_array($dependencies) ? $dependencies : false;
    }

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    public function getStmt()
    {
        return $this->stmt;
    }

    /**
     * @param $array
     *
     * @return array
     */
    public function toMultiArray(array $array): array
    {
        return count(array_filter(array_keys($array), 'is_string')) > 0 ? [$array] : $array;
    }
}
