<?php

namespace Modules\Qbrepository;

use Modules\Base\Repository\BaseRepository;
use Illuminate\Http\Request;

class QueryBuilderRepository {

    protected $query;
    protected $table;
    protected $request;
    protected $with = [];
    

    public function __construct(Request $request) {

        $this->rules = [];
        $this->request = $request;
    }

    public function index($request, $table) {

        $table = strtolower(str_plural(str_singular($table))); //make sure table name is singular and plural
        $this->table = $table;
        
        $q = \DB::table($table);
        $q = $this->applyAcl($q);
        $topCollection = $q->get();

        $loader = new EagerLoader($topCollection, $table);
        $collectionWithSubRes = $loader
                ->loadBelongsTo()
                ->get();
        
        $collectionWithSubRes = $this->embedWith($collectionWithSubRes, $table);

        return $collectionWithSubRes;
    }
    
    protected function applyAcl($q) {
        if(class_exists('\Modules\Acl\AclService')) {
            $acl = new \Modules\Acl\AclService();
            $q = $acl->forRes($this->table)->applyTo($q);
        } 
        
        return $q;
    }

    public function getFields($table) {
        $fields = \DB::table('eds_fields')->where('table', '=', $table)->get();
        return $fields;
    }

    public function getSelects($fields, $table) {
        $selects = [];
        $selects[] = $table . ".id as id";
        foreach ($fields as $field) {
            if ($field->key_type != "fk") {
                $selects[] = $table . "." . $field->field . " as " . $field->field;
            } else {
                $selects[] = $field->link_table . "." . $field->link_ui_label_field . " as " . $field->field;
            }
        }

        return $selects;
    }

    public function get($topRes, $topId) {

        $table = strtolower(str_plural(str_singular($topRes)));
        $this->table = $table;
        
        $q = \DB::table($table);
        $q = $this->applyAcl($q);
        $q = $q->where($table . '.id', '=', $topId);
        
        $topCollection = $q->get();
        if($topCollection->isEmpty()) {
            return [];
        }

        $loader = new LazyLoader($topCollection, $table, $topId);
        $collectionWithSubRes = $loader->load()->get();
        $relations = $loader->relations;

        return ['data' => $collectionWithSubRes, 'relations' => $relations];
    }
    
    public function with($with) {
        $this->with = array_merge($this->with, $with);
        return $this;
        
    }
    
    protected function embedWith($collection, $topRes) {
        if(empty($this->with)) {
            return $collection;
        }
        
        $loader = new EagerLoader($collection, $topRes);
        foreach($this->with as $subRes) {
            $loader->loadSubRes($subRes);
        }
        $collection = $loader->get();
        return $collection;
        
    }

    public function store($data, $table) {

        $tableName = strtolower(str_plural(str_singular($table)));

        $data = $this->encryptPasswordField($data);
        $data['created_at'] = \Carbon\Carbon::now()->toDateTimeString();
        

        $newInsertId = \DB::table($tableName)->insertGetId($data);

        $newObject = \DB::table($tableName)->find($newInsertId);

        return $newObject;
    }

    public function encryptPasswordField($data) {
        if(isset($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        }

        return $data;
    }

    public function remove($table, $topId) {

        $tableName = strtolower(str_plural(str_singular($table)));

        $deleteStatus = \DB::table($tableName)->where('id', '=', $topId)->delete();

        return $deleteStatus;
    }

    public function update($table, $topId, $request) {

        $tableName = strtolower(str_plural(str_singular($table)));
        $data = $request->all();
        $data = $this->encryptPasswordField($data);
        $data['updated_at'] = \Carbon\Carbon::now()->toDateTimeString();
        $data = $this->removeRelationData($table, $data);

        $updateStatus = \DB::table($tableName)->where('id', '=', $topId)->update($data);

        return $updateStatus;
    }

    public function getBelongsToRelations($table) {

        return \DB::table('eds_fields')->where('table', '=', $table)->where('key_type', '=', 'fk')->get();

    }

    public function guessRelationNames($table) {
        return $relations = $this->getBelongsToRelations($table)->pluck('field')->map(function($item){
            return substr($item, 0, -3);
        });
    }

    public function removeRelationData($table, $data) {
        $relations = $this->guessRelationNames($table)->toArray();
        $data = array_except($data, $relations);
        return $data;

    }

}
