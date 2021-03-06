<?php

namespace App\Http\Controllers;

use App\Notifications\InvitedToProject;
use App\ProjectResearcher;
use App\Publication;
use App\Services\Projects\ProjectDashboardService;
use App\Services\Projects\ProjectService;
use App\User;
use Illuminate\Http\Request;
use App\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProjectController extends Controller {

    public function getDashboard(Project $project, ProjectDashboardService $dashboardService) {
        return $dashboardService->getProjectStats($project);
    }

    public function create(Request $request, ProjectService $projectService) {
        $user = $request->user();

        DB::beginTransaction();
            $project = $projectService->makeProject($request->all(), $user);
        DB::commit();

        return $project;
    }

    public function update(Project $project, Request $request, ProjectService $projectService) {
        DB::beginTransaction();
            $projectService->updateProject($project, $request->all());
        DB::commit();

        return $project->refresh();
    }

    public function retrieve(Project $project) {
        return $project;
    }

    public function search(Request $request, ProjectService $projectService) {
        $validator = simpleSearchValidator($request->all());
        if ($validator->fails()) return invalidParamMessage($validator);
        return $projectService->search($request->search)->get();
    }

    public function delete(Project $project, ProjectService $projectService) {
        DB::beginTransaction();
            $projectService->deleteProject($project);
        DB::commit();
        return okMessage("Successfully deleted project");
    }

    public function retrieveForms(Project $project, ProjectService $projectService) {
        return $projectService->getForms($project);
    }

    public function retrievePublications(Project $project, Request $request, ProjectService $projectService) {
        return search(
            $project->publications(),
            request('search'),
            Publication::searchable
        )->paginate(request('page_size', 500));
    }

    public function addPublication(Project $project, Publication $publication, ProjectService $projectService) {
        $projectService->addPublication($project, $publication);
        return okMessage("Successfully added publication to project");
    }

    public function removePublication(Project $project, Publication $publication, ProjectService $projectService) {
        $res = $projectService->removePublication($project, $publication);
        if ($res === false) {
            return response()->json(static::PUBLICATION_NOT_FOUND, 404);
        }
        return okMessage("Successfully removed the publication");
    }

    public function addResearcher(Project $project, Request $request, ProjectService $projectService){
        $request->validate(['user_id' => 'exists:users,id']);
        $user = User::find($request->user_id);

        // TODO - introduce access levels
        $res = $projectService->addResearcher($project->id, $request->user_id);
        if(!$res){
            return response()->json([
                'msg' => 'That user is already in this project!'
            ], 409);
        }

        $user->notify(new InvitedToProject($project->id, $request->notification_payload));
        return response()->json([
            'msg' => "User invited to collaborate!"
        ], 200);
    }

    public function removeResearcher(Project $project, User $user) {
        $this->service->removeResearcher($project, $user);
        return okMessage("Successfully removed researcher");
    }

    public function addEncoder(Project $project, Request $request) {
        $request->validate(['user_id' => 'exists:users,id']);
        $user = User::find($request->user_id);

        $res = $this->service->addEncoder($project, $user);
        if(!$res){
            return okMessage("That user is already in this project!", 409);
        }

        $user->notify(new InvitedToProject($project->id, $request->notification_payload));
        return okMessage("User added to project");
    }

    public function removeEncoder(Project $project, User $user) {
        DB::beginTransaction();
            $this->service->removeEncoder($project, $user);
        DB::commit();
        return okMessage("Successfully removed encoder");
    }

    public function searchResearchers(Project $project, Request $request){
        $request->validate(['search' => 'string|nullable']);
        return $this->service->searchResearchers($project, $request->search);
    }

    public function searchEncoders(Project $project, Request $request) {
        $request->validate(['search' => 'string|nullable']);
        return $this->service->searchEncoders($project, $request->search);
    }

    /** @var ProjectService  */
    protected $service;

    public function __construct(ProjectService $projectService) {
        $this->service = $projectService;
    }

    const PUBLICATION_NOT_FOUND = [
        'status' => 'RESOURCE_NOT_FOUND',
        'msg' => 'The specified project does not have the specified publication'
    ];
}
