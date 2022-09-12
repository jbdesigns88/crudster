<?php

namespace Jbuapim\Crudster;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Controller as BaseController;
use Jbuapim\Crudster\Crudster;

class CrudsterController extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
    public function getRelationshipId($relationships = []){
		$id = "";
		$keys = empty($relationships[0]) ?  null : array_keys($relationships[0]) ;
		if($keys !== null)
		{

			foreach($keys as $key){
				if ( strpos($key,"_id") ) {
					$id = $key;
					break;
				}
			}
		}
		return $id;
	}
	public function relationshipToInclude(Request $request){
		// return "data.relationships.{$column}";
		return $request->has("includes") ? explode(",",trim($request->query("includes"))) : [];
	}

	//edit to handle empty array gracefully.
	public function combineDataWithRelationShip(Request $request,$included_relationships = []){
		$data =  $request->input('data');
		if(!empty($included_relationships))
		{
			foreach($included_relationships as $included_relationship){
				if($request->has("data.relationships.{$included_relationship}")){
					$included_relationship_data = $request->input("data.relationships.{$included_relationship}.data"); // an array which contain ids
					$included_relationship_id = $this->getRelationshipId($included_relationship_data); // get id name of relationship.
					$relationship_ids = array_column($included_relationship_data ,$included_relationship_id );
					$data[$included_relationship] = $relationship_ids;
				}
			}

		}
		return $data;
	}

    public function create($relationships,Mixed $data, $resource, Model $model){
        $crudService = new Crudster($relationships,$data);
        $response = $crudService->attachModel($model)->attachResource($resource)->create()->loadRelationships()->display();
        return $response;
    }

    public function get($relationships,$resource, Model $model){
        $crudService = new Crudster($relationships);
        $response = $crudService->attachModel($model)->attachResource($resource)->get($model->id)->loadRelationships()->display();
        return $response;
    }

    public function getAll($relationships,$resource, Model $model){
        $crudService = new Crudster($relationships);
        $response = $crudService->attachModel($model)->attachResource($resource)->getAll()->loadRelationships()->display();
        return $response;
    }
    public function update($relationships,Mixed $data, $resource, Model $model){
        $crudService = new Crudster($relationships,$data);
        $response = $crudService->attachModel($model)->attachResource($resource)->update($model->id)->loadRelationships()->display();
        return $response;
    }

    public function destroy($resource, Model $model){
        $crudService = new Crudster();
        $response = $crudService->attachModel($model)->attachResource($resource)->remove($model->id)->display();
        return $response;
    }

}
