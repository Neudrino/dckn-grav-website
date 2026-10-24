<?php

namespace Grav\Plugin\MediaGuard;

use Grav\Common\Config\Config;
use Grav\Common\Grav;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\User\Interfaces\UserInterface;
use Grav\Plugin\Api\ApiRouter;
use Grav\Plugin\Api\PageAcl;
use Grav\Plugin\Api\PermissionResolver;
use Grav\Plugin\Api\Response\ErrorResponse;
use Grav\Plugin\Api\Services\BlueprintPathResolver;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * API router that keeps the site media library read-only for everyone outside
 * the configured write groups and makes the generic blueprint-upload endpoint
 * honour the page ACL. Page media routes under /pages/{route}/media are already
 * page-ACL gated.
 */
class GuardedApiRouter extends ApiRouter
{
    /** @param list<string> $siteMediaWriteGroups */
    public function __construct(Grav $container, Config $config, private readonly array $siteMediaWriteGroups)
    {
        parent::__construct($container, $config);
    }

    protected function handleRoute(ServerRequestInterface $request, array $handler, array $vars): ResponseInterface
    {
        $reason = $this->forbiddenWriteReason($request);
        if ($reason !== null) {
            return ErrorResponse::create(403, 'Forbidden', $reason);
        }

        return parent::handleRoute($request, $handler, $vars);
    }

    private function forbiddenWriteReason(ServerRequestInterface $request): ?string
    {
        if (!in_array($request->getMethod(), ['POST', 'PATCH', 'PUT', 'DELETE'], true)) {
            return null;
        }

        $user = $request->getAttribute('api_user');
        if (!$user instanceof UserInterface) {
            return null;
        }

        $resolver = new PermissionResolver($this->container['permissions'] ?? null);
        if ($resolver->resolveExact($user, 'api.super') === true) {
            return null;
        }

        $path = $this->routePath($request);
        if ($path === null) {
            return null;
        }

        $mayWriteSiteMedia = (bool) array_intersect((array) $user->get('groups', []), $this->siteMediaWriteGroups);
        $mediaDenied = 'Site media is read-only for this account. Media can only be changed on pages this account may edit.';

        if (!$mayWriteSiteMedia && preg_match('#^/media(?:/|$)#', $path)) {
            return $mediaDenied;
        }

        if (str_ends_with($path, '/blueprint-upload')) {
            $target = $this->blueprintUploadTarget($request, $user);
            if ($target === null) {
                return null;
            }

            $mediaRoot = $this->container['locator']->findResource('user://media', true, true);
            if (!$mayWriteSiteMedia && $mediaRoot && $this->isInside($target, $mediaRoot)) {
                return $mediaDenied;
            }

            $pagesRoot = $this->container['locator']->findResource('user://pages', true, true);
            if ($pagesRoot && $this->isInside($target, $pagesRoot)) {
                $page = $this->pageForPath($target);
                if ($page === null) {
                    return 'This account may not write files to that page.';
                }
                $verdict = (new PageAcl())->authorize($page, $user, 'update');
                $allowed = $verdict ?? ($resolver->resolve($user, 'api.pages.write') === true);
                if (!$allowed) {
                    return 'This account may not write files to that page.';
                }
            }
        }

        return null;
    }

    private function routePath(ServerRequestInterface $request): ?string
    {
        $route = $request->getAttribute('route');
        if (!$route) {
            return null;
        }

        $base = '/' . trim((string) $this->config->get('plugins.api.route', '/api'), '/');
        $version = (string) $this->config->get('plugins.api.version_prefix', 'v1');
        $apiBase = $base . '/' . $version;

        $path = $route->getRoute();

        return str_starts_with($path, $apiBase . '/') ? substr($path, strlen($apiBase)) : null;
    }

    private function blueprintUploadTarget(ServerRequestInterface $request, UserInterface $user): ?string
    {
        $body = (array) ($request->getParsedBody() ?? []);
        $resolver = new BlueprintPathResolver($this->container);

        if ($request->getMethod() === 'DELETE') {
            $file = ltrim((string) ($body['path'] ?? ''), '/');
            if ($file === '' || str_contains($file, '..')) {
                return null;
            }
            if (str_starts_with($file, 'user/')) {
                $file = substr($file, 5);
            }
            $root = $resolver->userRoot();

            return $root !== null ? $root . '/' . $file : null;
        }

        $destination = (string) ($body['destination'] ?? '');
        if ($destination === '') {
            return null;
        }

        try {
            return $resolver->resolve($destination, (string) ($body['scope'] ?? ''), $user, false);
        } catch (\Throwable) {
            return null;
        }
    }

    private function pageForPath(string $target): ?PageInterface
    {
        $pages = $this->container['pages'];
        if (method_exists($pages, 'enablePages')) {
            $pages->enablePages();
        }

        $target = $this->normalizePath($target);
        $match = null;
        $matchLength = 0;

        foreach ($pages->all() as $page) {
            if (!$page instanceof PageInterface) {
                continue;
            }
            $pagePath = $this->normalizePath((string) $page->path());
            if ($pagePath !== ''
                && ($target === $pagePath || str_starts_with($target, $pagePath . '/'))
                && strlen($pagePath) > $matchLength) {
                $match = $page;
                $matchLength = strlen($pagePath);
            }
        }

        return $match;
    }

    private function isInside(string $target, string $root): bool
    {
        $target = $this->normalizePath($target);
        $root = $this->normalizePath($root);

        return $target === $root || str_starts_with($target . '/', $root . '/');
    }

    /**
     * Resolve symlinks for the part of the path that exists, so the user/
     * symlink of a container install compares equal to the realpath roots.
     */
    private function normalizePath(string $path): string
    {
        $path = rtrim(str_replace('\\', '/', $path), '/');
        $suffix = '';
        $probe = $path;

        while ($probe !== '' && !file_exists($probe)) {
            $suffix = '/' . basename($probe) . $suffix;
            $parent = dirname($probe);
            if ($parent === $probe) {
                break;
            }
            $probe = $parent;
        }

        $real = $probe !== '' ? realpath($probe) : false;
        $resolved = $real !== false ? $real : $probe;

        return rtrim($resolved . $suffix, '/');
    }
}
