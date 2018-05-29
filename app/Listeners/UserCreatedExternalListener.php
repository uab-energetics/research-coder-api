<?php

namespace App\Listeners;

use App\Events\UserCreatedExternal;
use App\Services\Users\UserService;
use Illuminate\Support\Facades\DB;

class UserCreatedExternalListener {

    /** @var UserService  */
    protected $userService;

    public function __construct(UserService $userService) {
        $this->userService = $userService;
    }


    public function handle(UserCreatedExternal $event) {
        $params = $event->params;
        DB::beginTransaction();
            $this->userService->make($params);
        DB::commit();
        $event->message->ack();
    }
}
