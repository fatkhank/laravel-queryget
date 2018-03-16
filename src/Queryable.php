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
            return [];
        }

        // queryable format = 
        // [
        //      aliasedKey1 => 'keyType1:unaliasedKey1',
        //      aliasedKey2 => 'keyType2:unaliasedKey2'
        // ]
        // 
        $queryables = $classObj->queryable;

        //begin normalize each spec
        return collect($queryables)->mapWithKeys(function($qVal, $aliasedKey) use ($selectedValue){
            //normalize value when unaliasedKey and type not specified
            if (is_numeric($aliasedKey)) {
                $aliasedKey = $qVal;
                $qVal = 'null:'.$aliasedKey;//null is default type, assume unaliasedKey is same as aliasedKey
            }
            
            //parse value
            if($selectedValue == 'plain'){
                $value = $qVal;
            }else{
                //split keyType and unaliasedKey
                $delim = strpos($qVal, ':');
                if($delim){
                    $keyType = substr($qVal, 0, $delim);
                    $unaliasedKey = substr($qVal, $delim+1);
                }else{
                    $keyType = $qVal;
                    //if unaliasedKey not specified, use aliasedKey
                    $unaliasedKey = $aliasedKey;
                }

                if($selectedValue == 'key'){
                    $value = $aliasedKey;
                }elseif($selectedValue == 'type'){
                    $value = $keyType;
                }else{
                    $value = [
                        'key' => $unaliasedKey,
                        'type' => $keyType
                    ];
                }
            }

            return [$aliasedKey => $value];
        });
    }
}
