# Do we really need a serializer for our JSON API?

The last years I did build a lot of JSON APIs but personally was
never happy about the magic of using a Serializer and lately I did
think more and more about do I really need a Serializer for my API.

## The current state

Mostly I did use the [JMS Serializer](https://github.com/schmittjoh/serializer) 
and lately did experiment with the [Symfony Serializer](https://symfony.com/doc/current/components/serializer.html).

Why I think using a Serializer does a great Job for Prototyping
or where you provide your entities over single endpoint. But can
be a pain if your entities are provided over several endpoints.
In my case I mostly have an own API for the [Sulu CMS Admin](https://github.com/sulu/sulu)
then maybe an additional API for the Website and in some cases
there were also additional APIs for an App and an Extranet.
All APIs did look a little different but provide data of the
same entity.

As having different APIs we needed to work with Serialization Groups
and also then side effects could be possible when no Group is specified,
and it did add accidentally properties you don't want to provide on another
API. So the solution in this case was that we did define by default
that we always need to use a Group and we always exclude all properties
by default via [ExclusionPolicy](https://jmsyst.com/libs/serializer/master/cookbook/exclusion_strategies):

```php
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Groups;

/**
 * @ExclusionPolicy("all")
 */
class Article {} 
```

So now if a property was added, we explicit need to define in which serialization
group the properties should be serialized.

```php
/** @Groups({"admin", "website"}) */
private string $title;
```

To avoid sideeffects between different entities we also decide to prefix all
serialization group with the name of the entity. So instead of `admin` or 
`website` we did go with `article_admin` and `article_website`. 

```php
/** @Groups({"article_admin", "article_website"}) */
private string $title;
```

So we don't have conflicts with related entities serialization groups.
This did work great for us and we avoided sideeffects well our APIs
but when looking at the serialization config ([xml config](https://jmsyst.com/libs/serializer/master/reference/xml_reference))
or at the classes annotations it is hard to understand how the response
of this object really looks without calling that endpoint.

## New project new solutions

In a new project which was actually driven by Symfony UX with Symfony
Forms. I had the need that I needed to provide my entity as `array`
to the form as we only used the form for validation and rendering
the form, but not actually to map data to the entity. As we did use
CommandBus, CommandMessage and CommandHandlers via the Symfony Messenger
to update our entities. And I wanted to avoid that we need to define
`mapped => false` in all cases.

So the question was how do I convert my Entity into the `array` format
of the form. First thought about using a Serializer but did find that
is too much magic and wanted to have something more typesafe. So instead
of a serializer I did go with a `toDetailFormArray` method on my entity.

```php
class Article {
    /**
     * @return array{
     *      title: string,
     *      description: string|null,
     * }
     */
    private function toDetailFormArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
        ];
    }
} 
```

The entity is big and was splitted into several forms and each form did then have
its own `toArray` method e.g.:

```php
class Article {
    /**
     * @return array{
     *      title: string,
     *      description: string|null,
     * }
     */
    private function toDetailFormArray(): array
    {
        // ...
    }
    
    /**
     * @return array{
     *      keywords: string,
     *      tags: string[],
     * }
     */
    private function toSeoFormArray(): array
    {
        // ...
    }
} 
```

This is working really well and everybody is understanding and see the structure
of the data we need here. Also testing this "toArray" is more efficient as it is
a Unit Test on the class. And also PHPStan is doing a great work here as we define
the return types of the array.

## From Forms to API

As this did really feel good and did work well for my use cases I did more and more
think about it - should I not do the same when providing my entity via a JSON API.

So instead of having a serializer for my entity in the Controller I replace it with
a toArray method e.g.:

```diff
-$data = $this->serializer->serialize($article, ['some' => 'options']);
+$data = $article->toAdminApiDetailArray();
```

Also some APIs did require some required options example our Article is multi language
and we want only provide a single language e.g. `/api/article/1?locale=en`.

Mostly this did work the following in our case we did adjust our entity the following way:

```php
class Article {
    private ?string $currentLocale;
    
    public function setCurrentLocale(string $currentLocale): void
    {
        $this->currentLocale = $currentLocale;
    }
    
    public function getCurrentTranslation(): void
    {
        Assert::notNull($this->currentLocale, 'The "currentLocale" property is required to be set.');
        
        return $this->getTranslation($this->currentLocale);
    }
    
    /**
     * @throws ArticleTrnaslationNotFoundException
     */
    public function getTranslation(string $locale): ArticleTrnaslation {/* .. */}
} 
```

The `currentLocale` needed to be set before serialization or by a more magic way via
a serialization listener.

```php
$article->setCurrentLocale($request->query->getAlnum('locale'));
$data = $this->serializer->serialize($article, ['some' => 'options']);
```

Instead with an own method we can define required options to convert our entity into an array:

```php
class Article {
    /**
     * @return array{
     *      title: string,
     *      description: string|null,
     * }
     */
    public function toAdminApiDetailTranslatedArray(string $locale): array
    {
        $translation = $this->getTranslation($locale);
    
        return [
            'id' => $translation->getTitle(),
            'title' => $translation->getTitle(),
            'description' => $translation->getDescription(),
        ];
    }
}
```

This is a lot better from my point of view for the developer experience. And does not hide
anything behind some serializer subscriber or serializer options.

## What about the Single Responsibility Principle

The [Single Responsibility Principle](https://en.wikipedia.org/wiki/Single-responsibility_principle)
tells us that "A class should have only one reason to change.".
I totally agree that it hurts the "Single Responsibility Principle". To fix that we could move
the whole method into an own Service called: `ArticleAdminApiDetailTranslatedFactory`.

```php
class ArticleAdminApiDetailTranslatedFactoryInterface {
    /**
     * @return array{
     *      title: string,
     *      description: string|null,
     * }
     */
    public function create(Article $article): array;
}
```

The kind of factories are used in many cases, mostly they are used to create an own Representation
of an Object so not returning an array instead return again an own Model:

```php
class ArticleAdminApiDetailTranslatedFactoryInterface {
    public function create(Article $article): ArticleAdminApiDetailTranslatedRepresentation;
}
```

Instead of a Factory we could directly go with an own Repository for this representation which fetches
the data by an array from the Article table:

```php
class ArticleAdminApiDetailTranslatedRepository {
    public function getOneBy(id): ArticleAdminApiDetailTranslatedRepresentation
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->form(Article::class, 'article')
            ->select('article.id')
            ->addSelect('article.title')
            ->addSelect('article.description');

        $result = $queryBuilder->getSingleResult();

        return new ArticleAdminApiDetailTranslatedRepresentation($result['id'], $result['title'], $result['description']);
    }
}
```

That would be from DDD (Domain Driven Design) view mostly be the best solution.

## Why I would go not with a Factory or own Repository

Why the above solution is very clean it is still something I would not use
in my projects. The above solution would need a lot of more classes to be 
maintained also to be tested. But the number one point what make for me
the above solution not a good one for my projects is extendability.

Example in [Sulu CMS](https://github.com/sulu/sulu) we provide some
core entities/models. This models are [extendable](https://docs.sulu.io/en/2.4/cookbook/extend-entities.html).
Now a developer wants an additional field for example on the Media table.
For this case they can extend from the exist model and add there new property:

```php
/**
 * @ORM\Table(name="me_media")
 * @ORM\Entity
 */
class Media extends SuluMedia {   
    /**
     * @ORM\Column(name="newProperty", type="string", length=255, nullable = true)
     */
    private string $newProperty;
}
```

With the previous solution for a factory with an own method or even a repository.
We would need a factory service for the representation class. This service
would the case of extendability be able to be overwritten so the end developer
need to extend the following things:

 1. Model
 2. Representation Model
 3. Representation Model Factory

So the factory / repository solution is great about [SOLID principles](https://en.wikipedia.org/wiki/SOLID)
but not very developer friendly for extendability.

With the solution of to array is a lot faster to add a new property to a
Model and its API by overwriting the `toArray` method e.g.:

```php
/**
 * @ORM\Table(name="me_media")
 * @ORM\Entity
 */
class Media extends SuluMedia {   
    /**
     * @ORM\Column(name="newProperty", type="string", length=255, nullable = true)
     */
    private string $newProperty;
    
    /**
     * @return MediaAdminApiDetailTranslatedArray&array{
     *      newProperty: string|null,
     * }
     */
    public function toAdminApiDetailTranslatedArray(string $locale): array
    {
        $data = parent::toAdminApiDetailTranslatedArray($locale);
        $data['newProperty'] = $this->newProperty;
    
        return $data;
    }
}
```

So instead of overwriting 3 classes only 1 class is required. This make it
form my point of view a lot easier. So developers wanting to extend exist
entities with their own properties to match there business logic.

As written a lot in last times - software should be created for humans
and should not make human work more difficult.

## API part of our Domain Logic

As I'm doing a lot of with [Hexagonal architecture](https://github.com/alexander-schranz/hexagonal-architecture-study)
in the last time. The questions is that the API json structure should
really be part of the "Application Core". As it should maybe
be the API Controller "Adapter" defining it s response structure
and not the Domain Model. I'm thinking that the Structure of my
API point are so important for my Business Logic that they should
be part of my Application Core and not should be defined outside
of it over some Infrastructure configuration or other things. Alternate
as listed above and [DDD](https://de.wikipedia.org/wiki/Domain-driven_Design)
representation models and repositories which I think hard sometimes
to maintain and getting to whole Team into this Mindset.

## Conclusion

At the end I think the toArray methods was I think in the past seen
as little bit evil because they were not typesafe. But with tools like
[phpstan](https://github.com/phpstan/phpstan) and [psalm](https://github.com/vimeo/psalm)
you can make sure that it will return also for arrays the correct types.

I think the solution for toArray make this process:

 - understandable for beginners
 - more readable
 - easier to extend
 - easier to test
 - less dependencies / easier upgrade / easier maintainable

Based on the toArray methods I found an easy wo to create an activity
log for our changes by making a diff on PHP internal methods before
and after an entity was changed.

I still can not say if in future I will build APIs this way at the end
it always need to be a **Team Decision** what is the best way for a Team
to develop things. What are they most familiar with and how they can
process the fastest and maintainable way.

As written in the intro there are a cases were a serializer is still
the fastest and maintainable way to move forward. Still it didn't match
most of my cases or did make my cases a lot harder. I think libraries like
[API Platform](https://github.com/api-platform/api-platform) are doing a
really great Job when you want to create a single endpoint for your entity.
The toolset around libraries like this is really great. And aslong as you are
happy with your solutions and your team is happy with it you should
stay with things what works best for you.

## Continue with a serializer

What you can do when you continue with a serializer. In most cases
to "Test" how an entity is serialized a whole API tests are created.
Instead you should think about creating more abstract test cases
where you just create the Model in the memory and call the serializer
on it. This will make your tests a lot faster then having the need
to persist your Model to a database.

```php
class MerchantSerializerTest extends TestCase {
    public function testSerializeAdminGroup(): void
    {
        $article = new Article();
        // .. call needed methods to create article
        
        $json = $this->serializer->serialize($article);

        // ... assert json
    }
}
```

In this case I also want to recommend the best way to test your serialization
to `json` or `xml` is in my opinion the [Coduo PHP Matcher Library](https://github.com/coduo/php-matcher)
This way you can test your JSON or XML API against an json definition like this: 

```json
{
    "id": "@integer@",
    "title": "Title",
    "description": "Title"
}
```

The best is that this tests will work so with auto incremented ID's as you can define
to match only the type of the response JSON. Also this way it is automatically
recognized if a new property is added to the response so automatically tests need
to be adopted then and you don't forget about testing new properties. Sure the
library can also used to match against an array.

I hope I could give at least one person some impact with this article. Let me know what
your solutions for API serialization are and what problems you did have in the past your
different solutions you tried.
