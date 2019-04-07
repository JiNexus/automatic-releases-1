<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Test\Unit\Github\Api\V3;

use Doctrine\AutomaticReleases\Git\Value\BranchName;
use Doctrine\AutomaticReleases\Git\Value\SemVerVersion;
use Doctrine\AutomaticReleases\Github\Api\V3\CreatePullRequest;
use Doctrine\AutomaticReleases\Github\Value\RepositoryName;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Zend\Diactoros\Request;
use Zend\Diactoros\Response;

final class CreatePullRequestTest extends TestCase
{
    /** @var ClientInterface&MockObject */
    private $httpClient;

    /** @var RequestFactoryInterface&MockObject */
    private $messageFactory;

    /** @var string */
    private $apiToken;

    /** @var CreatePullRequest */
    private $createPullRequest;

    protected function setUp() : void
    {
        parent::setUp();

        $this->httpClient        = $this->createMock(ClientInterface::class);
        $this->messageFactory    = $this->createMock(RequestFactoryInterface::class);
        $this->apiToken          = uniqid('apiToken', true);
        $this->createPullRequest = new CreatePullRequest(
            $this->messageFactory,
            $this->httpClient,
            $this->apiToken
        );
    }

    public function testSuccessfulRequest()
    {
        $this
            ->messageFactory
            ->method('createRequest')
            ->with('POST', 'https://api.github.com/repos/foo/bar/pulls')
            ->willReturn(new Request('https://the-domain.com/the-path'));

        $validResponse = new Response();

        $validResponse->getBody()->write(<<<'JSON'
{
    "url": "http://another-domain.com/the-pr"
}
JSON
        );
        $this
            ->httpClient
            ->expects(self::once())
            ->method('sendRequest')
            ->with(self::callback(function (RequestInterface $request) : bool {
                self::assertSame(
                    [
                        'Host'          => ['the-domain.com'],
                        'Content-Type'  => ['application/json'],
                        'User-Agent'    => ['Ocramius\'s minimal API V3 client'],
                        'Authorization' => ['bearer ' . $this->apiToken],
                    ],
                    $request->getHeaders()
                );

                self::assertJsonStringEqualsJsonString(
                    <<<'JSON'
{
    "title": "the-title",
    "head": "the/source-branch",
    "base": "the/target-branch",
    "body": "the-body",
    "maintainer_can_modify": true,
    "draft": false
}
JSON
                    ,
                    $request->getBody()->__toString()
                );

                return true;
            }))
            ->willReturn($validResponse);

        $this->createPullRequest->__invoke(
            RepositoryName::fromFullName('foo/bar'),
            BranchName::fromName('the/source-branch'),
            BranchName::fromName('the/target-branch'),
            'the-title',
            'the-body'
        );
    }
}
