<?php


namespace App\Services\Encodings;


use App\BranchResponse;
use App\Channel;
use App\Encoding;
use App\EncodingExperimentBranch;
use App\Form;
use App\Models\Response;
use App\Services\Comments\CommentService;
use Exception;

class EncodingService {

    public function makeEncoding($form_id, $publication_id, $user_id) {
        $form = Form::find($form_id);
        if ($form === null) return false;

        $encoding = Encoding::create([
            'form_id' => $form_id,
            'publication_id' => $publication_id,
            'owner_id' => $user_id,
            'type' => $form->type,
        ]);

        $this->upsertEncodingChannel($encoding);

        return $encoding;
    }

    public function updateEncoding(Encoding $encoding, $params) {
        batchUnset($params, ['owner_id', 'publication_id', 'form_id', 'type']);
        return $encoding->update($params);
    }

    public function deleteEncoding(Encoding $encoding) {
        return $encoding->delete();
    }

    public function upsertEncodingChannel(Encoding $encoding) {
        $existing = Channel::where('name', '=', $encoding->encodeToChannelName())->first();
        if ($existing === null) {
            $existing = $this->commentService->makeChannel([
                'name' => $encoding->encodeToChannelName(),
                'display_name' => "Discussion",
                'topic' => "Conflict Resolution",
            ]);
        }
        return $existing;
    }

    function recordBranch( $encoding_id, $branch ){
        $encoding = Encoding::find($encoding_id);
        if(!$encoding || !$branch) return false;

        // get a branch DB model
        $_branch = null;
        if(isset($branch['id']))
            $_branch = EncodingExperimentBranch::find($branch['id']);
        else
            $_branch = new EncodingExperimentBranch();

        // update and save it
        $_branch->fill($branch);
        $encoding->experimentBranches()
            ->save($_branch);

        return EncodingExperimentBranch::find($_branch->id)->toArray();
    }

    function recordResponse($encoding_id, $branch_id, $params ){
        $encoding = Encoding::find($encoding_id);
        $branch = EncodingExperimentBranch::find($branch_id);
        if(!$encoding || !$branch) return false;

        // get a response DB model
        $response = null;
        if(isset($params['id'])) {
            $response = Response::find($params['id']);
            $response->update($params);
        }
        else
            $response = Response::create($params);
        // update and save
        BranchResponse::upsert([
            'branch_id' => $branch->getKey(),
            'response_id' => $response->getKey(),
        ]);
        $response->saveSelections( getOrDefault($params['selections'], []) );

        return Encoding::find($encoding_id)->toArray();
    }

    function deleteBranch($encoding_id, $branch_id){
        $branch = EncodingExperimentBranch::find($branch_id);
        $branch->responses()
            ->delete();
        $branch->delete();
        return Encoding::find($encoding_id)->toArray();
    }

    public function dispatch( $encoding_action ){
        $result = false;
        switch ($encoding_action['type']){
            case EncodingActions::RECORD_BRANCH:
                $result = $this->recordBranch(
                    $encoding_action['encoding_id'],
                    $encoding_action['branch']
                );
                break;
            case EncodingActions::RECORD_RESPONSE:
                $result = $this->recordResponse(
                    $encoding_action['encoding_id'],
                    $encoding_action['branch_id'],
                    $encoding_action['response']
                );
                break;
            case EncodingActions::DELETE_BRANCH:
                $result = $this->deleteBranch(
                    $encoding_action['encoding_id'],
                    $encoding_action['branch_id']
                );
                break;
        }
        return $result;
    }

    public function dispatchAll( $encoding_actions ){
        $results = [];
        foreach ($encoding_actions as $action){
            $results[] = $this->dispatch($action);
        }
        return $results;
    }

    /** @var CommentService  */
    protected $commentService;

    public function __construct(CommentService $commentService) {
        $this->commentService = $commentService;
    }
}