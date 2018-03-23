<?php

namespace Hamba\QueryGet;

trait Queryable
{
    use Filterable;
    use Selectable;
    use Sortable;

    /**
     * Get normalized queryables
     */
    public static function collectNormalizedQueryables($selectedValue = null){
        $classObj = new static;
        $className = get_class($classObj);

        //empty
        if (!property_exists($classObj, 'queryable')) {
            return collect();
        }

        // queryable format = 
        // [
        //      alias1 => 'type1:key1',
        //      alias2 => 'type2:key2'
        // ]
        // 
        $queryables = $classObj->queryable;

        //begin normalize each spec
        return collect($queryables)
            ->mapWithKeys(function($qVal, $alias) use ($selectedValue){
            //normalize value when key and type not specified
            if (is_numeric($alias)) {
                $alias = $qVal;
                $qVal = 'null:'.$alias;//null is default type, assume key is same as alias
            }
            
            //parse value
            if($selectedValue == 'plain'){
                $value = $qVal;
            }else{
                //split type and key
                $delim = strpos($qVal, ':');
                if($delim){
                    $type = substr($qVal, 0, $delim);
                    $key = substr($qVal, $delim+1);
                }else{
                    $type = $qVal;
                    //if key not specified, use aliasedKey
                    $key = $alias;
                }

                if($selectedValue == 'key'){
                    $value = $key;
                }elseif($selectedValue == 'type'){
                    $value = $type;
                }else{
                    $value = [
                        'key' => $key,
                        'type' => $type
                    ];
                }
            }

            return [$alias => $value];
        });
    }
}
