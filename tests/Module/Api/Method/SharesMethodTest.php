<?php
/*
 * vim:set softtabstop=4 shiftwidth=4 expandtab:
 *
 * LICENSE: GNU Affero General Public License, version 3 (AGPL-3.0-or-later)
 * Copyright 2001 - 2020 Ampache.org
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 */

declare(strict_types=1);

namespace Ampache\Module\Api\Method;

use Ampache\Config\ConfigContainerInterface;
use Ampache\Config\ConfigurationKeyEnum;
use Ampache\MockeryTestCase;
use Ampache\Module\Api\Authentication\GatekeeperInterface;
use Ampache\Module\Api\Method\Exception\FunctionDisabledException;
use Ampache\Module\Api\Output\ApiOutputInterface;
use Ampache\Repository\Model\Browse;
use Ampache\Repository\Model\ModelFactoryInterface;
use Mockery\MockInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

class SharesMethodTest extends MockeryTestCase
{
    /** @var ConfigContainerInterface|MockInterface|null */
    private MockInterface $configContainer;

    /** @var StreamFactoryInterface|MockInterface|null */
    private MockInterface $streamFactory;

    /** @var ModelFactoryInterface|MockInterface|null */
    private MockInterface $modelFactory;

    private ?SharesMethod $subject;

    public function setUp(): void
    {
        $this->configContainer = $this->mock(ConfigContainerInterface::class);
        $this->streamFactory   = $this->mock(StreamFactoryInterface::class);
        $this->modelFactory    = $this->mock(ModelFactoryInterface::class);

        $this->subject = new SharesMethod(
            $this->configContainer,
            $this->streamFactory,
            $this->modelFactory
        );
    }

    public function testHandleThrowsExceptionIfSharingIsDisabled(): void
    {
        $gatekeeper = $this->mock(GatekeeperInterface::class);
        $response   = $this->mock(ResponseInterface::class);
        $output     = $this->mock(ApiOutputInterface::class);

        $this->configContainer->shouldReceive('isFeatureEnabled')
            ->with(ConfigurationKeyEnum::SHARE)
            ->once()
            ->andReturnFalse();

        $this->expectException(FunctionDisabledException::class);
        $this->expectExceptionMessage('Enable: share');

        $this->subject->handle(
            $gatekeeper,
            $response,
            $output,
            []
        );
    }

    public function testHandleReturnsList(): void
    {
        $gatekeeper = $this->mock(GatekeeperInterface::class);
        $response   = $this->mock(ResponseInterface::class);
        $output     = $this->mock(ApiOutputInterface::class);
        $stream     = $this->mock(StreamInterface::class);
        $browse     = $this->mock(Browse::class);

        $result      = 'some-result';
        $shareId     = 666;
        $filterValue = 'some-value';
        $limit       = 42;
        $offset      = 21;

        $this->configContainer->shouldReceive('isFeatureEnabled')
            ->with(ConfigurationKeyEnum::SHARE)
            ->once()
            ->andReturnTrue();

        $this->modelFactory->shouldReceive('createBrowse')
            ->withNoArgs()
            ->once()
            ->andReturn($browse);

        $browse->shouldReceive('reset_filters')
            ->withNoArgs()
            ->once();
        $browse->shouldReceive('set_type')
            ->with('share')
            ->once();
        $browse->shouldReceive('set_sort')
            ->with('title', 'ASC')
            ->once();
        $browse->shouldReceive('set_filter')
            ->with('exact_match', $filterValue)
            ->once();
        $browse->shouldReceive('get_objects')
            ->withNoArgs()
            ->once()
            ->andReturn([(string) $shareId]);

        $output->shouldReceive('shares')
            ->with([$shareId], true, $limit, $offset)
            ->once()
            ->andReturn($result);

        $this->streamFactory->shouldReceive('createStream')
            ->with($result)
            ->once()
            ->andReturn($stream);

        $response->shouldReceive('withBody')
            ->with($stream)
            ->once()
            ->andReturnSelf();

        $this->assertSame(
            $response,
            $this->subject->handle(
                $gatekeeper,
                $response,
                $output,
                ['exact' => 1, 'filter' => $filterValue, 'limit' => (string) $limit, 'offset' => (string) $offset]
            )
        );
    }
}
