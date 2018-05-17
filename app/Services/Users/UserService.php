<?php


namespace App\Services\Users;


use App\Services\Encodings\TaskService;
use App\User;

class UserService {

    public function retrieve($user_id) {
        return User::findOrFail($user_id);
    }

    public function search($query) {
        return User::search($query)->paginate(getPaginationLimit())->toArray()['data'];
    }

    public function make($params) {
        return User::create($params);
    }

    public function update(User $user, $params) {
        $user->update($params);
        return $user->refresh();
    }

    public function getResearcherProjects(User $user) {
        return $user->researcherProjects()->get();
    }

    public function getCoderProjects(User $user) {
        return $user->researcherProjects()->get();
    }

    public function getEncodings(User $user) {
        return $user->encodings()
            ->without(['experimentBranches', 'simpleResponses'])
            ->with(['publication', 'form' => function ($query) {
                $query->without(['rootCategory', 'questions']);
            }])
            ->get();
    }

    public function getTasks(User $user, $status = null, $search = null) {
        $query = $user->tasks()
            ->with([
                'encoding' => function($query) {
                    $query->without(['experimentBranches', 'simpleResponses']);
                },
                'form' => function ($query) {
                    $query->without(['rootCategory', 'questions']);
                },
                'publication'
            ]);
        $filtered = $this->taskService->filterTasksByStatus($query, $status);
        $searched = $this->taskService->filterTasksByKeyword($query, $search);
        return $searched;
    }

    public function getFormsEncoder(User $user) {
        return $user->projectFormsEncoder()
            ->with(['form' => function ($query) {
                $query->without(['questions', 'rootCategory']);
            },
                    'project'])
            ->get();
    }

    /** @var TaskService  */
    protected $taskService;

    public function __construct(TaskService $taskService) {
        $this->taskService = $taskService;
    }
}