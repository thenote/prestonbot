<?php

namespace tests\AppBundle\Diff;

use AppBundle\Diff\Diff;
use Lpdigital\Github\Parser\WebhookResolver;

/**
 * @author Mickaël Andrieu <andrieu.travail@gmail.com>
 */
class DiffTest extends \PHPUnit_Framework_TestCase
{
    const TRANS_PATTERN = '#(trans\(|->l\()#';

    private $event;
    private $pullRequest;
    private $webhookResolver;

    protected function setUp()
    {
        $this->webhookResolver = new WebhookResolver();
        $webhookResponse = file_get_contents(__DIR__.'/../webhook_examples/pull_request_opened_wording.json');
        $data = json_decode($webhookResponse, true);
        $this->event = $this->webhookResolver->resolve($data);
        $this->pullRequest = $this->event->pullRequest;
    }

    public function testMatch()
    {
        $diffMatched = Diff::create($this->getExpectedDiff());
        $this->assertTrue($diffMatched->contains(self::TRANS_PATTERN)->match());
    }

    public function testUnmatch()
    {
        $diffUnmatched = Diff::create($this->getNotExpectedDiff());

        $this->assertFalse($diffUnmatched->contains(self::TRANS_PATTERN)->match());
    }

    public function testFilterByPath()
    {
        $diff = Diff::create($this->getExpectedDiff());
        $filtered = $diff->path('#ProductCombination.php#');

        $this->assertEquals(
            1,
            $filtered->getIterator()->count(),
            'This iterator should contains only 1 file.'
        );
    }

    public function testFilterByPathAndContent()
    {
        $diff = Diff::create($this->getExpectedDiff());

        $iterator = $diff->path('#ProductCombination.php#')
            ->contains(self::TRANS_PATTERN)
            ->getIterator()
        ;

        $this->assertEquals(
            1,
            $iterator->count(),
            'This iterator should contains only 1 file.'
        );

        $found = false;
        foreach ($iterator as $file) {
            foreach ($file->lines() as $line) {
                if ($line->match(self::TRANS_PATTERN)) {
                    $found = true;
                }
            }
        }

        $this->assertTrue($found);
    }

    public function testFilterByAdditions()
    {
        $diff = Diff::create($this->getExpectedDiff());

        $iterator = $diff->additions();

        foreach ($iterator as $file) {
            foreach ($file->lines() as $line) {
                $this->assertTrue($line->isAddition());
            }
        }
    }

    public function testFilterByDeletions()
    {
        $diff = Diff::create($this->getExpectedDiff());

        $iterator = $diff->deletions();

        foreach ($iterator as $file) {
            foreach ($file->lines() as $line) {
                $this->assertTrue($line->isDeletion());
            }
        }
    }

    public function testMatchByAdditions()
    {
        $diff = Diff::create($this->getExpectedDiff());

        $iterator = $diff->additions()->contains(self::TRANS_PATTERN);

        $this->assertTrue($iterator->match());
    }

    public function testFromPullRequestResponse()
    {
        $diff = Diff::create(file_get_contents($this->pullRequest->getDiffUrl()));

        $this->assertTrue($diff->additions()->contains(self::TRANS_PATTERN)->match());
    }

    private function getExpectedDiff()
    {
        return file_get_contents(__DIR__.'/../webhook_examples/git_diff_matched.diff');
    }

    private function getNotExpectedDiff()
    {
        return file_get_contents(__DIR__.'/../webhook_examples/git_diff_not_matched.diff');
    }
}
