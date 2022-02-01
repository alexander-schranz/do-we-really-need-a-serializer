<?php

namespace App\Entity;

use JMS\Serializer\Annotation\VirtualProperty;

class Article
{
    private int $id;

    private string $key;

    private \DateTimeImmutable $created;

    private iterable $translations;

    public function toAdminApiDetailArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->key,
            'created' => $this->created->format('c'),
        ];
    }

    public function toWebsiteApiDetailArray(string $locale): array
    {
        $translation = $this->getTranslation($locale);

        return [
            'id' => $this->id,
            'key' => $this->key,
            'title' => $translation->getTitle(),
            'description' => $translation->getTitle(),
        ];
    }
}
