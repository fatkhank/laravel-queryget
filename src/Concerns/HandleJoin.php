<?php

namespace Hamba\QueryGet\Concerns;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

use QG;

trait HandleJoin
{
    protected $joined = [];
    
    /**
     * Join current query with required relations
     *
     * @param string $join relation name relative from current model, delimited by dot
     * @return string alias of join
     */
    public function leftJoin($join){
        $joinInfo = $this->leftJoinInfo($join);
        return $joinInfo? $joinInfo['alias'] : null;
    }

    /**
     * Join current query with required relations
     *
     * @param string $join relation name relative from current model, delimited by dot
     * @return string model name of last relation joined
     */
    public function leftJoinModel($join){
        $joinInfo = $this->leftJoinInfo($join);
        return $joinInfo? $joinInfo['class'] : null;
    }

    /**
     * Join current query with required relations
     *
     * @param string $join relation name relative from current model, delimited by dot
     * @return array information of join result, containing ['alias' => 'alias of the join', 'class' => 'name of model of last join']
     */
    public function leftJoinInfo($join){
        //prevent duplicate join
        if(array_key_exists($join, $this->joined)){
            return $this->joined[$join];
        }

        //save last join alias
        $lastJoin = null;
        $joinAlias = null;
        $sourceModel = $this->model;
        $splittedJoins = explode('.', $join);
        foreach ($splittedJoins as $splittedJoin) {
            $lastJoin = $this->doLeftJoin($sourceModel, $splittedJoin, $joinAlias);
            if(!$lastJoin){
                //fail to join
                return null;
            }
            $joinAlias = $lastJoin['alias'];
            $sourceModel = $lastJoin['class'];
        }

        return $lastJoin;
    }

    private function doLeftJoin($sourceModel, $join, $prefix){
        //prevent duplicate join
        if(array_key_exists($join, $this->joined)){
            return $this->joined[$join];
        }

        //parse context
        $classObj = self::getInstance($sourceModel);
        $relationName = $join;
        $relatedClass = $classObj;
        
        //make alias name
        if($prefix){
            $joinAlias = $prefix.'_'.$relationName.'_join';
        }else{
            $prefix = $classObj->getTable();
            $joinAlias = $relationName.'_join';
        }

        //relation not found
        if(!method_exists($classObj, $relationName)){
            $customJoinFunc = 'join'.studly_case($relationName);
            if(method_exists($classObj, $customJoinFunc)){
                $customAlias = $classObj->$customJoinFunc($this->query, $prefix, $joinAlias);
                if($customAlias){
                    $joinAlias = $customAlias;
                }
            }else{
                return null;
            }
        }else{            
            //parse relation
            $relation = $classObj->$relationName();
            $relatedClass = $relation->getRelated();
            $relatedTbl = $relatedClass->getTable();
            $foreignKey = $relation->getForeignKey();
            $ownedKey = $relation->getOwnerKey();
    
            if ($relation instanceof BelongsTo) {
                //belongsTo need foreign
                $this->query->leftJoin($relatedTbl.' as '.$joinAlias, $joinAlias.'.'.$ownedKey, '=', $prefix.'.'.$foreignKey);
            }
        }

        return ($this->joined[$join] = [
            'class' => $relatedClass,
            'alias' => $joinAlias,
        ]);
    }
}
