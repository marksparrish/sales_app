<?php

namespace App\Elastic;

use Illuminate\Support\Str;

/** @package App\Elastic */
trait Finder
{
    public static function finder()
    {
        // get the current model
        $model = (new static);
        // make the builder string
        $builder = __NAMESPACE__ . '\\Builders\\' . Str::studly(Str::singular($model->getTable())) . 'Builder' ;
        if (class_exists($builder)) {
            return app($builder,['model' => $model]);
        } else {
            // generic builder if there is no associated model builder class
            $builder = 'App\\Elastic\\Builders\\Builder' ;
            return app($builder,['model' => $model]);
        }
    }
}
