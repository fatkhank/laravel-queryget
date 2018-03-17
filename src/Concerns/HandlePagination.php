<?php

namespace Hamba\QueryGet\Concerns;

use QG;

trait HandlePagination
{
    /**
     * Apply paginate to query
     *
     * @return void
     */
    public function paginate($pagesize = null, $page = null)
    {
        if(!$page){
            $page = $this->pageNumber();
        }

        if(!$pagesize){
            $pagesize = $this->pageSize();
        }

        $skip = $this->offset($page, $pagesize);

        $this->query
            ->skip($skip)
            ->take($pagesize);

        //for chaining
        return $this;
    }

    /**
     * Calculate page offset
     *
     * @return void
     */
    public function offset($page, $pageSize){
        //calculate from page
        return ($page-1) * $pageSize;
    }

    /**
     * Get current page number
     *
     * @return void
     */
    public function pageNumber(){
        $pageInput = request('page', request('pagenumber', 1));
        if(!$pageInput){
            $offset = request('offset', request("skip"), 0);
            $pageInput = floor($offset / $this->pageSize()) + 1;
        }
        return $pageInput < 1? 1 : $pageInput;
    }

    /**
     * Get rows count per page
     *
     * @return void
     */
    public function pageSize(){
        return intval(request('pagesize', request('size', request('count', 10))));
    }
}
