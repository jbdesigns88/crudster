<?php
namespace Jbuapim\Crudster;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;
  class Crudster{

    protected $model = null;
    protected $query_results = null;
    protected $vulnerable = ['password'];
    protected $data = [];
    protected $collection = false;
    protected $relationships = [];
    protected $success = false;
    protected $errors = [];
    protected $baseResource = null; // add resource property to dynamically serve up resources

    public function __construct($relationships = [], $data = [])
    {
        $this->relationships = $relationships;
        $this->setData($data);
    }

    public static function event(){
        $results = get_class_methods(new Crudster([])); 
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
        if ($model !== null) {
            $this->model = $model;
        }

        // TODO might wanna throw error here

        return $this;
    }

    public function create()
    {
        // TODO change to just look at array;
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
            $this->setError("issue updating resource. Please check logs.");
            Log::error($th);
        }

        return $this;

    }

    /**
     * @param Model $model
     * @param bool $detach
     * @return Model
     */
    protected function syncRelationships(Model $model, bool $detach = false)
    {
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
            $this->setError("Error creating resource. Please check logs.");
            Log::error($th);
        }
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * TODO add any processes and validation to incoming data.
     *
     * @param array $data
     * @return void
     */
    protected function setData(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * @param int $id
     * @param bool $withRelationship
     * @return $this
     */
    public function update(int $id, bool $withRelationship = false): self
    {
        try {
            $results = $this->getByColumn($id);
            if ($results) {
                $results->update($this->getdata());
            }
            $this->query_results = $results;
            if ($withRelationship) {
                $this->syncRelationships($results);
            }
        } catch (Throwable $th) {
            $this->setError("issue updating resource. Please check logs.");
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

    public function get($id = -1)
    {
        $found = $this->getByColumn($id);
        if (!isset($found)) {
            $this->setError("The resource with id: {$id} cannot be found. ");
        }
        $this->query_results = $found;
        return $this;
    }

    public function getAll($paginate = true)
    {
        $this->collection = true;
        $this->query_results = $paginate ? $this->model::paginate() : $this->model::all();
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
        if (isset($this->query_results)) {

            $this->data = !($this->collection) ? new $this->baseResource($this->query_results) : $this->baseResource::collection($this->query_results);

        }
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