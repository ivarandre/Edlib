<?php

namespace App\Listeners;

use App\Events\ResourceSaved;
use App\Libraries\DataObjects\ResourceDataObject;
use Vinelab\Bowler\Producer;

class ResourceEventSubscriber
{
    static $producer;

    public function subscribe($events)
    {
        $events->listen('App\Events\ResourceSaved', 'App\Listeners\ResourceEventSubscriber@onResourceSaved');
    }

    public function onArticleSaved($event)
    {
        $event = new ResourceSaved(
            new ResourceDataObject($event->article->id, $event->article->title, $event->article->wasRecentlyCreated === true ? ResourceSaved::CREATE : ResourceSaved::UPDATE, ResourceDataObject::ARTICLE),
            $event->article->getEdlibDataObject()
        );
        return $this->onResourceSaved($event);
    }

    public function onLinkSaved($event)
    {
        $event = new ResourceSaved(
            new ResourceDataObject($event->link->id, $event->link->title, $event->link->wasRecentlyCreated === true ? ResourceSaved::CREATE : ResourceSaved::UPDATE, ResourceDataObject::LINK),
            $event->link->getEdlibDataObject()
        );
        return $this->onResourceSaved($event);
    }

    public function onQuestionsetSaved($event)
    {
        $event = new ResourceSaved(
            new ResourceDataObject($event->questionset->id, $event->questionset->title, $event->questionset->wasRecentlyCreated === true ? ResourceSaved::CREATE : ResourceSaved::UPDATE, ResourceDataObject::QUESTIONSET),
            $event->questionset->getEdlibDataObject()
        );
        return $this->onResourceSaved($event);
    }

    public function onGameSaved($event)
    {
        $event = new ResourceSaved(
            new ResourceDataObject($event->game->id, $event->game->title, $event->game->wasRecentlyCreated === true ? ResourceSaved::CREATE : ResourceSaved::UPDATE, ResourceDataObject::GAME),
            $event->game->getEdlibDataObject()
        );
        return $this->onResourceSaved($event);
    }

    public function onResourceSaved(ResourceSaved $event)
    {
        if (!config("feature.no-rabbitmq")) {
            if( empty(self::$producer)){
                self::$producer = app(Producer::class);
            }
            // @todo remove when core is gone
            self::$producer->setup(config('queue.connections.rabbitmq.exchange_params.name'), config('queue.connections.rabbitmq.exchange_params.type'));
            ob_start();
            self::$producer->send(json_encode($event->resourceData), "ca.resource.saved");
            ob_get_clean();

            // new queue for edlib cleanup
            self::$producer->setup(config('queue.connections.rabbitmq.edlibResourceUpdate.name'), config('queue.connections.rabbitmq.edlibResourceUpdate.type'));
            ob_start();
            self::$producer->send(json_encode($event->edlibResourceDataObject));
            ob_get_clean();

            return true;
        }
    }
}