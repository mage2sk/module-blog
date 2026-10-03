<?php
declare(strict_types=1);

namespace Panth\Blog\Controller\Router;

use Magento\Framework\App\Action\Redirect;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\App\RouterInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\UrlInterface;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\Url\UrlHistoryManager;

class BlogRouter implements RouterInterface
{
    private const FRONT_NAME_DEFAULT = 'blog';

    private const SLUG_PATTERN = '/^[a-z0-9][a-z0-9-]{0,159}$/';

    public function __construct(
        private readonly ActionFactory $actionFactory,
        private readonly ResourceConnection $resource,
        private readonly Config $config,
        private readonly UrlHistoryManager $history,
        private readonly UrlInterface $url,
        private readonly ResponseInterface $response
    ) {
    }

    public function match(RequestInterface $request): ?ActionInterface
    {
        if (!$this->config->isEnabled()) {
            return null;
        }

        if ($request->getModuleName() === "cms" && $request->getControllerName() === "noroute") {
            return null;
        }

        $path = trim((string) $request->getPathInfo(), '/');
        if ($path === '') {
            return null;
        }

        $prefix = $this->frontName();
        if ($path !== $prefix && !str_starts_with($path, $prefix . '/')) {
            return null;
        }

        $remainder = $path === $prefix ? '' : substr($path, strlen($prefix) + 1);

        if ($remainder === '') {
            return $this->forward($request, 'index', 'index');
        }

        if (preg_match('#^page/(\d+)$#', $remainder, $m)) {
            $request->setParam('page', (int) $m[1]);
            return $this->forward($request, 'index', 'index');
        }

        if ($remainder === 'search') {
            return $this->forward($request, 'index', 'search');
        }

        if ($remainder === 'comment/submit') {
            return $this->forward($request, 'comment', 'submit');
        }

        if ($remainder === 'feed.xml') {
            return $this->forward($request, 'feed', 'index');
        }

        if ($remainder === 'feed/atom.xml') {
            return $this->forward($request, 'feed', 'atom');
        }

        if (preg_match('#^feed/category/(.+)\.xml$#', $remainder, $m)) {
            $request->setParam('slug', $m[1]);
            return $this->forward($request, 'feed', 'category');
        }

        if (preg_match('#^feed/tag/(.+)\.xml$#', $remainder, $m)) {
            $request->setParam('slug', $m[1]);
            return $this->forward($request, 'feed', 'tag');
        }

        if (preg_match('#^feed/author/(.+)\.xml$#', $remainder, $m)) {
            $request->setParam('slug', $m[1]);
            return $this->forward($request, 'feed', 'author');
        }

        if (preg_match('#^category/(.+?)(?:/page/(\d+))?$#', $remainder, $m)) {
            if (!$this->categoryExists($m[1])) {
                $redirect = $this->resolveTypeHistoryRedirect('category', $m[1]);

                return $redirect !== null ? $this->buildRedirect($request, $redirect) : null;
            }
            $request->setParam('slug', $m[1]);
            if (isset($m[2]) && $m[2] !== '') {
                $request->setParam('page', (int) $m[2]);
            }
            return $this->forward($request, 'category', 'view');
        }

        if (preg_match('#^tag/(.+?)(?:/page/(\d+))?$#', $remainder, $m)) {
            if (!$this->tagExists($m[1])) {
                $redirect = $this->resolveTypeHistoryRedirect('tag', $m[1]);

                return $redirect !== null ? $this->buildRedirect($request, $redirect) : null;
            }
            $request->setParam('slug', $m[1]);
            if (isset($m[2]) && $m[2] !== '') {
                $request->setParam('page', (int) $m[2]);
            }
            return $this->forward($request, 'tag', 'view');
        }

        if (preg_match('#^author/(.+?)(?:/page/(\d+))?$#', $remainder, $m)) {
            if (!$this->authorExists($m[1])) {
                $redirect = $this->resolveTypeHistoryRedirect('author', $m[1]);

                return $redirect !== null ? $this->buildRedirect($request, $redirect) : null;
            }
            $request->setParam('slug', $m[1]);
            if (isset($m[2]) && $m[2] !== '') {
                $request->setParam('page', (int) $m[2]);
            }
            return $this->forward($request, 'author', 'view');
        }

        if (preg_match('#^(.+?)\.md$#', $remainder, $m)) {
            $request->setParam('slug', $m[1]);
            return $this->forward($request, 'markdown', 'export');
        }

        if (!preg_match(self::SLUG_PATTERN, $remainder)) {
            return null;
        }

        if ($this->postExists($remainder)) {
            $request->setParam('slug', $remainder);
            return $this->forward($request, 'post', 'view');
        }

        if ($this->categoryExists($remainder)) {
            $request->setParam('slug', $remainder);
            return $this->forward($request, 'category', 'view');
        }
        if ($this->tagExists($remainder)) {
            $request->setParam('slug', $remainder);
            return $this->forward($request, 'tag', 'view');
        }
        if ($this->authorExists($remainder)) {
            $request->setParam('slug', $remainder);
            return $this->forward($request, 'author', 'view');
        }

        $redirect = $this->resolveHistoryRedirect($remainder);
        if ($redirect !== null) {
            return $this->buildRedirect($request, $redirect);
        }

        return null;
    }

    private function frontName(): string
    {
        $name = $this->config->getRouteFrontName();
        return $name !== '' ? trim($name, '/') : self::FRONT_NAME_DEFAULT;
    }

    private function forward(RequestInterface $request, string $controller, string $action): ?ActionInterface
    {
        $request->setModuleName(self::FRONT_NAME_DEFAULT)
            ->setControllerName($controller)
            ->setActionName($action);

        if (method_exists($request, 'setRouteName')) {
            $request->setRouteName(self::FRONT_NAME_DEFAULT);
        }

        $className = sprintf(
            'Panth\\Blog\\Controller\\%s\\%s',
            ucfirst($controller),
            ucfirst($action)
        );

        if (!class_exists($className)) {
            return null;
        }

        return $this->actionFactory->create($className);
    }

    private function postExists(string $slug): bool
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('panth_blog_post');
        if (!$connection->isTableExists($table)) {
            return false;
        }

        $select = $connection->select()
            ->from($table, ['post_id'])
            ->where('url_key = ?', $slug)
            ->where('status = ?', 'published')
            ->limit(1);

        return (bool) $connection->fetchOne($select);
    }

    private function categoryExists(string $slug): bool
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('panth_blog_category');
        if (!$connection->isTableExists($table)) {
            return false;
        }

        $select = $connection->select()
            ->from($table, ['category_id'])
            ->where('url_key = ?', $slug)
            ->where('is_active = ?', 1)
            ->limit(1);

        return (bool) $connection->fetchOne($select);
    }

    private function tagExists(string $slug): bool
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('panth_blog_tag');
        if (!$connection->isTableExists($table)) {
            return false;
        }

        $select = $connection->select()
            ->from($table, ['tag_id'])
            ->where('url_key = ?', $slug)
            ->limit(1);

        return (bool) $connection->fetchOne($select);
    }

    private function authorExists(string $slug): bool
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('panth_blog_author');
        if (!$connection->isTableExists($table)) {
            return false;
        }

        $select = $connection->select()
            ->from($table, ['author_id'])
            ->where('url_key = ?', $slug)
            ->where('is_active = ?', 1)
            ->limit(1);

        return (bool) $connection->fetchOne($select);
    }

    private function resolveTypeHistoryRedirect(string $type, string $oldSlug): ?string
    {
        $hit = $this->history->findCurrent($type, $oldSlug);
        if ($hit === null) {
            return null;
        }
        $front = $this->frontName();
        $newKey = $hit['new_url_key'];
        $path = match ($type) {
            'post'     => $front . '/' . $newKey,
            'category' => $front . '/category/' . $newKey,
            'tag'      => $front . '/tag/' . $newKey,
            'author'   => $front . '/author/' . $newKey,
            default    => null,
        };

        return $path === null ? null : $this->url->getDirectUrl($path);
    }

    private function resolveHistoryRedirect(string $oldSlug): ?string
    {
        foreach (['post', 'category', 'tag', 'author'] as $type) {
            $hit = $this->history->findCurrent($type, $oldSlug);
            if ($hit === null) {
                continue;
            }
            $newKey = $hit['new_url_key'];
            $front = $this->frontName();
            $path = match ($type) {
                'post'     => $front . '/' . $newKey,
                'category' => $front . '/category/' . $newKey,
                'tag'      => $front . '/tag/' . $newKey,
                'author'   => $front . '/author/' . $newKey,
            };
            return $this->url->getDirectUrl($path);
        }

        return null;
    }

    private function buildRedirect(RequestInterface $request, string $targetUrl): ActionInterface
    {
        $this->response->setRedirect($targetUrl, 301);
        $request->setDispatched(true);

        return $this->actionFactory->create(Redirect::class);
    }
}
