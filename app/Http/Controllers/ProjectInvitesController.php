<?php

namespace App\Http\Controllers;

use App\Mail\InvitedToProject;
use App\Project;
use App\ProjectEncoderInviteToken;
use App\ProjectResearcherInviteToken;
use App\Services\Projects\ProjectService;
use App\User;
use Auth;
use Illuminate\Http\Request;
use Mail;

class ProjectInvitesController extends Controller {

    function redeemResearcherInviteToken(Request $request, ProjectService $projectService) {
        $request->validate([
            'token' => 'required'
        ]);

        // get the token
        $token = $request->get('token');

        // look for the token in database
        $invite = ProjectResearcherInviteToken::getToken($token);
        if (!$invite) abort(401);

        // create the membership records in database
        $project = Project::find($invite->project_id);
        $projectService->addResearcher($project->getKey(), Auth::user()->getKey());


        return response()->json([
            'project' => $project,
            'user' => User::find($invite->creator_id)
        ]);
    }

    function redeemEncoderInviteToken(Request $request, ProjectService $projectService) {
        $request->validate([
            'token' => 'required'
        ]);

        // get the token
        $token = $request->get('token');

        // look for the token in database
        $invite = ProjectEncoderInviteToken::getToken($token);
        if (!$invite) return okMessage("Token not found", 401);

        // create the membership records in database
        $project = Project::find($invite->project_id);
        $projectService->addEncoder($project, Auth::user());


        return response()->json([
            'project' => $project,
            'user' => User::find($invite->creator_id)
        ]);
    }

    function sendResearcherInviteToken(Request $request) {
        $request->validate([
            'project_id' => 'required|int|exists:projects,id',
            'to_email' => 'required|email',
            'callback_url' => 'required|url'
        ]);

        $invitee = Auth::user();
        $project = Project::find($request->input('project_id'));
        $callback_url = $request->input('callback_url');
        $callback_params = $request->input('callback_params', []);


        // Generating the invitation
        $token = ProjectResearcherInviteToken::generateInviteToken(
            $invitee->getKey(),
            $project->getKey()
        );


        // Sending the email
        $token_param = ['token' => $token];
        $query_params = http_build_query(array_merge($callback_params, $token_param));

        $target_email = request()->get('to_email');
        $invite_email = new InvitedToProject([
            'project' => $project->name,
            'user' => $invitee->name,
            'callback' => $callback_url . "?" . $query_params
        ]);
        Mail::to($target_email)->send($invite_email);


        return response()->json([
            'msg' => "Invite Sent!",
            'token' => $token
        ]);
    }

    function sendEncoderInviteToken(Request $request) {
        $request->validate([
            'project_id' => 'required|int|exists:projects,id',
            'to_email' => 'required|email',
            'callback_url' => 'required|url'
        ]);

        $invitee = Auth::user();
        $project = Project::find($request->input('project_id'));
        $callback_url = $request->input('callback_url');
        $callback_params = $request->input('callback_params', []);


        // Generating the invitation
        $token = ProjectEncoderInviteToken::generateInviteToken(
            $invitee->getKey(),
            $project->getKey()
        );


        // Sending the email
        $token_param = ['token' => $token];
        $query_params = http_build_query(array_merge($callback_params, $token_param));

        $target_email = request()->get('to_email');
        $invite_email = new InvitedToProject([
            'project' => $project->name,
            'user' => $invitee->name,
            'callback' => $callback_url . "?" . $query_params
        ]);
        Mail::to($target_email)->send($invite_email);


        return response()->json([
            'msg' => "Invite Sent!",
            'token' => $token
        ]);
    }

    function validateResearcherInvitation(Request $request) {
        $request->validate([
            'token' => 'required'
        ]);

        $token = $request->input('token');

        $invite = ProjectResearcherInviteToken::getToken($token);
        if(!$invite) abort(404);

        return response()->json([
            'project' => Project::find($invite->project_id),
            'user' => User::find($invite->creator_id),
            'access_level' => $invite->access_level
        ]);
    }

    function validateEncoderInvitation(Request $request) {
        $request->validate([
            'token' => 'required'
        ]);

        $token = $request->input('token');

        $invite = ProjectEncoderInviteToken::getToken($token);
        if (!$invite) abort(404);

        return response()->json([
            'project' => Project::find($invite->project_id),
            'user' => User::find($invite->creator_id),
            'access_level' => $invite->access_level
        ]);
    }
}
