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
    protected $hasMany = [];

    public function __construct(array $data = [])
    {
        $this->class = $this->class ?: $this->getClassFromBuilder();
        $this->data = $data;
        $this->factory = new Factory;
    }

    public static function create(array $data = [])
    {
        $instance = new static($data);

        return $instance;
    }

    public function get($quantity = 1)
    {
        $this->handleBelongsTo();

        $this->entities = $this->factory->build($this->class, $this->data, $quantity);

        $this->handleHasMany();
        
        return $this->entities->count() > 1
            ? $this->entities
            : $this->entities->first()
        ;
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

    protected function associate(Model $model, $relation = null)
    {
        $key = $relation ?: strtolower(substr(strrchr(get_class($model), '\\'), 1)) . '_id';

        $this->data[$key] = $model->id;

        return $this;
    }

    protected function addMany($class, $related = 1, $relation = null)
    {
        $relation = $relation ?: strtolower(substr(strrchr($this->class, '\\'), 1)) . '_id';

        $this->hasMany[$relation] = [$class => $related];

        return $this;
    }

    protected function handleHasMany()
    {
        foreach ($this->hasMany as $relation => $data) {
            $collection = array_first($data);

            foreach ($this->entities as $entity) {
                if (is_int($collection)) {
                    $class = array_first(array_keys($data));
                    $builderName = $this->getBuilderNameFromClass($class);
                    $builder = class_exists($builderName) ? $builderName : null;

                    $this->factory->build($class, [$relation => $entity->id], $collection, $builder);
                } else {
                    foreach ($collection as $item) {
                        $item->{$relation} = $entity->id;
                    }
                }
            }
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
