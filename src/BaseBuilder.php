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
    protected $belongsToUnique = [];
    protected $hasMany = [];

    public function __construct(array $data = [])
    {
        $this->entities = new Collection;
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
        
        for($i = 0; $i < $quantity; $i++) {
            $this->handleBelongsToUnique();
            $entities = $this->factory->build($this->class, $this->data);
            $this->entities = $this->entities->merge($entities);
        }
        
        $this->handleHasMany();
        
        return $this->entities->count() > 1
            ? $this->entities
            : $this->entities->first()
        ;
    }

    protected function handleBelongsTo()
    {
        foreach ($this->belongsTo as $index => $value) {
            $this->addBelongsToId($index, $value);
        }
    }

    protected function handleBelongsToUnique()
    {
        foreach ($this->belongsToUnique as $index => $value) {
            $this->addBelongsToId($index, $value, true);
        }      
    }

    protected function addBelongsToId($index, $value, $overrideExisting = false)
    {
        $class = is_int($index) ? $value : $index;
        $key = is_int($index) ? $this->getKey($class) : $value;

        if (! $overrideExisting and isset($this->data[$key])) {
            return;
        }

        $builderName = $this->getBuilderNameFromClass($class);
        $builder = class_exists($builderName) ? $builderName : null;

        $this->data[$key] = $this->factory->build($class, [], 1, $builder)->first()->id;
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

                    $items = $this->factory->build($class, [$relation => $entity->id], $collection, $builder);
                    $test = $items;
                } else {
                    foreach ($collection as $item) {
                        $item->{$relation} = $entity->id;
                        $item->save();
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
