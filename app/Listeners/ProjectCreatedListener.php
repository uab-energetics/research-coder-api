<?php

namespace App\Listeners;

use App\Events\ProjectCreated;
use App\Services\Projects\ProjectService;
use App\Services\RabbitMQ\RabbitMQService;

class ProjectCreatedListener {

    /** @var RabbitMQService  */
    protected $rabbitmqService;
    /** @var ProjectService  */
    protected $projectService;

    public function __construct(ProjectService $projectService, RabbitMQService $rabbitMQService) {
        $this->projectService = $projectService;
        $this->rabbitmqService = $rabbitMQService;
    }

    /**
     * Handle the event.
     *
     * @param  object $event
     * @return void
     */
    public function handle(ProjectCreated $event) {
        $project = $event->project;
        $owner = $event->user;

        $this->projectService->addResearcher($project->getKey(), $owner->getKey(), true);

        // publish to rabbitmq
        $event = RabbitMQService::projectCreated($project->getKey(), $owner->uuid);
        $this->rabbitmqService->publishMessage(...$event);
    }
}
