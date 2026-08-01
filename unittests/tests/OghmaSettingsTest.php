<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../lib/settings.php';

final class OghmaSettingsTest extends TestCase
{
    public function testOghmaControlsAreManagedGlobalSettings(): void
    {
        $managed = chimGetManagedGeneralSettingIds();

        foreach (['OGHMA_INFINIUM', 'OGHMA_AMOUNT', 'RACIAL_OGHMA', 'LOCATION_OGHMA'] as $settingId) {
            $this->assertContains($settingId, $managed);
            $this->assertSame('Oghma', chimGetOverrideableGeneralSettingCategory($settingId));
        }
    }

    public function testOghmaControlsRemainAvailableAsProfileOverrides(): void
    {
        $catalog = chimGetOverrideableGeneralSettingsCatalog();

        $this->assertSame('boolean', $catalog['OGHMA_INFINIUM']['type']);
        $this->assertSame('Oghma Infinium', $catalog['OGHMA_INFINIUM']['ui_label']);
        $this->assertSame('select', $catalog['OGHMA_AMOUNT']['type']);
        $this->assertSame(['1', '2', '3'], $catalog['OGHMA_AMOUNT']['values']);
        $this->assertSame('Oghma', $catalog['RACIAL_OGHMA']['category']);
        $this->assertSame('Oghma', $catalog['LOCATION_OGHMA']['category']);
        $this->assertSame('Oghma', $catalog['CORE_CONNECTOR_OGHMA_CUSTOM']['category']);
    }
}
