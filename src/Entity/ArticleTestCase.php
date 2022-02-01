<?php

namespace App\Entity;

use Coduo\PHPMatcher\PHPUnit\PHPMatcherAssertions;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\Serializer;
use PHPUnit\Framework\TestCase;

class ArticleTestCase extends TestCase
{
    use PHPMatcherAssertions;

    public function testToArray(): void
    {
        $article = new Article();
        // set properties

        $this->assertMatchesPattern(<<<EOT
{
    "id": "@integer@",
    "key": "key",
    "title": "Title",
    "description": "Description"
}
EOT, json_encode($article->toWebsiteApiDetailArray('en'), JSON_THROW_ON_ERROR));
    }

    public function testSerializerGroup(): void
    {
        $article = new Article();
        // set properties

        $serializer = new Serializer();
        $json = $serializer->serialize($article, [(new SerializationContext())->setAttribute('locale', 'en')]);

        $this->assertMatchesPattern(<<<EOT
{
    "id": "@integer@",
    "key": "key",
    "title": "Title",
    "description": "Description"
}
EOT, $json);
    }
}
