<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'core'.DIRECTORY_SEPARATOR.'action_catalog.php';

final class ActionCatalogTest extends TestCase
{
    public function testBuildActionCatalogSeedRows_AssignsNpcAndFollowerScopes(): void
    {
        $rows = herikaBuildActionCatalogSeedRows(
            [
                'MoveTo' => 'MoveTo',
                'ReadQuestJournal' => 'ReadQuestJournal',
                'Surrender' => 'Surrender',
                'SetCurrentTask' => 'SetCurrentTask',
            ],
            [],
            [],
            ['Surrender']
        );

        $this->assertTrue($rows['MoveTo']['available_to_npc']);
        $this->assertFalse($rows['MoveTo']['available_to_followers']);
        $this->assertTrue($rows['MoveTo']['is_activated']);

        $this->assertFalse($rows['ReadQuestJournal']['available_to_npc']);
        $this->assertTrue($rows['ReadQuestJournal']['available_to_followers']);
        $this->assertTrue($rows['ReadQuestJournal']['is_activated']);

        $this->assertFalse($rows['Surrender']['available_to_npc']);
        $this->assertFalse($rows['Surrender']['available_to_followers']);
        $this->assertTrue($rows['Surrender']['is_activated']);

        $this->assertFalse($rows['SetCurrentTask']['available_to_npc']);
        $this->assertFalse($rows['SetCurrentTask']['available_to_followers']);
        $this->assertFalse($rows['SetCurrentTask']['is_activated']);
    }
}
