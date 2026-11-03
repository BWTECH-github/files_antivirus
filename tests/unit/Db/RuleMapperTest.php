<?php
/**
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 */

namespace OCA\Files_Antivirus\Tests\unit\Db;

use OCA\Files_Antivirus\Db\Rule;
use OCA\Files_Antivirus\Db\RuleMapper;
use OCA\Files_Antivirus\Status;
use OCA\Files_Antivirus\Tests\unit\TestBase;

class RuleMapperTest extends TestBase {
	/** @var RuleMapper */
	private $ruleMapper;

	/** @var int Anzahl der Standardregeln */
	private $defaultCount;

	public function setUp(): void {
		parent::setUp();
		$this->ruleMapper = new RuleMapper($this->db);
		$this->ruleMapper->deleteAll();
		$this->ruleMapper->populate();
		$this->defaultCount = \count($this->ruleMapper->findAll());
	}

	public function tearDown(): void {
		$this->ruleMapper->deleteAll();
		$this->ruleMapper->populate();
		parent::tearDown();
	}

	private function insertCustomRule(): void {
		$this->ruleMapper->insert(Rule::fromParams([
			'groupId' => 0,
			'statusType' => Rule::RULE_TYPE_MATCH,
			'result' => 0,
			'match' => '/eigene Regel/',
			'description' => 'eigene Regel',
			'status' => Status::SCANRESULT_INFECTED
		]));
	}

	public function testResetReplacesAllRulesWithDefaults(): void {
		$this->assertGreaterThan(0, $this->defaultCount);
		$this->insertCustomRule();
		$this->assertCount($this->defaultCount + 1, $this->ruleMapper->findAll());

		$this->ruleMapper->reset();
		$this->ruleMapper->reset();

		$rules = $this->ruleMapper->findAll();
		$this->assertCount($this->defaultCount, $rules);
		foreach ($rules as $rule) {
			$this->assertNotSame('eigene Regel', $rule->getDescription());
		}
	}

	public function testResetKeepsRulesWhenPopulateFails(): void {
		$this->insertCustomRule();
		$failingMapper = $this->getMockBuilder(RuleMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['populate'])
			->getMock();
		$failingMapper->method('populate')
			->willThrowException(new \RuntimeException('populate failed'));

		try {
			$failingMapper->reset();
			$this->fail('reset() must rethrow');
		} catch (\RuntimeException $e) {
			$this->assertSame('populate failed', $e->getMessage());
		}

		$this->assertCount($this->defaultCount + 1, $this->ruleMapper->findAll());
	}
}
