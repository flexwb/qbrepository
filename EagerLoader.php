<?php

namespace Modules\Qbrepository;

use Illuminate\Http\Request;

class EagerLoader {

    protected $collection;
    public $topRes;
    public $topId;
    public $relations = [];

    public function __construct($collection, $topRes) {

        $this->collection = $collection;
        $this->topRes = $topRes;
    }

    public function get() {
//        $this->collection->sort();
        return $this->collection;
    }

//    public function load() {
//
//        $this->loadBelongsTo();
//        $this->loadOneToMany();
//        $this->loadBelongsToMany();
//
//        return $this;
//    }
//    public function loadOneToMany() {
//        $subResources = [];
//        $oneToManyRelations = \DB::table('eds_relations')->where('relation_type', 'hasMany')->where('table1', $this->topRes)->get();
//        $this->relations['onToMany'] = $oneToManyRelations->pluck('table2');
//        foreach ($oneToManyRelations as $relation) {
//            $fk = str_singular($relation->table1) . "_id";
//            $subResources[$relation->table2] = \DB::table($relation->table2)->where($fk, $this->topId)->get()->toArray();
//        }
//
//        $this->embed($subResources);
//
//        return $this;
//    }

    public function loadBelongsTo() {

//        dd($this->collection);
        $subResources = [];

        $fks = \DB::table('eds_fields')->where('table', '=', $this->topRes)->where('key_type', '=', 'fk')->get();
        $this->relations['belongsTo'] = $fks->pluck('field');
        $tempCollectionArray = $this->collection->first();
        foreach ($fks as $fk) {

            $fkIds = $this->collection->pluck($fk->field);
            $tempEmbedObj = \DB::table($fk->link_table)
                    ->whereIn($fk->link_field, $fkIds)
                    ->get();
            $subResources[$fk->field] = $tempEmbedObj;
        }

        $this->embed($subResources, 'id');
        return $this;
    }

    public function loadBelongsToMany() {
//        $subResources = [];
//        $belongsToMany = \DB::table('eds_relations')->where('relation_type', 'belongsToMany')->where('table1', $this->topRes)->get();
//        $this->relations['belongsToMany'] = $belongsToMany->pluck('table2');
//        foreach ($belongsToMany as $relation) {
//            $fk1 = str_singular($relation->table1) . "_id"; // contract_id
//            $fk2 = str_singular($relation->table2) . "_id"; // island_id
//            $join_table = $relation->table1 . "_" . $relation->table2; // contracts_islands
//
//            $subResources[$relation->table2] = \DB::table($join_table)
//                            ->select(['*', $join_table . '.id as id'])
//                            ->join($relation->table2, $join_table . "." . $fk2, '=', $relation->table2 . ".id")  // 'contacts', 'users.id', '=', 'contacts.user_id
//                            ->where($join_table . "." . $fk1, $this->topId)->get()->toArray();
//        }
//
//
//        $this->embed($subResources);
//
//
//        return $this;
    }
    
    public function loadSubRes($subRes) {
        
        $fks = \DB::table('eds_fields')
                ->where('link_table', '=', $this->topRes)
                ->where('table', '=', $subRes)
                ->where('key_type', '=', 'fk')
                ->first();
        if(empty($fks)) {
            abort(500, "invalid with clause");
        }
        
        $topResIds = $this->collection->pluck('id');
        $subResources = \DB::table($subRes)
                ->whereIn($fks->field, $topResIds)
                ->get();
        $this->embedSubRes([$subRes =>$subResources], $fks->field);
        
        return $this;
        
    }

    public function embed($subResources, $refKey) {


        if (!empty($subResources)) {

            $tempCollection = collect([]);

            foreach ($subResources as $key => $val) {

                $subResCollection = collect($val)->keyBy($refKey)->all();
             
                
                $tempCollection = $this->collection->map(function ($item) use ($key, $subResCollection) {
                    
                    $relKey = substr($key, 0, -3);
                    if(isset($subResCollection[$item->{$key}])) {
                        $item->{$relKey} = $subResCollection[$item->{$key}] ?: 0;
                    }
                    
                    return $item;
                });
            }
            $this->collection = $tempCollection;
        }


        return $this;
    }
    
    public function embedSubRes($subResources, $refKey) {



        foreach ($subResources as $subResName => $subResCollection) {

            $tempCollection = $this->collection->map(function ($item) use ($subResName, $subResCollection, $refKey) {

                $item->{$subResName} = array_values((array) $subResCollection->where($refKey, '=', $item->id)->all());
               
                return $item;
            });
        }
        $this->collection = $tempCollection;


        return $this;
    }

}
