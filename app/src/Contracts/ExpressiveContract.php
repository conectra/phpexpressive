<?php

namespace Solis\Expressive\Contracts;

use Solis\PhpSchema\Contracts\SchemaContract;
use Solis\Breaker\TException;

/**
 * Classes ExpressiveContract
 *
 * @package Solis\Expressive\Contracts
 */
interface ExpressiveContract
{
    /**
     * @return SchemaContract
     */
    public function getSchema();

    /**
     * @param SchemaContract $schema
     */
    public function setSchema(SchemaContract $schema);

    /**
     * @param $table
     */
    public function setTable($table);

    /**
     * @return string
     */
    public function getTable();

    /**
     * @param array $arguments
     * @param array $options
     *
     * @return object|array
     *
     * @throws TException
     */
    public function select(
        array $arguments,
        array $options = []
    );

    /**
     * @param bool $dependencies
     *
     * @return mixed
     */
    public function search($dependencies = true);

    /**
     * @return boolean
     */
    public function delete();

    /**
     * @return ExpressiveContract|boolean
     */
    public function create();

    /**
     * @param array $arguments
     *
     * @return int
     */
    public function count(array $arguments = []);

    /**
     * @return ExpressiveContract
     */
    public function last();

    /**
     * @return boolean
     */
    public function update();

    /**
     * @return boolean
     */
    public function patch();

    /**
     * @return ExpressiveContract|boolean
     */
    public function replicate();

    /**
     * @return string
     */
    public function getUniqid();

    /**
     * @param string $uniqid
     */
    public function setUniqid($uniqid);
}