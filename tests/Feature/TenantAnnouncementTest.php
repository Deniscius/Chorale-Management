<?php

namespace Tests\Feature;

use App\Mail\TenantAnnouncement;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

final class TenantAnnouncementTest extends TestCase
{
    public function test_relative_action_links_are_rendered_as_absolute_application_urls(): void
    {
        URL::forceRootUrl('https://chorale-management.example');
        URL::forceScheme('https');

        $mail = new TenantAnnouncement('Information', 'Bonjour', 'Chœur Test', '/mon-carnet', 'Consulter mon carnet');

        $this->assertSame('https://chorale-management.example/mon-carnet', $mail->actionLink());
        $this->assertSame('[Chœur Test] Information', $mail->envelope()->subject);
        URL::forceRootUrl(null);
        URL::forceScheme(null);
    }
}
