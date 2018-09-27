<?php

namespace Noogic\Builder;

class Factory
{
    public function build($class, $data = [], $quantity = 1, $builder = null)
    {
        return $this->withCallable($builder, $class, $data, $quantity)
            ?? $this->withBaseBuilder($builder, $data, $quantity)
            ?? $this->withDefault($class, $data, $quantity)
            ?? factory($class)->create($data, $quantity)
            ;
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
            return $builder::create($data, $quantity)->get();
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
