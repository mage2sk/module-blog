<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Schema;

use Panth\Blog\Api\Data\AuthorInterface;
use Panth\Blog\Model\Url\AuthorUrlBuilder;

class PersonBuilder
{
    public function __construct(
        private readonly AuthorUrlBuilder $authorUrl
    ) {
    }

    public function build(AuthorInterface $author): array
    {
        $url = $this->authorUrl->getAuthorUrl($author);

        return [
            '@type' => 'Person',
            '@id' => $url . '#person',
            'name' => $author->getDisplayName(),
            'jobTitle' => $author->getRole(),
            'description' => $author->getShortBio(),
            'image' => $author->getAvatar(),
            'url' => $url,
            'sameAs' => json_decode((string)$author->getSameAs(), true) ?: [],
            'knowsAbout' => json_decode((string)$author->getKnowsAbout(), true) ?: [],
            'alumniOf' => $author->getAlumniOf()
                ? ['@type' => 'CollegeOrUniversity', 'name' => $author->getAlumniOf()]
                : null,
        ];
    }
}
