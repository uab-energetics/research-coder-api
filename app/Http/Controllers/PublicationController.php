<?php

namespace App\Http\Controllers;

use App\Publication;
use App\Services\Publications\PublicationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PublicationController extends Controller {

    public function create(Request $request) {
        $params = $request->all();
        $validator = $this->createValidator($params);
        if ($validator->fails()) return invalidParamMessage($validator);

        DB::beginTransaction();
            $publication = $this->publicationService->makePublication($params);
        DB::commit();

        return $publication;
    }

    public function retrieve(Publication $publication) {
        return $publication;
    }

    public function search(Request $request) {
        $validator = $this->searchValidator($request->all());
        if ($validator->fails()) return invalidParamMessage($validator);
        return $this->publicationService->search($request->search);
    }

    public function update(Publication $publication, Request $request) {
        $params = $request->all();
        $validator = $this->updateValidator($params);
        if ($validator->fails()) return invalidParamMessage($validator);
        DB::beginTransaction();
            $this->publicationService->updatePublication($publication, $params);
        DB::commit();

        return $publication->refresh();
    }

    public function delete(Publication $publication) {
        DB::beginTransaction();
            $this->publicationService->deletePublication($publication);
        DB::commit();
        return okMessage("Successfully deleted publication");
    }

    protected function createValidator($data) {
        return Validator::make($data, [
            'name' => 'string|required',
            'embedding_url' => 'url|required',
        ]);
    }

    protected function updateValidator($data) {
        return Validator::make($data, [
            'name' => 'string',
            'embedding_url' => 'url',
        ]);
    }

    protected function searchValidator($data) {
        return Validator::make($data, [
            'search' => 'string|required'
        ]);
    }

    /** @var PublicationService  */
    protected $publicationService;

    public function __construct(PublicationService $publicationService) {
        $this->publicationService = $publicationService;
    }
}
