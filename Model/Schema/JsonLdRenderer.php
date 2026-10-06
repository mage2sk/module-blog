<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Schema;

use Panth\Blog\Api\Data\AuthorInterface;
use Panth\Blog\Api\Data\CategoryInterface;
use Panth\Blog\Api\Data\PostInterface;
use Panth\Blog\Helper\Config;

class JsonLdRenderer
{
    public function __construct(
        private readonly BlogPostingBuilder $blogPostingBuilder,
        private readonly PersonBuilder $personBuilder,
        private readonly BreadcrumbBuilder $breadcrumbBuilder,
        private readonly FaqPageBuilder $faqPageBuilder,
        private readonly HowToBuilder $howToBuilder,
        private readonly CollectionPageBuilder $collectionPageBuilder,
        private readonly GraphAssembler $graphAssembler,
        private readonly Config $config
    ) {
    }

    public function renderForPost(
        PostInterface $post,
        ?AuthorInterface $author,
        ?CategoryInterface $primary,
        array $tags,
        array $breadcrumbItems,
        array $faqs = []
    ): string {
        $entities = [];
        $entities[] = $this->blogPostingBuilder->build($post, $author, $primary, $tags);

        if ($author !== null) {
            $entities[] = $this->personBuilder->build($author);
        }

        if (!empty($breadcrumbItems)) {
            $entities[] = $this->breadcrumbBuilder->build($breadcrumbItems);
        }

        $faqEntity = $this->faqPageBuilder->build($faqs);
        if ($faqEntity !== null) {
            $entities[] = $faqEntity;
        }

        if ($this->config->isHowtoAutoDetect()) {
            $howTo = $this->howToBuilder->extractFromContent(
                (string)$post->getContent(),
                (string)$post->getTitle()
            );
            if ($howTo !== null) {
                $entities[] = $howTo;
            }
        }

        return $this->graphAssembler->assemble($entities);
    }

    public function renderForCategory(
        CategoryInterface $cat,
        string $url,
        array $itemListElements,
        array $breadcrumbItems,
        int $total
    ): string {
        $entities = [];
        $entities[] = $this->collectionPageBuilder->buildCategoryPage(
            $cat,
            $url,
            $itemListElements,
            $total
        );

        if (!empty($breadcrumbItems)) {
            $entities[] = $this->breadcrumbBuilder->build($breadcrumbItems);
        }

        return $this->graphAssembler->assemble($entities);
    }

    public function renderForBlogIndex(
        string $name,
        string $url,
        string $desc,
        array $postListItems,
        array $breadcrumbItems = []
    ): string {
        $entities = [
            $this->collectionPageBuilder->buildBlogIndex($name, $url, $desc, $postListItems),
        ];

        if (!empty($breadcrumbItems)) {
            $entities[] = $this->breadcrumbBuilder->build($breadcrumbItems);
        }

        return $this->graphAssembler->assemble($entities);
    }

    public function renderForTag(
        string $name,
        string $url,
        string $desc,
        array $postListItems,
        int $total,
        array $breadcrumbItems = []
    ): string {
        $collection = [
            '@type'       => 'CollectionPage',
            'name'        => $name,
            'description' => $desc,
            'url'         => $url,
            'mainEntity'  => [
                '@type'           => 'ItemList',
                'numberOfItems'   => $total,
                'itemListElement' => $postListItems ?: null,
            ],
        ];
        $entities = [$collection];
        if (!empty($breadcrumbItems)) {
            $entities[] = $this->breadcrumbBuilder->build($breadcrumbItems);
        }
        return $this->graphAssembler->assemble($entities);
    }

    public function renderForAuthor(
        AuthorInterface $author,
        array $breadcrumbItems = []
    ): string {
        $person = $this->personBuilder->build($author);

        $profilePage = [
            '@type' => 'ProfilePage',
            'mainEntity' => $person,
        ];

        $entities = [$profilePage, $person];
        if (!empty($breadcrumbItems)) {
            $entities[] = $this->breadcrumbBuilder->build($breadcrumbItems);
        }

        return $this->graphAssembler->assemble($entities);
    }
}
