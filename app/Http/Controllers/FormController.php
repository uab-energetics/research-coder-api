<?php

namespace App\Http\Controllers;

use App\Category;
use App\Form;
use App\Models\Question;
use App\Project;
use App\Rules\FormType;
use App\Services\Forms\FormService;
use App\Services\Projects\ProjectService;
use App\Services\Questions\QuestionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FormController extends Controller {

    private $formService;
    private $projectService;

    public function __construct(FormService $formService, ProjectService $projectService) {
        $this->formService = $formService;
        $this->projectService = $projectService;
    }

    public function create(Project $project, Request $request) {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'string',
            'type' => ['required', new FormType()],
        ]);

        $form = null;
        DB::beginTransaction();
            $form = $this->formService->makeForm($request->all());
            $edge = $this->projectService->addForm($project, $form);
        DB::commit();

        return $form->refresh();
    }

    public function retrieve(Form $form) {
        return $form;
    }

    public function search(Request $request) {

        $request->validate([
            'search' => 'string|nullable'
        ]);

        return $this->formService->search($request->search)->get();
    }

    public function update (Form $form, Request $request) {
        $request->validate([
            'name' => 'string|max:255',
            'description' => 'string',
        ]);

        DB::beginTransaction();
            $this->formService->updateForm($form, $request->all());
        DB::commit();

        return $form->refresh();
    }

    public function delete(Form $form) {
        DB::beginTransaction();
            $this->formService->deleteForm($form);
        DB::commit();
        return okMessage("Successfully deleted form");
    }

    public function addQuestion(Form $form, Question $question, Request $request) {
        $category = $this->formService->findCategory($form, $request->category_id);
        if ($category === false) {
            return response()->json(static::INVALID_CATEGORY, 403);
        }

        DB::beginTransaction();
            $this->formService->addQuestion($form, $question, $category);
        DB::commit();

        $form->refresh();
        return $form;
    }

    public function moveQuestion (Form $form, Question $question, Request $request) {
        $category = $this->formService->findCategory($form, $request->category_id);
        if ($category === false) {
            return response()->json(static::INVALID_CATEGORY, 403);
        }

        $res = $this->formService->moveQuestion($form, $question, $category);

        if ($res === false) {
            return response()->json(static::INVALID_QUESTION, 403);
        }

        $form->refresh();
        return $form;
    }

    public function removeQuestion(Form $form, Question $question) {
        $res = $this->formService->removeQuestion($form, $question);
        if ($res === false) {
            return response()->json([
                'status' => 'QUESTION_NOT_FOUND',
                'msg' => "The specified question wasn't found in the specified form"
            ], 404);
        }
        return okMessage("Successfully remove question from form");
    }

    public function export(Form $form) {
        $export = $this->formService->exportForm($form);
        $headers = array_shift($export);

        $filename = trim($form->name);
        $filename = preg_replace('/\s+/', "_", $filename);
        $filename .= '_'.Carbon::now()->toDateString();

        header("Access-Control-Expose-Headers: Content-Disposition");
	    return new StreamedResponse(
            getStreamWriter($headers, $export),
            200,
            csvResponseHeaders($filename)
        );
    }

    const INVALID_CATEGORY = [
        'status' => 'INVALID_CATEGORY',
        'msg' => "The specified category does not belong to the specified form"
    ];

    const INVALID_QUESTION = [
        'status' => 'INVALID_QUESTION',
        'msg' => "The specified question isn't already in the specified form."
    ];
}
