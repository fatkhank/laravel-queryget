<?php

namespace Hamba\QueryGet;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

trait Selectable
{
    public static function getSelects($requestSelects, $opt)
    {
        //get specification
        $classObj = new static;
        $className = get_class($classObj);

        //get from queryables
        $queryableSpecs = [];
        if(method_exists($className, 'getNormalizedQueryables')){
            $queryableSpecs = $className::getNormalizedQueryables(function($type, $realName, $queryability){
                if(
                    str_contains($queryability, '|select|') ||
                    str_contains($queryability, '|all|')
                ){
                    return $realName;
                }

                return false;
            });
        }
        
        //get from selectable
        $selectableSpecs = $classObj->selectable;
        
        $finalSpecs = 
            //make specifications uniform
            collect($queryableSpecs)
            ->merge($selectableSpecs)
            ->mapWithKeys(function ($select, $key) {
                if (is_numeric($key)) {
                    return [$select=>$select];
                } else {
                    return [$key=>$select];
                }
            })
            ->toArray();

        //filter specs from option
        if (array_has($opt, 'only')) {
            $finalSpecs = $finalSpecs->only($opt['only']);
        } elseif (array_has($opt, 'except')) {
            $finalSpecs = $finalSpecs->except($opt['except']);
        }
        
        $finalSelects = ['id'];
        $withs = [];

        $requestSelects = array_wrap($requestSelects);
        $selectAll = (in_array('*', $requestSelects));
        
        //groupped properties
        $groups = [];
        if ($selectAll) {
            //add all available properties
            foreach ($finalSpecs as $key => $select) {
                //check if relation
                if (isset($classObj) && (method_exists($classObj, $select))){
                    
                } else {
                    //it is properti
                    $finalSelects[$key] = $select;
                }
            }
        }

        //clean request
        $requestSelects = array_filter(array_unique($requestSelects), function ($val) {
            return $val != '*';
        });

        
        foreach ($requestSelects as $selectString) {
            $key = str_before($selectString, '.');
            $afterKey = substr($selectString, strlen($key)+1);
            
            if (!array_has($finalSpecs, $key)) {
                //if property not selectable -> skip
                continue;
            }

            //it is model property
            $mappedName = $finalSpecs[$key];
            
            //check if relation
            if (isset($classObj)) {
                //if class exists, determined whether it is relation or attribute
                if(method_exists($classObj, $mappedName)){
                    //it is relation
                    if (!array_has($groups, $key)) {
                        $groups[$key] = [];
                    }
                    if ($afterKey === false) {
                        //if relation but has no property specified, use key as relation name
                    } else {
                        //further select specified
                        $groups[$key][] = $afterKey;
                    }
                    continue;
                }else{
                    //check if custom select function specified
                    $customSelectFunc = 'select'.studly_case($mappedName);
                    if(method_exists($classObj, $customSelectFunc)){
                        //apply custom select
                        $finalSelects[] = $classObj->$customSelectFunc();
                        continue;
                    }
                }
            }

            //if not relation, add as renamed attribute
            $finalSelects[] = $mappedName.' as '.$key;
        }

        //reduce depth
        if (!isset($opt['depth'])) {
            $opt['depth'] = 5;
        }

        //select all must have depth
        if (--$opt['depth'] <= 0) {
            if ($selectAll || $opt['depth'] < -10) {
//                goto result;
            }
        }

        //process groups/relations
        foreach ($groups as $key => $groupSelects) {
            $relationName = $finalSpecs[$key];
            
            //find in relation definition
            $relationClass = null;
            
            //if has select all property
            if (($groupSelects == '*') || (array_search('*', $groupSelects) !== false)) {
                $groupSelects = '*';
            }

            //find relationClass and include foreign keys
            if (isset($classObj)) {
                $relation = $classObj->$relationName();
                //get class name
                $relationClass = $relation->getRelated();

                //include foreign keys
                if ($relation instanceof BelongsTo) {
                    //belongsTo need foreign
                    $foreignKey = $relation->getForeignKey();
                    $finalSelects[] = $foreignKey;
                    
                    if ($relation instanceof MorphTo) {
                        $finalSelects[] = $relation->getMorphType();
                    }
                } elseif ($relation instanceof HasOneOrMany) {
                    if (is_array($groupSelects)) {
                        $groupSelects[] = $relation->getForeignKeyName();
                        
                        if ($relation instanceof MorphOneOrMany) {
                            $groupSelects[] = $relation->getMorphType();
                        }
                    } else {
                        //its select all
                    }
                } elseif ($relation instanceof BelongsToMany) {
                    Notimplemented();
                    // if (is_array($groupSelects)) {
                    //     $groupSelects[] = $relation->getQualifiedForeignPivotKeyName();
                        
                    //     if ($relation instanceof MorphOneOrMany) {
                    //         $groupSelects[] = $relation->getMorphType();
                    //     }
                    // } else {
                    //     //its select all
                    // }
                }
            }

            //check if relation class is selectable
            if (!method_exists($className, 'getSelects')) {
                throw new \Exception($className.' is not selectable');
            }
            
            //process recursive select for relation
            $select = $relationClass::getSelects($groupSelects, $opt);
            //add relation name
            $select['name'] = $relationName;
            //push
            $withs[$key] = $select;
        }

        result:
        //wrap result
        return [
            'selects' => $finalSelects,
            'withs' => $withs
        ];
    }
}
