<?php

namespace Noogic\Builder;

use Illuminate\Support\Collection;

class Factory
{
    public function build($class, $data = [], $quantity = 1, $builder = null)
    {
        $instances = $this->withCallable($builder, $class, $data, $quantity)
            ?? $this->withBaseBuilder($builder, $data, $quantity)
            ?? $this->withDefault($class, $data, $quantity)
            ?? factory($class, $quantity)->create($data)
        ;

        return is_a($instances, Collection::class) ? $instances : new Collection([$instances]);
    }


    protected function withCallable($builder, $class, $data, $quantity)
    {
        if ($builder and is_callable($builder)) {
            return $builder($class, $data, $quantity);
        }

        return null;
    }

    protected function withBaseBuilder($builder, $data, $quantity)
    {
        if($this->isBaseBuilder($builder)) {
            return $builder::create($data)->get($quantity);
        }

        return null;
    }

    protected function withDefault($class, $data, $quantity)
    {
        $defaultFactory = config('builder.factory');

        if (is_callable($defaultFactory)) {
            return $defaultFactory($class, $data, $quantity);
        }

        return null;
    }

    protected function isBaseBuilder($builder) {
        if (is_a($builder, BaseBuilder::class)) {
            return true;
        }

        $classExists = class_exists($builder);
        $isBuilder = is_subclass_of($builder, BaseBuilder::class);

        if ($classExists and $isBuilder) {
            return true;
        }

        return false;
    }
}
