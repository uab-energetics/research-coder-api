<?php


namespace App\Services\ProjectForms;


use App\EncodingTask;
use App\Form;
use App\FormEncoder;
use App\FormPublication;
use App\Project;
use App\ProjectEncoder;
use App\ProjectForm;
use App\Publication;
use App\Services\Encodings\TaskService;
use App\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProjectFormService {

    public function handleFormDeleted(Form $form) {
        $projectForms = $this->getProjectFormsFromForm($form)->get();
        foreach ($projectForms as $projectForm) {
            $this->doDelete($projectForm);
        }
    }

    public function getSettings(Project $project, Form $form) {
        return $projectForm = $this->getProjectForm($project, $form);
    }

    public function updateSettings(Project $project, Form $form, $params) {
        $projectForm = $this->getProjectForm($project, $form);
        batchUnset($params, ['project_id', 'form_id']);
        $projectForm->update($params);
        return $projectForm->refresh();
    }

    public function retrievePublications(Project $project, Form $form, $term = "") {
        $projectForm = $this->getProjectForm($project, $form);
        return $this->doRetrievePublications($projectForm);
    }

    public function retrieveEncoders(Project $project, Form $form, $query = "") {
        $projectForm = $this->getProjectForm($project, $form);
        return $this->doRetrieveEncoders($projectForm);
    }

    public function getTasksByUser(ProjectForm $projectForm, User $user) {
        return $projectForm->tasks()
            ->where('encoder_id', '=', $user->getKey())
            ->get();
    }

    public function inheritProjectPublications(Project $project, Form $form) {
        $projectForm = $this->getProjectForm($project, $form);
        foreach($project->publications()->get() as $publication) {
            $this->doAddPublication($projectForm, $publication);
        }
        return true;
    }

    public function inheritProjectEncoders(Project $project, Form $form) {
        $projectForm = $this->getProjectForm($project, $form);
        foreach($project->encoders()->get() as $encoder) {
            $existing = FormEncoder::query()
                ->where('project_form_id', '=', $projectForm->getKey())
                ->where('encoder_id', '=', $encoder->getKey())
                ->first();
            if ($existing) continue; //skip encoders already in the project so they don't get more assignments
            $this->doAddEncoder($projectForm, $encoder);
        }
        return true;
    }

    /**
     * @param Project $project
     * @param Form $form
     * @param Publication[]|Collection $publications
     * @param null|integer $priority
     * @return Collection|FormPublication[]
     */
    public function addPublications(Project $project, Form $form, $publications, $priority = null) {
        $projectForm = $this->getProjectForm($project, $form);
        $result = collect();
        foreach($publications as $publication) {
            $result->push($this->doAddPublication($projectForm, $publication, $priority));
        }
        return $result;
    }

    public function addPublication(Project $project, Form $form, Publication $publication, $priority = null) {
        $projectForm = $this->getProjectForm($project, $form);
        return $this->doAddPublication($projectForm, $publication, $priority);
    }

    public function removePublication(Project $project, Form $form, Publication $publication) {
        $projectForm = $this->getProjectForm($project, $form);
        return $this->doRemovePublication($projectForm, $publication);
    }

    public function removeAllPublications(Project $project, Form $form) {
        $projectForm = $this->getProjectForm($project, $form);
        $this->doRemoveAllPublications($projectForm);
    }

    public function removePublications(Project $project, Form $form, $publications) {
        $projectForm = $this->getProjectForm($project, $form);
        $this->doRemovePublications($publications);
    }

    public function addEncoders(Project $project, Form $form, $encoders) {
        $projectForm = $this->getProjectForm($project, $form);
        foreach($encoders as $encoder) {
            $this->doAddEncoder($projectForm, $encoder);
        }
    }

    public function addEncoder(Project $project, Form $form, User $encoder) {
        $projectForm = $this->getProjectForm($project, $form);
        return $this->doAddEncoder($projectForm, $encoder);
    }

    public function removeEncoder(Project $project, Form $form, User $encoder) {
        $projectForm = $this->getProjectForm($project, $form);
        return $this->doRemoveEncoder($projectForm, $encoder);
    }

    /**
     * @param Project $project
     * @param Form $form
     * @param User $encoder
     * @param null|integer $count
     * @return Collection|EncodingTask[]
     */
    public function requestTasks(Project $project, Form $form, User $encoder, $count = null) {
        $projectForm = $this->getProjectForm($project, $form);
        if ($count === null) $count = $projectForm->task_target_encoder;
        $count = min($count, $projectForm->task_target_encoder);
        return $this->assignNextTasks($projectForm, $encoder, $count);
    }

    /**
     * @param ProjectForm $projectForm
     * @param User $encoder
     * @param integer $count
     * @param integer $target
     * @return Collection | Publication[]
     */
    public function getNextPublications(ProjectForm $projectForm, User $encoder, $count, $target) {
        $query = DB::select(self::SQL_PAPER_QUEUE,[
            'proj_form_id' => $projectForm->getKey(),
            'encoder_id' => $encoder->getKey(),
            'task_target' => $target,
            'task_limit' => intval($count),
        ]);
        return Publication::hydrate($query);
    }

    public function assignTask(ProjectForm $projectForm, Publication $publication, User $encoder) {
        $task = $this->taskService->make([
            'encoder_id' => $encoder->getKey(),
            'project_form_id' => $projectForm->getKey(),
            'publication_id' => $publication->getKey(),
            'form_id' => $projectForm->form_id,
        ]);
        return $task;
    }

    protected function doDelete(ProjectForm $projectForm) {
        $this->doRemoveAllPublications($projectForm);
        $this->doRemoveAllEncoders($projectForm);
        $this->doDeleteAllTasks($projectForm);
        $projectForm->delete();
    }

    protected function doRetrievePublications(ProjectForm $projectForm) {
        return $projectForm->formPublications();
    }

    protected function doRemoveAllPublications(ProjectForm $projectForm) {
        $publications = $this->doRetrievePublications($projectForm);
        $this->doRemovePublications($projectForm, $publications);
    }

    protected function doRemovePublications(ProjectForm $projectForm, $publications) {
        foreach ($publications as $publication) {
            $this->doRemovePublication($publication);
        }
    }

    protected function doAddPublication(ProjectForm $projectForm, Publication $publication, $priority = null) {
        $params = [
            'project_form_id' => $projectForm->getKey(),
            'publication_id' => $publication->getKey(),
        ];
        if ($priority !== null) $params['priority'] = $priority;
        return FormPublication::upsert($params);
    }

    protected function doRemovePublication(ProjectForm $projectForm, Publication $publication) {
        $existing = FormPublication::query()
            ->where('project_form_id', '=', $projectForm->getKey())
            ->where('publication_id', '=', $publication->getKey())
            ->firstOrFail();
        return $existing->delete();
    }

    protected function doRetrieveEncoders(ProjectForm $projectForm) {
        return $projectForm->encoders();
    }

    protected function doAddEncoder(ProjectForm $projectForm, User $encoder) {
        $edge = FormEncoder::upsert([
            'project_form_id' => $projectForm->getKey(),
            'encoder_id' => $encoder->getKey(),
        ]);
        $this->assignNextTasks($projectForm, $encoder, $projectForm->task_target_encoder);
        return $edge;
    }

    protected function doRemoveAllEncoders(ProjectForm $projectForm) {
        $encoders = $this->doRetrieveEncoders($projectForm)->get();
        $this->doRemoveEncoders($projectForm, $encoders);
    }

    protected function doRemoveEncoders(ProjectForm $projectForm, $encoders){
        foreach($encoders as $encoder) {
            $this->doRemoveEncoder($projectForm, $encoder);
        }
    }

    protected function doRemoveEncoder(ProjectForm $projectForm, User $encoder) {
        $existing = FormEncoder::query()
            ->where('project_form_id', '=', $projectForm->getKey())
            ->where('encoder_id', '=', $encoder->getKey())
            ->firstOrFail();
        $this->deactivateUserTasks($projectForm, $encoder);
        return $existing->delete();
    }

    protected function deactivateUserTasks(ProjectForm $projectForm, User $user) {
        $tasks = $this->getTasksByUser($projectForm, $user);
        foreach ($tasks as $task) {
            $task->update(['active' => false]);
        }
    }

    protected function doRetrieveAllTasks(ProjectForm $projectForm) {
        return $projectForm->tasks();
    }

    protected function doDeleteAllTasks(ProjectForm $projectForm) {
        $tasks = $this->doRetrieveAllTasks($projectForm)->get();
        $this->doDeleteTasks($projectForm, $tasks);
    }

    protected function doDeleteTasks(ProjectForm $projectForm, $tasks) {
        $this->taskService->deleteTasks($tasks);
    }

    protected function doDeleteTask(ProjectForm $projectForm, EncodingTask $task) {
        $this->taskService->deleteTask($task);
    }

    protected function assignNextTasks(ProjectForm $projectForm, User $encoder, $count) {
        $target = $projectForm->task_target_publication;
        $publications = $this->getNextPublications($projectForm, $encoder, $count, $target);

        $tasks = collect();
        foreach($publications as $publication) {
            $task = $this->assignTask($projectForm, $publication, $encoder);
            $tasks->push($task);
        }
        return $tasks;
    }

    /**
     * @param Project $project
     * @param Form $form
     * @return ProjectForm
     */
    protected function getProjectForm(Project $project, Form $form) {
        return ProjectForm::query()
            ->where('project_id', '=', $project->getKey())
            ->where('form_id', '=', $form->getKey())
            ->firstOrFail();
    }

    /**
     * @param Form $form
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function getProjectFormsFromForm(Form $form) {
        return ProjectForm::query()
            ->where('form_id', '=', $form->getKey());
    }

    protected function getProjectFormsFromProject(Project $project) {
        return ProjectForm::query()
            ->where('project_id', '=', $project->getKey());
    }


    /** @var Project */
    protected $project;

    /** @var TaskService  */
    protected $taskService;

    public function __construct(TaskService $taskService) {
        $this->taskService = $taskService;
    }

    const SQL_PAPER_QUEUE = "
SELECT
  publications.*,
  task_counts.task_count,
  form_pub.priority
FROM publications
  JOIN form_publication form_pub ON publications.id = form_pub.publication_id
  JOIN project_form proj_form ON form_pub.project_form_id = proj_form.id AND proj_form.id = :proj_form_id
  JOIN (SELECT
          publications.id,
          SUM(CASE WHEN tasks.id IS NULL
            THEN 0 ELSE 1 END
          ) AS task_count
        FROM publications
          LEFT JOIN encodings ON encodings.publication_id = publications.id
          LEFT JOIN encoding_tasks tasks ON encodings.id = tasks.encoding_id AND tasks.project_form_id = :proj_form_id
        GROUP BY publications.id
        HAVING NOT EXISTS(SELECT * FROM encoding_tasks et JOIN encodings e ON et.encoding_id = e.id AND et.encoder_id = :encoder_id
          WHERE e.publication_id = publications.id
          AND et.project_form_id = :proj_form_id)
       ) task_counts ON publications.id = task_counts.id

WHERE
  (priority = 0 AND task_count < :task_target)
  OR priority > 0

ORDER BY task_count DESC, priority DESC
LIMIT :task_limit
";
}