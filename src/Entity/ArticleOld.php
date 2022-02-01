<?php

namespace App\Entity;

use JMS\Serializer\Annotation as Serializer;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Groups;
use JMS\Serializer\Annotation\Expose;
use JMS\Serializer\Annotation\VirtualProperty;

/**
 * @ExclusionPolicy("all")
 */
class Article
{
    /**
     * @Expose()
     * @Groups({"admin", "website"})
     */
    private int $id;

    /**
     * @Expose()
     * @Groups({"admin", "website"})
     */
    private int $key;

    /**
     * @Expose()
     * @Groups({"admin"})
     */
    private \DateTimeImmutable $created;

    private iterable $translations;

    private string $currentLocale;

    public function setCurrentLocale(string $currentLocale): void
    {
        $this->currentLocale = $currentLocale;
    }

    /**
     * @VirtualProperty()
     * @Groups({"website"})
     */
    public function getCurrentTranslation(): ArticleTranslation
    {
        return $this->getTranslation($this->currentLocale);
    }
}
