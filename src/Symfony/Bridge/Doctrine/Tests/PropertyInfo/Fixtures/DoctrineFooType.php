<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\PropertyInfo\Fixtures;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

/**
 * @author Teoh Han Hui <teohhanhui@gmail.com>
 */
class DoctrineFooType extends Type
{
    /**
     * Type name.
     */
    private const NAME = 'foo';

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * {@inheritdoc}
     */
    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform): string
    {
        return $platform->getClobTypeDeclarationSQL([]);
    }

    /**
     * {@inheritdoc}
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }
        if (!$value instanceof Foo) {
            throw new ConversionException(sprintf('Expected "%s", got "%s"', 'Symfony\Bridge\Doctrine\Tests\PropertyInfo\Fixtures\Foo', get_debug_type($value)));
        }

        return $foo->bar;
    }

    /**
     * {@inheritdoc}
     */
    public function convertToPHPValue($value, AbstractPlatform $platform): ?Foo
    {
        if (null === $value) {
            return null;
        }
        if (!\is_string($value)) {
            throw ConversionException::conversionFailed($value, self::NAME);
        }

        $foo = new Foo();
        $foo->bar = $value;

        return $foo;
    }

    /**
     * {@inheritdoc}
     */
    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
