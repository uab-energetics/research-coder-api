<?php

namespace App\Http\Controllers;

use App\Form;
use App\Project;
use App\Publication;
use App\Services\ProjectForms\ProjectFormService;
use App\Services\Publications\PublicationService;
use App\Services\Users\UserService;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectFormController extends Controller {

    public function getSettings(Project $project, Form $form) {
        return $this->service->getSettings($project, $form);
    }

    public function updateSettings(Project $project, Form $form, Request $request) {
        $request->validate([
            'task_target_encoder' => 'integer|min:0',
            'task_target_publication' => 'integer|min:0',
            'auto_enroll' => 'boolean',
        ]);
        return $this->service->updateSettings($project, $form, $request->all());
    }

    public function searchPublications(Project $project, Form $form, Request $request) {
        $request->validate(['search' => 'string|nullable']);
        return paginate($this->service->retrievePublications($project, $form, $request->search));
    }

    public function searchEncoders(Project $project, Form $form, Request $request) {
        $request->validate(['search' => 'string|nullable']);
        return $this->service->retrieveEncoders($project, $form, $request->search)->get();
    }

    public function addPublication(Project $project, Form $form, Publication $publication, Request $request) {
        $request->validate(['priority' => 'nullable|integer']);
        return $this->service->addPublication($project, $form, $publication, $request->priority);
    }

    public function addPublications(Project $project, Form $form, Request $request, PublicationService $pubService) {
        $request->validate([
            'publications.*' => 'exists:publications,id',
            'priority' => 'nullable|integer'
        ]);
        $publications = collect($request->publications);
        $publications = $publications->map(function($pubID) use ($pubService) {
            return $pubService->getPublication($pubID);
        });
        return $this->service->addPublications($project, $form, $publications, $request->priority);
    }

    public function inheritProjectPublications(Project $project, Form $form) {
        $this->service->inheritProjectPublications($project, $form);
        return okMessage("Successfully inherited project publications");
    }

    public function inheritProjectEncoders(Project $project, Form $form) {
        $this->service->inheritProjectEncoders($project, $form);
        return okMessage("Successfully inherited project encoders");
    }

    public function removePublication(Project $project, Form $form, Publication $publication) {
        $this->service->removePublication($project, $form, $publication);
        return okMessage("Successfully removed publication");
    }

    public function updateRepo(Project $project, Form $form, Request $request) {
        $request->validate([
            'repo_uuid' => 'string|required'
        ]);
        DB::beginTransaction();
        $this->service->updateRepo($project, $form, $request->repo_uuid);
        DB::commit();
        return okMessage("Successfully updated repository");
    }

    public function removeCurrentRepo(Project $project, Form $form) {
        DB::beginTransaction();
        $this->service->removeCurrentRepo($project, $form);
        DB::commit();
        return okMessage("Successfully removed current repository");
    }

    public function addEncoder(Project $project, Form $form, User $user) {
        return $this->service->addEncoder($project, $form, $user);
    }

    public function addEncoders(Project $project, Form $form, Request $request, UserService $userService) {
        $request->validate(['users.*' => 'exists:users,id']);
        $users = collect($request->encoders);
        $users = $users->map(function($userID) use ($userService) {
            return $userService->retrieve($userID);
        });
        $this->service->addEncoders($project, $form, $users);
        return okMessage("Successfully added encoders");
    }

    public function removeEncoder(Project $project, Form $form, User $user) {
        $this->service->removeEncoder($project, $form, $user);
        return okMessage("Successfully removed encoder");
    }

    public function requestMyTasks(Project $project, Form $form, Request $request) {
        $user = $request->user();
        return $this->requestTasks($project, $form, $user, $request);
    }

    public function requestTasks(Project $project, Form $form, User $encoder, Request $request) {
        $request->validate(['count' => 'nullable|integer']);
        return $this->service->requestTasks($project, $form, $encoder, $request->count);
    }


    /** @var ProjectFormService  */
    protected $service;

    public function __construct(ProjectFormService $service) {
        $this->service = $service;
    }
}
