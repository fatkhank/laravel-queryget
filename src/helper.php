<?php
if (!function_exists('qg')) {
    /**
     * Create new query tool
     *
     * @param  mixed   $model
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @return array
     */
    function qg($model, $query = null)
    {
        return new QG($model, $query);
    }
}