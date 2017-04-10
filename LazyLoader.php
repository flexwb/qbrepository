<?php

namespace Modules\Qbrepository;

use Illuminate\Http\Request;

class LazyLoader {

    protected $collection;
    public $topRes;
    public $topId;
    public $relations = [];

    public function __construct($collection, $topRes, $topId) {

        $this->collection = $collection;
        $this->topRes = $topRes;
        $this->topId = $topId;
    }

    public function get() {
        $this->collection->sort();
        return $this->collection;
    }

    public function load() {

        $this->loadBelongsTo();
        $this->loadHasMany();
//        $this->loadBelongsToMany();

        return $this;
        
        
    }

    public function loadHasMany() {
        $subResources = [];
        $oneToManyRelations = \DB::table('eds_fields')->where('link_table', $this->topRes)->get();
        $this->relations['oneToMany'] = $oneToManyRelations->pluck( 'field', 'table');
        foreach($oneToManyRelations as $relation){
            $relationTable = $relation->table;
            $fkQuery = \DB::table($relation->table)->where($relation->field, $this->topId)->get();
//            dd($fkQuery);
            $eagerLoader = new EagerLoader($fkQuery, $relation->table);
            $collectionWithSubRes = $eagerLoader->loadBelongsTo()->get();
            $relationName = substr($relation->link_field, 0,-3);
            $subResources[$relation->table] = $collectionWithSubRes;
        }
        
        $this->embed($subResources);

        return $this;
    }

    public function loadBelongsTo() {
        $subResources = [];

        $fks = \DB::table('eds_fields')->where('table','=', $this->topRes)->where('key_type', '=', 'fk')->get();
        $this->relations['belongsTo'] = $fks->pluck('field');
        $tempCollectionArray = $this->collection->first();;
        foreach($fks as $fk) {
            $tempEmbedObj = \DB::table($fk->link_table)
                            ->where($fk->link_field, $tempCollectionArray->{$fk->field})
                            ->first();
            $subResources[$fk->field] = $tempEmbedObj;
        }
        $this->embed($subResources);
        return $this;


    }

    public function loadBelongsToMany() {
        $subResources = [];
        $belongsToMany = \DB::table('eds_relations')->where('relation_type', 'belongsToMany')->where('table1', $this->topRes)->get();
        $this->relations['belongsToMany'] = $belongsToMany->pluck('table2');
        foreach($belongsToMany as $relation){
            $fk1 = str_singular($relation->table1)."_id"; // contract_id
            $fk2 = str_singular($relation->table2)."_id"; // island_id
            $join_table = $relation->table1."_".$relation->table2; // contracts_islands
        
            $subResources[$join_table] =
                \DB::table($join_table)
                ->select(['*', $join_table.'.id as id'])
                ->join($relation->table2, $join_table.".".$fk2, '=', $relation->table2.".id" )  // 'contacts', 'users.id', '=', 'contacts.user_id
                ->where($join_table.".".$fk1, $this->topId)->get()->toArray();
        }

        
        $this->embed($subResources);
        

        return $this;
    }

    

    public function embed($array) {
        
        if(!empty($array)) {
            
            $tempCollection = collect([]);

            foreach($array as $key => $val) {

                $tempCollection = $this->collection->map(function ($item) use ($key, $val){
                    $item->{$key} = $val;
                    return $item;
                    
                });

            } 

            $this->collection = $tempCollection;
        }
        return $this;

    }

}