<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Tests\Globals\Verification;

use Symfony\Component\Intl\Tests\Globals\AbstractIntlGlobalsTestCase;
use Symfony\Component\Intl\Util\IntlTestHelper;

/**
 * Verifies that {@link AbstractIntlGlobalsTestCase} matches the behavior of the
 * intl functions with a specific version of ICU.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @group legacy
 */
class IntlGlobalsTest extends AbstractIntlGlobalsTestCase
{
    protected function setUp(): void
    {
        IntlTestHelper::requireFullIntl($this, false);

        parent::setUp();
    }

    protected function getIntlErrorName($errorCode)
    {
        return intl_error_name($errorCode);
    }
}
