<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../lib/oghma_aliases.php';

final class OghmaAliasesTest extends TestCase
{
    public function testAliasesMergeWithoutReplacingUserValues(): void
    {
        $this->assertSame(
            "Maven's Meadery, Riften Meadery",
            chimOghmaMergeAliases(
                'black_briar_meadery',
                'Riften Meadery',
                "Black-Briar Meadery, Maven's Meadery"
            )
        );
    }

    public function testCanonicalAndSharedAliasesAreRejected(): void
    {
        $rows = [
            ['topic' => 'dragonborn', 'aliases' => 'Dovah'],
            ['topic' => 'dovahkiin', 'aliases' => 'Dragonborn hero'],
        ];
        [$canonicalOwners, $aliasOwners] = chimOghmaBuildAliasOwnerMaps($rows);
        $filtered = chimOghmaFilterAliases(
            'dragonborn',
            'Dovahkiin, Dovah, Last Dragonborn',
            $canonicalOwners,
            $aliasOwners
        );

        $this->assertSame('Dovah, Last Dragonborn', $filtered['aliases']);
        $this->assertCount(1, $filtered['rejected']);
    }

    public function testNativeVectorIndexesAliasesAtTopicWeight(): void
    {
        $sql = chimOghmaNativeVectorSql();

        $this->assertStringContainsString("coalesce(topic, '')", $sql);
        $this->assertStringContainsString("coalesce(aliases, '')", $sql);
        $this->assertStringContainsString("to_tsvector(coalesce(topic_desc, ''))", $sql);
        $this->assertSame(2, substr_count($sql, "'A'"));
    }

    public function testComparableKeyHandlesLegacyTopicFormatting(): void
    {
        $this->assertSame(
            chimOghmaComparableAliasKey('guardian_stones'),
            chimOghmaComparableAliasKey(" guardian stones \n")
        );
        $this->assertSame(
            chimOghmaComparableAliasKey("shor's_stone"),
            chimOghmaComparableAliasKey("Shor's Stone")
        );
    }
}
