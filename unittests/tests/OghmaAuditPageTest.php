<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class OghmaAuditPageTest extends TestCase
{
    public function testProfileSessionBootstrapRunsBeforePageOutputAndNavbar(): void
    {
        $source = file_get_contents(__DIR__ . '/../../ui/oghma_audit.php');

        $this->assertIsString($source);
        $profileLoaderPosition = strpos($source, 'profile_loader.php');
        $documentPosition = strpos($source, '<!DOCTYPE html>');
        $navbarPosition = strpos($source, 'navbar.php');

        $this->assertNotFalse($profileLoaderPosition);
        $this->assertNotFalse($documentPosition);
        $this->assertNotFalse($navbarPosition);
        $this->assertLessThan($documentPosition, $profileLoaderPosition);
        $this->assertLessThan($navbarPosition, $documentPosition);
    }
}
