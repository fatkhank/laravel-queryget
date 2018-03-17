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

trait HandleSelection
{
    private $default_select;

    /**
     * Set default select if not specified in request
     *
     * @param array $opt accept:only, except
     * @return void
     */
	public function defaultSelect($selects){
		$this->default_select = $selects;
		return $this;//for chaining
	}

    /**
     * Select only specific selectable
     *
     * @param array $only array of selectable alias included to selection
     * @return void
     */
    public function selectOnly($only){
        return $this->select(null, [
            'only' => $only
        ]);
    }

    /**
     * Select except specific selectable
     *
     * @param array $except array of selectable alias to be excluded from selection
     * @return void
     */
    public function selectExcept($except){
        return $this->select(null, [
            'except' => $except
        ]);
    }

    /**
     * Perform selection to query.
     *
     * @param array $opt accept:only,except
     * @return void
     */
    public function select($selections = null, $opt = null)
    {
        //reset option
        if (!$opt) {
            $opt = [];
        }
        
        //set maximum recursive depth
        $opt['depth'] = 1;

        //filter selections by option
        if(array_key_exists('only', $opt)){
            $opt['only'] = QG::inflate($opt['only']);
        }else if(array_key_exists('except', $opt)){
            $opt['except'] = QG::inflate($opt['except']);
        }

        //if select not specified, select from request
        if(!$selections){
            $selections = request('props');
        }

        if(!$selections){
            //if no select requested, use default select
            if($this->default_select){
                $selections = $this->default_select;
            }else{
                //if no requested selects, default to select all without relation
                $selections = ['*'];
            }
        }

        if($selections){
            $selections = QG::normalizeList($selections);
        }else{
            return $this;
        }

        $selectionTree = QG::inflate(array_unique($selections));

        $this->recursiveSelects($this->query, $selectionTree, $opt);
        
        //for chaining
        return $this;
    }

    private function recursiveSelects($query, $selectionTree, $opt){
        //parse context
        $modelName = get_class($query->getModel());
        $classObj = self::getInstance($modelName);
        $tblName = $classObj->getTable();

        //check if class is selectable
        if (!method_exists($modelName, 'getSelectMapping')) {
            throw new \Exception($modelName.' is not selectable');
        }
        $mapping = $modelName::getSelectMapping($opt);

        //if select all, select all from mapping that is not relation
        $isSelectAll = ($selectionTree == '*') || (array_key_exists('*', $selectionTree));
        if ($isSelectAll) {
            foreach ($mapping as $alias => $key) {
                if (!method_exists($classObj, $alias)){
                    $selectionTree[$alias] = true;
                }
            }
            $selectionTree = array_except($selectionTree, ['*']);
        }

        $selectedAttributes = [];
        $selectedRelations = [];
        foreach ($selectionTree as $alias => $children) {
            $key = array_get($mapping, $alias);
            //skip if not selectable
            if(!$key){continue;}

            //check weather key is relation or not
            if(!method_exists($classObj, $key)){
                //check if custom select function specified
                $customSelectFunc = 'select'.studly_case($key);
                if(method_exists($classObj, $customSelectFunc)){
                    //apply custom select
                    $selectedAttributes[] = $classObj->$customSelectFunc($tblName, $alias, $this);
                }else{
                    //custom select function not found, assume attribute
                    $selectedAttributes[] = $key.' as '.$alias;
                }
            }else{//it is relation
                if($children == null){
                    //if relation attribute not specified, use select all
                    array_set($selectedRelations, $key, '*');
                }else{
                    array_set($selectedRelations, $key, $children);
                }
            }
        }

        //reference
        $qg = $this;

        //process relations
        $withFunctions = [];
        foreach ($selectedRelations as $relationName => $relationSelections) {
            //normalize select all
            if (($relationSelections == '*') || (array_key_exists('*', $relationSelections))) {
                $relationSelections = ['*' => null];
            }
            
            //get relation context
            $relation = $classObj->$relationName();
            $relationClass = $relation->getRelated();

            //include foreign keys
            $additionalRelationAttrs = [];
            if ($relation instanceof BelongsTo) {
                //belongsTo need foreign
                $selectedAttributes[] = $relation->getQualifiedForeignKey();
                
                if ($relation instanceof MorphTo) {
                    $selectedAttributes[] = $relation->getMorphType();
                }else{
                    $additionalRelationAttrs[$relation->getQualifiedOwnerKeyName()] = null;
                }
            } elseif ($relation instanceof HasOneOrMany) {
                $selectedAttributes[] = $relation->getQualifiedParentKeyName();
                $additionalRelationAttrs[$relation->getQualifiedForeignKeyName()] = null;
                
                if ($relation instanceof MorphOneOrMany) {
                    $additionalRelationAttrs[$relation->getMorphType()] = null;
                }
            } elseif ($relation instanceof BelongsToMany) {
                throw new \Exception('BelongsToMany relations not supported');
            }

            //merge additional relation selection
            $relationSelections = array_merge($relationSelections, $additionalRelationAttrs);
            
            //get option for relation
            $relationOpt = ['depth' => $opt['depth']--];
            if(array_key_exists('only', $opt)){
                $originalOnly = array_get($opt['only'], $relationName);
                $mergedOnly = array_merge($originalOnly, $additionalRelationAttrs);
                $relationOpt['only'] = $mergedOnly;
            }else if(array_key_exists('except', $opt)){
                $originalExcept = array_get($opt['except'], $relationName);
                $mergedExcept = array_merge($originalExcept, $additionalRelationAttrs);
                $relationOpt['except'] = $mergedExcept;
            }
            
            //generate function for lazy load query
            $withFunctions[$relationName] = function($query) use ($qg, $relationClass, $relationSelections, $relationOpt){
                $qg->recursiveSelects($query, $relationSelections, $relationOpt);
            };
        }

        //apply selection
        $query->select($selectedAttributes);

        //apply selection
        if(!empty($withFunctions)){
            $query->with($withFunctions);
        }
    }
}
