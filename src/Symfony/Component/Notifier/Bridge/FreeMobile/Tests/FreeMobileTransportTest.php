<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\FreeMobile\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Bridge\FreeMobile\FreeMobileTransport;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class FreeMobileTransportTest extends TransportTestCase
{
    /**
     * @return FreeMobileTransport
     */
    public static function createTransport(HttpClientInterface $client = null): TransportInterface
    {
        return new FreeMobileTransport('login', 'pass', '0611223344', $client ?? new MockHttpClient());
    }

    public static function toStringProvider(): iterable
    {
        yield ['freemobile://smsapi.free-mobile.fr/sendmsg?phone=0611223344', self::createTransport()];
    }

    public static function supportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0611223344', 'Hello!')];
        yield [new SmsMessage('+33611223344', 'Hello!')];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0699887766', 'Hello!')]; // because this phone number is not configured on the transport!
        yield [new ChatMessage('Hello!')];
        yield [new DummyMessage()];
    }
}
