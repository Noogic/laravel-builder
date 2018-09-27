<?php

namespace Noogic\Builder;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

abstract class BaseBuilder
{
    /** @var Factory */
    protected $factory;

    /** @var Collection */
    protected $entities;
    
    protected $class;
    protected $data = [];

    protected $belongsTo = [];

    public function __construct(array $data = [])
    {
        $this->factory = new Factory;
        $this->data = $data;
    }

    public static function create(array $data = [], int $quantity = 1)
    {
        $instance = new static($data);

        $instance->handleBelongsTo();

        $class = $instance->getClassFromBuilder();
        $instance->entities = $instance->factory->build($class, $instance->data, $quantity);

        return $instance;
    }

    public function get()
    {
        return $this->entities->count() > 1
            ? $this->entities
            : $this->entities->first()
            ;
    }

    public function entities()
    {
        return $this->entities;
    }

    protected function build($class, $data, $quantity = 1)
    {
        $defaultFactory = config('builder.factory');

        if (is_callable($defaultFactory)) {
            return $defaultFactory($class, $data, $quantity);
        }

        return factory($class)->create($data, $quantity);
    }

    protected function handleBelongsTo()
    {
        foreach ($this->belongsTo as $index => $value) {
            $class = is_int($index) ? $value : $index;
            $key = is_int($index) ? $this->getKey($class) : $value;

            if (isset($this->data[$key])) {
                continue;
            }

            $builderName = $this->getBuilderNameFromClass($class);
            $builder = class_exists($builderName) ? $builderName : null;

            $this->data[$key] = $this->factory->build($class, [], 1, $builder)->first()->id;
        }
    }

    protected function getKey($class)
    {
        $key = preg_replace('/(.*\b)(\w+)/', '$2', $class);
        $key = strtolower($key) . '_id';

        return $key;
    }

    protected function getClassFromBuilder()
    {
        if ($this->class) {
            return $this->class;
        }

        $namespace = config('builder.entities_namespace');
        $className = preg_replace(
            '/(.*\b)(.+)(Builder)/',
            '$2',
            get_class($this)
        );

        return $namespace . $className;
    }

    protected function getBuilderNameFromClass($class)
    {
        $namespace = config('builder.builder_namespace');
        $builderName = preg_replace(
            '/(.*\b)(.+)/',
            '$2' . 'Builder',
            $class
        );

        return $namespace . $builderName;
    }
}
