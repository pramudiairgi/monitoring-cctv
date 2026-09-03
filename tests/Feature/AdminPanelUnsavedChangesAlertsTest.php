<?php

namespace Tests\Feature;

use Filament\Facades\Filament;
use Tests\TestCase;

class AdminPanelUnsavedChangesAlertsTest extends TestCase
{
    public function test_admin_panel_has_unsaved_changes_alerts_enabled(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertNotNull($panel);
        $this->assertTrue($panel->hasUnsavedChangesAlerts());
    }
}
