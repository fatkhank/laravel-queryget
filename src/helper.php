<?php
if (!function_exists('qt')) {
    /**
     * Create new query tool
     *
     * @param  mixed   $model
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @return array
     */
    function qt($model, $query = null)
    {
        return new QT($model, $query);
    }
}