<?php

namespace Jbuapim\Crudster;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;
  abstract class Crudster{

    protected $model = null;
    protected $query_results = null;
    protected $vulnerable = ['password'];
    protected $data = [];
    protected $collection = false;
    protected $relationships = [];
    protected $success = false;
    protected $errors = [];
    protected $baseResource = null; // add resource property to dynamically serve up resources

    public function __construct(Model $model = null)
    {
      $this->attachModel($model);
    }

    public static function event(){
        date_default_timezone_set("America/New_York");
        $d = date("h:i:sa");
        $c = get_called_class();

        $results = [
            "update" => "{$c} {$d}"
        ]; 
        return $results;
    }

    /**
     * @param $resource
     * @return $this
     */
    public function attachResource($resource)
    {
        $this->baseResource = $resource;

        return $this;
    }

    /**
     * @param Model|null $model
     * @return $this
     */
    public function attachModel(Model $model = null)
    {
        if($model === null){ throw new Exception("No Model passed.");}
    
            $this->model = $model;
            $this->relationships = $model->relationsToArray();
       

        // TODO might wanna throw error here

        return $model;
    }

    public function create()
    {
        !empty($this->relationships) ? $this->createWithRelationships() : $this->createWithoutRelationships();
        return $this;
    }

    /**
     * @return $this
     */
    protected function createWithRelationships()
    {
        $data = $this->data;

        try {
            $created = $this->model::create($data);
            $this->query_results = $this->syncRelationships($created);
        } catch (Throwable $th) {
            // $this->setError("issue updating resource. Please check logs.");
            $this->setError($th->getMessage());
            Log::error($th);
        }

        return $this;

    }

    /**
     * @param Model $model
     * @param bool $detach
     * @return Model
     */
    protected function syncRelationships(bool $detach = false)
    {
        $model = $this->model;
        $data = $this->data;
        foreach ($this->relationships as $relationship) {
            if (isset($data[$relationship])) {
                $relationship_ids = $data[$relationship];
                !($detach) ? $model->$relationship()->sync($relationship_ids) : $model->$relationship()->detach($relationship_ids);
            }
        }

        $model->refresh();

        return $model;
    }

    /**
     * @param string $msg
     * @return void
     */
    protected function setError(string $msg = "")
    {
        if (trim($msg) !== "") {
            $this->errors[] = trim($msg);
        }
    }

    /**
     * @return void
     */
    protected function createWithoutRelationships()
    {
        try {
            $created = $this->model::create($this->getData());
            $this->query_results = $created;
        } catch (Throwable $th) {
            // $this->setError("Error creating resource. Please check logs.");
            $this->setError($th->getMessage());
            Log::error($th);
        }
    }

    /**
     * @return array
     */
    public function getData()
    {
         
        return !($this->data) ? [] : $this->data ;
    }

    /**
     * TODO add any processes and validation to incoming data.
     *
     * @param mixed $data
     * @return $this
     */
    public function setData(mixed $data = null)
    {
        
        if (!($data)){ $this->setError("The data cannot be null");return $this; }
        $this->data = $data;
        return $this;
    }
    protected function hasRelationships(){
        return !(empty($this->relationships));
    }
    /**
     * @param Illuminate\Database\Eloquent\Model $model
     * @param bool $withRelationship
     * @return $this
     */
    public function update(Model $model = null): self
    {
       if(!($this->data)){$this->setError("There is no data"); return $this;}
        try {
            $this->model = $model;
            if ($this->model) {
                $this->model->update($this->data);
                if(!($this->model->wasChanged())){
                    $model_keys = implode(", ",array_keys($this->model->toArray()));
                    $non_existiing_keys = implode(", ",array_diff(array_keys($this->data),array_keys($this->model->toArray())));
                    $this->setError("Columns could not be updated. Check logs for more info.");
                    Log::error("The following columns are not in the corresponding table {$non_existiing_keys} : Existing Columns: {$model_keys}");
                    
                }
                
                $this->data = $this->model;
            }
            
            if ($this->hasRelationships()) {
                $this->syncRelationships();
            }
        } catch (Throwable $th) {
            $this->setError($th->getMessage());
            Log::error($th);
        }

        return $this;
    }

    /**
     * @param mixed $search_value
     * @param string $column
     * @return null|Model
     */
    protected function getByColumn(mixed $search_value, string $column = "id"): ?Model
    {
        try {
            $found = $this->model::where($column, $search_value)->first();
        } catch (Throwable $th) {
            $this->setError("");
            Log::error($th);
        }

        return $found;
    }

    public function get($id)
    {
        $found = $this->getByColumn($id);
        if (!isset($found)) {
            $this->setError("The resource with id: {$id} cannot be found. ");
        }
        $this->data = $found;
        return $this;
    }

    public function getAll($paginate = true)
    {
        $this->collection = true;
        $this->data = $paginate ? $this->model::paginate() : $this->model::all();
        return $this;
    }

    // protected function withResource(){

    // }

    public function display()
    {
        if (isset($this->baseResource)) {
            $this->withResource();
        }
        return [
            'data' => $this->data,
            'errors' => $this->errors,
            'success' => empty($this->errors),
        ];
    }

    protected function withResource()
    {
         $this->data = !($this->collection) ? new $this->baseResource($this->data) : $this->baseResource::collection($this->data);
    }

    public function loadRelationships()
    {
        try {
            if (!empty($this->relationships)) {

                $this->query_results->load($this->relationships);
            }
        } catch (Throwable $th) {
            $this->setError("");
            //add error message ... undefined relationship, make sure you have included a relationship to add [current relationships: $this->relationships]
            Log::error($th->getMessage());
        }
        return $this;
    }

    public function remove($id = -1)
    {
        $resource = $this->getByColumn($id);
        if (isset($resource)) {
            $resource->delete();

        } else {
            $this->setError("no resource found with id {$id}");
        }
        return $this;
    }
  }
?>