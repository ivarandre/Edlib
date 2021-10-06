<?php

namespace App;

use DB;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CollaboratorContext extends Model
{
    public $incrementing = false;
    public $timestamps = false;

    public static function contextShouldUpdate($systemId, $contextId, $timestamp)
    {
        if (!config('feature.context-collaboration', false)) {
            return false;
        }

        try {
            self::where('system_id', $systemId)
                ->where('context_id', $contextId)
                ->where('timestamp', '>', Carbon::createFromTimestamp($timestamp))
                ->firstOrFail();
            $response = false;
        } catch (ModelNotFoundException $e) {
            $response = true;
        } catch (Exception $e) {
            throw $e;
        }

        return $response;
    }

    public static function deleteContext($systemId, $contextId)
    {
        if (!config('feature.context-collaboration', false)) {
            return;
        }

        self::where('system_id', $systemId)
            ->where('context_id', $contextId)
            ->delete();
    }

    public static function updateContext($systemId, $contextId, $collaborators, $resources, $timestamp)
    {
        if (!config('feature.context-collaboration', false)) {
            return;
        }

        if (!self::contextShouldUpdate($systemId, $contextId, $timestamp)) {
            return;
        }
        DB::transaction(function () use ($systemId, $contextId, $collaborators, $resources, $timestamp) {
            self::deleteContext($systemId, $contextId);

            if (empty($collaborators) || empty($resources)) {
                return;
            }

            $data = [];
            foreach ($collaborators as $collaborator) {
                foreach ($resources as $resource) {
                    $item['system_id'] = $systemId;
                    $item['context_id'] = $contextId;
                    $item['type'] = $collaborator->type;
                    $item['collaborator_id'] = $collaborator->authId;
                    $item['content_id'] = $resource->contentAuthorId;
                    $item['timestamp'] = Carbon::createFromTimestamp((int)$timestamp);
                    $data[] = $item;
                }
            }

            if (!empty($data)) {
                self::insert($data);
            }

        });
    }

    /**
     * @param $collaboratorId
     * @param $resourceId
     * @return bool
     * @throws Exception
     */
    public static function isUserCollaborator($collaboratorId, $resourceId)
    {
        if (!config('feature.context-collaboration', false)) {
            return false;
        }

        if (!$collaboratorId || !$resourceId) {
            return false;
        }

        try {
            self::where('collaborator_id', $collaboratorId)
                ->where('content_id', $resourceId)
                ->firstOrFail();
            return true;
        } catch (ModelNotFoundException $e) {
            return false;
        }
    }

    public static function getResourceContextCollaborators($resourceId): array
    {
        if (!config('feature.context-collaboration', false) || !$resourceId) {
            return [];
        }

        return self::where('content_id', $resourceId)->get()->map(function ($collaborator) {
            return strtolower($collaborator->collaborator_id);
        })->toArray();
    }
}
