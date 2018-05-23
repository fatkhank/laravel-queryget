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
    private $selections = [];

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
     * Add selection to query (accumulated).
     *
     * @param mixed $selections array of selection alias; or selection aliases separated with comma;
     * @param array $opt accept:only,except
     * @return void
     */
    public function select($selections = null, $opt = null)
    {
        //reset option
        if (!$opt) {
            $opt = [];
        }

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

        if(!$selections){
            //nothing to select
            return $this;
        }

        $selections = QG::normalizeList($selections);

        //set maximum recursive depth
        $maxDepth = 1;
        foreach ($selections as $selection) {
            $dotCount = substr_count($selection, '.');
            $maxDepth = max($maxDepth, $dotCount + 1);
        }
        $opt['depth'] = $maxDepth;

        //merge with old selections
        $this->selections = array_merge($this->selections, $selections);

        $this->applySelections($opt);

        //for chaining
        return $this;
    }

    /**
     * Unselect some accumulated selection
     *
     * @param mixed $patterns array of pattern to filter selections
     * @return void
     */
    public function unselect($patterns = null){
        $patterns = array_wrap($patterns);

        //unselect
        $opt = [
            'unselect' => true,
        ];
        $matchSelections = array_filter($this->selections, function($selection) use ($patterns){
            foreach ($patterns as $pattern) {
                if(str_is($pattern, $selection)){
                    return true;
                }
            }
            return false;
        });
        $selectionTree = QG::inflate(array_unique($matchSelections));
        $this->recursiveSelects($this->query, $selectionTree, $opt);

        //remove selection that match patterns
        $this->selections = array_diff($this->selections, $matchSelections);
        $this->applySelections();

        return $this;
    }

    /**
     * Apply selections to query (automatically called when using select)
     *
     * @return void
     */
    public function applySelections($opt = []){
        $selectionTree = QG::inflate(array_unique($this->selections));
        $this->recursiveSelects($this->query, $selectionTree, $opt);
    }

    /**
     * Perform selections recursively
     *
     * @param [type] $query
     * @param [type] $selectionTree
     * @param [type] $opt
     * @return void
     */
    private function recursiveSelects($query, $selectionTree, $opt){
        if(!array_key_exists('depth', $opt)){
            $opt['depth'] = 1;
        }
        if($opt['depth'] < 0){ return; }

        //parse context
        $modelName = array_get($opt, 'model');
        if(!$modelName){
            $modelName = get_class($query->getModel());
        }

        $classObj = self::getInstance($modelName);

        if(array_get($opt, 'skip_table', false)){
            $tblName = null;
        }else{
            $tblName = $classObj->getTable();
        }


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
            $config = array_get($mapping, $alias);
            //skip if not selectable
            if(!$config){continue;}

            $key = $config['key'];

            //check weather key is relation or not
            if(!method_exists($classObj, $key)){
                //check if custom select function specified
                $customSelectFunc = 'select'.studly_case($key);
                if(method_exists($classObj, $customSelectFunc)){
                    //apply custom select
                    $customSelect = $classObj->$customSelectFunc($tblName, $alias, $this);
                    if($customSelect){
                        $selectedAttributes = array_merge($selectedAttributes, array_wrap($customSelect));
                    }
                }else{
                    //custom select function not found, assume attribute
                    if($tblName){
                        $qualifiedKey = $tblName.'.'.$key;
                    }else{
                        $qualifiedKey = $key;
                    }
                    $selectedAttributes[] = $qualifiedKey.' as '.$alias;
                }
            }else{//it is relation
                if($children == null){
                    //if relation attribute not specified, use select all
                    $selectedRelationSelections = '*';
                }else{
                    $selectedRelationSelections = $children;
                }

                //find model used as relation model (if specified)
                $selectedRelationModel = array_get($config, 'model');

                //add to selection
                array_set($selectedRelations, $key, [
                    'model' => $selectedRelationModel,
                    'selection' => $selectedRelationSelections
                ]);
            }
        }

        //reference
        $qg = $this;

        //process relations
        $withFunctions = [];
        foreach ($selectedRelations as $relationName => $relationConfig) {
            $relationSelections = $relationConfig['selection'];

            //normalize select all
            if (($relationSelections == '*') || (array_key_exists('*', $relationSelections))) {
                $relationSelections = ['*' => null];
            }

            //get relation context
            $relation = $classObj->$relationName();
            $relationClass = $relationConfig['model'];

            //include foreign keys
            $additionalRelationAttrs = [];
            if ($relation instanceof BelongsTo) {
                //belongsTo need foreign
                $selectedAttributes[] = $relation->getQualifiedForeignKey();

                if ($relation instanceof MorphTo) {
                    $selectedAttributes[] = $relation->getMorphType();
                }else{
                    $additionalRelationAttrs[$relation->getOwnerKey()] = null;
                }
            } elseif ($relation instanceof HasOneOrMany) {
                $selectedAttributes[] = $relation->getQualifiedParentKeyName();
                $additionalRelationAttrs[$relation->getForeignKeyName()] = null;

                if ($relation instanceof MorphOneOrMany) {
                    $additionalRelationAttrs[$relation->getMorphType()] = null;
                }
            } elseif ($relation instanceof BelongsToMany) {
                throw new \Exception('BelongsToMany relations not supported');
            }

            //merge additional relation selection
            $relationSelections = array_merge($relationSelections, $additionalRelationAttrs);

            //get option for relation
            $relationOpt = ['depth' => $opt['depth'] - 1];
            if(array_key_exists('only', $opt)){
                $originalOnly = array_get($opt['only'], $relationName);
                $mergedOnly = array_merge($originalOnly, $additionalRelationAttrs);
                $relationOpt['only'] = $mergedOnly;
            }else if(array_key_exists('except', $opt)){
                $originalExcept = array_get($opt['except'], $relationName);
                $mergedExcept = array_merge($originalExcept, $additionalRelationAttrs);
                $relationOpt['except'] = $mergedExcept;
            }

            if($relation instanceof MorphTo){
                $relationOpt['model'] = $relationClass;
                $relationOpt['skip_table'] = true;
            }

            //generate function for lazy load query
            $withFunctions[$relationName] = function($withQuery) use ($qg, $relationSelections, $relationOpt){
                $qg->recursiveSelects($withQuery, $relationSelections, $relationOpt);
            };
        }

        $unselect = array_get($opt, 'unselect', false);

        //apply selection
        if(!$unselect){
            $query->select($selectedAttributes);
        }

        //apply selection
        if(!empty($withFunctions)){
            if($unselect){
                $withouts = array_keys($withFunctions);
                $query->without($withouts);
            }else{
                $query->with($withFunctions);
            }
        }
    }
}
