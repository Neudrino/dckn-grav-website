<?php

namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Common\Processors\Events\RequestHandlerEvent;

class MediaGuardPlugin extends Plugin
{
    public static function getSubscribedEvents(): array
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0],
        ];
    }

    public function onPluginsInitialized(): void
    {
        if (!class_exists(\Grav\Plugin\Api\ApiRouter::class)
            || !$this->grav['config']->get('plugins.api.enabled', false)) {
            return;
        }

        $this->enable([
            'onRequestHandlerInit' => ['onRequestHandlerInit', 98000],
        ]);
    }

    public function onRequestHandlerInit(RequestHandlerEvent $event): void
    {
        $base = '/' . trim((string) $this->grav['config']->get('plugins.api.route', '/api'), '/');
        if (!str_starts_with($event->getRoute()->getRoute(), $base . '/')) {
            return;
        }

        require_once __DIR__ . '/classes/GuardedApiRouter.php';

        $event->addMiddleware('media_guard', new MediaGuard\GuardedApiRouter(
            $this->grav,
            $this->grav['config'],
            (array) $this->grav['config']->get('plugins.media-guard.site_media_write_groups', []),
        ));
    }
}
